## Vendra Console

The `misaf/vendra-console` package is the **console (platform admin) panel**. It is the operator's cross-tenant view of resellers, plans, and stores, and it is presentation only — every state change it performs belongs to a domain package's action.

### Translatable Persistence

- Making a persisted model field translatable is an explicit domain choice unless this package already requires it.
- Every field listed in a model's `$translatable` array must definitely use a JSON database column. Keep its model traits/casts, factories, validation, Filament locale UI, API serialization, and tests translation-aware.
- A field not listed in `$translatable` must use the appropriate scalar database type and must not use Spatie Translatable, translatable slug traits, locale switchers, translated callbacks, or translation-shaped array data.

### Vendra Transitive API Policy

- Treat a Vendra dependency intentionally exposed through the public API of a directly required Vendra platform package as part of the supported public contract of that package.
- Do not add a redundant direct Composer requirement solely because source code imports a type from that exposed dependency.
- Apply this only to Vendra platform packages listed under `require`; never extend it to `require-dev`, `suggest`, incidental implementation dependencies, or third-party packages. Removing or replacing an exposed dependency is a breaking change; keep `self.version` alignment across the Vendra package graph.

- Keep console panel code inside `packages/vendra-console` using the `Misaf\VendraConsole` namespace.
- **This is the topmost layer.** The dependency arrow points one way — `vendra-console` → `vendra-reseller` → `vendra-store` → `vendra-container` — so nothing may depend on this package. A behaviour other panels also need belongs in the domain package, not here.
- **The console panel runs outside the tenant middleware stack** so an operator can work across every tenant. Never assume a current tenant, and never scope a console query with tenant-aware helpers.
- The panel has its own `console` auth guard, `console_users` password broker, and `Models\ConsoleUser`. It is served on `console.<app host>` by `Providers\ConsolePanelServiceProvider`; the domain is derived from `app.url`, so do not hard-code a host.
- Resources are thin. `StoreResource`, `ResellerResource`, and `PlanResource` render and delegate: store creation goes through `Misaf\VendraStore`'s `CreateStorePage` and `StorefrontConfigurationFields`, domain replacement through its `ReplaceDomainAction`, and reseller offboarding through `Misaf\VendraReseller`'s `OffboardResellerAction`. Do not write a state change directly in a page or table action.
- `StorefrontDeploymentResource` is the console's read-only operational history. Runtime reads go through `Misaf\VendraStore\Contracts\StorefrontProvisioner`; retry, reconcile, and restart go through `vendra-store` actions. Never call Docker, Podman, or the controller API from Filament.
- `ContainerRuntimeHealth` may use only the runtime-neutral `ContainerRuntime` contract and its configuration object. Keep runtime calls out of resource table rows; live inspection and logs are explicit operator reads on a deployment or store.
- `ConsoleOverview` stats link to existing resource filters. Keep the count and the linked filter semantically identical so an operator lands on the records represented by the stat.
- Store lifecycle, storefront lifecycle, safe offboarding/restoration, administrator membership, reseller-owner credentials/replacement, and subscription lifecycle controls must invoke the corresponding `vendra-store`, `vendra-user`, `vendra-reseller`, or `vendra-subscription` actions. Do not reintroduce active/domain toggle columns or raw delete/force-delete actions for domain transitions.
- The console store wizard resolves the optional billing reseller and exposes `create_storefront`, defaulting on. When explicitly off, `CreateStorePage` still creates the store and domain but skips `RequestStorefrontDeploymentAction`; the reseller panel keeps storefront creation mandatory.
- Reassigning a store's owner goes through `Misaf\VendraStore`'s `AssignStoreOwnerAction`, driven by `Filament\Resources\Stores\Actions\AssignResellerAction`. Never expose `reseller_id` as a plain form field: reassignment consumes a slot in the receiving reseller's plan, and a select would write the column straight past `StoreQuota`.
- `Filament\Resources\ActivityLogs\ActivityLogResource` is the console's own read-only audit view, deliberately not the clustered, permission-gated resource `misaf/vendra-activity-log` ships to the admin panel. A `ConsoleUser` holds no tenant roles, so this resource states its own rule (`canViewAny()` true, every write false). Rows arrive unscoped because `TenantScope` only applies while a tenant is current and this panel has none — that is the feature, not a leak.
- `config/console.php` under `platform` holds only deployment-fixed console values, read per request (`Config::string('console.platform.name')`). Anything an operator edits at runtime is a settings row instead: `Filament\Pages\ManagePlatformSettings` edits `Misaf\VendraStore\Settings\StoreCreationSettings`, and `StoreResource::canCreate()` reads it through `Misaf\VendraStore\Support\StoreCreationPolicy`. A rule the reseller or store layer must honour belongs to the layer that enforces it, never to console config.
- Filament resources with a cluster live in `src/Filament/Clusters/Resources/`; resources without one live in `src/Filament/Resources/`. The console's resources are uncluttered and live in `src/Filament/Resources/`.
- Follow Laravel comment style: document with PHPDoc (array shapes, generics, `@see`) and reserve inline comments for genuinely complex logic.
