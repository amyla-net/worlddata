<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\WorldCollection;

final class Timezone extends Item
{
    public function matches(string $code): bool
    {
        return $code === $this->code;
    }

    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->relatedCountries('timezones', $this->code);
    }
}
