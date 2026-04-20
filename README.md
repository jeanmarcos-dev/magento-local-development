# Zento Dev Modules

Magento 2 modules designed **exclusively for local development** inside the Zento environment. Each module alters native Magento behavior to speed up the development loop (authentication, live reload, etc.).

> ⚠️ **GLOBAL SECURITY NOTICE**
>
> These modules **must not run in production**. Each one implements a *production guard* that disables it automatically whenever `bin/magento deploy:mode:show` returns `production`. The guard can be **forced on** from the admin panel, but doing so exposes the site to severe risks: full admin login bypass, customer impersonation, third-party script injection.
>
> Before any deploy: run `bin/magento module:status | grep Development_`. If any appear as `Enabled`, consider disabling them via `bin/magento module:disable` in your pipeline.

---

## Module index

| Module | Purpose | Risk level | Default in `production` |
|---|---|---|---|
| [**AdminBypass**](./AdminBypass/README.md) | Accepts any admin password and auto-logs in a hardcoded user (`local/local123`) | 🔴 Critical | 🛑 Disabled |
| [**CustomerBypass**](./CustomerBypass/README.md) | Accepts any password for any existing customer | 🔴 Critical | 🛑 Disabled |
| [**LiveReload**](./LiveReload/README.md) | Injects `/livereload.js` on storefront and admin pages for automatic browser reload | 🟡 Low | 🛑 Not injected |

---

## Shared contract: Production Guard

All three modules share the same guard architecture:

1. **Helper** (`Development\<Module>\Helper\Config::isEnabled()`) evaluates:
   - `State::getMode() !== production` → active (returns `true`).
   - `State::getMode() === production` → active only if `development/<module_key>/allow_in_production` is set to `Yes`.
   - If `State::getMode()` throws (early CLI bootstrap), the exception is swallowed and treated as "not production".
2. **Plugins / Blocks** call the helper before running any bypass logic and delegate to `$proceed(...)` (or return empty, for LiveReload) when the guard is off.
3. **Admin panel**: all toggles live under the `⚠ Development Modules` tab in `Stores → Configuration` (defined in `AdminBypass/etc/adminhtml/system.xml`).

### Config paths

| Module | Magento config path |
|---|---|
| AdminBypass | `development/admin_bypass/allow_in_production` |
| CustomerBypass | `development/customer_bypass/allow_in_production` |
| LiveReload | `development/live_reload/allow_in_production` |

All default to `0` (No).

---

## Enable everything (dev)

```bash
bin/magento module:enable \
    Development_AdminBypass \
    Development_CustomerBypass \
    Development_LiveReload

bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Make sure you are in developer mode:

```bash
bin/magento deploy:mode:show
# developer
```

## Disable everything (before a staging/prod deploy)

```bash
bin/magento module:disable \
    Development_AdminBypass \
    Development_CustomerBypass \
    Development_LiveReload

bin/magento setup:upgrade
bin/magento cache:flush
```

---

## Manual verification matrix

Test each module across the four combinations:

| Magento mode | `allow_in_production` flag | Expected result |
|---|---|---|
| `developer` | `No` | bypass/livereload active |
| `developer` | `Yes` | bypass/livereload active |
| `production` | `No` | **bypass inactive; native Magento behavior** |
| `production` | `Yes` | bypass/livereload active (explicit override) |

Useful commands:

```bash
# Toggle mode
bin/magento deploy:mode:set developer
bin/magento deploy:mode:set production

# Toggle flag from CLI (alternative to the admin panel)
bin/magento config:set development/admin_bypass/allow_in_production 1
bin/magento cache:clean config
```

---

## Compatibility

- Magento 2.4.x
- PHP 8.1+ (all modules use `declare(strict_types=1)`, constructor property promotion, and `readonly` properties).
- PSR-12 + Magento 2 best practices (`around` plugins with explicit sortOrder, constructor DI, no service-locator lookups).

---

## Inter-module dependencies

- **`AdminBypass`** defines the `⚠ Development Modules` tab in `system.xml`. If AdminBypass is disabled while the others remain active, their config sections may not render. Workaround: copy the `<tab id="development">` block into any other module, or keep them enabled together.
- None of the modules depend on each other functionally (plugins and blocks are independent).
