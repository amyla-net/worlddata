<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Items\Continent;
use Amyla\Worlddata\WorldCollection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class CollectionHelpersTest extends TestCase
{
    public static function searches(): array
    {
        return [
            ['continents', 'Europe', 'EU'],
            ['countries', 'Germany', 'DE'],
            ['currencies', 'Japanese Yen', 'JPY'],
            ['languages', 'Japanese', 'ja'],
            ['timezones', 'Berlin', 'Europe/Berlin'],
            ['tlds', '.РФ', 'xn--p1ai'],
        ];
    }

    #[DataProvider('searches')]
    public function test_name_search_works_across_all_datasets(string $dataset, string $query, string $code): void
    {
        $items = world('de')->{$dataset}();
        $result = $items->searchByName($query);
        $this->assertSame($items->findOrFail($code), $result->findOrFail($code));
        $this->assertSame($items->keys()->all(), $items->searchByName('  ')->keys()->all());
        $this->assertNotSame($items, $items->searchByName(''));
        $this->assertTrue($items->searchByName('no such worlddata item!')->isEmpty());
    }

    public function test_search_includes_aliases_and_explicit_additional_languages(): void
    {
        $countries = world('de')->countries();
        $this->assertSame(['DE'], $countries->searchByName('Deutschland')->keys()->all());
        $this->assertSame(['DE'], $countries->searchByName('  gErMaNy  ')->keys()->all());
        $this->assertSame(['DE'], $countries->searchByName('DEU')->keys()->all());
        $this->assertSame(['DE'], $countries->searchByName('276')->keys()->all());
        $this->assertSame(['AT'], $countries->searchByName('Osterreich')->keys()->all());
        $this->assertSame(['AT'], $countries->searchByName("O\u{0308}sterreich")->keys()->all());
        $this->assertNull($countries->searchByName('Allemagne')->find('DE'));
        $this->assertSame(['DE'], $countries->searchByName('Allemagne', ['fr-FR'])->keys()->all());
        $this->assertTrue($countries->except('DE')->searchByName('Allemagne', ['fr'])->isEmpty());
        $this->assertSame(['xn--p1ai'], world()->tlds()->searchByName('.XN--P1AI')->keys()->all());
    }

    public function test_search_preserves_the_input_order_keys_and_worker_locale(): void
    {
        $countries = world('de')->countries()->only(['AT', 'DE', 'CH'])->reverse();
        app()->setLocale('fr');
        $this->assertSame(['AT'], $countries->filter(fn ($country) => $country->code !== 'DE')->searchByName('Osterreich')->keys()->all());
        $this->assertSame($countries->keys()->all(), $countries->searchByName('e')->keys()->all());
        $this->assertSame('Deutschland', $countries->searchByName('Germany')->findOrFail('DE')->name);
        $this->assertTrue($countries->searchByName('Allemagne')->isEmpty());
    }

    public function test_search_folds_unicode_case_without_transliterating_scripts(): void
    {
        $world = world('de');
        $items = new WorldCollection([
            'street' => new Continent($world, 'S', 'Straße'),
            'japan' => new Continent($world, 'J', '日本'),
        ]);
        $this->assertSame(['street'], $items->searchByName('STRASSE')->keys()->all());
        $this->assertSame(['japan'], $items->searchByName('日本')->keys()->all());
        $this->assertTrue($items->searchByName('nihon')->isEmpty());
    }

    public function test_sorting_uses_the_captured_locale_and_keeps_keys_and_ties(): void
    {
        $world = world('de');
        $items = new WorldCollection([
            'o' => new Continent($world, 'O', 'Öland'),
            'z' => new Continent($world, 'Z', 'Zulu'),
            'a1' => new Continent($world, 'A1', 'Aland'),
            'a2' => new Continent($world, 'A2', 'Aland'),
        ]);
        app()->setLocale('sv');
        $this->assertSame(['a1', 'a2', 'o', 'z'], $items->sortByName()->keys()->all());
        $this->assertSame(['a1', 'a2', 'z', 'o'], $items->sortByName('sv-SE')->keys()->all());
        $this->assertSame(['a1', 'a2', 'o', 'z'], $items->sortByName('zz_INVALID')->keys()->all());
        $this->assertSame(['o', 'z', 'a1', 'a2'], $items->keys()->all());
        $this->assertSame(['a1', 'a2', 'o'], $items->except('z')->sortByName()->keys()->all());
    }

    public function test_empty_collections_and_laravels_existing_search_are_preserved(): void
    {
        $items = new WorldCollection;
        $this->assertSame([], $items->sortByName()->all());
        $this->assertNotSame($items, $items->sortByName());
        $this->assertSame([], $items->searchByName('anything')->all());
        $countries = world()->countries();
        $this->assertSame('DE', $countries->search($countries->findOrFail('DE'), true));
        $this->assertSame('DE', $countries->search(fn ($country) => $country->code === 'DE'));
        $this->assertFalse($countries->search(fn ($country) => $country->code === 'ZZ'));
    }

    public static function scalarHelpers(): array
    {
        return [['searchByName', 'German'], ['searchByName', ''], ['sortByName', null]];
    }

    #[DataProvider('scalarHelpers')]
    public function test_name_helpers_reject_scalar_collections(string $method, ?string $argument): void
    {
        $this->expectException(InvalidArgumentException::class);
        world()->countries()->pluck('name')->{$method}($argument);
    }

    public function test_search_rejects_invalid_utf8(): void
    {
        $this->expectException(InvalidArgumentException::class);
        world()->countries()->searchByName("\xff");
    }
}
