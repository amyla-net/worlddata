# Amyla Worlddata

Localized world data and relationships for Laravel, powered by Symfony Intl,
Unicode CLDR and IANA. All lookups work offline against installed data.

**1.0 is in development.** This branch introduces a new package and API. The
installation command below applies once `amyla/worlddata` 1.0 is published;
the existing `crcl/worlddata` releases keep their original API.

## Installation

Requires Laravel 12 or 13, PHP 8.3 or later, and the PHP Intl extension.

```sh
composer require amyla/worlddata:^1.0
```

Laravel discovers the service provider automatically. No configuration publishing,
database, migrations, initialization, or data downloads are required.
This package requires a Laravel application; standalone PHP usage is not supported.

## Usage

```php
world()->continents();
world()->countries();
world()->currencies();
world()->languages();
world()->timezones();
world()->tlds();

world()->currencies()->find('EUR')->name;
world('de')->countries()->find('DE')->name; // Deutschland

$country = world()->countries()->findOrFail('DE');
$country->continent();
$country->currencies();
$country->languages();
$country->timezones();
$country->tlds();

world()->continents()->findOrFail('EU')->countries();
world()->currencies()->findOrFail('EUR')->countries();
world()->languages()->findOrFail('de')->countries();
world()->timezones()->findOrFail('Europe/Berlin')->countries();
world()->tlds()->findOrFail('.de')->countries();
```

Results are `WorldCollection` instances extending Laravel's support collection.
They are keyed by their canonical code and support the usual collection methods:

```php
world()->countries()->pluck('name', 'code');
world()->countries()->sortBy('name');
world()->countries()->groupBy('continentCode');
world()->countries()->filter(fn ($country) => $country->currencies()->has('EUR'));
```

`find()` returns an item or `null`. `findOrFail()` throws
`Amyla\Worlddata\Exceptions\ItemNotFoundException`. Country, continent, currency
and language lookup is case-insensitive and trims surrounding whitespace.
Timezone identifiers retain their IANA spelling and case.

## Country codes

Countries expose nullable `alpha3` and `numericCode` properties alongside their
canonical Alpha-2 `code`. All three forms work with `find()` and `findOrFail()`:

```php
$country = world()->countries()->findOrFail('deu');
$country->code;        // DE
$country->alpha3;      // DEU
$country->numericCode; // "276"

world()->countries()->find('276'); // same DE item within the same world context
world()->countries()->find('004')->code; // AF
world()->countries()->find('4'); // null: numeric codes require all three digits
```

Collection keys stay canonical: `has('DE')` checks the key, while `find('DEU')`
resolves an alias. Filtered collections only resolve items they still contain.
Unknown supplementary codes are `null`. Symfony's optional user-assigned-country
setting applies to aliases as well.

## Names and language

`world()` uses Laravel's current application locale. Pass an explicit locale to
override it for one chain without changing the application or global Intl locale:

```php
app()->setLocale('de');
$german = world();
$english = world('en');

$german->countries()->findOrFail('DE')->name;  // Deutschland
$english->countries()->findOrFail('DE')->name; // Germany
```

Each world context captures its language and the current UTC date. Its related
items retain that context, including across application-locale changes in workers.
Regional locales such as `de-CH` and `de_CH` are supported. Unsupported display
locales try their parents, then `app.fallback_locale`, then English. A small set of
official-language names absent from Symfony uses the IANA English description.

The `Amyla\Worlddata\World` service can also be injected through Laravel's container.

Languages additionally expose an immutable nullable `nativeName`:

```php
world('en')->languages()->findOrFail('de')->nativeName; // Deutsch
world('en')->languages()->findOrFail('ja')->nativeName; // 日本語
world('en')->languages()->findOrFail('caa')->nativeName; // null
```

An own-language label is provided when Symfony supports the language's own display
locale. Languages without that coverage retain their regular localized or IANA
`name` and have a `null` native name. Language items represent base languages;
native names use the script chosen by the installed Symfony data.

## Search and sorting

All six item collections support multilingual name searches and locale-aware sorting:

```php
world('de')->countries()->searchByName('Germany')->sortByName();
world('de')->countries()->searchByName('Osterreich'); // includes AT
world('de')->countries()->searchByName('Allemagne', ['fr']); // includes DE
world('de')->countries()->sortByName('sv-SE');
```

`searchByName(string $query, array $locales = [])` searches names and codes using
the captured display name plus English. The locale list adds further translations.
Country aliases and TLD ASCII/Unicode forms are included. Matching uses substrings,
Unicode case folding and accent removal; scripts are not transliterated.
An empty or whitespace-only query returns a copy of the current collection.
Invalid UTF-8 queries throw `InvalidArgumentException`.

