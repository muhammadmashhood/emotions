<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class SeedDemoData extends Command
{
    protected $signature = 'app:seed-demo {--large : Seed about 50 000 evaluations for performance testing} {--count= : Exact number of evaluations}';

    protected $description = 'Seed demo hospitals, forms and evaluations (local / staging only)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to seed demo data in production.');

            return self::FAILURE;
        }

        $count = $this->option('count') !== null ? (int) $this->option('count') : ($this->option('large') ? 50000 : 300);
        config(['emotion.demo_evaluations' => $count]);

        $seeder = new DemoSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->info("Demo data ready ({$count} evaluations target).");

        return self::SUCCESS;
    }
}
