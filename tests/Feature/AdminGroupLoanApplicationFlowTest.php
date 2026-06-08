<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\GroupLoanApplication;
use App\Models\GroupLoanApplicationDocument;
use App\Models\GroupLoanApplicationMember;
use App\Models\GroupMemberTitle;
use App\Models\LoanProduct;
use App\Models\Wallet;
use App\Services\GroupLoanCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminGroupLoanApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Group Loan Co '.$suffix,
            'slug' => 'group-loan-co-'.$suffix,
            'code' => 'GLC-'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Group',
            'last_name' => 'Admin',
            'email' => 'group-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * @return array{product: LoanProduct, group: CustomerGroup, titles: array<string, GroupMemberTitle>, customers: \Illuminate\Support\Collection<int, Customer>}
     */
    private function createGroupLoanContext(Admin $admin, int $memberCount = 4): array
    {
        $suffix = Str::lower(Str::random(5));

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Group Loans '.$suffix,
            'code' => 'GROUP-'.$suffix,
            'category' => 'group_loans',
            'tenure_months' => 3,
            'max_amount' => 200000,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Default Group '.$suffix,
            'code' => 'GL-GRP-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $titles = [
            'leader' => GroupMemberTitle::firstOrCreate(
                ['name' => 'Leader'],
                ['description' => 'Leader role', 'is_active' => true]
            ),
            'coordinator' => GroupMemberTitle::firstOrCreate(
                ['name' => 'Coordinator'],
                ['description' => 'Coordinator role', 'is_active' => true]
            ),
            'member' => GroupMemberTitle::firstOrCreate(
                ['name' => 'Member'],
                ['description' => 'Member role', 'is_active' => true]
            ),
        ];

        $customers = collect();

        for ($i = 1; $i <= $memberCount; $i++) {
            $customers->push(Customer::create([
                'loan_product_id' => $product->id,
                'customer_group_id' => $group->id,
                'first_name' => 'Member'.$i,
                'last_name' => 'Group'.$suffix,
                'email' => 'group-member-'.$suffix.'-'.$i.'@example.com',
                'phone' => '26097'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'national_id' => 'NID-'.$suffix.'-'.$i,
                'tpin' => 'TPIN-'.$suffix.'-'.$i,
                'password' => '1234',
                'status' => 'active',
                'approval_status' => 'approved',
            ]));
        }

        return [
            'product' => $product,
            'group' => $group,
            'titles' => $titles,
            'customers' => $customers,
        ];
    }

    private function createPendingGroupLoanApplication(Admin $admin, array $context, int $memberCount = 3): GroupLoanApplication
    {
        $selectedCustomers = $context['customers']->take($memberCount)->values();

        $principals = [];
        foreach ($selectedCustomers as $index => $customer) {
            $principals[$customer->id] = 1000 + ($index * 200);
        }

        $calculation = app(GroupLoanCalculationService::class)->calculate([
            'processing_fee_percentage' => 5,
            'monthly_interest_rate' => 4,
            'arrears_rate' => 2,
            'repayment_structure' => 'monthly',
            'start_date' => '2026-03-01',
            'due_date' => '2026-06-01',
            'principals' => $principals,
        ]);

        $application = GroupLoanApplication::create([
            'loan_product_id' => $context['product']->id,
            'customer_group_id' => $context['group']->id,
            'relationship_manager_id' => $admin->id,
            'reference' => 'GLA-TEST-'.Str::upper(Str::random(6)),
            'group_name' => $context['group']->name,
            'loan_name' => 'Community Growth Loan',
            'terms_and_conditions' => 'Standard terms.',
            'repayment_structure' => 'monthly',
            'start_date' => '2026-03-01',
            'due_date' => '2026-06-01',
            'processing_fee_percentage' => 5,
            'monthly_interest_rate' => 4,
            'arrears_rate' => 2,
            'total_principal_amount' => $calculation['totals']['principal_amount'],
            'total_processing_fee_amount' => $calculation['totals']['processing_fee_amount'],
            'total_interest_amount' => $calculation['totals']['interest_amount'],
            'total_repayment_amount' => $calculation['totals']['repayment_amount'],
            'total_disbursement_amount' => $calculation['totals']['disbursement_amount'],
            'status' => 'pending_approval',
            'created_by' => $admin->id,
            'submitted_at' => now(),
        ]);

        foreach ($calculation['members'] as $index => $memberCalc) {
            $customer = $selectedCustomers->firstWhere('id', $memberCalc['customer_id']);

            $titleId = $index === 0
                ? $context['titles']['leader']->id
                : ($index === 1 ? $context['titles']['coordinator']->id : $context['titles']['member']->id);

            GroupLoanApplicationMember::create([
                'group_loan_application_id' => $application->id,
                'customer_id' => $customer->id,
                'customer_group_id' => $context['group']->id,
                'group_member_title_id' => $titleId,
                'principal_amount' => $memberCalc['principal_amount'],
                'calculated_processing_fee_amount' => $memberCalc['processing_fee_amount'],
                'calculated_interest_amount' => $memberCalc['interest_amount'],
                'calculated_arrears_basis_amount' => $memberCalc['arrears_basis_amount'],
                'calculated_total_repayment_amount' => $memberCalc['total_repayment_amount'],
                'disbursement_amount' => $memberCalc['disbursement_amount'],
                'disbursement_account_reference' => $customer->phone,
                'disbursement_status' => 'pending',
            ]);
        }

        return $application;
    }

    public function test_onboarding_group_loan_customer_falls_back_to_default_group_when_group_not_selected(): void
    {
        config(['approval.customers.create' => true]);

        $admin = $this->makeAdminWithPermissions(['customers.create']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Group Loans',
            'code' => 'GROUP-ONB-001',
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $defaultGroup = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Default Group',
            'code' => 'GL-DEFAULT',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.customers.store'), [
            'loan_product_id' => $product->id,
            'first_name' => 'Grace',
            'last_name' => 'Chola',
            'email' => 'grace.chola@example.com',
            'phone' => '260955111111',
            'national_id' => '123456/78/1',
            'tpin' => '12345678',
            'address_line1' => 'Plot 10',
            'city' => 'Lusaka',
            'country' => 'Zambia',
            'occupation_type' => 'employed',
            'employer_or_business_name' => 'Kafue Textiles',
            'average_income' => 5200,
            'work_address_line1' => 'Plot 80, Cairo Road',
            'work_city' => 'Lusaka',
            'work_country' => 'Zambia',
        ]);

        $response->assertRedirect();

        $customer = Customer::query()->where('email', 'grace.chola@example.com')->first();

        $this->assertNotNull($customer);
        $this->assertSame($product->id, $customer->loan_product_id);
        $this->assertSame($defaultGroup->id, $customer->customer_group_id);
        $this->assertSame('employed', $customer->employment_status);
        $this->assertSame(5200.0, (float) $customer->net_salary);
        $this->assertSame('Plot 80, Cairo Road', $customer->work_address_line1);
        $this->assertSame('Lusaka', $customer->work_city);
        $this->assertSame('Kafue Textiles', data_get($customer->metadata, 'group_loans_employer_or_business_name'));
        $this->assertSame('employed', data_get($customer->metadata, 'group_loans_occupation_type'));
    }

    public function test_group_loan_customer_group_change_updates_group_assignment(): void
    {
        $admin = $this->makeAdminWithPermissions(['customers.change-group']);
        $context = $this->createGroupLoanContext($admin, 1);

        $secondGroup = CustomerGroup::create([
            'loan_product_id' => $context['product']->id,
            'name' => 'Students',
            'code' => 'GL-STUDENTS',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = $context['customers']->first();

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.customers.update-group', $customer),
            [
                'customer_group_id' => $secondGroup->id,
            ]
        );

        $response->assertRedirect(route('admin.customers.show', $customer));

        $customer->refresh();
        $this->assertSame($secondGroup->id, $customer->customer_group_id);
    }

    public function test_group_loan_application_can_be_created_via_wizard_flow(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.create', 'loans.view']);
        $context = $this->createGroupLoanContext($admin, 4);

        $memberIds = $context['customers']->pluck('id')->all();
        $titleMap = [
            $memberIds[0] => $context['titles']['leader']->id,
            $memberIds[1] => $context['titles']['coordinator']->id,
            $memberIds[2] => $context['titles']['member']->id,
            $memberIds[3] => $context['titles']['member']->id,
        ];

        $membersResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-members', $context['product']),
            [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => $titleMap,
            ]
        );
        $membersResponse->assertRedirect(route('admin.loan-applications.group-loans.details', $context['product']));

        $detailsResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-details', $context['product']),
            [
                'loan_name' => 'Village Development Group Loan',
                'terms_and_conditions' => 'Terms accepted by members.',
                'start_date' => '2026-03-01',
                'due_date' => '2026-06-15',
                'repayment_structure' => 'monthly',
                'processing_fee_percentage' => 5,
                'monthly_interest_rate' => 4,
                'arrears_rate' => 2,
            ]
        );
        $detailsResponse->assertRedirect(route('admin.loan-applications.group-loans.principals', $context['product']));

        $principalsResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-principals', $context['product']),
            [
                'principals' => [
                    $memberIds[0] => 1200,
                    $memberIds[1] => 1000,
                    $memberIds[2] => 900,
                    $memberIds[3] => 800,
                ],
            ]
        );
        $principalsResponse->assertRedirect(route('admin.loan-applications.group-loans.documents', $context['product']));

        $documentsResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-documents', $context['product']),
            ['action' => 'continue']
        );
        $documentsResponse->assertRedirect(route('admin.loan-applications.group-loans.review', $context['product']));

        $reviewResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.review', $context['product']));
        $reviewResponse->assertOk();
        $reviewResponse->assertSee('Repayment Schedule');
        $reviewResponse->assertSee('Download Corporate Copy (PDF)');
        $reviewResponse->assertSee('Individual Expected Repayment Trail');
        $reviewResponse->assertSee('Expected Installment');
        $reviewResponse->assertDontSee('Modification Progress Note');

        $printResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.review-print', $context['product']));
        $printResponse->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $printResponse->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'attachment;',
            (string) $printResponse->headers->get('content-disposition')
        );

        $submitResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.submit', $context['product'])
        );
        $submitResponse->assertRedirect();

        $application = GroupLoanApplication::query()->latest('id')->first();

        $this->assertNotNull($application);
        $this->assertSame('pending_approval', $application->status);
        $this->assertSame($context['group']->id, $application->customer_group_id);
        $this->assertSame($admin->id, $application->relationship_manager_id);
        $this->assertSame(4, $application->members()->count());
        $this->assertGreaterThan(0, (float) $application->total_repayment_amount);

        $this->assertDatabaseHas('group_loan_application_members', [
            'group_loan_application_id' => $application->id,
            'customer_id' => $memberIds[0],
            'group_member_title_id' => $context['titles']['leader']->id,
        ]);
    }

    public function test_group_loan_details_can_auto_calculate_due_date_using_dynamic_term_input(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.create']);
        $context = $this->createGroupLoanContext($admin, 3);

        $memberIds = $context['customers']->pluck('id')->all();

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-members', $context['product']),
            [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => [
                    $memberIds[0] => $context['titles']['leader']->id,
                    $memberIds[1] => $context['titles']['member']->id,
                    $memberIds[2] => $context['titles']['member']->id,
                ],
            ]
        );

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-details', $context['product']),
            [
                'loan_name' => 'Term Driven Group Loan',
                'start_date' => '2026-03-01',
                'loan_term_value' => 3,
                'loan_term_unit' => 'months',
                'repayment_structure' => 'monthly',
                'processing_fee_percentage' => 5,
                'monthly_interest_rate' => 4,
                'arrears_rate' => 2,
            ]
        );

        $response->assertRedirect(route('admin.loan-applications.group-loans.principals', $context['product']));

        $wizardKey = 'group_loan_wizard_'.$context['product']->id.'_'.$admin->id;
        $response->assertSessionHas($wizardKey, function (array $wizard): bool {
            return ($wizard['start_date'] ?? null) === '2026-03-01'
                && ($wizard['loan_term_value'] ?? null) === 3
                && ($wizard['loan_term_unit'] ?? null) === 'months'
                && ($wizard['due_date'] ?? null) === '2026-06-01'
                && ($wizard['relationship_manager_id'] ?? null) !== null;
        });
    }

    public function test_non_relationship_manager_without_assign_permission_cannot_proceed_from_details_step(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.create']);
        $admin->update(['is_relationship_manager' => false]);
        $context = $this->createGroupLoanContext($admin, 3);

        $memberIds = $context['customers']->pluck('id')->all();

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-members', $context['product']),
            [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => [
                    $memberIds[0] => $context['titles']['leader']->id,
                    $memberIds[1] => $context['titles']['member']->id,
                    $memberIds[2] => $context['titles']['member']->id,
                ],
            ]
        );

        $response = $this->from(route('admin.loan-applications.group-loans.details', $context['product']))
            ->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.group-loans.store-details', $context['product']), [
                'loan_name' => 'Blocked RM Assignment',
                'start_date' => '2026-03-01',
                'due_date' => '2026-04-01',
                'repayment_structure' => 'monthly',
                'processing_fee_percentage' => 5,
                'monthly_interest_rate' => 4,
                'arrears_rate' => 2,
            ]);

        $response->assertRedirect(route('admin.loan-applications.group-loans.details', $context['product']));
        $response->assertSessionHasErrors('relationship_manager_id');
    }

    public function test_admin_with_assign_permission_can_select_relationship_manager_in_details_step(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.create',
            'can assign relationship manager to group',
        ]);
        $admin->update(['is_relationship_manager' => false]);

        $suffix = Str::lower(Str::random(5));
        $relationshipManager = Admin::create([
            'company_id' => $admin->company_id,
            'first_name' => 'Assigned',
            'last_name' => 'Manager',
            'email' => 'assigned-rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
        ]);

        $context = $this->createGroupLoanContext($admin, 3);
        $memberIds = $context['customers']->pluck('id')->all();

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-members', $context['product']),
            [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => [
                    $memberIds[0] => $context['titles']['leader']->id,
                    $memberIds[1] => $context['titles']['member']->id,
                    $memberIds[2] => $context['titles']['member']->id,
                ],
            ]
        );

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-details', $context['product']),
            [
                'loan_name' => 'Assignable RM Group Loan',
                'start_date' => '2026-03-01',
                'due_date' => '2026-04-01',
                'repayment_structure' => 'monthly',
                'processing_fee_percentage' => 5,
                'monthly_interest_rate' => 4,
                'arrears_rate' => 2,
                'relationship_manager_id' => $relationshipManager->id,
            ]
        );

        $response->assertRedirect(route('admin.loan-applications.group-loans.principals', $context['product']));

        $wizardKey = 'group_loan_wizard_'.$context['product']->id.'_'.$admin->id;
        $response->assertSessionHas($wizardKey, function (array $wizard) use ($relationshipManager): bool {
            return (int) ($wizard['relationship_manager_id'] ?? 0) === $relationshipManager->id;
        });
    }

    public function test_admin_with_assign_permission_can_change_relationship_manager_from_review_step(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.create',
            'can assign relationship manager to group',
        ]);
        $admin->update(['is_relationship_manager' => false]);

        $suffix = Str::lower(Str::random(5));
        $initialRelationshipManager = Admin::create([
            'company_id' => $admin->company_id,
            'first_name' => 'Initial',
            'last_name' => 'Manager',
            'email' => 'initial-rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
        ]);
        $updatedRelationshipManager = Admin::create([
            'company_id' => $admin->company_id,
            'first_name' => 'Updated',
            'last_name' => 'Manager',
            'email' => 'updated-rm-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
        ]);

        $context = $this->createGroupLoanContext($admin, 3);
        $memberIds = $context['customers']->pluck('id')->all();

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-members', $context['product']),
            [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => [
                    $memberIds[0] => $context['titles']['leader']->id,
                    $memberIds[1] => $context['titles']['member']->id,
                    $memberIds[2] => $context['titles']['member']->id,
                ],
            ]
        );

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-details', $context['product']),
            [
                'loan_name' => 'RM Change Review Loan',
                'start_date' => '2026-03-01',
                'due_date' => '2026-04-01',
                'repayment_structure' => 'monthly',
                'processing_fee_percentage' => 5,
                'monthly_interest_rate' => 4,
                'arrears_rate' => 2,
                'relationship_manager_id' => $initialRelationshipManager->id,
            ]
        );

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-principals', $context['product']),
            [
                'principals' => [
                    $memberIds[0] => 1000,
                    $memberIds[1] => 1200,
                    $memberIds[2] => 900,
                ],
            ]
        );

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-documents', $context['product']),
            ['action' => 'continue']
        );

        $updateResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.update-review-relationship-manager', $context['product']),
            [
                'relationship_manager_id' => $updatedRelationshipManager->id,
            ]
        );

        $updateResponse->assertRedirect(route('admin.loan-applications.group-loans.review', $context['product']));

        $wizardKey = 'group_loan_wizard_'.$context['product']->id.'_'.$admin->id;
        $updateResponse->assertSessionHas($wizardKey, function (array $wizard) use ($updatedRelationshipManager): bool {
            return (int) ($wizard['relationship_manager_id'] ?? 0) === $updatedRelationshipManager->id;
        });
    }

    public function test_group_loan_members_validation_rejects_less_than_three_members(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.create']);
        $context = $this->createGroupLoanContext($admin, 2);

        $memberIds = $context['customers']->pluck('id')->all();

        $response = $this->from(route('admin.loan-applications.group-loans.members', $context['product']))
            ->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.group-loans.store-members', $context['product']), [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => [
                    $memberIds[0] => $context['titles']['leader']->id,
                    $memberIds[1] => $context['titles']['member']->id,
                ],
            ]);

        $response->assertRedirect(route('admin.loan-applications.group-loans.members', $context['product']));
        $response->assertSessionHasErrors('member_ids');
    }

    public function test_group_loan_members_validation_rejects_more_than_ten_members(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.create']);
        $context = $this->createGroupLoanContext($admin, 11);

        $memberIds = $context['customers']->pluck('id')->all();
        $titleMap = [];

        foreach ($memberIds as $index => $memberId) {
            $titleMap[$memberId] = $index === 0
                ? $context['titles']['leader']->id
                : $context['titles']['member']->id;
        }

        $response = $this->from(route('admin.loan-applications.group-loans.members', $context['product']))
            ->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.group-loans.store-members', $context['product']), [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => $titleMap,
            ]);

        $response->assertRedirect(route('admin.loan-applications.group-loans.members', $context['product']));
        $response->assertSessionHasErrors('member_ids');
    }

    public function test_group_loan_details_validation_requires_rates_and_valid_dates(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.create']);
        $context = $this->createGroupLoanContext($admin, 3);

        $memberIds = $context['customers']->pluck('id')->all();

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-members', $context['product']),
            [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $memberIds,
                'member_titles' => [
                    $memberIds[0] => $context['titles']['leader']->id,
                    $memberIds[1] => $context['titles']['member']->id,
                    $memberIds[2] => $context['titles']['member']->id,
                ],
            ]
        );

        $response = $this->from(route('admin.loan-applications.group-loans.details', $context['product']))
            ->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.group-loans.store-details', $context['product']), [
                'loan_name' => 'Invalid Group Loan',
                'start_date' => '2026-06-01',
                'due_date' => '2026-05-01',
                'repayment_structure' => 'monthly',
            ]);

        $response->assertRedirect(route('admin.loan-applications.group-loans.details', $context['product']));
        $response->assertSessionHasErrors([
            'due_date',
            'processing_fee_percentage',
            'monthly_interest_rate',
            'arrears_rate',
        ]);
    }

    public function test_approving_group_loan_moves_it_to_awaiting_disbursement_and_creates_member_loans(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.approve', 'loans.view']);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.approve', $application),
            ['notes' => 'All checks passed']
        );

        $response->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $application->refresh();
        $this->assertSame('awaiting_disbursement', $application->status);
        $this->assertSame($admin->id, $application->approved_by);

        $application->load('members.loan');

        foreach ($application->members as $member) {
            $this->assertNotNull($member->loan_id);
            $this->assertNotNull($member->loan);
            $this->assertSame('approved', $member->loan->status);
            $this->assertSame('pending', $member->loan->disbursement_status);
            $this->assertSame($application->id, data_get($member->loan->metadata, 'group_loan_application_id'));
        }
    }

    public function test_manual_disbursement_can_be_recorded_per_member_for_group_application(): void
    {
        config(['app.disbursement_type' => 'manual']);

        $admin = $this->makeAdminWithPermissions(['loans.approve', 'loans.disburse', 'loans.view']);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $this->actingAs($admin, 'admin')->post(route('admin.loan-applications.group-loans.approve', $application));

        $application->refresh()->load('members.loan');

        $wallet = Wallet::create([
            'name' => 'Operations Wallet',
            'wallet_number' => '260970000001',
            'provider' => 'mtn',
            'opening_balance' => 500000,
            'current_balance' => 500000,
            'is_active' => true,
        ]);

        foreach ($application->members as $member) {
            $loan = $member->loan;
            $this->assertNotNull($loan);

            $disbursementResponse = $this->actingAs($admin, 'admin')->post(
                route('admin.loans.disburse', $loan),
                [
                    'source_type' => 'wallet',
                    'source_id' => $wallet->id,
                    'reference_number' => 'GL-DISB-'.$member->id,
                    'disbursement_date' => Carbon::now()->toDateString(),
                    'description' => 'Manual group disbursement',
                ]
            );

            $disbursementResponse->assertRedirect(route('admin.loans.show', $loan));
        }

        // Trigger status sync from member loans
        $this->actingAs($admin, 'admin')->get(route('admin.loan-applications.group-loans.disbursement', $application))
            ->assertOk();

        $application->refresh()->load('members.loan');

        $this->assertSame('disbursed', $application->status);
        foreach ($application->members as $member) {
            $this->assertSame('completed', $member->disbursement_status);
            $this->assertSame('completed', $member->loan?->disbursement_status);
        }
    }

    public function test_group_loan_show_supports_approval_modals_customer_links_and_document_view_download(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.approve',
            'loans.reject',
            'approvals.approve',
            'approvals.reject',
            'customers.view',
        ]);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $documentPath = 'group-loan-documents/supporting/reviewer-proof.pdf';
        Storage::disk('public')->put($documentPath, 'dummy-review-file-content');

        $document = GroupLoanApplicationDocument::create([
            'group_loan_application_id' => $application->id,
            'document_name' => 'Reviewer Proof',
            'file_path' => $documentPath,
            'description' => 'Attachment for reviewer checks',
            'uploaded_by' => $admin->id,
        ]);

        $showResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.show', $application));

        $showResponse->assertOk();
        $showResponse->assertSee('Approve Application');
        $showResponse->assertSee('Reject Application');
        $showResponse->assertSee(route('admin.customers.show', $context['customers']->first()));
        $showResponse->assertSee(route('admin.loan-applications.group-loans.documents.view', [$application, $document]));
        $showResponse->assertSee(route('admin.loan-applications.group-loans.documents.download', [$application, $document]));

        $viewResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.documents.view', [$application, $document]));
        $viewResponse->assertOk();
        $this->assertStringContainsString(
            'inline;',
            (string) $viewResponse->headers->get('content-disposition')
        );

        $downloadResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.documents.download', [$application, $document]));
        $downloadResponse->assertOk();
        $this->assertStringContainsString(
            'attachment;',
            (string) $downloadResponse->headers->get('content-disposition')
        );
    }

    public function test_reviewer_can_send_application_back_for_modifications_and_prepare_revision_draft(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $rejectResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'changes_requested',
                'notes' => 'Please adjust the principal allocation and remove one member with high risk.',
                'action_required' => 'Reduce total principal by ZMW 5,000 and replace the third member.',
            ]
        );

        $rejectResponse->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame(
            'changes_requested',
            (string) data_get($application->metadata, 'rejection.resolution')
        );
        $this->assertSame(
            'Reduce total principal by ZMW 5,000 and replace the third member.',
            (string) data_get($application->metadata, 'rejection.action_required')
        );

        $showResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.show', $application));
        $showResponse->assertOk();
        $showResponse->assertSee('Modification Request');
        $showResponse->assertSee('Modify Application');
        $showResponse->assertSee('Decision Trail');

        $revisionResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.revision-draft', $application)
        );
        $revisionResponse->assertRedirect(route('admin.loan-applications.group-loans.members', [
            'loanProduct' => $context['product'],
            'customer_group_id' => $context['group']->id,
        ]));

        $wizardKey = 'group_loan_wizard_'.$context['product']->id.'_'.$admin->id;
        $revisionResponse->assertSessionHas($wizardKey, function (array $wizard) use ($application): bool {
            return (int) ($wizard['revision_source_application_id'] ?? 0) === $application->id
                && !empty($wizard['member_ids'])
                && !empty($wizard['member_calculations'])
                && !empty($wizard['totals']);
        });
    }

    public function test_revision_draft_prefills_loan_term_fields_on_details_step(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'changes_requested',
                'notes' => 'Please adjust and resubmit.',
                'action_required' => 'Revise as instructed.',
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.revision-draft', $application)
        )->assertRedirect(route('admin.loan-applications.group-loans.members', [
            'loanProduct' => $context['product'],
            'customer_group_id' => $context['group']->id,
        ]));

        $wizardKey = 'group_loan_wizard_'.$context['product']->id.'_'.$admin->id;
        $this->assertSame(3, (int) session($wizardKey.'.loan_term_value'));
        $this->assertSame('months', (string) session($wizardKey.'.loan_term_unit'));

        $detailsResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.details', $context['product']));
        $detailsResponse->assertOk();
        $detailsResponse->assertSee('name="loan_term_value"', false);
        $detailsResponse->assertSee('value="3"', false);
        $detailsResponse->assertSee('option value="months" selected', false);
    }

    public function test_changes_requested_application_shows_action_required_and_prepare_revision_from_index(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'changes_requested',
                'notes' => 'Adjust members and reduce the requested amount.',
                'action_required' => 'Replace one member and lower principal by ZMW 2,500.',
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $indexResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Changes Requested');
        $indexResponse->assertSee('Action required:');
        $indexResponse->assertSee('Replace one member and lower principal by ZMW 2,500.');
        $indexResponse->assertSee('Modify Application');
    }

    public function test_resubmission_updates_existing_application_in_place_without_creating_new_record(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $context = $this->createGroupLoanContext($admin, 4);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $originalId = $application->id;
        $originalReference = $application->reference;
        $removedCustomerId = $context['customers'][2]->id;
        $addedCustomerId = $context['customers'][3]->id;

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'changes_requested',
                'notes' => 'Please revise group composition and lower exposure.',
                'action_required' => 'Remove one member and reduce total amount.',
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.revision-draft', $application)
        )->assertRedirect(route('admin.loan-applications.group-loans.members', [
            'loanProduct' => $context['product'],
            'customer_group_id' => $context['group']->id,
        ]));

        $revisedMemberIds = [
            $context['customers'][0]->id,
            $context['customers'][1]->id,
            $addedCustomerId,
        ];

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-members', $context['product']),
            [
                'customer_group_id' => $context['group']->id,
                'member_ids' => $revisedMemberIds,
                'member_titles' => [
                    $revisedMemberIds[0] => $context['titles']['leader']->id,
                    $revisedMemberIds[1] => $context['titles']['coordinator']->id,
                    $revisedMemberIds[2] => $context['titles']['member']->id,
                ],
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.details', $context['product']));

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-details', $context['product']),
            [
                'loan_name' => 'Community Growth Loan - Revised',
                'terms_and_conditions' => 'Revised terms accepted by members.',
                'start_date' => '2026-03-10',
                'due_date' => '2026-06-10',
                'repayment_structure' => 'monthly',
                'processing_fee_percentage' => 4.5,
                'monthly_interest_rate' => 3.25,
                'arrears_rate' => 2,
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.principals', $context['product']));

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-principals', $context['product']),
            [
                'principals' => [
                    $revisedMemberIds[0] => 1500,
                    $revisedMemberIds[1] => 1300,
                    $revisedMemberIds[2] => 900,
                ],
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.documents', $context['product']));

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-documents', $context['product']),
            ['action' => 'continue']
        )->assertRedirect(route('admin.loan-applications.group-loans.review', $context['product']));

        $submitResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.submit', $context['product'])
        );
        $submitResponse->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $application->refresh();

        $this->assertSame($originalId, $application->id);
        $this->assertSame($originalReference, $application->reference);
        $this->assertSame('pending_approval', $application->status);
        $this->assertSame('Community Growth Loan - Revised', $application->loan_name);
        $this->assertNull($application->approved_by);
        $this->assertNull($application->approved_at);
        $this->assertNull($application->approval_notes);
        $this->assertDatabaseCount('group_loan_applications', 1);

        $application->load('members');
        $this->assertCount(3, $application->members);
        $this->assertDatabaseHas('group_loan_application_members', [
            'group_loan_application_id' => $application->id,
            'customer_id' => $addedCustomerId,
        ]);
        $this->assertDatabaseMissing('group_loan_application_members', [
            'group_loan_application_id' => $application->id,
            'customer_id' => $removedCustomerId,
        ]);

        $trail = collect(data_get($application->metadata, 'decision_trail', []));
        $this->assertTrue(
            $trail->contains(fn ($entry) => is_array($entry) && (($entry['action'] ?? null) === 'resubmitted'))
        );
        $this->assertGreaterThanOrEqual(2, (int) data_get($application->metadata, 'revision_number', 1));
    }

    public function test_relationship_manager_can_add_modification_note_to_decision_trail(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'changes_requested',
                'notes' => 'Please revise this application and resubmit.',
                'action_required' => 'Reduce total disbursement by ZMW 3,000.',
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $noteText = 'Removed one high-risk member and adjusted principal allocations as requested.';
        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-modification-note', $application),
            [
                'modification_note' => $noteText,
            ]
        );

        $response->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $application->refresh();
        $trail = collect(data_get($application->metadata, 'decision_trail', []));

        $this->assertTrue(
            $trail->contains(function ($entry) use ($noteText): bool {
                return is_array($entry)
                    && ($entry['action'] ?? null) === 'relationship_manager_note'
                    && ($entry['notes'] ?? null) === $noteText;
            })
        );

        $showResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.show', $application));
        $showResponse->assertOk();
        $showResponse->assertSee('Add Note to Trail');
        $showResponse->assertSee($noteText);
    }

    public function test_revision_review_step_shows_modification_note_and_can_post_back_to_review(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'changes_requested',
                'notes' => 'Please revise this application and resubmit.',
                'action_required' => 'Reduce total disbursement by ZMW 3,000.',
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.revision-draft', $application)
        )->assertRedirect(route('admin.loan-applications.group-loans.members', [
            'loanProduct' => $context['product'],
            'customer_group_id' => $context['group']->id,
        ]));

        $reviewResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.group-loans.review', $context['product']));
        $reviewResponse->assertOk();
        $reviewResponse->assertSee('Modification Progress Note');
        $reviewResponse->assertSee('Add Note to Decision Trail');

        $noteText = 'Updated member principals and replaced ineligible member before resubmission.';
        $noteResponse = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.store-modification-note', $application),
            [
                'modification_note' => $noteText,
                'return_to' => 'review',
                'loan_product_id' => $context['product']->id,
            ]
        );

        $noteResponse->assertRedirect(route('admin.loan-applications.group-loans.review', $context['product']));

        $application->refresh();
        $trail = collect(data_get($application->metadata, 'decision_trail', []));

        $this->assertTrue(
            $trail->contains(function ($entry) use ($noteText): bool {
                return is_array($entry)
                    && ($entry['action'] ?? null) === 'relationship_manager_note'
                    && ($entry['notes'] ?? null) === $noteText;
            })
        );
    }

    public function test_original_submitter_can_add_modification_note_when_not_assigned_relationship_manager(): void
    {
        $submitter = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $submitter->update(['is_relationship_manager' => false]);

        $suffix = Str::lower(Str::random(6));
        $assignedRelationshipManager = Admin::create([
            'company_id' => $submitter->company_id,
            'first_name' => 'Assigned',
            'last_name' => 'RM',
            'email' => 'assigned-rm-note-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
        ]);

        $context = $this->createGroupLoanContext($submitter, 3);
        $application = $this->createPendingGroupLoanApplication($submitter, $context, 3);
        $application->update([
            'relationship_manager_id' => $assignedRelationshipManager->id,
        ]);

        $this->actingAs($submitter, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'changes_requested',
                'notes' => 'Please update and resubmit.',
                'action_required' => 'Lower risk exposure in the member mix.',
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $noteText = 'Submitter update: revised composition and reduced total exposure.';
        $response = $this->actingAs($submitter, 'admin')->post(
            route('admin.loan-applications.group-loans.store-modification-note', $application),
            [
                'modification_note' => $noteText,
            ]
        );

        $response->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $application->refresh();
        $trail = collect(data_get($application->metadata, 'decision_trail', []));

        $this->assertTrue(
            $trail->contains(function ($entry) use ($noteText): bool {
                return is_array($entry)
                    && ($entry['action'] ?? null) === 'relationship_manager_note'
                    && ($entry['notes'] ?? null) === $noteText;
            })
        );
    }

    public function test_permanent_rejection_does_not_allow_revision_draft_creation(): void
    {
        $admin = $this->makeAdminWithPermissions([
            'loans.view',
            'loans.reject',
            'loans.create',
        ]);
        $context = $this->createGroupLoanContext($admin, 3);
        $application = $this->createPendingGroupLoanApplication($admin, $context, 3);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-applications.group-loans.reject', $application),
            [
                'rejection_resolution' => 'rejected_permanent',
                'notes' => 'This application does not meet lending policy requirements.',
            ]
        )->assertRedirect(route('admin.loan-applications.group-loans.show', $application));

        $application->refresh();
        $this->assertSame(
            'rejected_permanent',
            (string) data_get($application->metadata, 'rejection.resolution')
        );

        $revisionResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.group-loans.revision-draft', $application));
        $revisionResponse->assertRedirect(route('admin.loan-applications.group-loans.show', $application));
        $revisionResponse->assertSessionHas('error');
    }

    public function test_existing_individual_loan_search_flow_still_works_and_group_loans_redirect_to_members_step(): void
    {
        $admin = $this->makeAdminWithPermissions(['loans.create']);

        $characterProduct = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Loan',
            'code' => 'CHAR-REG-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $groupProduct = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Group Loan',
            'code' => 'GROUP-REG-001',
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $characterResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.search-customer', $characterProduct));

        $characterResponse->assertOk();
        $characterResponse->assertSee('Search Customer');

        $groupResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.search-customer', $groupProduct));

        $groupResponse->assertRedirect(route('admin.loan-applications.group-loans.members', $groupProduct));
    }
}
