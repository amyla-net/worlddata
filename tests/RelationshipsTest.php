<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Items\Country;
use Symfony\Component\Intl\Countries;

final class RelationshipsTest extends TestCase
{
    public function test_symfonys_optional_user_assigned_country_codes_have_relationships(): void
    {
        Countries::withUserAssigned(true);
        try {
            $country = world()->countries()->findOrFail('XK');
            $this->assertSame('EU', $country->continent()->code);
            $this->assertNotNull($country->languages()->find('sq'));
        } finally {
            Countries::withUserAssigned(false);
        }
    }

    public function test_every_country_has_one_consistent_continent(): void
    {
        $world = world();
        $seen = [];
        foreach ($world->continents() as $continent) {
            foreach ($continent->countries() as $country) {
                $this->assertSame($continent->code, $country->continent()->code);
                $this->assertArrayNotHasKey($country->code, $seen);
                $seen[$country->code] = true;
            }
        }
        $this->assertEqualsCanonicalizing($world->countries()->keys()->all(), array_keys($seen));
        $this->assertSame('AN', $world->countries()->findOrFail('AQ')->continentCode);
        $this->assertSame('NA', $world->countries()->findOrFail('MX')->continentCode);
        $this->assertSame('NA', $world->countries()->findOrFail('CU')->continentCode);
        $this->assertSame('EU', $world->countries()->findOrFail('GB')->continentCode);
        $this->assertSame('AS', $world->countries()->findOrFail('TR')->continentCode);
    }

    public function test_currency_relationships_are_current_and_capture_the_context_date(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 29));
        $current = world();
        foreach (['BG', 'HR', 'DE', 'FR'] as $country) {
            $this->assertSame(['EUR'], $current->countries()->findOrFail($country)->currencies()->keys()->all());
            $this->assertNotNull($current->currencies()->findOrFail('EUR')->countries()->find($country));
        }
        $this->assertNotNull($current->currencies()->find('DEM'));
        $this->assertTrue($current->currencies()->findOrFail('DEM')->countries()->isEmpty());
        $this->assertGreaterThan(1, $current->currencies()->findOrFail('EUR')->countries()->count());

        $this->travelTo(now('UTC')->setDate(2022, 6, 1));
        $this->assertSame(['HRK'], world()->countries()->findOrFail('HR')->currencies()->keys()->all());
        $this->assertSame(['EUR'], $current->countries()->findOrFail('HR')->currencies()->keys()->all());
        $this->travelBack();
    }

    public function test_official_languages_include_regional_and_supplemental_languages(): void
    {
        $world = world();
        $swiss = $world->countries()->findOrFail('CH')->languages();
        foreach (['de', 'fr', 'it', 'rm', 'gsw'] as $code) {
            $this->assertNotNull($swiss->find($code));
            $this->assertNotNull($world->languages()->findOrFail($code)->countries()->find('CH'));
        }
        $this->assertNull($swiss->find('en'));
        $this->assertSame('Chortí', $world->languages()->findOrFail('caa')->name);
        $this->assertNotNull($world->countries()->findOrFail('GT')->languages()->find('caa'));
        $this->assertNotNull($world->languages()->findOrFail('caa')->countries()->find('GT'));
        $canadian = $world->countries()->findOrFail('CA')->languages()->keys()->all();
        $this->assertSame($canadian, array_values(array_unique($canadian)));
        $this->assertTrue($world->countries()->findOrFail('AQ')->languages()->isEmpty());
    }

    public function test_tlds_use_assignments_instead_of_registry_operator_addresses(): void
    {
        $world = world();
        foreach (['de' => 'DE', 'uk' => 'GB', 'xn--p1ai' => 'RU', 'xn--fiqs8s' => 'CN'] as $tld => $country) {
            $this->assertSame([$country], $world->tlds()->findOrFail($tld)->countries()->keys()->all());
            $this->assertNotNull($world->countries()->findOrFail($country)->tlds()->find($tld));
        }
        foreach (['com', 'berlin', 'eu', 'su', 'ac'] as $tld) {
            $this->assertTrue($world->tlds()->findOrFail($tld)->countries()->isEmpty());
        }
        $this->assertNull($world->tlds()->find('abarth'));
    }

    public function test_all_country_relationships_have_existing_targets_and_matching_reverse_relations(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 29));
        $world = world();
        foreach ($world->countries() as $country) {
            foreach (['currencies', 'languages', 'timezones', 'tlds'] as $relation) {
                foreach ($country->{$relation}() as $item) {
                    $this->assertNotNull($world->{$relation}()->find($item->code));
                    $this->assertNotNull($item->countries()->find($country->code), $country->code.': '.$relation.' '.$item->code);
                }
            }
        }
        $this->travelBack();
    }

    public function test_reverse_relations_never_refer_to_nonexistent_countries(): void
    {
        $world = world();
        foreach (['currencies', 'languages', 'timezones', 'tlds'] as $relation) {
            foreach ($world->{$relation}() as $item) {
                $this->assertTrue($item->countries()->every(fn (Country $country) => $country->{$relation}()->find($item->code) !== null));
            }
        }
    }
}
