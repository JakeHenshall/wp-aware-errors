# WP Aware Errors

An Ignition-inspired, **WordPress-aware** exception debugger. One package works as a normal plugin, a theme drop-in, or an MU-plugin loader target and detects how it was loaded automatically.

## Install as a plugin

Upload `wp-aware-errors.zip` in **Plugins → Add New → Upload Plugin**, then activate it.

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

It renders automatically for `local` / `development`, or when `WP_DEBUG` is true outside production.

### Production safety

It refuses to render in production unless **both** are explicitly enabled:

```php
define('WP_AWARE_ERRORS_ENABLED', true);
define('WP_AWARE_ERRORS_ALLOW_PRODUCTION', true);
```

Do not expose the developer error screen to public traffic.


### Architecture

The runtime is split into one PSR-4 class/interface per file under `src/`. The package ships a tiny dependency-free `autoload.php` using the same namespace mapping as `composer.json`, so WordPress installs do not require Composer at runtime.

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
```

Hook argument snapshots are bounded by default: only the last 50 hook names are kept, payloads are summarised (type / count / keys, not deep copies), known large hooks such as `alloptions` skip arguments entirely, and recording stops if PHP memory pressure is high. Set `WP_AWARE_ERRORS_CAPTURE_HOOK_ARGUMENTS` to `false` to store hook names and timestamps only.

## Optional early fatal handler

WordPress supports `wp-content/fatal-error-handler.php`. The included `dropins/fatal-error-handler.php` is a minimal early renderer for failures that happen before the full package can boot.
