# Vendra Console

The console (platform admin) panel for Laravel. It is the console user's
cross-tenant view of the platform: resellers, plans, and every store with its
domains and storefront state.

The panel is presentation. Every state change it performs belongs to a domain
package's action, so this package stays thin on purpose.

## Requirements

- PHP 8.4+
- Laravel 13
- Filament 5
- `misaf/vendra-reseller`, `misaf/vendra-store`, `misaf/vendra-subscription`,
  `misaf/vendra-tenant`, `misaf/vendra-activity-log`, `misaf/vendra-localization`,
  `misaf/vendra-user` and `misaf/vendra-support`

## Installation

```bash
composer require misaf/vendra-console
php artisan migrate
```

The `consoles` table holds one row per canonical user
(`misaf/vendra-user`) allowed into the panel, and only active rows grant access; the host application's
`config/auth.php` points the `console` guard at the tenantless
`console` provider and the `console` password broker, whose
reset tokens live in `console_password_reset_tokens`. Console users hold no tenant or reseller relationship. No credentials are
configured for the first one: on a fresh install `ConsoleSeeder` creates it at the
`vendra-console.default_email` address (`VENDRA_CONSOLE_DEFAULT_EMAIL`, default
`console@vendra.test`) with the explicit username `console` and a generated password, and prints it once to the seed
output (the container's first-boot log). That address is a plain config value rather than
something derived from `app.url`, and it is validated by `UserRules::email()` like every
other address in the application, so a dotless domain such as `console@localhost` is rejected.

Four commands manage console users; each does one job and points at its sibling when the
user it names is in the wrong state.

- `php artisan vendra-console:user-create` creates a console user with `--username`,
  `--email` and `--password`. `--username` and `--email` are both required (the configured
  address belongs to the seeded user, so the command never falls back to it); the password
  is generated unless `--password` is given. The command never prompts. It
  never turns into a password reset or a grant — when the address or username already belongs to a tenantless user, the
  run fails and names the command to use instead.
- `php artisan vendra-console:user-password` issues a new password to an existing console
  user, found by `--username`, `--email` or both, and falling back to the configured
  address when neither is given. It asks before replacing a password with a generated one;
  `--password` or `--force` skips that prompt. It never creates a user and never grants
  access: an unknown user or one without console access fails with a pointer.
- `php artisan vendra-console:user-grant` grants console access to an existing tenantless
  user, reactivating a revoked console rather than adding a second row, and optionally sets
  a password with `--password`. It requires `--email`, `--username` or both, never prompts,
  and reports a user who already has access without changing anything. Given `--password`
  for a user who already has access it fails and points at `vendra-console:user-password`.
- `php artisan vendra-console:user-revoke` deactivates the user's console while keeping the
  user, and refuses to deactivate the last active console user. It names the user the same
  way and never prompts.

Every command that takes both `--email` and `--username` requires them to resolve to the
same tenantless user: an identifier that names nobody fails naming it, and two that name
different users are rejected as mismatched. A blank identifier is rejected. Usernames must contain 3–12 letters,
numbers, dashes, or underscores and be unique among tenantless users that have not been
soft-deleted; duplicates fail rather than receive an automatic suffix. Supplied and
generated passwords must pass the application's default password rules, so an explicitly
empty or whitespace-only password is rejected. Generated passwords come from
`UserRules::generatePassword()`, so tightening the policy in a provider never leaves a
command unable to issue one.

## The panel

`Providers\ConsolePanelServiceProvider` registers everything:

- served on `vendra-console.domain` (`VENDRA_CONSOLE_DOMAIN`), which the config file defaults to the `console.` subdomain of `APP_URL`'s host
- `console` auth guard against the canonical `User` (`misaf/vendra-user`)
  through the tenantless `console` provider and the
  `console` password broker, whose reset tokens live in
  `console_password_reset_tokens`; panel access is granted by an active
  `consoles` row, with password reset and required email verification
- top navigation, global search key bindings, database notifications and
  transactions

**It runs outside the tenant middleware stack** so a console user can work across
every tenant. There is no current tenant: never scope a console query with
tenant-aware helpers, and join explicitly where a listing must be per-tenant.

## Resources

| Resource | Delegates to |
| --- | --- |
| `StoreResource` | `Misaf\VendraStore`'s provisioning, lifecycle, storefront, domain, billing-reseller, and offboarding actions; administrator membership delegates to `misaf/vendra-user` |
| `StorefrontDeploymentResource` | Read-only deployment history and live observation through `StorefrontProvisioner`; recovery delegates to `vendra-store` actions |
| `ResellerResource` | `Misaf\VendraReseller`'s reseller/user account actions and `misaf/vendra-subscription`'s lifecycle actions |
| `PlanResource` | `misaf/vendra-subscription`'s plan model |
| `ActivityLogResource` | `misaf/vendra-activity-log`'s model, read-only and across every tenant |

`DomainsRelationManager` manages a store's domains.

The console dashboard (`Filament\Pages\Dashboard`) lists its widgets by
urgency. `NeedsAttention` shows only what a console user should act on — stores
still provisioning or failed, failed storefront deployments, past-due
subscriptions, payments awaiting review, subscriptions ending within a week, and
jobs that failed in the last day — each linked to the filtered list that
resolves it, and collapses to one all-clear stat when there is nothing.
`PlatformMetrics` shows store, reseller and subscription totals and this month's
paid revenue per currency against last month's. `PlatformGrowthChart` plots new
stores, resellers and subscriptions per day over 7, 30 or 90 days.
`RecentActivity` lists the latest audit entries.

`ContainerRuntimeHealth` never contacts the runtime: only the storefront worker
holds the runtime socket. The scheduler dispatches `vendra-store`'s
`RecordStorefrontRuntimeHealthJob` onto the `storefronts` queue every minute,
and the widget reads the report it records through `StorefrontRuntimeHealth`,
warning when no report exists or the last one is more than five minutes old.

Store rows expose suspend/reactivate, provisioning recovery, managed storefront
start/stop/restart/redeploy/retry/reconcile, deployment viewing, recent logs,
safe offboarding, and restoration. The deployment resource filters by status,
store, and request date and exposes confirmed recovery controls without copying
provisioning logic into Filament. The edit
page manages store administrators without permitting the final enabled
administrator to be removed, demoted, or disabled. Reseller edit pages manage
user credentials/account replacement and subscription change, renewal,
extension, cancellation, and reactivation. Each control invokes the owning
domain package; no meaningful transition is an Eloquent column toggle. Every
password form uses the shared `NewPasswordInput` and
`PasswordConfirmationInput` fields.

### Assigning a store to a reseller

The `Assign reseller` row action moves a store to another reseller, or back to
the platform when no reseller is chosen. It runs
`Misaf\VendraStore\Actions\AssignStoreResellerAction`, which takes the same row
lock and quota check as creating a store — a reassignment consumes a slot in the
receiving reseller's plan — and reports a full plan as a notification rather
than writing the column anyway. This is why `reseller_id` is not an editable
form field.

### Activity

`ActivityLogResource` is the platform's audit trail. It is deliberately the
console's own resource rather than the clustered, permission-gated one
`misaf/vendra-activity-log` registers on the admin panel: a console user
holds no tenant roles and is trusted by panel access alone, so the read is
granted here and every write stays closed. Rows arrive unscoped because the
tenant scope applies only while a tenant is current and this panel has none.

## Platform settings

The console keeps no config file. Everything a console user changes at runtime
is a settings row, edited on `ManagePlatformSettings`.

The panel's brand name is `Misaf\VendraConsole\Settings\ConsoleSettings::$platform_name`,
seeded as `Vendra Console` by a settings migration and read per request, so a
rename takes effect on the next page load.

The page also exposes one platform rule: whether the platform is creating
stores at all. That rule is
`Misaf\VendraStore\Settings\StoreCreationSettings`, and it lives in
`misaf/vendra-store` precisely because the reseller panel creates stores too and
sits below this package. Closing it therefore closes reseller creation as well;
a reseller's own plan remains the second half of their gate.

The console picks a store's billing reseller from the form and lets the console user
turn off `Create storefront`. Turning it off creates the store and domain but
does not record or provision a managed storefront, which supports storefront
source running outside Docker. The reseller panel continues to require a
managed storefront.

## Layering

This is the topmost layer:

```
vendra-console → vendra-reseller → vendra-store → laravel-docker-engine
```

Nothing depends on this package, so anything reusable belongs one layer down.

## Testing

Act as a canonical user with an active `consoles` row on the `console` guard.
A test that sets up a current tenant is testing the wrong panel. Assert that
the domain action ran rather than re-asserting the domain package's own
behaviour.

```bash
php artisan test --compact --testsuite=vendra-console
```

## License

MIT. See [LICENSE](LICENSE).
