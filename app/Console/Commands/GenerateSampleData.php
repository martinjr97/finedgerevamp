<?php

namespace App\Console\Commands;

use App\Support\SampleDataGenerator;
use Illuminate\Console\Command;

class GenerateSampleData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sample-data:generate {--force : Force generation even if data exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sample data for development and testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting sample data generation...');
        $this->newLine();

        $startTime = microtime(true);

        try {
            // Pass a progress callback to show real-time updates
            $counts = SampleDataGenerator::run(function (string $message, string $type = 'info') {
                switch ($type) {
                    case 'success':
                        $this->info("✓ {$message}");
                        break;
                    case 'warning':
                        $this->warn("⚠ {$message}");
                        break;
                    case 'error':
                        $this->error("✗ {$message}");
                        break;
                    default:
                        $this->line("  {$message}");
                        break;
                }
            });

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info('✓ Sample data generated successfully!');
            $this->newLine();

            // Display summary table
            $this->table(
                ['Type', 'Count'],
                collect($counts)->map(function ($count, $key) {
                    return [
                        ucfirst(str_replace('_', ' ', $key)),
                        number_format($count),
                    ];
                })->toArray()
            );

            $this->newLine();
            $this->info("Total execution time: {$duration} seconds");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to generate sample data');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}

