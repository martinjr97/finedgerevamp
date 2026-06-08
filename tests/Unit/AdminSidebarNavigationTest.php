<?php

namespace Tests\Unit;

use App\Support\AdminSidebarNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    public function test_matches_route_patterns(): void
    {
        $request = Request::create('/admin/companies', 'GET');
        $route = Route::get('/admin/companies', fn () => null)->name('admin.companies.index');
        $request->setRouteResolver(fn () => $route);

        $this->app->instance('request', $request);

        $this->assertTrue(AdminSidebarNavigation::matches(['admin.companies.index']));
        $this->assertTrue(AdminSidebarNavigation::matches('admin.companies.*'));
        $this->assertFalse(AdminSidebarNavigation::matches(['admin.companies.create']));
    }

    public function test_apply_route_patterns_to_companies_menu(): void
    {
        $items = AdminSidebarNavigation::applyRoutePatterns([
            [
                'label' => 'Companies',
                'id' => 'menu-companies',
                'children' => [
                    ['label' => 'View Companies', 'route' => '/admin/companies'],
                    ['label' => 'Register Company', 'route' => '/admin/companies/create'],
                ],
            ],
        ]);

        $this->assertNotEmpty($items[0]['children'][0]['routes']);
        $this->assertContains('admin.companies.index', $items[0]['children'][0]['routes']);
        $this->assertContains('admin.companies.create', $items[0]['children'][1]['routes']);
    }
}
