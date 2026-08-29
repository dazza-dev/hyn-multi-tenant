# Contributing

## Running the test suite

The suite creates and drops real databases and database users, so it needs an
environment you can throw away. One matching CI is included:

```bash
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
```

> The suite drops tenant and system databases. Never point it at anything you
> care about.

The default run is MySQL 8 with a database per tenant. The rest of the matrix
is reached with environment variables:

```bash
# PostgreSQL
docker compose run --rm \
    -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e TENANCY_SYSTEM_CONNECTION_NAME=pgsql \
    php vendor/bin/phpunit

# MariaDB, tenants separated by table prefix
docker compose run --rm \
    -e DB_HOST=mariadb -e TENANCY_DATABASE_DIVISION_MODE=prefix \
    php vendor/bin/phpunit

# PostgreSQL schemas
docker compose run --rm \
    -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e TENANCY_SYSTEM_CONNECTION_NAME=pgsql \
    -e TENANCY_DATABASE_DIVISION_MODE=schema \
    php vendor/bin/phpunit
```

Another PHP version:

```bash
PHP_VERSION=8.4 docker compose build php
```

CI runs PHP 8.2, 8.3 and 8.4 against MySQL, MariaDB and PostgreSQL, in the
`database` and `prefix` division modes, plus `schema` on PostgreSQL. A change
that passes locally on MySQL alone has been tested in one of fourteen
combinations.

`MultiDatabaseTest` pins a tenant to a second database server, reached at
`<DB_HOST>2`. It skips unless `IN_CI` is set and that host answers; the compose
file starts one for each engine.

## Code style

```bash
docker compose run --rm php vendor/bin/pint --test
```

PSR-12, enforced in CI.

## Writing tests

Tests extend `Hyn\Tenancy\Tests\TestCase`, which runs on Orchestra Testbench.
Three hooks are available, in the order they run:

| Hook | When |
| --- | --- |
| `prepareSkeleton(string $path)` | Before the providers register, for files a provider will look for while booting |
| `beforeSetUp(Application $app)` | After the application boots, before tenancy is set up |
| `duringSetUp(Application $app)` | After the system database is migrated |

`InteractsWithTenancy` gives you `setUpHostnames()`, `setUpWebsites()` and
`activateTenant()`; `InteractsWithMigrations` gives you `migrateAndTest()` and
`seedAndTest()`. Tests are declared with PHPUnit attributes, `#[Test]`.

## Opening a pull request

- One concern per pull request. It is the difference between a review and an
  archaeology session.
- Say what breaks. Anything that changes behaviour needs an `UPGRADE.md` entry,
  even when the change is obviously right.
- Bring a test that fails without the change. For a bug fix, that test is the
  argument; the fix is a detail.
