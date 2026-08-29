# dazza-dev/hyn-multi-tenant

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](license.md)
[![PHP](https://img.shields.io/badge/php-8.2%2B-777bb4.svg)](https://php.net)

Run multiple websites from a single Laravel installation, each with one or more
hostnames, keeping their data, assets and behaviour separated.

---

## Which version do you need?

| Your Laravel version | What to use |
| --- | --- |
| **11** | `dazza-dev/hyn-multi-tenant` — this package |
| **9 and 10** | The `0.x` line of this repository, or [hyn/multi-tenant](https://github.com/tenancy/multi-tenant) |

The `0.x` line is 5.9 with the isolation and provisioning fixes and nothing
else. It is installed by tag, from this repository:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/dazza-dev/hyn-multi-tenant" }
  ],
  "require": { "hyn/multi-tenant": "v0.10.4" }
}
```

## About this repository

A maintained fork of [hyn/multi-tenant](https://github.com/tenancy/multi-tenant)
by **[Daniël Klabbers](https://luceos.com)**. The design and practically all of
the code are his and the original contributors'.

The original has received no commits since August 2023 and does not support
Laravel 11. Rather than migrate away, we picked up its maintenance. This is the
same code, the same `Hyn\Tenancy\` namespace and the same API.

If the original project becomes active again we will gladly contribute these
changes back.

## Features

- Event driven, extensible architecture.
- Optional integration with nginx and apache.
- Tenant specific configuration, routes, views, translations and assets.

Tenant separation modes:

- A separate database per tenant, with its own user and credentials (default).
- A table prefix within the system database.
- Separate PostgreSQL schemas.
- Or your own, by listening to an event.

## Requirements

- Laravel 11
- PHP 8.2 or newer
- MySQL, MariaDB or PostgreSQL

## Installation

```bash
composer require dazza-dev/hyn-multi-tenant
```

Coming from `hyn/multi-tenant`, read [UPGRADE.md](UPGRADE.md) first. The
namespace does not change.

The service providers are registered through package auto discovery. To opt out
and register them yourself, add `dazza-dev/hyn-multi-tenant` to
`extra.laravel.dont-discover` and register:

```php
Hyn\Tenancy\Providers\TenancyProvider::class,
Hyn\Tenancy\Providers\WebserverProvider::class,
```

### Configuration

```bash
php artisan vendor:publish --tag tenancy
php artisan migrate --database=system
```

Adjust `config/tenancy.php` and `config/webserver.php` to taste. Make sure the
system connection is configured in `database.php`; the `default` connection is
used unless you override the system connection name.

## Documentation

The [original documentation](https://tenancy.dev) applies to everything not
listed as changed in [UPGRADE.md](UPGRADE.md).

Contributing, and what the test suite needs: [CONTRIBUTING.md](CONTRIBUTING.md).

## Testing

The suite creates and drops real databases and database users, so it needs a
disposable environment. One matching CI is included:

```bash
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
```

Target another engine or separation mode:

```bash
docker compose run --rm \
    -e DB_CONNECTION=pgsql -e DB_HOST=pgsql \
    -e TENANCY_SYSTEM_CONNECTION_NAME=pgsql \
    php vendor/bin/phpunit

docker compose run --rm \
    -e DB_HOST=mariadb -e TENANCY_DATABASE_DIVISION_MODE=prefix \
    php vendor/bin/phpunit
```

Build against another PHP version with `PHP_VERSION=8.4 docker compose build php`.
The suite runs on 8.2, 8.3 and 8.4.

> Running the suite drops tenant and system databases. Never point it at
> anything you care about.

## Credits

- **[Daniël Klabbers](https://luceos.com)** — author of the original package.
- [Contributors to the original project](https://github.com/tenancy/multi-tenant/graphs/contributors).
- [Andres Daza](https://github.com/dazza-dev) — maintainer of this continuation.

To support the original author's work, the original project has an
[Open Collective](https://opencollective.com/tenancy).

## License

MIT. Original copyright by Daniël Klabbers — see [license.md](license.md).