`sortByName(?string $locale = null)` uses Intl's `Collator`, defaulting to the
first item's captured display locale. An explicit locale uses the same parent and
English fallback resolution, with the captured locale as fallback. Equal names
retain their input order. Sort order follows the installed runtime ICU version.

Both methods preserve keys, leave the source collection unchanged, and work after
`filter()` or `only()`. They require item collections and throw
`InvalidArgumentException` after transformations to scalar values such as
`pluck('name')`. Laravel's existing `search()` keeps its usual key-or-false result.

## Currency details

```php
$currency = world('de')->currencies()->findOrFail('CHF');
$currency->symbol;                // CHF
$currency->fractionDigits;        // 2
$currency->roundingIncrement;     // 0
$currency->cashFractionDigits;    // 2
$currency->cashRoundingIncrement; // 5
```

These properties are immutable. The symbol follows the captured display locale;
it may be shared by several currencies or be the currency code. The digit counts
and rounding increments follow Symfony's currency metadata, including defaults
for historical currencies. Increments are integers in units of the corresponding
least-significant fractional digit: CHF's cash increment `5` with two cash
fraction digits represents `0.05`. An increment of `0` specifies no extra rounding
step beyond the digit precision. These are metadata fields; the package does not
round or convert monetary amounts.

## Validation

Use the package's Laravel validation rule objects in Form Requests or validators:

```php
use Amyla\Worlddata\Rules\CountryCode;
use Amyla\Worlddata\Rules\CurrencyCode;
use Amyla\Worlddata\Rules\LanguageCode;
use Amyla\Worlddata\Rules\TimezoneCode;

$request->validate([
    'country' => ['required', new CountryCode(only: ['DE', 'AT', 'CH'])],
    'currency' => ['required', new CurrencyCode(only: ['EUR', 'CHF'], currentOnly: true)],
    'language' => ['nullable', new LanguageCode(only: ['de', 'fr', 'it'])],
    'timezone' => ['required', new TimezoneCode],
]);
```

All rules accept an optional `only` list of code strings. An empty list imposes no
restriction. Rules validate string values with the same normalization as lookups;
countries accept Alpha-2, Alpha-3 and three-digit numeric strings by default.
Allow lists compare canonical items, so allowing `DE` also allows `DEU` and `"276"`.
Timezone spelling and case remain strict. Language codes refer to language items,
not full locales such as `de-DE`.

Validation preserves the submitted value. Use `findOrFail($value)->code` when
storing a canonical code. Combine these non-implicit rules with Laravel's
`required`, `sometimes` or `nullable` as appropriate. Failures use Laravel's
translated `validation.in` message, which applications can override normally.

By default, currency validation includes historical currencies. `currentOnly: true`
requires at least one country using the currency as current legal tender. A reused
rule resolves a fresh world context for each validation, including the current UTC
date and Laravel locale.

## Timezone details

```php
$zone = world()->timezones()->findOrFail('Europe/Berlin');
$at = new DateTimeImmutable('2026-07-15T12:00:00Z');

$zone->offsetAt($at);      // 7200 seconds
$zone->offsetLabelAt($at); // UTC+02:00
$zone->isDstAt($at);       // true
```

Each method requires an explicit `DateTimeInterface` and uses its instant, whatever
its input timezone. Mutable DateTime inputs are left unchanged. Offsets and DST
follow PHP's installed timezone database. Labels preserve fractional-hour offsets
and include seconds for historical offsets when needed. Computed timezone values
are not included in item serialization.

## Items and data semantics

Items have immutable public `code` and `name` properties. `Country` additionally
exposes a nullable `continentCode`. All items implement `Arrayable` and `JsonSerializable`;
serialization includes scalar data, never automatically expanded relationships.
Country serialization includes `alpha3` and `numericCode`; currency serialization
includes the symbol and all four numeric metadata fields; language serialization
includes `nativeName`, including when it is `null`.
Collections are mutable copies, so filtering or removing entries does not alter
other lookups.

| Dataset | Canonical code | Meaning |
| --- | --- | --- |
| Countries | `DE` | Symfony's countries and territories; optional user-assigned codes follow Symfony's setting |
| Continents | `AF`, `AN`, `AS`, `EU`, `NA`, `OC`, `SA` | Seven geographical groupings; `EU` means Europe |
| Currencies | `EUR`, `DEM` | All currencies known to Symfony, including historical currencies |
| Languages | `de`, `gsw` | Symfony languages plus additional official languages required by the country relationships |
| Timezones | `Europe/Berlin` | Symfony's IANA timezone identifiers and localized names |
| TLDs | `de`, `xn--p1ai` | Currently delegated IANA top-level domains |

