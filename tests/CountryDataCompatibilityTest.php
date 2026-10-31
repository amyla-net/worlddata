<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Support\Snapshot;
use Amyla\Worlddata\World;
use Symfony\Component\Intl\Countries;

final class CountryDataCompatibilityTest extends TestCase
{
    public function test_new_symfony_countries_remain_available_when_the_snapshot_lags(): void
    {
        $data = $this->snapshot();
        // Treat DE as a country added after this relationship snapshot was built.
        unset($data['countryContinents']['DE'], $data['countryLanguages']['DE']);
        foreach ($data['tlds'] as &$tld) {
            if ($tld['countryCode'] === 'DE') {
                $tld['countryCode'] = null;
            }
        }
        unset($tld);
        $world = $this->worldWithSnapshot($data);
        $country = $world->countries()->findOrFail('DE');

        $this->assertSame(array_keys(Countries::getNames('de')), $world->countries()->keys()->all());
        $this->assertSame('Deutschland', $country->name);
        $this->assertNull($country->continentCode);
        $this->assertNull($country->continent());
        $this->assertSame(['code' => 'DE', 'name' => 'Deutschland', 'continentCode' => null, 'alpha3' => 'DEU', 'numericCode' => '276'], $country->toArray());
        $this->assertSame($country, $world->countries()->find('DEU'));
        $this->assertSame($country, $world->countries()->find('276'));
        $this->assertSame($country->toArray(), json_decode(json_encode($country, JSON_THROW_ON_ERROR), true));
        $this->assertTrue($country->languages()->isEmpty());
        $this->assertTrue($country->tlds()->isEmpty());
        $this->assertTrue($country->currencies()->has('EUR'));
        $this->assertTrue($country->timezones()->has('Europe/Berlin'));
        $this->assertTrue($world->currencies()->findOrFail('EUR')->countries()->has('DE'));
        $this->assertTrue($world->timezones()->findOrFail('Europe/Berlin')->countries()->has('DE'));
        $this->assertFalse($world->continents()->findOrFail('EU')->countries()->has('DE'));
        $this->assertFalse($world->languages()->findOrFail('de')->countries()->has('DE'));
        $this->assertTrue($world->tlds()->findOrFail('de')->countries()->isEmpty());
        $this->assertSame('EU', $world->countries()->findOrFail('FR')->continent()?->code);
    }

    public function test_a_missing_continent_preserves_other_known_relationships(): void
    {
        $data = $this->snapshot();
        unset($data['countryContinents']['DE']);
        $world = $this->worldWithSnapshot($data);
        $country = $world->countries()->findOrFail('DE');

        $this->assertNull($country->continent());
        $this->assertTrue($country->languages()->has('de'));
        $this->assertTrue($country->tlds()->has('de'));
        $this->assertTrue($world->languages()->findOrFail('de')->countries()->has('DE'));
        $this->assertTrue($world->tlds()->findOrFail('de')->countries()->has('DE'));
    }

    public function test_countries_removed_by_symfony_are_not_restored_by_stale_relationships(): void
    {
        $data = $this->snapshot();
        // Simulate a stale snapshot that still includes the retired CS code.
        $data['countryContinents']['CS'] = 'EU';
        $data['countryLanguages']['CS'] = ['cs'];
        $data['tlds']['cs'] = ['name' => 'cs', 'type' => 'country-code', 'countryCode' => 'CS'];
        $world = $this->worldWithSnapshot($data);

        $this->assertSame(array_keys(Countries::getNames('de')), $world->countries()->keys()->all());
        $this->assertNull($world->countries()->find('CS'));
        $this->assertFalse($world->continents()->findOrFail('EU')->countries()->has('CS'));
        $this->assertFalse($world->languages()->findOrFail('cs')->countries()->has('CS'));
        $this->assertTrue($world->tlds()->findOrFail('cs')->countries()->isEmpty());
        $this->assertTrue($world->currencies()->findOrFail('EUR')->countries()->has('DE'));
        $this->assertTrue($world->timezones()->findOrFail('Europe/Berlin')->countries()->has('DE'));
    }

    private function snapshot(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../resources/data.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function worldWithSnapshot(array $data): World
    {
        $path = tempnam(sys_get_temp_dir(), 'worlddata-country-test-');
        try {
            file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
            app()->instance(Snapshot::class, new Snapshot($path));

            return world('de');
        } finally {
            unlink($path);
        }
    }
}
