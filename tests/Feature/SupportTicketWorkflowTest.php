<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\SupportTicket;
use App\Models\SupportTicketAssignment;
use App\Models\SupportTicketComment;
use App\Services\SupportTicketService;
use App\Support\PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        $suffix = Str::lower(Str::random(5));

        return Company::create([
            'name' => 'Support Co '.$suffix,
            'slug' => 'support-'.$suffix,
            'code' => 'SC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function customer(Company $company): Customer
    {
        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Support Product',
            'code' => 'SUP-'.Str::upper(Str::random(3)),
            'category' => 'character',
            'is_active' => true,
        ]);

        return Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Support',
            'last_name' => 'Customer',
            'email' => 'support-customer-'.Str::random(5).'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function admin(Company $company, string $role = 'support-analyst'): Admin
    {
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Support',
            'last_name' => 'Agent',
            'email' => 'agent-'.Str::random(5).'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Role::findOrCreate($role, 'admin');
        $admin->assignRole($role);

        return $admin;
    }

    private function superAdmin(Company $company): Admin
    {
        return $this->admin($company, PermissionMatrix::SUPER_ADMIN_ROLE);
    }

    private function openTicket(Customer $customer): SupportTicket
    {
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'name' => $customer->full_name,
            'email' => $customer->email,
            'subject' => 'Need help',
            'message' => 'Initial issue description',
            'status' => SupportTicket::STATUS_IN_PROGRESS,
        ]);

        SupportTicketComment::create([
            'support_ticket_id' => $ticket->id,
            'author_type' => SupportTicketComment::AUTHOR_CUSTOMER,
            'customer_id' => $customer->id,
            'comment' => $ticket->message,
            'is_internal' => false,
            'is_visible_to_customer' => true,
        ]);

        return $ticket;
    }

    public function test_super_admin_can_assign_ticket_to_staff(): void
    {
        $company = $this->company();
        $super = $this->superAdmin($company);
        $staff = $this->admin($company);
        $customer = $this->customer($company);
        $ticket = $this->openTicket($customer);

        $this->actingAs($super, 'admin')
            ->post(route('admin.support-tickets.assign', $ticket), [
                'assigned_to_id' => $staff->id,
                'note' => 'Please handle',
            ])
            ->assertRedirect(route('admin.support-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame($staff->id, $ticket->assigned_to_id);
    }

    public function test_assignment_creates_history_and_system_comment(): void
    {
        $company = $this->company();
        $super = $this->superAdmin($company);
        $staff = $this->admin($company);
        $ticket = $this->openTicket($this->customer($company));

        app(SupportTicketService::class)->assignTicket($ticket, $staff, $super, 'Handover');

        $this->assertDatabaseHas('support_ticket_assignments', [
            'support_ticket_id' => $ticket->id,
            'assigned_to_id' => $staff->id,
            'assigned_by_id' => $super->id,
        ]);

        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'author_type' => SupportTicketComment::AUTHOR_SYSTEM,
        ]);
    }

    public function test_assigned_staff_can_comment(): void
    {
        $company = $this->company();
        $staff = $this->admin($company);
        $ticket = $this->openTicket($this->customer($company));
        $ticket->update(['assigned_to_id' => $staff->id]);

        $this->actingAs($staff, 'admin')
            ->post(route('admin.support-tickets.comments.store', $ticket), [
                'comment' => 'We are looking into this.',
            ])
            ->assertRedirect();
    }

    public function test_admin_can_post_public_and_internal_comments(): void
    {
        $company = $this->company();
        $super = $this->superAdmin($company);
        $ticket = $this->openTicket($this->customer($company));

        $this->actingAs($super, 'admin')
            ->post(route('admin.support-tickets.comments.store', $ticket), [
                'comment' => 'Public update',
            ])
            ->assertRedirect();

        $this->actingAs($super, 'admin')
            ->post(route('admin.support-tickets.comments.store', $ticket), [
                'comment' => 'Internal only note',
                'is_internal' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'comment' => 'Public update',
            'is_internal' => false,
            'is_visible_to_customer' => true,
        ]);

        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'comment' => 'Internal only note',
            'is_internal' => true,
            'is_visible_to_customer' => false,
        ]);
    }

    public function test_customer_can_comment_on_open_ticket(): void
    {
        $customer = $this->customer($this->company());
        $ticket = $this->openTicket($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support-tickets.comments.store', $ticket), [
                'comment' => 'Following up on my issue.',
            ])
            ->assertRedirect(route('customer.support-tickets.show', $ticket));

        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'comment' => 'Following up on my issue.',
        ]);
    }

    public function test_customer_cannot_comment_on_resolved_ticket(): void
    {
        $customer = $this->customer($this->company());
        $ticket = $this->openTicket($customer);
        $ticket->update([
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support-tickets.comments.store', $ticket), [
                'comment' => 'One more thing',
            ])
            ->assertForbidden();
    }

    public function test_customer_cannot_see_internal_comments(): void
    {
        $company = $this->company();
        $customer = $this->customer($company);
        $ticket = $this->openTicket($customer);
        $super = $this->superAdmin($company);

        app(SupportTicketService::class)->addAdminComment($ticket, $super, 'Secret internal', true);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.support-tickets.show', $ticket));

        $response->assertOk();
        $response->assertDontSee('Secret internal');
    }

    public function test_customer_cannot_access_another_customers_ticket(): void
    {
        $company = $this->company();
        $ticket = $this->openTicket($this->customer($company));
        $other = $this->customer($company);

        $this->actingAs($other, 'customer')
            ->get(route('customer.support-tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_resolved_status_sets_resolved_at(): void
    {
        $company = $this->company();
        $super = $this->superAdmin($company);
        $ticket = $this->openTicket($this->customer($company));

        $this->actingAs($super, 'admin')
            ->patch(route('admin.support-tickets.status.update', $ticket), [
                'status' => SupportTicket::STATUS_RESOLVED,
                'resolution_note' => 'Fixed the login issue.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $ticket->status);
    }

    public function test_closed_status_sets_closed_at(): void
    {
        $company = $this->company();
        $super = $this->superAdmin($company);
        $ticket = $this->openTicket($this->customer($company));

        $this->actingAs($super, 'admin')
            ->patch(route('admin.support-tickets.status.update', $ticket), [
                'status' => SupportTicket::STATUS_CLOSED,
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_ticket_age_helpers(): void
    {
        $ticket = SupportTicket::create([
            'name' => 'Guest',
            'subject' => 'Test',
            'message' => 'Hello',
            'status' => SupportTicket::STATUS_NEW,
            'created_at' => now()->subHours(2),
        ]);

        $this->assertNotEmpty($ticket->ageForHumans());
    }

    public function test_super_admin_can_create_ticket_from_admin(): void
    {
        $company = $this->company();
        $super = $this->superAdmin($company);
        $customer = $this->customer($company);

        $this->actingAs($super, 'admin')
            ->post(route('admin.support-tickets.store'), [
                'customer_id' => $customer->id,
                'subject' => 'Admin logged issue',
                'message' => 'Customer called about payment.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'customer_id' => $customer->id,
            'subject' => 'Admin logged issue',
            'status' => SupportTicket::STATUS_NEW,
        ]);
    }

    public function test_customer_can_submit_ticket_with_attachment(): void
    {
        Storage::fake('public');
        $customer = $this->customer($this->company());

        $this->actingAs($customer, 'customer')
            ->post(route('customer.support.store'), [
                'name' => $customer->full_name,
                'email' => $customer->email,
                'subject' => 'Issue with statement',
                'message' => 'Please see attached file.',
                'attachment' => UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->where('subject', 'Issue with statement')->first();
        $this->assertNotNull($ticket);
        $this->assertDatabaseHas('support_ticket_attachments', [
            'support_ticket_id' => $ticket->id,
            'original_name' => 'statement.pdf',
            'uploader_type' => 'customer',
        ]);
    }

    public function test_admin_create_ticket_with_attachment(): void
    {
        Storage::fake('public');
        $super = $this->superAdmin($this->company());

        $this->actingAs($super, 'admin')
            ->post(route('admin.support-tickets.store'), [
                'name' => 'Walk-in Guest',
                'subject' => 'Branch visit',
                'message' => 'Guest brought documents.',
                'attachment' => UploadedFile::fake()->image('scan.jpg'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_ticket_attachments', [
            'original_name' => 'scan.jpg',
            'uploader_type' => 'admin',
        ]);
    }

    public function test_admin_index_shows_assigned_staff(): void
    {
        $company = $this->company();
        $super = $this->superAdmin($company);
        $staff = $this->admin($company);
        $ticket = $this->openTicket($this->customer($company));
        $ticket->update(['assigned_to_id' => $staff->id]);

        $this->actingAs($super, 'admin')
            ->get(route('admin.support-tickets.index'))
            ->assertOk()
            ->assertSee($staff->full_name);
    }
}
