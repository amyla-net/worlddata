<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\WorldCollection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class Timezone extends Item
{
    private ?DateTimeZone $zone = null;

    public function matches(string $code): bool
    {
        return $code === $this->code;
    }

    public function offsetAt(DateTimeInterface $at): int
    {
        return $this->zone()->getOffset($at);
    }

    public function offsetLabelAt(DateTimeInterface $at): string
    {
        $offset = $this->offsetAt($at);
        $seconds = abs($offset);
        $label = sprintf('UTC%s%02d:%02d', $offset < 0 ? '-' : '+', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));

        return $seconds % 60 === 0 ? $label : $label.sprintf(':%02d', $seconds % 60);
    }

    public function isDstAt(DateTimeInterface $at): bool
    {
        return DateTimeImmutable::createFromInterface($at)->setTimezone($this->zone())->format('I') === '1';
    }

    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->relatedCountries('timezones', $this->code);
    }

    private function zone(): DateTimeZone
    {
        return $this->zone ??= new DateTimeZone($this->code);
    }
}
