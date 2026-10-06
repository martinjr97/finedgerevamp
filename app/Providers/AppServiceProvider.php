<?php

namespace App\Providers;

use App\Migration\Database\ReadOnlyMySqlConnection;
use App\Support\AuditLogger;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AuditLogger::register();

        DB::extend('legacy-mysql', function (array $config, string $name) {
            $config['name'] = $name;

            return new ReadOnlyMySqlConnection(
                (new MySqlConnector)->connect($config),
                $config['database'],
                $config['prefix'],
                $config,
            );
        });
    }
}
