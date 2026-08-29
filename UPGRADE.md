# Upgrading

## From 1.x to 2.0

### Requirements

Laravel 12. PHP stays at 8.2 or newer.

The only change is the framework this line accepts: Laravel 11 and 12 need no
different line of code from this package, so 1.x and 2.x are the same code with
a different constraint. `1.x` stays available for Laravel 11, with security and
isolation fixes.

```bash
composer require dazza-dev/hyn-multi-tenant:^2.0
```

### The advisory ignore list is gone

`config.policy.advisories.ignore-id` existed because every Laravel 11 release
carries advisories that will not be fixed: the line left security support in
March 2026. Laravel 12 installs clean, so the block went with it. If you copied
it into your own `composer.json` to install this package, you no longer need it
for that reason.


## From hyn/multi-tenant 5.9 to dazza-dev/hyn-multi-tenant 1.0

### Requirements

- PHP 8.2 or newer, up from 8.0.
- Laravel 11. Laravel 9 and 10 are supported by the `0.x` line of this same
  repository, which is 5.9 with the fixes below and nothing else.

### Installation

```bash
composer remove hyn/multi-tenant
composer require dazza-dev/hyn-multi-tenant
```

The namespace stays `Hyn\Tenancy\`, so no `use` statement changes. The package
declares a conflict with `hyn/multi-tenant`, so the two can never be installed
side by side.

### The hostname is identified per request   ⚠️ behaviour change

**What changed.** Identification used to happen while the service providers
booted, which is once per application. It happens in the
`EagerIdentification` middleware now, which is once per request.

**How this can affect you.** On PHP-FPM, nothing: every request builds an
application, so identification already happened per request. On a long lived
process — Octane, Swoole, RoadRunner — the second request used to be served the
tenant identified for the first one, and its database with it. It is now served
its own.

**What to do.** Nothing, unless you worked around this with your own
re-identification, in which case you can drop it.

### Events\Database\ConnectionSet takes a required first parameter

```php
// before
public function __construct(Website $website = null, string $connection, bool $purged = true)
// after
public function __construct(?Website $website, string $connection, bool $purged = true)
```

The parameter was optional before a required one, which PHP has deprecated
since 8.0 and which never worked as a default anyway. Only affects you if you
construct the event by hand; passing `null` explicitly is still fine.

### tenancy:migrate will not create a database that is missing

Laravel 11 lets `migrate` create a missing database. In multi-tenancy that
masks a provisioning failure by leaving a tenant connectable with an empty
schema, so it is refused here. There is no setting for it: a tenant without a
database has not been provisioned, and that is worth an error.

### The two escape hatches of the 0.x line are gone

`tenancy.queue.reset-tenant-between-jobs` and
`tenancy.db.allow-migrate-to-create-database` do not exist here. Remove them
from `config/tenancy.php` if you carried the file over.

The first restored the behaviour where a queue worker carried one job's tenant
into the next, so that jobs written against it kept working while they were
adapted. That is a leak between tenants, and this is the version where the
adapting was supposed to have happened.

The second guarded a path the tenant loop never reaches: the connection to the
missing database fails before the framework offers to create it. A setting that
cannot be observed doing anything is not worth honouring for the life of a
major version.

### tenancy:migrate refuses --graceful

`--graceful` returns a successful exit code even when the migration failed.
Across a loop of tenants that hides which of them are left unmigrated.

### The destructive commands honour a prohibited environment

`tenancy:migrate:fresh`, `:refresh`, `:reset` and `:rollback` now respect
`isProhibited()`, which is how Laravel lets you block them in production. The
package used to replace the framework's `handle()` and lose the check with it.

### Connection::DEFAULT_MIGRATION_NAME is gone

It was marked deprecated and referenced nowhere, here or in the tests.

### Doctrine DBAL is no longer required

It was declared as a runtime dependency and used nowhere. If your own code
relied on it arriving through this package, require it yourself.

## 0.x

The line that continues 5.9 for Laravel 9 and 10. Everything below applies to
1.0 as well.

### The queue no longer carries a tenant between jobs   ⚠️ behaviour change

**What changed.** A queue worker used to keep whatever tenant the previous job
activated. A job with no `website_id` inherited it, a finished job left it
active, a job that threw left it active, and a job naming a deleted website
inherited whichever tenant ran before it.

Now the active tenant is scoped to the job: it is released when a job declares
none, and restored to whatever was active beforehand when a job ends or fails.

**How this can affect you.** Jobs that quietly relied on inheriting an ambient
tenant will start failing with a connection error instead of reading and
writing another tenant's database. That is the point of the change, but it does
mean previously silent behaviour becomes a visible failure.

**What to do.** Make sure every job that touches tenant data is dispatched from
within that tenant's context, so its payload carries `website_id`. Jobs that
belong to the system should not touch models using `UsesTenantConnection`.

**Escape hatch.** `TENANCY_QUEUE_RESET_TENANT=false` restores the old
behaviour while you adapt. It is not recommended: the old behaviour leaks
tenant context between jobs, on a connection that is valid and authenticated,
so nothing errors and nothing is logged.

**Not affected.** Synchronous dispatch. `dispatch_sync` runs inside the request
that asked for it, and `DispatcherMiddleware` deliberately switches the ambient
tenant there. That behaviour is unchanged.

### Releasing the tenant now releases its disk too   ⚠️ behaviour change

**What changed.** `ActivatesDisk` rooted the `tenant` disk in the active
tenant's directory when one was identified or switched to, and did nothing when
one was released. The disk stayed pointed at the tenant that had just been let
go, so anything writing to `Storage::disk('tenant')` afterwards landed in that
customer's files. It is the filesystem counterpart of the connection keeping the
previous tenant's credentials.

Releasing the tenant now clears the disk as well, which makes the next access
fail loudly rather than write to the wrong place.

**How this can affect you.** Code that wrote to `Storage::disk('tenant')`
outside any tenant's context used to succeed silently against whichever tenant
came last. It now throws. That is the point of the change, but it does turn
previously silent behaviour into a visible failure — most likely on a queue
worker, which releases the tenant between jobs.

**What to do.** Make sure anything touching `disk('tenant')` runs inside the
context of the tenant it belongs to.

### tenancy:run puts back the tenant it found   ⚠️ behaviour change

**What changed.** The command switched tenant on each website in turn and left
the last one active when it finished, and on the way out of an exception. Run
from a terminal that hardly matters, since the process ends. Called from a
request or a job through `Artisan::call('tenancy:run')`, the caller silently
carried on as a customer it never asked for.

It now restores whatever tenant was active beforehand, or releases it when
there was none.

**Not affected.** The commands built on `processHandle()` —
`tenancy:migrate` and friends — keep their current behaviour: with a single
tenant in the chunk the connection is deliberately left active, and the suite
asserts it.

### tenancy:migrate:fresh no longer wipes the whole database in prefix mode   ⚠️ behaviour change

**What changed.** The command called `db:wipe` on the tenant connection, which
empties every table in the database behind it. In the `database` and `schema`
division modes that database belongs to the tenant alone, so that was right. In
`prefix` mode all tenants and the system tables share one database, so running
it dropped **every tenant's tables along with `websites` and `hostnames`**. The
damage was visible from inside the command itself, whose next page of websites
was read from the table it had just dropped.

It now drops only the tables carrying that tenant's prefix.

**How this can affect you.** If you use `prefix` mode, this command was
destroying your installation and you very likely never ran it twice. Nothing you
relied on changes; it simply stops taking everything else with it.

**Views and user defined types are not dropped** in `prefix` mode. `--drop-views`
and `--drop-types` are honoured in the modes that give the tenant a database of
its own, where dropping everything is safe. In a shared database there is no
prefix on those objects to tell whose they are.

**In the `bypass` division mode the command now refuses to run.** There, the
tenant connection *is* the system connection and nothing marks a tenant's tables
apart, so any wipe would take the system with it. It fails with an explanation
instead.

**Not affected.** The `database` and `schema` division modes, which keep calling
`db:wipe` exactly as before.

### PostgreSQL 15 and newer can provision tenants again   ⚠️ behaviour change

**What changed.** PostgreSQL 15 revoked the `CREATE` privilege that the `public`
schema used to hand to every role. The driver only ever granted privileges on
the *database*, which on 15 and newer is no longer enough to create a table, so
a tenant database was created successfully and then failed every migration with
`permission denied for schema public`. Provisioning now also grants on the
schema, from a connection to the new database, because a schema grant has no
effect from anywhere else.

Two things follow from that grant. It is issued to `PUBLIC` rather than to the
tenant role, since a role level grant is recorded as a dependency and would make
`DROP USER` fail when the tenant is deleted. And to keep that from widening
anything, `CONNECT` is now revoked from `PUBLIC` on each new tenant database.

**How this can affect you.** Any role that used to reach a tenant database
purely through the default `PUBLIC` connect privilege will be refused on
databases created from now on. Reading rows was already refused, but listing
tables was not. Roles that were granted access explicitly, the tenant itself,
and the owner of the database are unaffected.

Backup, monitoring or reporting roles are the ones to check. Grant them what
they need explicitly:

```sql
GRANT CONNECT ON DATABASE "<tenant uuid>" TO "<your role>";
```

**Databases created before this change do not gain the revoke retroactively.**
They keep working exactly as before. To apply the same boundary to them, run
this once per existing tenant database:

```sql
REVOKE CONNECT ON DATABASE "<tenant uuid>" FROM PUBLIC;
```

**Not affected.** The `schema` division mode, which grants on its own schema and
never relied on `public`. MySQL and MariaDB.

### Connection::set(null) now clears the connection configuration

**What changed.** Releasing the tenant with `Connection::set(null)` used to
close the connection but leave the previous tenant's database, user and
password in `config('database.connections.tenant')`. The next model using
`UsesTenantConnection` reopened it straight into that tenant's database. It now
purges the configuration as well.

**What to do.** Nothing, unless you relied on the connection configuration
surviving a release, which was never safe.

### New: Environment::forgetTenant()

There was no supported way to say "no tenant is active".
`Environment::tenant(null)` reads as releasing one but does nothing: the null
branch simply returns whatever is currently active. `forgetTenant()` releases
it and emits `Events\Websites\Forgotten`.

### New: Events\Websites\Forgotten

The counterpart to `Websites\Switched`. Listeners that set up per-tenant state
when a tenant becomes active should tear it down here. It carries no website on
purpose: the point is that there is not one.

### New configuration key

```php
'queue' => [
    'reset-tenant-between-jobs' => env('TENANCY_QUEUE_RESET_TENANT', true),
],
```
