# Development_CustomerBypass

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

Implementation: `Helper/Config.php::isEnabled()`. When disabled, the plugin delegates to `$proceed($username, $password)` and Magento authenticates normally.

---

## Configuration

Panel path: **Stores → Configuration → ⚠ Development Modules → Customer Bypass → General → Allow in Production**

- Default: `No`.
- Changing the flag requires `bin/magento cache:clean config`.

---

## Install

```bash
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

---

## Security risks

- Anyone who knows a customer's email/username can log in as them without the password.
- Exposes full order history, addresses, saved payment methods (if stored), and wishlists.
- No audit trail: bypassed logins look identical to legitimate ones in the session.

---

## File structure

```
CustomerBypass/
├── Helper/
│   └── Config.php                        # production-guard helper
├── Plugin/
│   └── BypassCustomerAuthentication.php  # password bypass around plugin
├── etc/
│   ├── acl.xml
│   ├── adminhtml/
│   │   └── system.xml
│   ├── config.xml
│   ├── di.xml
│   └── module.xml
├── registration.php
└── README.md
```

---

## Troubleshooting

- **Toggle doesn't take effect:** `bin/magento cache:clean config`.
- **"Invalid login or password" still appears:** the plugin only overrides `AccountManagement::authenticate`; some integrations (OAuth, external SSO) use different entry points and are unaffected.

---

## Compatibility

- Magento 2.4.x
- PHP 8.1+
- Depends on `Development_AdminBypass` only for the shared `development` tab definition in `Stores → Configuration`. If `Development_AdminBypass` is disabled, this section may not appear; enable `Development_AdminBypass` or copy the `<tab id="development">` block into this module's `system.xml`.
