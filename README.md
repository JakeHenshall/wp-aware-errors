# WP Aware Errors

A **WordPress-aware** exception debugger that renders [Spatie Ignition](https://github.com/spatie/ignition). One package works as a normal plugin, a theme drop-in, or an MU-plugin loader target and detects how it was loaded automatically.

## Install as a plugin

Install dependencies, then upload the plugin:

```bash
composer install --no-dev --optimize-autoloader
```

`spatie/ignition` is required at runtime. Upload `wp-aware-errors.zip` in **Plugins → Add New → Upload Plugin**, then activate it. The zip must include the `vendor/` directory produced by Composer.

## Use as a theme drop-in

Copy the whole `wp-aware-errors` directory into your theme, for example:

```text
wp-content/themes/my-theme/dev/wp-aware-errors/
```

Then load the same entry file from `functions.php`:

```php
require_once __DIR__ . '/dev/wp-aware-errors/wp-aware-errors.php';
```

The package detects that its own entry file lives beneath `wp-content/themes/` and reports **Theme Drop-in** mode. There is no separate theme edition.

## MU-plugin use

WordPress only auto-loads PHP files directly inside `mu-plugins`. Put the package in `mu-plugins/wp-aware-errors/` and create a tiny root loader:

```php
<?php
require_once WPMU_PLUGIN_DIR . '/wp-aware-errors/wp-aware-errors.php';
```

The package detects **MU Plugin** mode.

## Recommended development config

```php
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('SAVEQUERIES', true); // optional, development only
```

The renderer can activate in `local` / `development`, or when `WP_DEBUG` is true outside production. **Detailed information has a separate access policy.**

### Access policy

Detailed error pages are for authenticated administrators by default:

- `local` / `development`: administrators see details by default.
- `staging`: administrators see details only when `WP_AWARE_ERRORS_ENABLED` is explicitly `true`.
- `production`: administrators see details only when **both** constants are `true`:

```php
define('WP_AWARE_ERRORS_ENABLED', true);
define('WP_AWARE_ERRORS_ALLOW_PRODUCTION', true);
```

- `WP_DEBUG` alone never grants detailed access.
- Anonymous visitors never receive details on `staging` or `production`.
- Anonymous local/development debugging requires an explicit opt-in:

```php
define('WP_AWARE_ERRORS_ALLOW_ANONYMOUS', true);
```

- Unknown environments, unavailable authentication, or capability-check failures fall back to a generic page.
- The capability can be changed with the `wp_aware_errors_view_capability` filter (default `manage_options`).
- Setting `WP_AWARE_ERRORS_ENABLED` to `false` disables the plugin handler entirely and leaves WordPress's native fatal handling in control. Without that explicit opt-out, production/staging still receives the plugin's generic page and reference; expensive diagnostics are only recorded when detailed output could be authorised.

When details are not permitted, the visitor receives only a clean generic page with an opaque reference such as `WAE-1A2B3C4D5E6F7A8B`. The same reference is stored with the sanitised internal history record.

Do not expose the developer error screen to public traffic.


### Architecture

The runtime is split into one PSR-4 class/interface per file under `src/`. Composer installs Spatie Ignition into `vendor/`. The package also ships `autoload.php` for its own classes, using the same namespace mapping as `composer.json`.

```text
wp-aware-errors/
├── autoload.php
├── composer.json
├── wp-aware-errors.php
├── dropins/
│   └── fatal-error-handler.php
└── src/
    ├── Bootstrap.php
    ├── Installation.php
    ├── ErrorHandler.php
    ├── ContextCollector.php
    ├── Renderer.php
    ├── SolutionProvider.php
    ├── SolutionManager.php
    └── ...
```

## v0.3 features

- One codebase / one ZIP with automatic plugin, theme drop-in and MU-plugin detection
- Plugin/theme/MU-plugin/Core/vendor ownership for stack frames
- Sanitised **hook argument inspection** for recent hooks
- Active/recent hook callback ownership via Reflection (Query Monitor-style attribution)
- Plugin dependency intelligence using `Requires Plugins`
- WordPress/PHP requirement checks
- WooCommerce extension checks using `WC requires at least` / `WC tested up to`
- WooCommerce context: version, `wc-ajax`, HPOS, cart count/total and page context where safely available
- AJAX action identification
- REST route/method/parameter capture
- Recent `$wpdb` queries when `SAVEQUERIES` is enabled
- Provider-based suggested solutions with an extension filter
- Local persistent error history under **Tools → WP Aware Errors**
- Cursor / VS Code file links and AI-context copy
- Request/header secret redaction

## Extend solution providers

Use the filter:

```php
add_filter('wp_aware_errors_solution_providers', function (array $providers): array {
    $providers[] = new My_Project_Solution_Provider();
    return $providers;
});
```

Custom providers must implement `Hensh\WpAwareErrors\SolutionProvider`.

## Optional constants

```php
define('WP_AWARE_ERRORS_CAPTURE_HOOK_ARGUMENTS', false); // names only; snapshots are already size-bounded
define('WP_AWARE_ERRORS_HISTORY', false);              // disable history
define('WP_AWARE_ERRORS_HISTORY_LIMIT', 50);           // default 30
define('WP_AWARE_ERRORS_ALLOW_ANONYMOUS', true);       // local/development only; explicit opt-in
define('WP_AWARE_ERRORS_SHOW_SOURCE', true);           // default false; source snippets in detailed HTML
```

Request URIs, headers, hook arguments, SQL and error messages are redacted for common secret keys (`password`, `token`, `nonce`, `authorization`, API keys, cookies, etc.) before they are stored or rendered. The error screen still contains server paths and application internals, so keep it behind your development environment or login. If history is disabled, the generic page reference is written to the PHP error log instead of the history table.

Hook argument snapshots are bounded by default: only the last 50 hook names are kept, payloads are summarised (type / count / keys, not deep copies), known large hooks such as `alloptions` skip arguments entirely, and recording stops if PHP memory pressure is high. Set `WP_AWARE_ERRORS_CAPTURE_HOOK_ARGUMENTS` to `false` to store hook names and timestamps only.

Source snippets are disabled by default in both Ignition and the fallback renderer. Authorised administrators can opt in explicitly with `WP_AWARE_ERRORS_SHOW_SOURCE=true`. Ignition frame arguments and source snippets remain disabled regardless of this option.

### Ignition hardening

Ignition is given a sanitised report rather than the raw throwable: frame arguments are removed, source snippets are disabled, request/cookie/session context is sanitised, and only this plugin's solution bridge is registered. The page still contains Ignition's own UI and styling. External Flare reporting is disabled by the plugin's renderer; add an explicit integration if remote reporting is required.

### Protocol responses

- Browser requests get the HTML error page (Ignition when available, otherwise the custom renderer).
- REST/AJAX get sanitised JSON.
- XML-RPC gets a valid XML-RPC fault with HTTP 200.
- XML/feed requests get a minimal XML error.
- JSONP is supported only with a validated WordPress callback; invalid callbacks receive plain JSON with HTTP 400.
- WP-CLI gets a terminal-friendly message.
- Unknown request types receive the generic HTML fallback.

### Legacy history migration

Existing `wp_aware_errors_history_v1` records are migrated once, sanitised, and marked with a schema version. Unreadable records are quarantined from display; their raw unreadable data is discarded for security and only non-sensitive count/time metadata remains. Administrators see a warning with a **Clear Legacy Error History** action; only the plugin's own options are affected.

Fatal errors that happen before the main plugin can load are recorded by the optional drop-in as opaque early-fatal references (reference and timestamp only). They appear on **Tools → WP Aware Errors** and correlate with the server error log; **Clear history** removes them.

## Compatibility

- WordPress 6.2 or newer.
- PHP 8.2 or newer.
- Composer is required for the Ignition runtime; the plugin's own classes also ship with a dependency-free PSR-4 autoloader.

## Development

```bash
composer install
composer test
```

The unit suite covers sanitisation, request redaction, access-policy decisions, renderer escaping/accessibility hooks, protocol responses, Ignition report sanitisation, history migration, providers, source reading and component classification.

### WordPress integration tests

The integration suite uses the official WordPress test library and `WP_UnitTestCase`. The WordPress test library currently requires PHPUnit 9, while the unit suite uses PHPUnit 11:

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib \
WP_PHPUNIT_BIN=/path/to/phpunit-9 \
composer test:integration
```

Run the same suite in multisite mode with:

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib \
WP_TESTS_MULTISITE=1 \
WP_PHPUNIT_BIN=/path/to/phpunit-9 \
composer test:integration
```

Use a disposable database. The integration harness is a development-only convenience; never point it at production data.

### HTTP smoke tests

`tests/http/run.sh` starts local and production WordPress servers against a disposable installation, triggers a synthetic exception as anonymous and administrator users, and asserts:

- Anonymous users receive only the generic page and reference.
- Local administrators receive the detailed page with redacted secrets.
- Production administrators receive only the generic page.
- WordPress `wp_die()` fatal errors follow the same policy as thrown exceptions.
- No synthetic credentials or exception details leak into HTML/JSON.

The GitHub Actions workflow in `.github/workflows/tests.yml` runs unit tests, single-site integration tests, multisite integration tests, and the HTTP smoke suite.

## Optional early fatal handler

WordPress supports `wp-content/fatal-error-handler.php`. The included `dropins/fatal-error-handler.php` is a minimal early renderer for failures that happen before the full package can boot.

The drop-in applies the same environment/administrator policy, redacts common credentials from the fatal message, stores only opaque early-fatal references, and keeps source snippets disabled unless `WP_AWARE_ERRORS_SHOW_SOURCE=true`. Standard WordPress `wp_die()` handlers are wrapped by the main plugin; a third-party handler installed after this plugin that replaces every WordPress handler can still bypass that wrapper, so the drop-in remains the safety net for fatal errors.
