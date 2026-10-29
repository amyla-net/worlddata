# Data sources and notices

The package code's MIT license does not relabel upstream data. Source versions,
dates and SHA-256 checksums are recorded in both the source and runtime snapshots.

| Source | Used for | Versioning / notice |
| --- | --- | --- |
| [Symfony Intl](https://symfony.com/doc/current/components/intl.html) | Countries, localized names, currencies, current currency relationships and timezones | Composer resolves the supported 7.4 line; Symfony and its data notices accompany that dependency |
| [Unicode CLDR](https://github.com/unicode-org/cldr-json) | Geographical containment, continent names and official-language status | Pinned CLDR release, initially 49.0.0; Unicode License v3, included in `LICENSES/Unicode-3.0.txt` |
| [IANA TLD registry](https://data.iana.org/TLD/v1/tlds.json) | Active TLD labels, Unicode forms, types and IDN country assignments | Export `specversion` 1, generated date and original SHA-256; [IANA terms](https://www.iana.org/help/terms-of-service) |
| [IANA Language Subtag Registry](https://www.iana.org/assignments/language-subtag-registry/language-subtag-registry) | English labels for official languages not named in Symfony | File-Date and original SHA-256; [IANA/IETF protocol-registry licensing statement](https://www.iana.org/help/licensing-terms), CC0 |

The CC0 protocol-registry statement is not presented as a license for all IANA
website material or for the separate TLD export. Only the factual fields needed
for this package are retained; registry contacts, addresses and DNS records are
not redistributed in the snapshots.

## Derivation rules

The Unicode npm packages `cldr-core` and `cldr-localenames-full` are downloaded from
their versioned distribution archives. No JavaScript execution or npm installation
is involved. The committed sanitized source snapshot is sufficient for offline
recompilation; original upstream bytes can be checked against the recorded hashes
when available. IANA's changing exports are not historical archives, so the
committed snapshot, rather than a future download, is the reproduction input.

Continent regions: Africa `002`, Asia `142`, Europe `150`, North America `003`,
South America `005`, Oceania `009`, with Antarctica `AQ` explicitly assigned to
`AN` instead of CLDR's Outlying Oceania grouping. The selected geographical groups
are expanded recursively. Political and deprecated grouping branches are ignored.

Official language statuses are `official`, `de_facto_official`, and
`official_regional`. Script variants share a base-language item; duplicates are
removed. Missing language descriptions cause the import to fail instead of
silently dropping relationships. Supplementary labels use the first IANA
Description field when Symfony has no localized label.

TLD records are filtered to `active`. ASCII country-code labels match the country
code, with the explicit `.uk → GB` exception. IDN ccTLDs use `iso3166_code`.
Assignments without a corresponding country item remain unlinked. Generic and
supranational TLDs never infer a country from operator details or political unions.

Currency-country relationships are derived at lookup time from Symfony's public
API, using legal-tender and active filters and the context's UTC date. Timezone
relationships use Symfony's country-to-zone mapping and its inverse; no separate
historical timezone snapshot or UTC-offset calculator is maintained here.

## Updating and reviewing

`composer data:update` proposes new IANA data with the recorded CLDR version.
`composer data:update -- --latest` also checks the current stable CLDR release.
`composer data:build` recompiles the committed sources without network access.
Downloads and compilation must complete and validate before either snapshot is
replaced. Generated runtime files are replaced atomically. Identical data with
only refreshed fetch metadata is left unchanged.

Review source-version changes, removed/added assignments, unknown statuses and
test results before merging an update. Runtime installation never downloads,
refreshes or writes to package data. The CI matrix verifies compatibility with the
latest supported Composer dependencies, and data changes are never auto-merged.
