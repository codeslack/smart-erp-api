<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:backup {filename?}')]
#[Description('Backup the database to a SQL file')]
class DatabaseBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename') ?? 
                    'backup_' . date('Y_m_d_His') . '.sql';
        $path = storage_path('app/backups/' . $filename);

        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0777, true);
        }

        $command = sprintf(
            'mysqldump -u%s -p%s %s > %s',
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            env('DB_DATABASE'),
            $path
        );

        system($command);

        $this->info("Backup created: $path");
    }
}
