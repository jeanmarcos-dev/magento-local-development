# Development_AdminBypass

[![Packagist](https://img.shields.io/packagist/v/jeanmarcos/module-admin-bypass.svg)](https://packagist.org/packages/jeanmarcos/module-admin-bypass)

> ⚠️ **FOR LOCAL DEVELOPMENT ONLY — NEVER ENABLE IN PRODUCTION**

Bypasses Magento 2 admin authentication and auto-logs in a hardcoded development user (`local` / `local123`) whenever the admin login page is visited.

---

## What it does

- **`BypassAdminAuthentication`** (plugin `around` on `Magento\User\Model\User::verifyIdentity`) — accepts **any password** for any existing admin user.
- **`AdminAutologin`** (plugin `around` on `Magento\Backend\Controller\Adminhtml\Auth\Login::execute`) — when `/admin` is visited and nobody is logged in, creates the admin user `local` (password `local123`, email `john.smith@gmail.com`, role `Administrators`) if missing and authenticates as that user. Redirects to `*/dashboard`.

---

## Safety model

This module is **guarded by Magento's application mode**:

| Mode | `Allow in Production` flag | Behavior |
|---|---|---|
| `developer` / `default` | any | **active** — bypass and autologin work |
| `production` | `No` (default) | **inactive** — Magento behaves normally, no user is created |
| `production` | `Yes` | **active** — explicit override (use at your own risk) |

The guard is implemented in [`Development_Core`](https://packagist.org/packages/jeanmarcos/module-core-local-development) (`Development\Core\Model\ProductionGuard::isEnabled()`) and wired into every plugin via a `virtualType` in `etc/di.xml` that binds it to the config path `development/admin_bypass/allow_in_production`. In production + flag off, the plugins short-circuit with `$proceed(...)` and do **not** create the `local` user nor touch authentication.

---

## Configuration

Panel path: **Stores → Configuration → ⚠ Development Modules → Admin Bypass → General → Allow in Production**

- Default: `No`.
- Changing this flag requires `bin/magento cache:clean config` to take effect.

---

## Install

```bash
composer require --dev jeanmarcos/module-admin-bypass
bin/magento module:enable Development_AdminBypass
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Kill switch (strongly recommended before any deploy)

```bash
bin/magento module:disable Development_AdminBypass
bin/magento setup:upgrade
bin/magento cache:flush
```

The `disable` path is the last line of defense — it removes the module entirely regardless of the `allow_in_production` flag.

For permanent removal:

```bash
composer remove jeanmarcos/module-admin-bypass
```

---

## Security risks (read before using)

- Anyone who can reach `/admin` gains full `Administrators` access when the bypass is active. No password needed.
- The hardcoded `local/local123` admin user persists in the database once created, even after disabling the module.
- Credentials are in plain text in `Plugin/AdminAutologin.php` and are searchable in git history.

After disabling the module in a shared environment, consider deleting the `local` admin user manually:

```sql
DELETE FROM admin_user WHERE username = 'local';
```

---

## File structure

```
AdminBypass/
├── Plugin/
│   ├── AdminAutologin.php             # autologin around plugin
│   └── BypassAdminAuthentication.php  # password bypass around plugin
├── etc/
│   ├── acl.xml                        # ACL for the config section
│   ├── adminhtml/
│   │   └── system.xml                 # admin panel toggle
│   ├── config.xml                     # default values
│   ├── di.xml                         # plugin wiring + ProductionGuard virtualType
│   └── module.xml                     # module declaration (depends on Development_Core)
├── composer.json
├── registration.php
└── README.md
```

The production-guard helper lives in the shared core package
[`jeanmarcos/module-core-local-development`](https://packagist.org/packages/jeanmarcos/module-core-local-development).

---

## Troubleshooting

- **Toggle doesn't take effect:** `bin/magento cache:clean config`.
- **Bypass still works after `module:disable`:** check `app/etc/config.php` for the module entry; run `setup:upgrade`.
- **Autologin loops:** another plugin on `Login::execute` may be conflicting; inspect `generated/code/Magento/Backend/Controller/Adminhtml/Auth/Login/Interceptor.php`.

---

## Compatibility

- Magento 2.4.x
- PHP 8.1+ (uses constructor property promotion and `readonly` properties)
- Depends on `jeanmarcos/module-core-local-development` (installed automatically by Composer).

---

## License

MIT
