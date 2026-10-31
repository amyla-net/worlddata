<?php

namespace Amyla\Worlddata\Tests;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class TimezoneDetailsTest extends TestCase
{
    public static function instants(): array
    {
        return [
            ['Europe/Berlin', '2026-01-15T12:00:00Z', 3600, 'UTC+01:00', false],
            ['Europe/Berlin', '2026-07-15T12:00:00Z', 7200, 'UTC+02:00', true],
            ['Europe/Berlin', '2026-03-29T00:59:59Z', 3600, 'UTC+01:00', false],
            ['Europe/Berlin', '2026-03-29T01:00:00Z', 7200, 'UTC+02:00', true],
            ['Europe/Berlin', '2026-10-25T00:59:59Z', 7200, 'UTC+02:00', true],
            ['Europe/Berlin', '2026-10-25T01:00:00Z', 3600, 'UTC+01:00', false],
            ['Asia/Kathmandu', '2026-01-15T12:00:00Z', 20700, 'UTC+05:45', false],
            ['America/St_Johns', '2026-01-15T12:00:00Z', -12600, 'UTC-03:30', false],
            ['Europe/Paris', '1900-01-01T12:00:00Z', 561, 'UTC+00:09:21', false],
        ];
    }

    #[DataProvider('instants')]
    public function test_offsets_and_dst_follow_the_explicit_instant(string $code, string $date, int $offset, string $label, bool $dst): void
    {
        $zone = world()->timezones()->findOrFail($code);
        $at = new DateTimeImmutable($date);
        $this->assertSame($offset, $zone->offsetAt($at));
        $this->assertSame($label, $zone->offsetLabelAt($at));
        $this->assertSame($dst, $zone->isDstAt($at));
        $this->assertSame(['code', 'name'], array_keys($zone->toArray()));
    }

    public function test_input_timezone_and_mutable_datetime_remain_unchanged(): void
    {
        $zone = world()->timezones()->findOrFail('Europe/Berlin');
        $at = new DateTime('2026-07-15T12:00:00-07:00');
        $original = clone $at;
        $this->assertSame(7200, $zone->offsetAt($at));
        $this->assertSame('UTC+02:00', $zone->offsetLabelAt($at));
        $this->assertTrue($zone->isDstAt($at));
        $this->assertEquals($original, $at);
    }
}
