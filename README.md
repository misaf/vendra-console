# Vendra Console

The console (platform admin) panel for Laravel. It is the operator's
cross-tenant view of the platform: resellers, plans, and every store with its
domains and storefront state.

The panel is presentation. Every state change it performs belongs to a domain
package's action, so this package stays thin on purpose.

## Requirements

- PHP 8.4+
- Laravel 13
- Filament 5
- `misaf/vendra-reseller`, `misaf/vendra-store`, `misaf/vendra-subscription`,
  `misaf/vendra-tenant`, `misaf/vendra-activity-log`, `misaf/vendra-localization`
  and `misaf/vendra-support`

## Installation

```bash
composer require misaf/vendra-console
php artisan migrate
```

The `console_users` table and the `console` guard, `console_users` provider, and
`console_password_reset_tokens` broker are configured by the host application's
`config/auth.php` and migrations.

## The panel

`Providers\ConsolePanelServiceProvider` registers everything:

- served on `console.<app host>`, derived from `app.url` — no hard-coded host
- `console` auth guard against `Models\ConsoleUser`, with the `console_users`
  password broker, password reset, and required email verification
- top navigation, global search key bindings, database notifications and
  transactions

**It runs outside the tenant middleware stack** so an operator can work across
every tenant. There is no current tenant: never scope a console query with
tenant-aware helpers, and join explicitly where a listing must be per-tenant.

## Resources

| Resource | Delegates to |
| --- | --- |
| `StoreResource` | `Misaf\VendraStore`'s `CreateStorePage` (which runs `ProvisionStoreAction`), `StorefrontConfigurationFields` and `ReplaceDomainAction` |
| `ResellerResource` | `Misaf\VendraReseller`'s `CreateResellerAction`, `CreateResellerOwnerAction` and `OffboardResellerAction`, plus `misaf/vendra-subscription`'s `SubscribeAction` |
| `PlanResource` | `misaf/vendra-subscription`'s plan model |
| `ActivityLogResource` | `misaf/vendra-activity-log`'s model, read-only and across every tenant |

`DomainsRelationManager` manages a store's domains, and `ConsoleOverview`
gives the operator the platform-wide counts: resellers and how many are active,
stores split into active and suspended, how many are provisioning against how
many failed, live storefronts against failed deployments, and the subscription
position.

### Assigning a store to a reseller

The `Assign reseller` row action moves a store to another reseller, or back to
the platform when no reseller is chosen. It runs
`Misaf\VendraStore\Actions\AssignStoreOwnerAction`, which takes the same row
lock and quota check as creating a store — a reassignment consumes a slot in the
receiving reseller's plan — and reports a full plan as a notification rather
than writing the column anyway. This is why `reseller_id` is not an editable
form field.

### Activity

`ActivityLogResource` is the platform's audit trail. It is deliberately the
console's own resource rather than the clustered, permission-gated one
`misaf/vendra-activity-log` registers on the admin panel: a `ConsoleUser` holds
no tenant roles and is trusted by panel access alone, so the read is granted
here and every write stays closed. Rows arrive unscoped because the tenant scope
applies only while a tenant is current and this panel has none.

## Platform settings

`config/console.php` holds only what is fixed for a deployment:

```dotenv
CONSOLE_PLATFORM_NAME="Vendra Console"
```

`CONSOLE_PLATFORM_NAME` is the panel's brand name, read per request so a rename
takes effect on the next page load.

Anything an operator flips at runtime is a settings row instead.
`ManagePlatformSettings` is the page they edit it on, and today it exposes one
rule: whether the platform is creating stores at all. That rule is
`Misaf\VendraStore\Settings\StoreCreationSettings`, read through
`Misaf\VendraStore\Support\StoreCreationPolicy`, and it lives in
`misaf/vendra-store` precisely because the reseller panel creates stores too and
sits below this package. Closing it therefore closes reseller creation as well;
a reseller's own plan remains the second half of their gate.

The console picks a store's billing owner from the form and lets the operator
turn off `Create storefront`. Turning it off creates the store and domain but
does not record or provision a managed storefront, which supports storefront
source running outside Docker. The reseller panel continues to require a
managed storefront.

## Layering

This is the topmost layer:

```
vendra-console → vendra-reseller → vendra-store → vendra-container
```

Nothing depends on this package, so anything reusable belongs one layer down.

## Testing

Act as a `ConsoleUser` on the `console` guard. A test that sets up a current
tenant is testing the wrong panel. Assert that the domain action ran rather than
re-asserting the domain package's own behaviour.

```bash
php artisan test --compact --testsuite=vendra-console
```

## License

MIT. See [LICENSE](LICENSE).
