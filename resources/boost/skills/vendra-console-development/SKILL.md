---
name: vendra-console-development
description: "Create, modify, review, or test the Vendra Console module in packages/vendra-console, changing the console (platform admin) panel that manages resellers, plans, and stores across every tenant. Use for ConsolePanelServiceProvider, ConsoleUser, ConsoleOverview, StoreResource, StoreForm, StoreTable, DomainsRelationManager, ResellerResource, ResellerForm, ResellerTable, PlanResource, PlanForm, PlanTable, the console auth guard, the console_users password broker, and the console.<host> panel domain."
---

# Vendra Console

## Workflow

- Inspect `composer.json`, sibling files, and existing tests before changing the package.
- Use Laravel Boost `application-info` and `search-docs` before code changes.
- Apply `laravel-best-practices` to Laravel PHP and `pest-testing` whenever tests change.
- Keep changes inside this package's boundary and preserve its public contracts.
- Add or update focused Pest coverage, then run `php artisan test --compact --testsuite=vendra-console` from the project root.

## Translatable Persistence

- Making a persisted model field translatable is an explicit domain choice unless this package already requires it.
- Every field listed in a model's `$translatable` array must definitely use a JSON database column. Keep its model traits/casts, factories, validation, Filament locale UI, API serialization, and tests translation-aware.
- A field not listed in `$translatable` must use the appropriate scalar database type and must not use Spatie Translatable, translatable slug traits, locale switchers, translated callbacks, or translation-shaped array data.

## Vendra Transitive API Policy

- Treat a Vendra dependency intentionally exposed through the public API of a directly required Vendra platform package as part of the supported public contract of that package.
- Do not add a redundant direct Composer requirement solely because source code imports a type from that exposed dependency.
- Apply this only to Vendra platform packages listed under `require`; never extend it to `require-dev`, `suggest`, incidental implementation dependencies, or third-party packages. Removing or replacing an exposed dependency is a breaking change; keep `self.version` alignment across the Vendra package graph.

## Module Boundary

- This is the topmost layer: `vendra-console` → `vendra-reseller` → `vendra-store` → `laravel-docker-engine`. Nothing depends on this package, so anything another panel also needs belongs one layer down.
- The panel is presentation. Business operations live in the domain packages' actions — including store/storefront lifecycle and offboarding, administrator membership, reseller-owner accounts, and subscriptions. A page or table action that mutates state directly is in the wrong place.

## Tenancy

- The console panel runs **outside** the tenant middleware stack; an operator works across all tenants.
- Never assume a current tenant, and never scope console queries with tenant-aware helpers. Where a listing must be per-tenant, join explicitly.

## Panel Wiring

- `Providers\ConsolePanelServiceProvider` owns the panel: `console` auth guard, `console_users` password broker, `console.<app host>` domain derived from `app.url`, top navigation, and the `AddPanelToRequestJobContext` / `SetLocale` middleware.
- Authentication is against `Models\ConsoleUser`, not the tenant user model. Email verification is required.
- Do not hard-code the panel host; it is derived from configuration.

## Resources

- `StoreResource`, `ResellerResource`, and `PlanResource` render and delegate. Store creation extends `Misaf\VendraStore\Filament\Pages\CreateStorePage` and reuses `StorefrontConfigurationFields`; domain replacement reuses that package's `ReplaceDomainAction`; reseller offboarding calls `Misaf\VendraReseller\Actions\OffboardResellerAction`.
- `StorefrontDeploymentResource` is read-only history and operator inspection. Read live state and logs through `StorefrontProvisioner`; invoke `vendra-store` retry, reconcile, and restart actions for mutations. Do not import runtime-specific clients into Filament.
- `ContainerRuntimeHealth` reports connection and configured-network health through `ContainerRuntime::ping()` and the runtime-neutral network contract. Never perform runtime calls while rendering large tables.
- Keep `ConsoleOverview` stat counts aligned with their destination resource filters.
- Never expose direct active/domain toggle columns or raw store delete/force-delete actions. Invoke `vendra-store` lifecycle/offboarding actions, `vendra-user` administrator actions, `vendra-reseller` owner actions, and `vendra-subscription` lifecycle actions.
- The console store wizard picks the optional billing reseller and exposes a `create_storefront` toggle that defaults on. Pass `optional: true` to its shared storefront field groups so an explicit off can create only the store and domain; the reseller panel keeps storefront creation mandatory.

## Platform Settings

- `config/console.php` under `platform` holds only deployment-fixed values (`Config::string('console.platform.name')`). Anything an operator flips at runtime is a settings row.
- `Filament\Pages\ManagePlatformSettings` edits `Misaf\VendraStore\Settings\StoreCreationSettings`; `StoreResource::canCreate()` reads it through `Misaf\VendraStore\Support\StoreCreationPolicy`. A rule the reseller or store layer must honour belongs to the layer that enforces it.

## Testing

- Act as a `ConsoleUser` on the `console` guard and assert against the panel's own pages; a test that sets up a current tenant is testing the wrong panel.
- Cover delegation: assert the domain action ran, rather than re-asserting the domain package's own behaviour.

## Filament

- Resources with a cluster live in `src/Filament/Clusters/Resources/`; resources without one live in `src/Filament/Resources/`.
