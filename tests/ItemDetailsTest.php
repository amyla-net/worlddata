<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Exceptions\ItemNotFoundException;
use Amyla\Worlddata\Items\Country;
use Symfony\Component\Intl\Countries;

final class ItemDetailsTest extends TestCase
{
    public function test_country_aliases_resolve_to_the_same_canonical_items(): void
    {
        $countries = world('de')->countries();
        $germany = $countries->findOrFail('DE');
        foreach (['de', ' DEU ', 'deu', '276'] as $code) {
            $this->assertSame($germany, $countries->findOrFail($code));
        }
        $this->assertSame('AF', $countries->findOrFail('004')->code);
        $this->assertSame('004', $countries->findOrFail('AFG')->numericCode);
        foreach (['4', '000', 'ZZZ', '0276', 'D!E'] as $code) {
            $this->assertNull($countries->find($code));
        }
        $this->assertFalse($countries->has('DEU'));
        $this->assertNull($countries->except('DE')->find('276'));

        foreach ($countries as $code => $country) {
            $this->assertSame($code, $country->code);
            $this->assertSame($country, $countries->find($country->alpha3));
            $this->assertSame($country, $countries->find($country->numericCode));
        }
    }

    public function test_unknown_country_aliases_keep_the_package_exception(): void
    {
        $this->expectException(ItemNotFoundException::class);
        world()->countries()->findOrFail('000');
    }

    public function test_user_assigned_aliases_follow_symfonys_setting(): void
    {
        $previous = Countries::withUserAssigned();
        try {
            Countries::withUserAssigned(false);
            $this->assertNull(world()->countries()->find('XKK'));
            $this->assertNull(world()->countries()->find('983'));
            Countries::withUserAssigned(true);
            $country = world()->countries()->findOrFail('XKK');
            $this->assertSame('XK', $country->code);
            $this->assertSame('983', $country->numericCode);
            $this->assertSame('XK', world()->countries()->findOrFail('983')->code);
        } finally {
            Countries::withUserAssigned($previous);
        }
    }

    public function test_missing_country_code_metadata_is_nullable(): void
    {
        $country = new Country(world(), 'ZZ', 'Unknown', null);
        $this->assertNull($country->alpha3);
        $this->assertNull($country->numericCode);
        $this->assertFalse($country->matches(''));
        $this->assertSame([
            'code' => 'ZZ', 'name' => 'Unknown', 'continentCode' => null,
            'alpha3' => null, 'numericCode' => null,
        ], $country->toArray());
    }

    public function test_currency_details_cover_fraction_digits_and_cash_rounding(): void
    {
        $currencies = world('en')->currencies();
        $this->assertSame([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€',
            'fractionDigits' => 2, 'roundingIncrement' => 0,
            'cashFractionDigits' => 2, 'cashRoundingIncrement' => 0,
        ], $currencies->findOrFail('EUR')->toArray());
        $this->assertSame(0, $currencies->findOrFail('JPY')->fractionDigits);
        $this->assertSame(3, $currencies->findOrFail('KWD')->fractionDigits);
        $franc = $currencies->findOrFail('CHF');
        $this->assertSame(2, $franc->cashFractionDigits);
        $this->assertSame(5, $franc->cashRoundingIncrement);
        $this->assertSame(0, $franc->roundingIncrement);
        $this->assertSame(2, $currencies->findOrFail('DEM')->fractionDigits);
        $this->assertSame($franc->toArray(), json_decode(json_encode($franc, JSON_THROW_ON_ERROR), true));
        $this->assertArrayNotHasKey('countries', $franc->toArray());
    }

    public function test_symbols_keep_their_original_display_locale(): void
    {
        $english = world('en')->currencies()->findOrFail('USD');
        $french = world('fr')->currencies()->findOrFail('USD');
        app()->setLocale('ja');
        $this->assertSame('$', $english->symbol);
        $this->assertSame('$US', $french->symbol);
        $this->assertSame('￥', world()->currencies()->findOrFail('JPY')->symbol);
    }

    public function test_native_names_are_available_only_with_a_supported_own_locale(): void
    {
        $languages = world('en')->languages();
        foreach (['de' => 'Deutsch', 'fr' => 'français', 'ja' => '日本語'] as $code => $nativeName) {
            $language = $languages->findOrFail($code);
            $this->assertSame($nativeName, $language->nativeName);
            $this->assertSame($nativeName, $language->toArray()['nativeName']);
        }
        $this->assertSame('German', $languages->findOrFail('de')->name);
        $this->assertSame('German', $languages->findOrFail('de')->toArray()['name']);
        $this->assertNull($languages->findOrFail('ace')->nativeName);
        $this->assertNull($languages->findOrFail('caa')->nativeName);
        $this->assertSame('Chortí', $languages->findOrFail('caa')->name);
        $this->assertNull(json_decode(json_encode($languages->findOrFail('caa'), JSON_THROW_ON_ERROR), true)['nativeName']);
    }

    public function test_new_scalar_properties_are_immutable(): void
    {
        $items = [world()->countries()->findOrFail('DE'), world()->currencies()->findOrFail('EUR'), world()->languages()->findOrFail('de')];
        foreach (array_combine(['alpha3', 'fractionDigits', 'nativeName'], $items) as $property => $item) {
            try {
                $item->{$property} = null;
                $this->fail('Expected a readonly property.');
            } catch (\Error $error) {
                $this->assertStringContainsString('readonly', $error->getMessage());
            }
        }
    }
}
