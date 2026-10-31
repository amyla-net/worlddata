# Migration from crcl/worlddata 0.4

Amyla Worlddata 1.0 is a new Laravel-only package with intentional breaking changes.
The existing tags are preserved. No Composer `replace` or old-namespace alias is
provided: applications must explicitly migrate.

After 1.0 is published, replace the Composer requirement `crcl/worlddata` with
`amyla/worlddata:^1.0`. Laravel 12/13, PHP 8.3+ and ext-intl are required.

| Before | After |
| --- | --- |
| `\crcl\worlddata\World::countries()` | `world()->countries()` |
| `World::countries('DE')` | `world()->countries()->find('DE')` |
| `World::topLevelDomains()` | `world()->tlds()` |
| `->exist('EUR')` | `->find('EUR') !== null`, or `->has('EUR')` for a canonical key |
| Mutable item/array access | Immutable item properties; `toArray()` for an array copy |
| Country `continent` string | `continentCode`, or `continent()` for the object |
| Country `timezones` array | `timezones()` returning timezone objects |
| Country codes | Alpha-2 keys with Alpha-3 and three-digit numeric aliases for lookups |
| Currency `country`/`country()` | `countries()` returning every current country |
| Language codes such as `DE` | Canonical keys such as `de`; `find('DE')` still works |
| Uppercase TLD keys | Lowercase ASCII keys such as `de` and `xn--p1ai` |

Names now follow Laravel's application locale. Use `world('en')` when previous
behavior depended on English labels. Item properties are read-only and JSON does
not expand relationships. `find()` returns null when absent; `findOrFail()` throws
the package's `ItemNotFoundException`.

Countries expose nullable `alpha3` and `numericCode`, currencies expose localized
`symbol` and integer digit/rounding metadata, and languages expose nullable
`nativeName`. These fields are included in arrays and JSON. Numeric country codes
are strings preserving leading zeros. Validation accepts country aliases but
does not change the submitted value; explicitly resolve `->code` before storing
canonical Alpha-2 codes.

Use `searchByName()` for multilingual item filtering and `sortByName()` for
locale-aware ordering. The inherited Laravel `search()` retains its key-or-false
semantics. The new name helpers require item collections, including after filters,
and reject collections transformed to scalar values.

The country dataset now follows Symfony's current countries and territories.
Obsolete country codes are removed. Currency lists include historical currencies,
but their country relationships describe current legal tender only. Language
relationships describe official, de facto official and regional official status.
Country TLD relationships contain current assigned ccTLDs, not arbitrary domains.
When Symfony adds a country before its snapshot relationships are available,
`continentCode` and `continent()` return null. Use `$country->continent()?->name`
when displaying the continent. Missing collection relationships remain empty.

Coordinates, capitals, country native-name fields, telephone calling codes and the old
country-center/timezone-longitude arrays are not part of 1.0. There is no direct
substitute for those fields. Applications using them must choose an appropriate
additional source before migrating.

Publication order for maintainers: validate and publish `amyla/worlddata` 1.0,
then mark `crcl/worlddata` abandoned on Packagist with this package as the suggested
migration target. Do not move old tags or mark the old package abandoned before the
replacement is installable.
