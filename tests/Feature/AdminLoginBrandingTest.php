<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginBrandingTest extends TestCase
{
    public function test_admin_login_page_uses_admin_specific_background_and_card_treatment(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('auth-page admin-auth-page')
            ->assertSee('admin-auth-card')
            ->assertSee('auth-submit-button')
            ->assertSee('customer-view.png')
            ->assertDontSee('Restricted operations console for authorized staff.');
    }

    public function test_customer_login_page_does_not_use_admin_specific_card_treatment(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertDontSee('admin-auth-card')
            ->assertDontSee('Restricted operations console for authorized staff.');
    }
}
