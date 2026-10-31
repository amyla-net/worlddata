<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Exceptions\ItemNotFoundException;
use Amyla\Worlddata\Items\Continent;
use Amyla\Worlddata\Items\Country;
use Amyla\Worlddata\Items\Currency;
use Amyla\Worlddata\Items\Language;
use Amyla\Worlddata\Items\Timezone;
use Amyla\Worlddata\Items\Tld;
use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

final class WorldTest extends TestCase
{
    public static function datasets(): array
    {
        return [
            ['continents', 'EU', Continent::class],
            ['countries', 'DE', Country::class],
            ['currencies', 'EUR', Currency::class],
            ['languages', 'de', Language::class],
            ['timezones', 'Europe/Berlin', Timezone::class],
            ['tlds', 'de', Tld::class],
        ];
    }

    #[DataProvider('datasets')]
    public function test_datasets_work_offline_with_typed_items_and_laravel_collections(string $dataset, string $code, string $class): void
    {
        Http::preventStrayRequests();
        $collection = world()->{$dataset}();

        $this->assertInstanceOf(WorldCollection::class, $collection);
        $this->assertInstanceOf($class, $collection->find($code));
        $this->assertSame($collection->find($code), $collection[$code]);
        $this->assertNotEmpty($collection->pluck('name', 'code')->all());
        $this->assertSame($code, $collection->sortBy('name')->filter(fn ($item) => $item->code === $code)->findOrFail($code)->code);
        $this->assertNull($collection->find('not a code!'));
        Http::assertNothingSent();
    }

    public function test_helper_and_container_use_current_application_locale_without_leaking_context(): void
    {
        $this->assertInstanceOf(World::class, app(World::class));
        $english = world();
        app()->setLocale('de');
        $german = world();

        $this->assertSame('Germany', $english->countries()->findOrFail('DE')->name);
        $this->assertSame('Deutschland', $german->countries()->findOrFail('DE')->name);
        $this->assertSame('Germany', world('en')->countries()->findOrFail('DE')->name);
        $this->assertSame('de', app()->getLocale());
        $this->assertSame('Deutschland', $german->continents()->findOrFail('EU')->countries()->findOrFail('DE')->name);

        app()->setLocale('fr');
        $this->assertSame('Deutschland', $german->countries()->findOrFail('DE')->name);
        $this->assertSame('Allemagne', world()->countries()->findOrFail('DE')->name);
    }

    public function test_regional_locales_and_application_fallback_are_resolved(): void
    {
        config(['app.fallback_locale' => 'de']);
        $this->assertSame('Deutschland', world('de-de')->countries()->findOrFail('DE')->name);
        $this->assertSame('Europa', world('de-CH')->continents()->findOrFail('EU')->name);
        $this->assertSame('Deutschland', world('zz_INVALID')->countries()->findOrFail('DE')->name);
        $this->assertSame('Europa', world('zz')->continents()->findOrFail('EU')->name);
        config(['app.fallback_locale' => 'zz']);
        $this->assertSame('Germany', world('zz')->countries()->findOrFail('DE')->name);
    }

    public function test_names_do_not_change_the_global_intl_locale(): void
    {
        $previous = \Locale::getDefault();
        world('de')->countries();
        world('fr')->languages();
        $this->assertSame($previous, \Locale::getDefault());
    }

    public function test_find_normalizes_only_the_documented_code_forms(): void
    {
        $this->assertSame('DE', world()->countries()->findOrFail(' de ')->code);
        $this->assertSame('de', world()->languages()->findOrFail('DE')->code);
        $this->assertNull(world()->countries()->find('d!e'));
        $this->assertNull(world()->timezones()->find('europe/berlin'));
        foreach (['.рф', '.РФ', 'рф', 'xn--p1ai', '.XN--P1AI'] as $code) {
            $this->assertSame('xn--p1ai', world()->tlds()->findOrFail($code)->code);
        }
        $this->assertNull(world()->tlds()->find('..de'));
        $this->assertNull(world()->tlds()->find('xn-p1ai'));
    }

    public function test_find_or_fail_uses_a_package_exception(): void
    {
        $this->expectException(ItemNotFoundException::class);
        world()->countries()->findOrFail('unknown');
    }

    public function test_json_serializes_only_scalar_fields_and_objects_are_immutable(): void
    {
        $country = world('de')->countries()->findOrFail('DE');
        $expected = ['code' => 'DE', 'name' => 'Deutschland', 'continentCode' => 'EU', 'alpha3' => 'DEU', 'numericCode' => '276'];
        $this->assertSame($expected, $country->toArray());
        $this->assertSame($expected, json_decode(json_encode($country, JSON_THROW_ON_ERROR), true));
        $this->assertSame(['DE' => $expected], world('de')->countries()->only('DE')->toArray());
        $tld = world()->tlds()->findOrFail('.рф');
        $this->assertSame('.рф', $tld->name);
        $this->assertSame('.xn--p1ai', $tld->ascii);
        $this->assertSame('country-code', $tld->type);

        $this->expectException(\Error::class);
        $country->name = 'changed';
    }

    public function test_mutating_one_collection_does_not_modify_other_results(): void
    {
        $world = world();
        $countries = $world->countries();
        $countries->forget('DE');
        $this->assertNotNull($world->countries()->find('DE'));
    }
}
