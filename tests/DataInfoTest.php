<?php

namespace Amyla\Worlddata\Tests;

use Amyla\Worlddata\Rules\CountryCode;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Intl\Intl;

final class DataInfoTest extends TestCase
{
    public function test_data_info_exposes_snapshot_provenance_and_distinct_runtime_versions(): void
    {
        $snapshot = json_decode(file_get_contents(__DIR__.'/../resources/data.json'), true, 512, JSON_THROW_ON_ERROR);
        $world = world();
        $info = $world->dataInfo();
        $this->assertSame($snapshot['meta'], $info['snapshot']);
        $this->assertSame(InstalledVersions::getPrettyVersion('symfony/intl'), $info['runtime']['symfonyIntlVersion']);
        $this->assertSame(Intl::getIcuDataVersion(), $info['runtime']['symfonyIcuDataVersion']);
        $this->assertSame(INTL_ICU_VERSION, $info['runtime']['icuVersion']);
        $this->assertSame(INTL_ICU_DATA_VERSION, $info['runtime']['icuDataVersion']);
        $this->assertSame(timezone_version_get(), $info['runtime']['timezoneDatabaseVersion']);
        $info['snapshot']['sources'] = [];
        $this->assertNotEmpty($world->dataInfo()['snapshot']['sources']);
    }

    public function test_all_new_features_work_with_network_streams_disabled(): void
    {
        Http::preventStrayRequests();
        $wrappers = [];
        foreach (['http', 'https', 'ftp', 'ftps'] as $wrapper) {
            if (in_array($wrapper, stream_get_wrappers(), true)) {
                stream_wrapper_unregister($wrapper);
                $wrappers[] = $wrapper;
            }
        }
        try {
            $world = world('de');
            $this->assertSame('DE', $world->countries()->findOrFail('DEU')->code);
            $this->assertSame('€', $world->currencies()->findOrFail('EUR')->symbol);
            $this->assertSame('日本語', $world->languages()->findOrFail('ja')->nativeName);
            $this->assertSame('EU', $world->continents()->searchByName('Europe')->sortByName()->findOrFail('EU')->code);
            $this->assertNotNull($world->timezones()->searchByName('Berlin')->find('Europe/Berlin'));
            $this->assertSame('UTC+01:00', $world->timezones()->findOrFail('Europe/Berlin')->offsetLabelAt(new \DateTimeImmutable('2026-01-01T00:00:00Z')));
            $this->assertNotEmpty($world->tlds()->searchByName('.рф'));
            $this->assertNotEmpty($world->dataInfo()['snapshot']['sources']);
            $this->assertTrue(Validator::make(['country' => '276'], ['country' => [new CountryCode]])->passes());
            Http::assertNothingSent();
        } finally {
            foreach ($wrappers as $wrapper) {
                stream_wrapper_restore($wrapper);
            }
        }
    }
}
