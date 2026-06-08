<?php

namespace Tests\Unit;

use App\Rules\ZambianPhoneNumber;
use App\Support\PhoneNumberFormatter;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ZambianPhoneNumberTest extends TestCase
{
    /**
     * @dataProvider validNumbersProvider
     */
    public function test_valid_zambian_mobile_numbers(string $number): void
    {
        $this->assertTrue(PhoneNumberFormatter::isValid($number));
        $this->assertEmpty($this->validate($number));
    }

    /**
     * @dataProvider invalidNumbersProvider
     */
    public function test_invalid_numbers_are_rejected(?string $number): void
    {
        if ($number === null || $number === '') {
            $this->assertFalse(PhoneNumberFormatter::isValid($number));

            return;
        }

        $this->assertFalse(PhoneNumberFormatter::isValid($number));
        $this->assertNotEmpty($this->validate($number));
    }

    public function test_diagnose_gives_actionable_hint_for_local_format(): void
    {
        $message = PhoneNumberFormatter::diagnose('0978232334');

        $this->assertStringContainsString('260978232334', $message);
        $this->assertStringContainsString('0', $message);
    }

    public static function validNumbersProvider(): array
    {
        return [
            ['260978232334'],
            ['260752334544'],
            ['260961234567'],
            ['260771234567'],
        ];
    }

    public static function invalidNumbersProvider(): array
    {
        return [
            ['0978232334'],
            ['+260978232334'],
            ['26097823233'],
            ['2609782323344'],
            ['260118232334'],
            ['26097823ABCD'],
            [''],
            [null],
        ];
    }

  /**
     * @return array<string, list<string>>
     */
    private function validate(?string $value): array
    {
        return Validator::make(
            ['phone' => $value],
            ['phone' => ['required', 'string', new ZambianPhoneNumber()]]
        )->errors()->toArray();
    }
}
