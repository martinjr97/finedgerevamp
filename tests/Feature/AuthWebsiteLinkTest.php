<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthWebsiteLinkTest extends TestCase
{
    public function test_customer_and_admin_login_pages_show_visit_website_link_when_configured(): void
    {
        $websiteUrl = 'https://example.test';

        config(['app.website_url' => $websiteUrl]);

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('Visit Website')
            ->assertSee('href="'.$websiteUrl.'"', false);

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Visit Website')
            ->assertSee('href="'.$websiteUrl.'"', false);
    }

    public function test_customer_and_admin_login_pages_hide_visit_website_link_when_not_configured(): void
    {
        config(['app.website_url' => null]);

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertDontSee('Visit Website');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('Visit Website');
    }
}
