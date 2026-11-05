=== WP Aware Errors ===
Contributors: hensh
Tags: debug, errors, developer, stack trace, woocommerce
Requires at least: 6.2
Requires PHP: 8.2
Stable tag: 0.3.2
License: GPLv2 or later

WordPress-aware exception debugging with hook arguments, component ownership, dependency intelligence, WooCommerce context, REST/AJAX identification and local error history.

== Description ==

WP Aware Errors provides an Ignition-inspired error experience specifically for WordPress developers. It attributes stack frames and hook callbacks to WordPress Core, plugins, themes, MU plugins and Composer vendor code, captures sanitised hook arguments, understands REST/AJAX requests, inspects plugin requirements, adds WooCommerce context and keeps a compact local error history.

The same package can be activated as a normal plugin or required from inside a theme; it detects its installation mode automatically.

== Installation ==

1. Upload and activate as a plugin, OR copy the folder into a theme and require `wp-aware-errors.php` from `functions.php`.
2. Use `WP_ENVIRONMENT_TYPE=local` or `development`, or enable `WP_DEBUG` outside production.
3. Optionally enable `SAVEQUERIES` while developing.

== Changelog ==

= 0.3.2 =
* Security: central access policy for detailed error pages; administrators only by default, with explicit local anonymous opt-in.
* Security: sanitised Ignition reports disable frame arguments, source snippets, raw request context and external reporting.
* Security: legacy history migration sanitises readable records, discards raw unreadable data, keeps only non-sensitive quarantine metadata and adds an administrator clear action.
* Added opaque `WAE-` correlation references to generic pages, early fatal references and history.
* Added generic production/staging handling with diagnostics gated to authorised contexts; `WP_AWARE_ERRORS_ENABLED=false` keeps WordPress native handling.
* Added `wp_die()` fatal-handler wrappers, a WordPress-fatal-output marker and a memory-exhaustion minimal fallback.
* Added early-fatal reference storage and drop-in credential redaction.
* Added opt-in source snippets (`WP_AWARE_ERRORS_SHOW_SOURCE`), disabled by default.
* Added protocol-specific responses for HTML, REST/AJAX JSON, XML-RPC, XML, JSONP, CLI and unknown requests.
* Refactored the custom renderer into smaller presentation helpers.
* Added WordPress integration tests with `WP_UnitTestCase` alongside the PHPUnit unit suite.

= 0.3.1 =
* Security: redact secrets in URLs, request values, hook arguments, SQL and error messages.
* Fixed duplicate fatal-error rendering when WordPress's fatal handler runs first.
* Fixed duplicate history entries and added JSON error responses for AJAX/REST/XML.
* Performance: lazy plugin/theme indexing, bounded source reads and sampled memory checks.
* Accessibility: language attributes, focus styles, contrast fixes, copy status announcements and labelled controls.
* Added PHPUnit coverage and updated dependencies with known advisories fixed.

= 0.3.0 =
* Refactored the monolithic runtime into PSR-4-style one-class-per-file source files.
* Added a dependency-free PSR-4 autoloader plus Composer autoload metadata.
* Preserved unified plugin/theme/MU-plugin auto-detection and all v0.2 diagnostics.

= 0.2.0 =
* Unified plugin/theme/MU-plugin bootstrap and automatic mode detection.
* Hook argument inspection and callback component ownership.
* Plugin dependency / WordPress / PHP compatibility intelligence.
* WooCommerce compatibility and runtime context.
* AJAX/REST route identification.
* Provider-based solutions.
* Persistent local error history and Tools screen.

= 0.1.0 =
* Initial WordPress-aware exception renderer.