Currency-country relationships show **current legal tender**, using the context's
UTC date. A historical currency such as DEM remains searchable but has no current
countries. Some currency units are never legal tender and also have no countries.

Language-country relationships include CLDR's `official`, `de_facto_official` and
`official_regional` statuses. They do not enumerate every spoken language.
Script variants are combined under their base language code.

Continent assignments follow CLDR geographical groupings. North America includes
Central America and the Caribbean. Antarctica is explicitly `AN`. These are
documented display groupings, not assertions about sovereignty or political unions.

Missing relationships return empty collections; `continent()` returns `null` when
no continent is known. Names and assignments reflect the installed dependency and
snapshot versions, rather than live services.

The installed Symfony version determines which countries are listed. A newly
added country remains available even when the bundled snapshot does not yet
contain its continent or official languages: `continentCode` and `continent()`
return `null`, and missing language or TLD relationships are empty collections.
Currency and timezone relationships remain available when Symfony provides them.
Countries removed by Symfony are omitted, including from reverse relationships.
The next snapshot update fills in new assignments; runtime lookups never fetch data.

## TLDs

```php
$tld = world()->tlds()->findOrFail('.рф');
$tld->code; // xn--p1ai
$tld->name; // .рф
$tld->ascii; // .xn--p1ai
$tld->type; // country-code
$tld->countries()->keys(); // Collection containing RU

world()->tlds()->find('.XN--P1AI'); // same assignment
```

TLD lookup accepts ASCII labels, Punycode and Unicode labels, with or without a
leading dot. Digits and hyphens are preserved. Only assigned country-code TLDs
link to countries, including `.uk → GB` and IANA's IDN assignments.
Generic or supranational labels such as `.com`, `.berlin` and `.eu` do not acquire
a country from the registry operator's address. Country-code labels outside the
country dataset, such as `.ac` and `.su`, likewise have no country relationship.
This is a TLD catalog, not a domain validator, Public Suffix List, or availability check.

## Installed data information

`world()->dataInfo()` returns a copy of the installed provenance and runtime metadata:

- `snapshot`: existing `cldrVersion`, `ianaTldsDate`, `ianaLanguagesDate` and
  `sources` entries containing upstream URLs and original SHA-256 hashes.
- `runtime`: `symfonyIntlVersion`, `symfonyIcuDataVersion`, `icuVersion`,
  `icuDataVersion` and `timezoneDatabaseVersion`.

Symfony's bundled ICU data version is distinct from the runtime ICU data version
used for collation. The PHP timezone database version is returned as reported by
PHP, including `0.system` on installations using system timezone files. This API
does not fetch sources or infer versions that the runtime does not report.

## Development and maintenance

```sh
composer install
composer test
composer format:check
composer analyse
composer audit --locked

composer data:update                 # refresh IANA with the pinned CLDR release
composer data:update -- --latest     # propose the latest stable CLDR release too
composer data:update -- --cldr-version=49.0.0
composer data:build                  # reproduce from committed sources, offline
```

Online data maintenance additionally uses PHP's Phar and zlib support. It downloads
official Unicode npm archives directly; Node.js is not needed. The compiler uses
the sanitized, committed source snapshot in `build/sources.json`. It records
upstream URLs, versions/dates and SHA-256 hashes of the original downloaded bytes.
The runtime snapshot is `resources/data.json`. Registry contact details are omitted.
Compilation validates references and completes before replacing the files. Invalid
or failed imports preserve the existing snapshots. A metadata-only refresh does
not create a data-update commit.

CI checks Laravel 12/13 on PHP 8.3/8.4/8.5, installation discovery, data relationships,
offline reproducibility, formatting, static analysis and dependency advisories.
A weekly workflow creates validated data-update PRs; Dependabot checks Composer
dependencies and Actions weekly. Actions are pinned to complete commit SHAs.

The repository must allow Actions to create pull requests for the update workflow
to open its PR. For PRs created or updated with `GITHUB_TOKEN`, approve the CI runs
using **Approve workflows to run**, or manually run CI on `automation/worlddata`.
Wait for the full compatibility matrix before merging. See
[GitHub's workflow triggering rules](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/trigger-a-workflow#triggering-a-workflow-from-a-workflow).
The update workflow itself runs the tests and quality checks before proposing the data.
No workflow merges PRs, publishes packages or creates releases automatically.

## Migration, sources and security

See [MIGRATION.md](MIGRATION.md) for changes from `crcl/worlddata` 0.4 and
[DATA_SOURCES.md](DATA_SOURCES.md) for provenance, update rules and data notices.
Report vulnerabilities as described in [SECURITY.md](SECURITY.md).

The PHP code is MIT licensed, preserving the original CRCL copyright notice.
Imported data retains its upstream notices; see [LICENSES](LICENSES).
