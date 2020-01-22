<?php

namespace Exolnet\DbUpgrade\Console;

use Exception;
use Exolnet\DbUpgrade\Exceptions\PreConditionNotMetException;
use Exolnet\DbUpgrade\Exceptions\DbUpgradeException;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DbUpgradeCommand extends Command
{
    use ConfirmableTrait;

    /**
     * @var string
     */
    const UPGRADE_CONTENT_FILENAME = 'content.sql';

    /**
     * @var string
     */
    const UPGRADE_BACKUP_FILENAME = 'backup.sql';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:upgrade {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade an existing database structure to use Laravel migrations';

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Symfony\Component\Process\ExecutableFinder
     */
    protected $executableFinder;

    /**
     * @var string|null
     */
    protected $commandMysqlDump;

    /**
     * @var string|null
     */
    protected $commandMysql;

    /**
     * @var string|null
     */
    protected $temporaryPathPrefix;

    /**
     * Create a new command instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $filesystem
     * @param \Symfony\Component\Process\ExecutableFinder $executableFinder
     */
    public function __construct(Filesystem $filesystem, ExecutableFinder $executableFinder)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->executableFinder = $executableFinder;
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        // Check pre-conditions
        $this->checkPreConditions();

        try {
            // 1. Prepare the upgrade
            $this->prepareUpgrade();

            // 2. Backup database
            $this->backupDatabase();

            // 3. Create a backup of the content
            $this->exportContent();

            // 4. Empty the database
            $this->wipeDatabase();

            try {
                // 5. Migrate up to the last migration for upgrade
                $this->upgradeStructure();

                // 6. Import the data
                $this->importContent();

                // 7. Finish the upgrade
                $this->finishUpgrade();
            } catch (Exception $e) {
                $this->restoreDatabase();

                throw $e;
            }
        } finally {
            $this->cleanUp();
        }
    }

    /**
     * @return void
     */
    protected function checkPreConditions(): void
    {
        $this->info('Checking pre-conditions.');

        $this->checkExistingDatabase();

        $migrationPath = $this->getTemporaryUpgradePath();

        if ($this->filesystem->exists($migrationPath)) {
            if (!$this->confirm('A migration file already exists. Would you like to clean it up?')) {
                $this->info('Aborting.');
                return;
            }

            $this->cleanUp();
        }

        if (!$this->option('force') && !$this->confirm('Do you have a backup of your database?')) {
            throw new PreConditionNotMetException('You need to have a backup to perform the upgrade.');
        }

        $this->commandMysqlDump = $this->findExecutable('mysqldump');
        $this->commandMysql = $this->findExecutable('mysql');
    }

    /**
     * @return void
     */
    protected function prepareUpgrade(): void
    {
        $migrationPath = $this->getTemporaryUpgradePath();

        $this->filesystem->makeDirectory($migrationPath, 0755, true, true);
    }

    /**
     * @return void
     */
    protected function backupDatabase(): void
    {
        $config = $this->getDatabaseConfig();
        $backupFile = $this->getTemporaryUpgradePath(static::UPGRADE_BACKUP_FILENAME);

        $this->info('Backup the database to ' . $backupFile . '.');

        $command = '"' . $this->commandMysqlDump .
            '" -h "' . $config['host'] .
            '" -u "' . $config['username'] . '" ' .
            ($config['password'] ? '-p"' . $config['password'] . '"' : '') .
            ' "' . $config['database'] .
            '" > "' . $backupFile . '"';

        $export = Process::fromShellCommandline($command);

        if ($export->run() !== 0) {
            throw new DbUpgradeException(
                'Could not create a backup of the database.' . PHP_EOL .
                $export->getErrorOutput()
            );
        }

        // Verify the file exists and is not empty
        $stat = @stat($backupFile);
        if (!$stat || $stat['size'] === 0) {
            throw new DbUpgradeException('Database backup file was not created or was empty.');
        }
    }

    /**
     * @return void
     */
    protected function restoreDatabase(): void
    {
        $config = $this->getDatabaseConfig();
        $backupFile = $this->getTemporaryUpgradePath(static::UPGRADE_BACKUP_FILENAME);

        $this->output->error('Error... restoring the database using ' . $backupFile . '.');

        // Verify the file exists and is not empty
        $stat = @stat($backupFile);
        if (!$stat || $stat['size'] === 0) {
            throw new DbUpgradeException('Source backup file does not exist or is empty, aborting.');
        }

        $this->wipeDatabase();

        $command = 'cat "' . $backupFile . '" | "' . $this->commandMysql .
            '" -h "' . $config['host'] .
            '" -u "' . $config['username'] . '" ' .
            ($config['password'] ? '-p"' . $config['password'] . '"' : '') .
            ' "' . $config['database'] . '"';

        $export = Process::fromShellCommandline($command);

        if ($export->run() !== 0) {
            throw new DbUpgradeException('Could not restore the database.' . PHP_EOL . $export->getErrorOutput());
        }
    }

    /**
     * @return void
     */
    protected function exportContent(): void
    {
        $config = $this->getDatabaseConfig();
        $contentFile = $this->getTemporaryUpgradePath(static::UPGRADE_CONTENT_FILENAME);

        $this->info('Export the content to ' . $contentFile . '.');

        $command = '"' . $this->commandMysqlDump .
            '" -h "' . $config['host'] .
            '" -u "' . $config['username'] . '" ' .
            ($config['password'] ? '-p"' . $config['password'] . '"' : '') .
            ' "' . $config['database'] .
            '"  --no-create-info --skip-triggers --complete-insert > "' . $contentFile . '"';

        $export = Process::fromShellCommandline($command);

        if ($export->run() !== 0) {
            throw new DbUpgradeException(
                'Could not create a backup of the database content.' . PHP_EOL .
                $export->getErrorOutput()
            );
        }

        // Verify the file exists and is not empty
        $stat = @stat($contentFile);
        if (!$stat || $stat['size'] === 0) {
            throw new DbUpgradeException('Database content backup file was not created or was empty.');
        }
    }

    /**
     * @return void
     */
    protected function wipeDatabase(): void
    {
        $this->call('db:wipe', [
            'force' => $this->option('force'),
        ]);
    }

    /**
     * @return void
     */
    protected function upgradeStructure(): void
    {
        $this->info('Upgrade the database structure.');

        $migrationPath = $this->getTemporaryUpgradePath('migrations');
        $migrations = $this->filesystem->glob(base_path('database/migrations/*'));

        $this->filesystem->makeDirectory($migrationPath);

        foreach ($migrations as $migration) {
            $migrationFile = basename($migration);

            $this->filesystem->copy($migration, $migrationPath . '/' . $migrationFile);

            if (pathinfo($migrationFile, PATHINFO_FILENAME) === $this->getLastMigrationForUpgrade()) {
                break;
            }
        }

        $this->call('migrate', [
            '--path' => str_replace(base_path(), '', $migrationPath),
            '--force',
        ]);

        $this->filesystem->deleteDirectory($migrationPath);
    }

    /**
     * @return void
     */
    protected function importContent(): void
    {
        $this->info('Import the content.');

        $config = $this->getDatabaseConfig();
        $contentFile = $this->getTemporaryUpgradePath(static::UPGRADE_CONTENT_FILENAME);

        $command = 'cat "' . $contentFile . '" | "' .$this->commandMysql .
            '" -h "' . $config['host'] .
            '" -u "' . $config['username'] . '" ' .
            ($config['password'] ? '-p"' . $config['password'] . '"' : '') .
            ' "' . $config['database'] . '"';

        $export = Process::fromShellCommandline($command);

        if ($export->run() !== 0) {
            throw new DbUpgradeException('Could not import the current content.' . PHP_EOL . $export->getErrorOutput());
        }
    }

    /**
     * @return void
     */
    protected function finishUpgrade(): void
    {
        $this->info('Finishing the structure upgrade.');

        $this->call('migrate', [
            '--force',
        ]);
    }

    /**
     * @return void
     */
    protected function cleanUp(): void
    {
        $this->info('Clean up the upgrade.');

        $this->filesystem->deleteDirectory($this->getTemporaryUpgradePath());
    }

    /**
     * @return void
     */
    protected function checkExistingDatabase(): void
    {
        // If the migrations table exist, it must be empty, otherwise fail
        if (Schema::hasTable('migrations')) {
            if (DB::table('migrations')->count() !== 0) {
                throw new PreConditionNotMetException('A not empty migrations table already exists.');
            }
        }

        foreach ($this->getExpectedTables() as $table) {
            if (! Schema::hasTable($table)) {
                throw new PreConditionNotMetException('Could not find required table "'. $table .'".');
            }
        }
    }

    /**
     * @param string|null $path
     * @return string
     */
    protected function getTemporaryUpgradePath($path = null): string
    {
        if (! $this->temporaryPathPrefix) {
            $this->temporaryPathPrefix = now()->toDateTimeString();
        }

        return storage_path(rtrim('upgrade-'. $this->temporaryPathPrefix .'/' . $path, '/'));
    }

    /**
     * @param string $name
     * @return string
     */
    protected function findExecutable($name): string
    {
        $executable = $this->executableFinder->find($name);

        if (!$executable) {
            throw new PreConditionNotMetException('Could not find executable ' . $name . ' on your system.');
        }

        return $executable;
    }

    /**
     * @return string
     */
    protected function getDatabaseConnection(): string
    {
        return config('database.default');
    }

    /**
     * @return array
     */
    protected function getDatabaseConfig(): array
    {
        $config  = config('database.connections.' . $this->getDatabaseConnection());
        $driver = $config['driver'] ?? null;

        if ($driver !== 'mysql') {
            throw new DbUpgradeException('Only driver MySQL is supported.');
        }

        return $config;
    }

    /**
     * @return string
     */
    protected function getLastMigrationForUpgrade(): string
    {
        return config('db-upgrade.last_migration_for_upgrade');
    }

    /**
     * @return array
     */
    protected function getExpectedTables(): array
    {
        return config('db-upgrade.expected_tables');
    }
}
