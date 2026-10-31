<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Rules\CountryCode;
use Amyla\Worlddata\Rules\CurrencyCode;
use Amyla\Worlddata\Rules\LanguageCode;
use Amyla\Worlddata\Rules\TimezoneCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Intl\Countries;

final class ValidationRulesTest extends TestCase
{
    public static function values(): array
    {
        return [
            [CountryCode::class, 'DE', true],
            [CountryCode::class, ' deu ', true],
            [CountryCode::class, '276', true],
            [CountryCode::class, '004', true],
            [CountryCode::class, '4', false],
            [CountryCode::class, 276, false],
            [CountryCode::class, 'ZZ', false],
            [CountryCode::class, ['DE'], false],
            [CurrencyCode::class, ' eur ', true],
            [CurrencyCode::class, 'DEM', true],
            [CurrencyCode::class, 'not-a-currency', false],
            [CurrencyCode::class, true, false],
            [LanguageCode::class, ' DE ', true],
            [LanguageCode::class, 'caa', true],
            [LanguageCode::class, 'de-DE', false],
            [LanguageCode::class, new \stdClass, false],
            [TimezoneCode::class, ' Europe/Berlin ', true],
            [TimezoneCode::class, 'europe/berlin', false],
            [TimezoneCode::class, 'Mars/Olympus', false],
        ];
    }

    #[DataProvider('values')]
    public function test_rules_accept_only_supported_string_codes(string $rule, mixed $value, bool $valid): void
    {
        $validator = Validator::make(['value' => $value], ['value' => ['required', new $rule]]);
        $this->assertSame($valid, $validator->passes());
        if (! $valid) {
            $this->assertStringContainsString('selected value is invalid', $validator->errors()->first('value'));
        }
    }

    public function test_allow_lists_compare_canonical_items_without_changing_the_input(): void
    {
        $data = ['country' => ' deu ', 'currency' => ' eur ', 'language' => 'DE', 'zone' => 'Europe/Berlin'];
        $validator = Validator::make($data, [
            'country' => [new CountryCode(only: ['276'])],
            'currency' => [new CurrencyCode(only: ['EUR'])],
            'language' => [new LanguageCode(only: ['de'])],
            'zone' => [new TimezoneCode(only: ['Europe/Berlin'])],
        ]);
        $this->assertTrue($validator->passes());
        $this->assertSame($data, $validator->validated());
        foreach ([
            [new CountryCode(only: ['AT']), 'DE'],
            [new CountryCode(only: ['ZZ']), 'DE'],
            [new CurrencyCode(only: ['USD']), 'EUR'],
            [new LanguageCode(only: ['fr']), 'de'],
            [new TimezoneCode(only: ['Europe/Paris']), 'Europe/Berlin'],
        ] as [$rule, $value]) {
            $this->assertFalse($this->passes($rule, $value));
        }
    }

    public function test_presence_and_nullability_follow_standard_laravel_rules(): void
    {
        $rule = new CountryCode;
        $this->assertFalse(Validator::make([], ['country' => ['required', $rule]])->passes());
        $this->assertFalse(Validator::make(['country' => ''], ['country' => ['required', $rule]])->passes());
        $this->assertTrue(Validator::make([], ['country' => ['sometimes', $rule]])->passes());
        $this->assertTrue(Validator::make(['country' => null], ['country' => ['nullable', $rule]])->passes());
        $this->assertFalse(Validator::make(['country' => null], ['country' => [$rule]])->passes());
    }

    public function test_current_currency_validation_refreshes_the_date_when_a_rule_is_reused(): void
    {
        $current = new CurrencyCode(currentOnly: true);
        try {
            $this->travelTo(now('UTC')->setDate(2022, 6, 1));
            $this->assertTrue($this->passes($current, 'HRK'));
            $this->travelTo(now('UTC')->setDate(2026, 10, 31));
            $this->assertFalse($this->passes($current, 'HRK'));
            $this->assertFalse($this->passes($current, 'DEM'));
            $this->assertTrue($this->passes($current, 'EUR'));
            $this->assertFalse($this->passes(new CurrencyCode(only: ['USD'], currentOnly: true), 'EUR'));
            $this->assertTrue($this->passes(new CurrencyCode, 'HRK'));
        } finally {
            $this->travelBack();
        }
    }

    public function test_country_rules_respect_changes_to_user_assigned_code_support(): void
    {
        $rule = new CountryCode;
        $previous = Countries::withUserAssigned();
        try {
            Countries::withUserAssigned(false);
            $this->assertFalse($this->passes($rule, 'XKK'));
            Countries::withUserAssigned(true);
            $this->assertTrue($this->passes($rule, 'XKK'));
            $this->assertTrue($this->passes($rule, '983'));
        } finally {
            Countries::withUserAssigned($previous);
        }
    }

    public function test_error_messages_follow_the_locale_at_validation_time(): void
    {
        app('translator')->addLines(['validation.in' => ':attribute ist ungültig.'], 'de');
        $rule = new CountryCode;
        $english = Validator::make(['country' => 'ZZ'], ['country' => [$rule]]);
        $this->assertSame('The selected country is invalid.', $english->errors()->first('country'));
        app()->setLocale('de');
        $german = Validator::make(['country' => 'ZZ'], ['country' => [$rule]]);
        $this->assertSame('country ist ungültig.', $german->errors()->first('country'));
    }

    private function passes(ValidationRule $rule, mixed $value): bool
    {
        return Validator::make(['value' => $value], ['value' => [$rule]])->passes();
    }
}
