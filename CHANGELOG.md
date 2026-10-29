### [1.0.0] Unreleased

* Rename the package to `amyla/worlddata`, with the `Amyla\Worlddata` namespace.
* Require Laravel 12 or 13, PHP 8.3+, and the Intl extension.
* Add automatic package discovery and the locale-aware `world()` helper.
* Replace legacy snapshots with Symfony Intl, Unicode CLDR and IANA sources.
* Add typed immutable items, Laravel collections, explicit lookups and all country relationships.
* Include historical currencies while limiting currency-country relationships to current legal tender.
* Add versioned, reproducible data imports, CI and weekly maintenance workflows.
* Remove the old static API, mutable array access, coordinates, capitals and calling codes.

### [0.4.0] 2022-08-25

* Laravel 9 support

### [0.3.0] 2022-03-08

* Rename functions: use functions like ::countries() as a factory method, see [README.md](README.md) for the current documentation.
* Use of Illuminate/Support/Collections instead of arrays

### [0.2.0] 2022-02-28

* ADD: new data: currency, language, emoji, capital, calling code, currency, native names
* FIX: return null if no continent set on a country
* FIX: add continents on countries BQ, CS, MF, SS, SX

### [0.1.0] 2022-02-19

* Initial commit
