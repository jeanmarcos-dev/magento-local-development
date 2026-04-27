# Development_LiveReload

[![Packagist](https://img.shields.io/packagist/v/jeanmarcos/module-livereload.svg)](https://packagist.org/packages/jeanmarcos/module-livereload)

> ⚠️ **FOR LOCAL DEVELOPMENT ONLY** — Production injection is disabled by default.

Injects the [LiveReload](http://livereload.com/) browser auto-reload script into Magento 2 storefront and admin pages, so code changes refresh the browser without manual `F5`.

---

## What it does

Injects the following tag just before `</body>` on every storefront and admin page:

```html
<script defer src="/livereload.js?port=443"></script>
```

The script is rendered by `Development\LiveReload\Block\Head\LiveReloadScript`, which reads the production guard before rendering.

A local LiveReload server (e.g. `npx livereload ./pub`) must serve `/livereload.js` for the browser to connect.

---

## Safety model

| Mode | `Allow in Production` flag | Behavior |
|---|---|---|
| `developer` / `default` | any | **script injected** |
| `production` | `No` (default) | **not injected** |
| `production` | `Yes` | **script injected** |

Implementation: [`Development_Core`](https://packagist.org/packages/jeanmarcos/module-core-local-development) (`Development\Core\Model\ProductionGuard::isEnabled()`), wired via a `virtualType` in `etc/di.xml` bound to the config path `development/live_reload/allow_in_production`, plus `Block\Head\LiveReloadScript::_toHtml()` — when disabled, returns an empty string so nothing reaches the page.

The block's `getCacheKeyInfo()` includes the flag, so layout/block cache stays consistent across toggles without needing a full cache flush.

---

## Configuration

Panel path: **Stores → Configuration → ⚠ Development Modules → Live Reload → General → Allow in Production**

- Default: `No`.
- Changing the flag requires `bin/magento cache:clean config layout block_html`.

---

## Install

```bash
composer require --dev jeanmarcos/module-livereload
bin/magento module:enable Development_LiveReload
bin/magento setup:upgrade
bin/magento cache:flush
```

Then start a LiveReload server pointing at your theme assets:

```bash
# Node-based server example
npx livereload pub/static -p 443

# Or with livereload-bin
livereload ./ --port 443
```

## Kill switch

```bash
bin/magento module:disable Development_LiveReload
bin/magento setup:upgrade
bin/magento cache:flush
```

For permanent removal:

```bash
composer remove jeanmarcos/module-livereload
```

---

## Security and performance considerations

- Low security impact on its own — serves only static reload JS.
- If left on in production with no LiveReload server running, every page load returns `404` on `/livereload.js`, polluting logs and breaking the page speed budget by a negligible margin.
- The script is loaded with `defer`, so it does not block rendering.

---

## File structure

```
LiveReload/
├── Block/
│   └── Head/
│       └── LiveReloadScript.php
├── etc/
│   ├── acl.xml
│   ├── adminhtml/
│   │   └── system.xml
│   ├── config.xml
│   ├── di.xml                       # block wiring + ProductionGuard virtualType
│   └── module.xml                   # depends on Development_Core
├── view/
│   ├── adminhtml/
│   │   └── layout/
│   │       └── default.xml
│   ├── base/
│   │   └── templates/
│   │       └── head/
│   │           └── livereload.phtml
│   └── frontend/
│       └── layout/
│           └── default_head_blocks.xml
├── composer.json
├── registration.php
└── README.md
```

---

## Troubleshooting

- **Script doesn't appear:** check `bin/magento deploy:mode:show`; if in production, check the flag; then `cache:clean layout block_html`.
- **Port mismatch:** the path `/livereload.js?port=443` is hardcoded in `view/base/templates/head/livereload.phtml`. Edit the template if your server uses a different port.
- **404 on `/livereload.js`:** LiveReload server is not running — start it or disable this module.

---

## Compatibility

- Magento 2.4.x
- PHP 8.1+
- Depends on `jeanmarcos/module-core-local-development` (installed automatically by Composer).

---

## License

MIT
