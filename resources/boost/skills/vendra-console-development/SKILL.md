---
name: vendra-console-development
description: "Create, modify, review, or test the Vendra Console module in packages/vendra-console, changing the console (platform admin) panel that manages resellers, plans, and stores across every tenant. Use for ConsolePanelServiceProvider, ConsoleSeeder, Dashboard (NeedsAttention, PlatformMetrics, PlatformGrowthChart, RecentActivity), StoreResource, StoreForm, StoreTable, DomainsRelationManager, ResellerResource, ResellerForm, ResellerTable, PlanResource, PlanForm, PlanTable, InvoiceResource, InvoiceTable, BillingSettings, SettingsBillingProfile, the console auth guard, the consoles authorization table, and the console.<host> panel domain."
---

# Vendra Console

## Workflow

- Inspect `composer.json`, sibling files, and existing tests before changing the package.
- Use Laravel Boost `application-info` and `search-docs` before code changes.
- Apply `laravel-best-practices` to Laravel PHP and `pest-testing` whenever tests change.
- Keep changes inside this package's boundary and preserve its public contracts.
- `PlanForm` edits plan features (`PlanFeature`) and per-store limits (`PlanLimit`, under `limits`); `CreatePlan`/`EditPlan` normalize them with `PlanForm::normalizeLimits()` so empty limits stay unlimited. Lowering a plan is allowed, but `EditPlan` warns after a save that leaves resellers on the plan over it (`Misaf\VendraReseller\Support\ResellersOverPlan`), and the reseller table's `over_plan` filter lists every reseller whose stores no longer fit its active plan.
- `ChangePlanTableAction` disables and labels plans a reseller's stores have outgrown (`PlanCoverage::covers()`); the current plan stays selectable to drop a scheduled change. `RenewSubscriptionTableAction` charges and describes the plan `PlanCoverage::renewalPlan()` returns and notes an outgrown scheduled downgrade. The reseller table and view show `PlanFeature::PrioritySupport` from the active plan (`has_priority_support`, a `withExists` on the resource query) and filter by it. The table reuses `vendra-user`'s `UsernameColumn`, `EmailColumn` and `EmailVerifiedAtColumn` and their query-builder constraints, named with the `user.` relationship prefix.
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
- The panel is presentation. Business operations live in the domain packages' actions — including store/storefront lifecycle and offboarding, administrator membership, reseller-user accounts, and subscriptions. A page or table action that mutates state directly is in the wrong place.

## Tenancy

- The console panel runs **outside** the tenant middleware stack; a console user works across all tenants.
- Never assume a current tenant, and never scope console queries with tenant-aware helpers. Where a listing must be per-tenant, join explicitly.

## Panel Wiring

- `Providers\ConsolePanelServiceProvider` owns the panel: `console` auth guard against the canonical `User` (the `console` provider and broker), `console.<app host>` domain derived from `app.url`, top navigation, and the `AddPanelToRequestJobContext` / `SetLocale` middleware.
- Authentication is against the canonical user model, not a separate console model; panel access is an active `consoles` row, modelled by `Models\Console` (one per user, with a `user()` relation and `active()`/`inactive()`/`forUser()` scopes, never an authenticatable model). Email verification is required. The first console user is created by `ConsoleSeeder` with a generated password printed once; afterwards `vendra-console:user-create` creates another, `vendra-console:user-password` issues a new password (`--force` skips the confirmation, for unattended runs), `vendra-console:user-grant` grants access to an existing tenantless user, and `vendra-console:user-revoke` revokes it (`RevokeConsoleUserAction`, which deactivates the console and never the last active one) — there is no credentials config.
- Two-factor authentication is required on the console: the panel registers Filament's `AppAuthentication` provider with recovery codes and `isRequired: true`, so a console user without an authenticator app is sent to set one up before any page, and manages it from the profile page. A console user who lost both the app and the recovery codes is reset with `vendra-console:user-two-factor-reset` (same identifier handling as `user-revoke`, `--force` to skip the confirmation), never from the panel, so one console account cannot strip another's second factor. A reseller's user is reset from the reseller row's `ResetUserTwoFactorTableAction`. Both go through `vendra-user`'s `ResetUserAppAuthenticationAction`. Console tests that make HTTP requests act as a user from `User::factory()->withAppAuthentication()`, or the request redirects to the setup page.
- Do not hard-code the panel host; it is derived from configuration.

### Console user commands

