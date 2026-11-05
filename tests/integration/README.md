# WordPress Integration Tests

These tests extend `WP_UnitTestCase` and require the official WordPress test
library. They use a disposable test database only.

## Setup

1. Install the WordPress test library with `wp scaffold plugin-tests` and its
   `bin/install-wp-tests.sh`, or point `WP_TESTS_DIR` at an existing checkout.
2. Use a dedicated test database. Never point these tests at production data.
3. The current WordPress test library expects PHPUnit 9. The unit suite uses
   PHPUnit 11, so keep the runners separate.

## Run

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib \
WP_PHPUNIT_BIN=/path/to/phpunit-9 \
composer test:integration
```

Multisite:

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib \
WP_TESTS_MULTISITE=1 \
WP_PHPUNIT_BIN=/path/to/phpunit-9 \
composer test:integration
```

If `WP_PHPUNIT_BIN` is omitted it defaults to `phpunit9` or `phpunit` from
`PATH`. The runner rejects PHPUnit 10+ because the WordPress test library
currently requires PHPUnit 9.

HTTP smoke tests are in `tests/http/run.sh` and are also run by CI.

The bootstrap automatically points the WordPress test suite at this project's
`vendor/yoast/phpunit-polyfills` directory and uses `WP_ENVIRONMENT_TYPE=local`
so access-policy integration tests run in a development environment.
