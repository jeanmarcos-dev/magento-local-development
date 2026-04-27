# Development_CustomerBypass

[![Packagist](https://img.shields.io/packagist/v/jeanmarcos/module-customer-bypass.svg)](https://packagist.org/packages/jeanmarcos/module-customer-bypass)

> ⚠️ **FOR LOCAL DEVELOPMENT ONLY — NEVER ENABLE IN PRODUCTION**

Bypasses Magento 2 customer authentication. Any password is accepted for any existing customer account on storefront login.

---

## What it does

- **`BypassCustomerAuthentication`** (plugin `around` on `Magento\Customer\Model\AccountManagement::authenticate`) — resolves the customer via `CustomerRepositoryInterface::get($username)` and returns it, ignoring the password.

No new users are created; only existing customers can be impersonated.

---

## Safety model

Guarded by Magento's application mode:

| Mode | `Allow in Production` flag | Behavior |
|---|---|---|
| `developer` / `default` | any | **active** — password ignored |
| `production` | `No` (default) | **inactive** — normal authentication |
| `production` | `Yes` | **active** — explicit override |

Implementation: [`Development_Core`](https://packagist.org/packages/jeanmarcos/module-core-local-development) (`Development\Core\Model\ProductionGuard::isEnabled()`), wired via a `virtualType` in `etc/di.xml` bound to the config path `development/customer_bypass/allow_in_production`. When disabled, the plugin delegates to `$proceed($username, $password)` and Magento authenticates normally.

---

## Configuration

Panel path: **Stores → Configuration → ⚠ Development Modules → Customer Bypass → General → Allow in Production**

- Default: `No`.
- Changing the flag requires `bin/magento cache:clean config`.

---

## Install

```bash
composer require --dev jeanmarcos/module-customer-bypass
bin/magento module:enable Development_CustomerBypass
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Kill switch

```bash
bin/magento module:disable Development_CustomerBypass
bin/magento setup:upgrade
bin/magento cache:flush
```

For permanent removal:

```bash
composer remove jeanmarcos/module-customer-bypass
```

---

## Security risks

- Anyone who knows a customer's email/username can log in as them without the password.
- Exposes full order history, addresses, saved payment methods (if stored), and wishlists.
- No audit trail: bypassed logins look identical to legitimate ones in the session.

---

## File structure

```
CustomerBypass/
├── Plugin/
│   └── BypassCustomerAuthentication.php  # password bypass around plugin
├── etc/
│   ├── acl.xml
│   ├── adminhtml/
│   │   └── system.xml
│   ├── config.xml
│   ├── di.xml                            # plugin wiring + ProductionGuard virtualType
│   └── module.xml                        # depends on Development_Core
├── composer.json
├── registration.php
└── README.md
```

The production-guard helper lives in the shared core package
[`jeanmarcos/module-core-local-development`](https://packagist.org/packages/jeanmarcos/module-core-local-development).

---

## Troubleshooting

- **Toggle doesn't take effect:** `bin/magento cache:clean config`.
- **"Invalid login or password" still appears:** the plugin only overrides `AccountManagement::authenticate`; some integrations (OAuth, external SSO) use different entry points and are unaffected.

---

## Compatibility

- Magento 2.4.x
- PHP 8.1+
- Depends on `jeanmarcos/module-core-local-development` (installed automatically by Composer).

---

## License

MIT
