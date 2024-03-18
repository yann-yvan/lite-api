<?php

namespace Nycorp\LiteApi\Console\Commands;

use Illuminate\Console\Command;

class InstallMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lite-api:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('migrate');
    }
}
