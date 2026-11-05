#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${WP_TESTS_DIR:-}" ]]; then
    echo "WP_TESTS_DIR must point at the WordPress test library." >&2
    echo "Example: WP_TESTS_DIR=/tmp/wordpress-tests-lib WP_PHPUNIT_BIN=/path/to/phpunit-9 vendor/bin/phpunit -c phpunit-integration.xml.dist" >&2
    exit 1
fi

if [[ -n "${WP_PHPUNIT_BIN:-}" ]]; then
    PHPUNIT_BIN="$WP_PHPUNIT_BIN"
elif command -v phpunit9 >/dev/null 2>&1; then
    PHPUNIT_BIN="phpunit9"
else
    PHPUNIT_BIN="phpunit"
fi

if ! command -v "$PHPUNIT_BIN" >/dev/null 2>&1; then
    echo "PHPUnit binary not found: $PHPUNIT_BIN (set WP_PHPUNIT_BIN)." >&2
    exit 1
fi

if "$PHPUNIT_BIN" --version 2>/dev/null | grep -Eq 'PHPUnit 1[0-9]\.'; then
    echo "The WordPress test library used here requires PHPUnit 9.x; set WP_PHPUNIT_BIN to a PHPUnit 9 binary." >&2
    exit 1
fi

exec "$PHPUNIT_BIN" -c phpunit-integration.xml.dist "$@"
