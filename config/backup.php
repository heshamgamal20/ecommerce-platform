<?php

return [
    'enabled' => (bool) env('BACKUP_ENABLED', false),
    'schedule' => env('BACKUP_SCHEDULE', '02:00'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    'directory' => env('BACKUP_DIR', storage_path('app/backups')),
    'offsite' => [
        'enabled' => (bool) env('BACKUP_OFFSITE_ENABLED', false),
        'bucket' => env('BACKUP_OFFSITE_BUCKET'),
        'prefix' => trim((string) env('BACKUP_OFFSITE_PREFIX', 'ecommerce-platform/database'), '/'),
        'endpoint' => env('BACKUP_OFFSITE_ENDPOINT'),
    ],
    'scripts' => [
        'pgsql' => base_path('scripts/backup_postgres.sh'),
        'mysql' => base_path('scripts/backup_mysql.sh'),
        'sqlite' => base_path('scripts/backup_sqlite.sh'),
    ],
];
