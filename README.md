# Magento 2 Development Modules

A small suite of Magento 2 modules designed **exclusively for local development** environments. Each module alters native Magento behavior to speed up the development loop (admin/customer auth bypass, live browser reload).

Each module is published as an independent Composer package and can be installed on its own. They share a small **core** package that provides the production-mode guard and the shared admin tab. A **suite** meta-package is also available for installing the whole set in one command.

| Package | Composer name | Type | Purpose |
|---|---|---|---|
| [**Suite**](./Suite/README.md) | `jeanmarcos/magento-local-development-suite` | metapackage | Installs the full set in one `composer require`. |
| [**Core**](./Core/README.md) | `jeanmarcos/module-core-local-development` | magento2-module | Shared production-guard service (`ProductionGuard`) + the `⚠ Development Modules` admin tab. No user-facing behavior on its own. |
| [**AdminBypass**](./AdminBypass/README.md) | `jeanmarcos/module-admin-bypass` | magento2-module | Accepts any admin password and auto-logs in a hardcoded user (`local`/`local123`). |
| [**CustomerBypass**](./CustomerBypass/README.md) | `jeanmarcos/module-customer-bypass` | magento2-module | Accepts any password for any existing customer on storefront login. |
| [**LiveReload**](./LiveReload/README.md) | `jeanmarcos/module-livereload` | magento2-module | Injects `/livereload.js` on storefront and admin pages for automatic browser reload. |

> ⚠️ **GLOBAL SECURITY NOTICE**
>
> These modules **must not run in production**. Each one implements a *production guard* that disables it automatically whenever `bin/magento deploy:mode:show` returns `production`. The guard can be **forced on** from the admin panel, but doing so exposes the site to severe risks: full admin login bypass, customer impersonation, third-party script injection.
>
> Before any deploy: run `bin/magento module:status | grep Development_`. If any appear as `Enabled`, consider disabling them via `bin/magento module:disable` in your pipeline.

---

## Install

The fastest path — install everything at once via the suite meta-package:

```bash
composer require --dev jeanmarcos/magento-local-development-suite
```

Or pick only the modules you need (Composer pulls in the shared core automatically):

```bash
composer require --dev jeanmarcos/module-admin-bypass
composer require --dev jeanmarcos/module-customer-bypass
composer require --dev jeanmarcos/module-livereload
```

Then enable in Magento (the order is irrelevant; `<sequence>` handles it):

```bash
bin/magento module:enable \
    Development_Core \
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
    Development_LiveReload \
    Development_Core

bin/magento setup:upgrade
bin/magento cache:flush
```

---

## Shared contract: Production Guard

All consumer modules share a single guard service: `Development\Core\Model\ProductionGuard`.

1. **Service** (`Development\Core\Model\ProductionGuard::isEnabled()`) evaluates:
   - `State::getMode() !== production` → active (returns `true`).
   - `State::getMode() === production` → active only if the consumer's `allow_in_production` flag is set to `Yes`.
   - If `State::getMode()` throws (early CLI bootstrap), the exception is swallowed and treated as "not production".
2. **Per-module wiring**: each consumer declares a `virtualType` of `ProductionGuard` in its own `etc/di.xml`, binding it to its specific XML config path. The plugin/block then receives this virtual type by argument name.
3. **Admin panel**: the `⚠ Development Modules` tab is declared once in `Development_Core/etc/adminhtml/system.xml`. Consumer modules contribute their own `<section>` referencing `<tab>development</tab>`.

### Config paths

| Module | Magento config path |
|---|---|
| AdminBypass | `development/admin_bypass/allow_in_production` |
| CustomerBypass | `development/customer_bypass/allow_in_production` |
| LiveReload | `development/live_reload/allow_in_production` |

All default to `0` (No).

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

## Building your own dev-only module on top of Core

The core is published independently so you can build new dev-only modules with the same guarantees:

1. `composer require --dev jeanmarcos/module-core-local-development`
2. Add `Development_Core` to your module's `<sequence>` in `etc/module.xml`.
3. In your `etc/di.xml`, declare a `virtualType` of `Development\Core\Model\ProductionGuard` bound to your XML path:
   ```xml
   <virtualType name="Vendor\YourModule\Model\ProductionGuard"
                type="Development\Core\Model\ProductionGuard">
       <arguments>
           <argument name="configPath" xsi:type="string">development/your_module/allow_in_production</argument>
       </arguments>
   </virtualType>
   ```
4. Inject it into your plugins/blocks and call `$this->guard->isEnabled()` before doing anything dev-only.
5. Reference `<tab>development</tab>` from your `system.xml` section.

See `Core/README.md` for full details.

---

## Compatibility

- Magento 2.4.x
- PHP 8.1+ (all modules use `declare(strict_types=1)`, constructor property promotion, and `readonly` properties).
- PSR-12 + Magento 2 best practices (`around` plugins with explicit sortOrder, constructor DI, no service-locator lookups).

---

## License

MIT — see each package's `composer.json` for details.
