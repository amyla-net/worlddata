<?php

namespace Amyla\Worlddata;

use Amyla\Worlddata\Items\Continent;
use Amyla\Worlddata\Items\Country;
use Amyla\Worlddata\Items\Currency;
use Amyla\Worlddata\Items\Language;
use Amyla\Worlddata\Items\Timezone;
use Amyla\Worlddata\Items\Tld;
use Amyla\Worlddata\Support\DisplayLocale;
use Amyla\Worlddata\Support\Snapshot;
use DateTimeImmutable;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Intl\Timezones;

final class World
{
    private readonly DisplayLocale $display;

    /** @var array<string, list<string>> */
    private array $currenciesByCountry = [];

    /** @var array<string, list<string>> */
    private array $timezonesByCountry = [];

    /** @var array<string, list<string>>|null */
    private ?array $tldsByCountry = null;

    /** @var array<string, array<string, list<string>>> */
    private array $reverseRelations = [];

    /** @var WorldCollection<string, Continent>|null */
    private ?WorldCollection $continentItems = null;

    /** @var WorldCollection<string, Country>|null */
    private ?WorldCollection $countryItems = null;

    /** @var WorldCollection<string, Currency>|null */
    private ?WorldCollection $currencyItems = null;

    /** @var WorldCollection<string, Language>|null */
    private ?WorldCollection $languageItems = null;

    /** @var WorldCollection<string, Timezone>|null */
    private ?WorldCollection $timezoneItems = null;

    /** @var WorldCollection<string, Tld>|null */
    private ?WorldCollection $tldItems = null;

    public function __construct(
        private readonly Snapshot $snapshot,
        string $locale,
        string $fallbackLocale,
        private readonly DateTimeImmutable $date,
    ) {
        $this->display = new DisplayLocale($locale, $fallbackLocale);
    }

    /** @return WorldCollection<string, Continent> */
    public function continents(): WorldCollection
    {
        if ($this->continentItems !== null) {
            return clone $this->continentItems;
        }
        $items = [];
        foreach ($this->snapshot->data['continents'] as $code => $names) {
            $items[$code] = new Continent($this, $code, $this->display->name($names));
        }

        $this->continentItems = new WorldCollection($items);

        return clone $this->continentItems;
    }

    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        if ($this->countryItems !== null) {
            return clone $this->countryItems;
        }
        $items = [];
        foreach (Countries::getNames($this->display->locale) as $code => $name) {
            $items[$code] = new Country($this, $code, $name, $this->snapshot->data['countryContinents'][$code] ?? null);
        }

        $this->countryItems = new WorldCollection($items);

        return clone $this->countryItems;
    }

    /** @return WorldCollection<string, Currency> */
    public function currencies(): WorldCollection
    {
        if ($this->currencyItems !== null) {
            return clone $this->currencyItems;
        }
        $items = [];
        foreach (Currencies::getNames($this->display->locale) as $code => $name) {
            $items[$code] = new Currency($this, $code, $name);
        }

        $this->currencyItems = new WorldCollection($items);

        return clone $this->currencyItems;
    }

    /** @return WorldCollection<string, Language> */
    public function languages(): WorldCollection
    {
        if ($this->languageItems !== null) {
            return clone $this->languageItems;
        }
        $items = [];
        foreach (Languages::getNames($this->display->locale) + $this->snapshot->data['extraLanguages'] as $code => $name) {
            $items[$code] = new Language($this, $code, $name);
        }

        $this->languageItems = new WorldCollection($items);

        return clone $this->languageItems;
    }

    /** @return WorldCollection<string, Timezone> */
    public function timezones(): WorldCollection
    {
        if ($this->timezoneItems !== null) {
            return clone $this->timezoneItems;
        }
        $items = [];
        foreach (Timezones::getNames($this->display->locale) as $code => $name) {
            $items[$code] = new Timezone($this, $code, $name);
        }

        $this->timezoneItems = new WorldCollection($items);

        return clone $this->timezoneItems;
    }

    /** @return WorldCollection<string, Tld> */
    public function tlds(): WorldCollection
    {
        if ($this->tldItems !== null) {
            return clone $this->tldItems;
        }
        $items = [];
        foreach ($this->snapshot->data['tlds'] as $code => $record) {
            $items[$code] = new Tld($this, $code, $record['name'], $record['type']);
        }

        $this->tldItems = new WorldCollection($items);

        return clone $this->tldItems;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function currencyCodes(string $country): array
    {
        if (! isset($this->currenciesByCountry[$country])) {
            try {
                $this->currenciesByCountry[$country] = Currencies::forCountry($country, date: $this->date);
            } catch (MissingResourceException) {
                $this->currenciesByCountry[$country] = [];
            }
        }

        return $this->currenciesByCountry[$country];
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function languageCodes(string $country): array
    {
        return $this->snapshot->data['countryLanguages'][$country] ?? [];
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function timezoneCodes(string $country): array
    {
        if (! isset($this->timezonesByCountry[$country])) {
            try {
                $codes = [];
                foreach (Timezones::forCountryCode($country) as $code) {
                    if (! is_string($code)) {
                        throw new \UnexpectedValueException('Invalid timezone identifier.');
                    }
                    $codes[] = $code;
                }
                $this->timezonesByCountry[$country] = $codes;
            } catch (MissingResourceException) {
                $this->timezonesByCountry[$country] = [];
            }
        }

        return $this->timezonesByCountry[$country];
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function tldCodes(string $country): array
    {
        if ($this->tldsByCountry === null) {
            $this->tldsByCountry = [];
            foreach ($this->snapshot->data['tlds'] as $code => $record) {
                if ($record['countryCode'] !== null) {
                    $this->tldsByCountry[$record['countryCode']][] = $code;
                }
            }
        }

        return $this->tldsByCountry[$country] ?? [];
    }

    /**
     * @internal
     *
     * @param  'currencies'|'languages'|'timezones'|'tlds'  $relation
     * @return WorldCollection<string, Country>
     */
    public function relatedCountries(string $relation, string $code): WorldCollection
    {
        if (! isset($this->reverseRelations[$relation])) {
            $this->reverseRelations[$relation] = [];
            foreach ($this->countries()->keys() as $country) {
                $codes = match ($relation) {
                    'currencies' => $this->currencyCodes($country),
                    'languages' => $this->languageCodes($country),
                    'timezones' => $this->timezoneCodes($country),
                    'tlds' => $this->tldCodes($country),
                };
                foreach ($codes as $related) {
                    $this->reverseRelations[$relation][$related][] = $country;
                }
            }
        }

        return $this->countries()->only($this->reverseRelations[$relation][$code] ?? []);
    }
}
