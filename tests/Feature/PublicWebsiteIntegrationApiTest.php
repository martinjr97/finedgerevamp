<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Faq;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicWebsiteIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['website.api_key' => 'test-website-key']);
    }

    public function test_faqs_requires_api_key(): void
    {
        $this->getJson('/api/v1/public/website/faqs')
            ->assertUnauthorized();
    }

    public function test_lists_public_faqs_only(): void
    {
        Faq::create([
            'question' => 'Public question?',
            'answer' => 'Public answer.',
            'visibility' => Faq::VISIBILITY_PUBLIC,
            'is_active' => true,
        ]);
        Faq::create([
            'question' => 'Customer only?',
            'answer' => 'Hidden.',
            'visibility' => Faq::VISIBILITY_AUTHENTICATED,
            'is_active' => true,
        ]);

        $response = $this->withHeader('X-Finedge-Website-Key', 'test-website-key')
            ->getJson('/api/v1/public/website/faqs');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Public question?', $response->json('data.0.question'));
    }

    public function test_loan_products_requires_api_key(): void
    {
        $this->getJson('/api/v1/public/website/loan-products')
            ->assertUnauthorized();
    }

    public function test_lists_public_loan_products_with_selected_rates(): void
    {
        $context = $this->seedPublicLoanProduct();

        $response = $this->withHeader('X-Finedge-Website-Key', 'test-website-key')
            ->getJson('/api/v1/public/website/loan-products');

        $response->assertOk();
        $this->assertSame($context['loan_product']->id, $response->json('data.0.id'));
        $this->assertCount(1, $response->json('data.0.tenures'));
    }

    public function test_quote_uses_selected_rate_rows_only(): void
    {
        $context = $this->seedPublicLoanProduct();

        $response = $this->withHeader('X-Finedge-Website-Key', 'test-website-key')
            ->postJson('/api/v1/public/website/loan-quotes', [
                'loan_product_id' => $context['loan_product']->id,
                'principal' => 5000,
                'tenure_months' => 3,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.loan_product_id', $context['loan_product']->id)
            ->assertJsonPath('data.principal', '5000.00');
    }

    /**
     * @return array{loan_product: LoanProduct, loan_rate: LoanRate, rate_type: LoanRateType}
     */
    private function seedPublicLoanProduct(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Website Co '.$suffix,
            'slug' => 'website-co-'.$suffix,
            'code' => 'WC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character Loan '.$suffix,
            'code' => 'CHAR-'.$suffix,
            'category' => 'character',
            'description' => 'Public website test product',
            'is_active' => true,
            'is_public_on_website' => true,
            'accrual_type' => 'at_beginning',
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $loanProduct->id,
            'name' => 'Standard '.$suffix,
            'code' => 'RT-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
            'is_public_on_website' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 2,
            'term_interest_percentage' => 15,
            'arrear_rate' => 0,
            'is_active' => true,
        ]);

        $loanProduct->update(['public_website_loan_rate_type_id' => $rateType->id]);
        $loanProduct->publicWebsiteRates()->sync([$loanRate->id]);

        return [
            'loan_product' => $loanProduct->fresh(['publicWebsiteRateType', 'publicWebsiteRates']),
            'loan_rate' => $loanRate,
            'rate_type' => $rateType,
        ];
    }
}
