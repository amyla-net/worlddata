<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;

final class Country extends Item
{
    public function __construct(World $world, string $code, string $name, public readonly string $continentCode)
    {
        parent::__construct($world, $code, $name);
    }

    public function continent(): Continent
    {
        return $this->world->continents()->findOrFail($this->continentCode);
    }

    /** @return WorldCollection<string, Currency> */
    public function currencies(): WorldCollection
    {
        return $this->world->currencies()->only($this->world->currencyCodes($this->code));
    }

    /** @return WorldCollection<string, Language> */
    public function languages(): WorldCollection
    {
        return $this->world->languages()->only($this->world->languageCodes($this->code));
    }

    /** @return WorldCollection<string, Timezone> */
    public function timezones(): WorldCollection
    {
        return $this->world->timezones()->only($this->world->timezoneCodes($this->code));
    }

    /** @return WorldCollection<string, Tld> */
    public function tlds(): WorldCollection
    {
        return $this->world->tlds()->only($this->world->tldCodes($this->code));
    }

    public function toArray(): array
    {
        return parent::toArray() + ['continentCode' => $this->continentCode];
    }
}
