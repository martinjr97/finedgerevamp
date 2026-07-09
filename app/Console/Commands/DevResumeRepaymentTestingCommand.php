<?php

namespace App\Console\Commands;

use Database\Seeders\DevRepaymentResumeSeeder;
use Illuminate\Console\Command;

class DevResumeRepaymentTestingCommand extends Command
{
    protected $signature = 'dev:resume-repayment-testing';

    protected $description = 'Additively restore dev data for repayment testing (never deletes existing records)';

    public function handle(): int
    {
        $this->warn('This command only ADDS or UPDATES fixture records. It does not delete, truncate, or migrate:fresh.');
        $this->newLine();

        $this->call('db:seed', [
            '--class' => DevRepaymentResumeSeeder::class,
            '--no-interaction' => true,
        ]);

        $this->newLine();
        $this->comment('Set CGRATE_ENABLED=true in .env if you want live gateway collection prompts.');

        return self::SUCCESS;
    }
}