- One command per job — `vendra-console:user-create`, `vendra-console:user-password`, `vendra-console:user-grant`, `vendra-console:user-revoke` — with no mode flags. A command whose user is in the wrong state fails and names the sibling to run; none of them falls through into another's job.
- `Console\Commands\Concerns\IdentifiesConsoleUser` normalizes `--email`/`--username`; each supplied identifier is validated with `UserRules::exists()`, then `User::query()->tenantless()->identifiedBy($email, $username)->first()` fetches the user matching all of them, where null means they name different users. `Console\Commands\Concerns\ReadsGivenPassword` reads `--password` and asks for it without echo when the option is given no value, keeping it out of shell history. Each command still owns its validation order, its messages, and returns before any write. Command messages are hardcoded English, as in every other package; there are no command translation keys.
- Identifiers resolve separately: a username-only run does not fall back to the default email account, and both identifiers together must resolve to the same tenantless user: when one names nobody the run fails naming that identifier, and when they name different users it is rejected as mismatched. When an interactive `user-grant`, `user-revoke` or `user-password` run names neither identifier, it searches for the user with Laravel Prompts' `search()` (`IdentifiesConsoleUser::searchForMissingUser()`), failing instead when no user fits; without interaction, `user-grant` and `user-revoke` require an identifier and reject a blank one, and `user-password` falls back to `vendra-console.default_email` only when no identifier is given, and a blank `--email` is still rejected. `user-create` requires `--email`, asks for it with Laravel Prompts when an interactive run omits it (asking again until the answer passes `UserRules`), and never uses the default, which only the seeder assigns.
- `user-create` requires an explicit `--username`, passed to `CreateConsoleUserAction::execute($username, $email, $password)`; an interactive run that omits it is asked for one, and a run without interaction fails. Validate its format with `UserRules::username()`; an email or username already held by a tenantless user fails `UserRules::unique()` validation without suffixes or retries, and its message names both `user-grant` and `user-password`, and `ConsoleSeeder` explicitly uses `console`.
- `user-password` requires an active console row and confirms before replacing a password with a generated one (`--password` or `--force` skips it); run without interaction it fails and points at those options instead of prompting. `user-grant` reactivates a revoked console rather than adding a row, takes an optional `--password` that is set only alongside a new grant, and reports an already-granted user without changing anything; given `--password` for an already-granted user it fails and points at `user-password`.
- Supplied and generated passwords must pass `required` and `UserRules::password()`; explicitly empty or whitespace-only passwords fail. Generated ones come from `PasswordGenerator::generate()`, which reads the same policy. Existing-user operations preserve the username.

## Resources

- `StoreResource`, `ResellerResource`, and `PlanResource` render and delegate. Store creation extends `Misaf\VendraStore\Filament\Pages\CreateStorePage` and reuses `StorefrontConfigurationFields`; aliases are managed in `DomainsRelationManager`, a subclass of that package's, with its header `AddDomainAliasTableAction` and row `RemoveDomainAliasTableAction` (the primary, the store's first domain, is never offered); list pages carry `StoreStatusOverview` and `ResellerSubscriptionOverview` header stats linking to filtered lists, and ViewStore lists `StorePlanUsage` only when `StorePlanUsage::hasStats()`; reseller offboarding calls `Misaf\VendraReseller\Actions\OffboardResellerAction`; wallet credits call `Misaf\VendraReseller\Actions\CreditResellerWalletAction` from `CreditWalletTableAction`, and the infolist reads balances through `Reseller::formattedWalletBalances()` so the console never imports `misaf/vendra-transaction`.
- `StorefrontDeploymentResource` is read-only history and console inspection. Read live state and logs through `StorefrontProvisioner`, observing live state only in the lazy `StorefrontRuntimeObservation` widget so a page render never waits on the runtime; invoke `vendra-store` retry, reconcile, and restart actions for mutations. Do not import runtime-specific clients into Filament.
- `ContainerRuntimeHealth` only reads the report `vendra-store`'s `RecordStorefrontRuntimeHealthJob` records on the storefront worker (`StorefrontRuntimeHealth::latest()`). Never call the runtime from a panel request: the web container has no runtime socket.
- Keep `NeedsAttention` and `PlatformMetrics` stat counts aligned with their destination resource filters. Count stores by status with `StoreStatusCounts`.
- Never expose direct active/domain toggle columns or raw store delete/force-delete actions. Invoke `vendra-store` lifecycle/offboarding actions, `vendra-user` administrator actions, `vendra-reseller` user actions, and `vendra-subscription` lifecycle actions.
- The console store wizard picks the optional billing reseller and exposes a `create_storefront` toggle that defaults on. Pass `optional: true` to its shared storefront field groups so an explicit off can create only the store and domain; the reseller panel keeps storefront creation mandatory.

## Platform Settings

- The console has no config file. The brand name is `Settings\ConsoleSettings::$brand_name` (global repository), edited on `ManagePlatformSettings`. Anything a console user flips at runtime is a settings row.
- `Filament\Pages\ManagePlatformSettings` also edits `Settings\BillingSettings` (seller details, tax rate in basis points, tax label); `Support\SettingsBillingProfile` is bound as the subscription engine's `BillingProfile`, so tax and seller come from there and never from panel code. `InvoiceResource` is read-only.
- `Filament\Pages\ManagePlatformSettings` edits `Misaf\VendraStore\Settings\StoreCreationSettings`; `StoreResource::canCreate()` reads its `open` flag. A rule the reseller or store layer must honour belongs to the layer that enforces it.

## Testing

- Act as a canonical user with an active `consoles` row (`Console::factory()->for($user)->create()`) on the `console` guard and assert against the panel's own pages; a test that sets up a current tenant is testing the wrong panel.
- Cover delegation: assert the domain action ran, rather than re-asserting the domain package's own behaviour.

## Filament

- Resources with a cluster live in `src/Filament/Clusters/Resources/`; resources without one live in `src/Filament/Resources/`.
- Every console password form (reseller user accounts, store administrators, reseller creation) uses `Filament\Forms\Components\NewPasswordInput` and `PasswordConfirmationInput`: revealable per panel, required, confirmed, and validated with `Password::default()`. Add per-form extras such as `visibleOn()` or a `GeneratePasswordAction` hint at the call site.
