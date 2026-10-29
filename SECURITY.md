# Security policy

Report vulnerabilities privately to Noël Sabosch at **ns@amyla.net**. Include
the affected version, reproduction steps and the impact. Please do not publish
an issue containing sensitive details before the report has been assessed.

The `main` branch preparing Amyla Worlddata 1.0 is maintained. Once released,
the latest 1.x release supports Laravel 12 and 13 on PHP 8.3, 8.4 and 8.5.
The legacy `crcl/worlddata` 0.x releases are no longer maintained.

The package performs no external requests during normal application use. Only
maintainer data-update tooling fetches upstream data. Accuracy reports about
countries, names or assignments may be filed as ordinary issues with a link to
the relevant upstream source.
