<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;

final class Country extends Item
{
    public readonly ?string $alpha3;

    public readonly ?string $numericCode;

    public function __construct(World $world, string $code, string $name, public readonly ?string $continentCode)
    {
        parent::__construct($world, $code, $name);
        try {
            $this->alpha3 = Countries::getAlpha3Code($code);
        } catch (MissingResourceException) {
            $this->alpha3 = null;
        }
        try {
            $this->numericCode = Countries::getNumericCode($code);
        } catch (MissingResourceException) {
            $this->numericCode = null;
        }
    }

    public function matches(string $code): bool
    {
        return parent::matches($code)
            || ($this->alpha3 !== null && strcasecmp($this->alpha3, $code) === 0)
            || ($this->numericCode !== null && $this->numericCode === $code);
    }

    public function continent(): ?Continent
    {
        return $this->continentCode === null ? null : $this->world->continents()->find($this->continentCode);
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
        return parent::toArray() + [
            'continentCode' => $this->continentCode,
            'alpha3' => $this->alpha3,
            'numericCode' => $this->numericCode,
        ];
    }
}
