<?php

namespace App\Providers;

use App\Support\PermissionMatrix;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function ($request) {
            $admin = $request->user('admin');

            if (app()->environment('local')) {
                return $admin !== null;
            }

            return $admin?->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE) ?? false;
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Authorization is handled via Horizon::auth() in boot().
    }
}
