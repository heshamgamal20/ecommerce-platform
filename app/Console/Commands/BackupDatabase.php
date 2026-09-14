<?php

namespace App\Console\Commands;

use App\Modules\Backup\Infrastructure\Configuration\BackupSettings;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class BackupDatabase extends Command
{
    protected $signature = 'backup:database
        {--force : Run even when scheduled backups are disabled}
        {--dry-run : Validate configuration without creating a backup}';

    protected $aliases = ['backup:postgres'];

    protected $description = 'Create a backup for the configured SQLite, MySQL, or PostgreSQL database';

    public function handle(BackupSettings $settings): int
    {
        if (! $this->option('force') && ! $settings->enabled()) {
            $this->components->warn('Backup is disabled. Enable backup.enabled in settings or use --force.');

            return self::SUCCESS;
        }

        $driver = (string) config('database.default');
        if (! in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            $this->components->error("Unsupported database driver: {$driver}. Use sqlite, mysql, or pgsql.");

            return self::FAILURE;
        }

        $script = (string) config("backup.scripts.{$driver}");
        if (! is_file($script) || ! is_executable($script)) {
            $this->components->error("Backup script is missing or not executable: {$script}");

            return self::FAILURE;
        }

        $connection = (array) config("database.connections.{$driver}", []);
        $database = trim((string) ($connection['database'] ?? ''));
        $backupDirectory = trim((string) config('backup.directory', storage_path('app/backups')));

        if ($backupDirectory === '') {
            $this->components->error('BACKUP_DIR cannot be empty.');

            return self::FAILURE;
        }

        if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0770, true) && ! is_dir($backupDirectory)) {
            $this->components->error("Backup directory could not be created: {$backupDirectory}");

            return self::FAILURE;
        }

        if ($database === '') {
            $this->components->error("DB_DATABASE is required for {$driver} backup.");

            return self::FAILURE;
        }

        if ($driver === 'sqlite' && ! is_file($database)) {
            $this->components->error("SQLite database file does not exist: {$database}. Run php artisan migrate first.");

            return self::FAILURE;
        }

        if ($driver !== 'sqlite') {
            foreach (['host', 'database', 'username'] as $key) {
                if (trim((string) ($connection[$key] ?? '')) === '') {
                    $this->components->error(strtoupper($key === 'host' ? 'DB_HOST' : ($key === 'username' ? 'DB_USERNAME' : 'DB_DATABASE'))." is required for {$driver} backup.");

                    return self::FAILURE;
                }
            }
        }

        $environment = array_merge(getenv() ?: [], [
            'BACKUP_DIR' => $backupDirectory,
            'BACKUP_RETENTION_DAYS' => (string) $settings->retentionDays(),
            'BACKUP_OFFSITE_ENABLED' => config('backup.offsite.enabled') ? 'true' : 'false',
            'BACKUP_OFFSITE_BUCKET' => (string) config('backup.offsite.bucket', ''),
            'BACKUP_OFFSITE_PREFIX' => (string) config('backup.offsite.prefix', 'ecommerce-platform/database'),
            'BACKUP_OFFSITE_ENDPOINT' => (string) config('backup.offsite.endpoint', ''),
            'DB_CONNECTION' => $driver,
            'DB_DATABASE' => $database,
        ]);

        foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $variable) {
            if (array_key_exists($key, $connection) && $connection[$key] !== null) {
                $environment[$variable] = (string) $connection[$key];
            }
        }

        if ($this->option('dry-run')) {
            $this->components->info("{$driver} backup configuration is valid. No backup was created.");

            return self::SUCCESS;
        }

        $process = new Process(['bash', $script], base_path(), $environment);
        $process->setTimeout(3600);
        $process->run(function (string $type, string $buffer): void {
                if ($type === Process::ERR) {
                        $this->components->error(trim($buffer));
                                return;
                                    }

                                        $this->output->write($buffer);
                                        });
        
        if (! $process->isSuccessful()) {
            $this->components->error("{$driver} backup failed. Check the command output and application logs.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
