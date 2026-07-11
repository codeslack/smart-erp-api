<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:restore {filename}')]
#[Description('Restore the database from a SQL file')]
class DatabaseRestore extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $path = storage_path('app/backups/' . $filename);

        if (!file_exists($path)) {
            $this->error("Backup file not found: $path");
            return;
        }

        $command = sprintf(
            'mysql -u%s -p%s %s < %s',
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            env('DB_DATABASE'),
            $path
        );

        system($command);

        $this->info("Database restored from: $path");
    }
}
