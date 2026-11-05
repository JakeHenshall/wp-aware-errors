#!/usr/bin/env bash
set -euo pipefail

WP_PATH="${1:-${WP_PATH:-}}"
if [[ -z "$WP_PATH" || ! -f "$WP_PATH/wp-load.php" ]]; then
    echo "Usage: tests/http/run.sh /path/to/disposable/wordpress" >&2
    exit 1
fi

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="${PLUGIN_DIR:-$(cd "$HERE/../.." && pwd)}"
LOCAL_PORT="${LOCAL_PORT:-18899}"
PROD_PORT="${PROD_PORT:-18898}"
TMP_DIR="$(mktemp -d)"
PIDS=()

cleanup() {
    for pid in "${PIDS[@]:-}"; do
        kill "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
    done
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

mkdir -p "$WP_PATH/wp-content/mu-plugins" "$WP_PATH/wp-content/plugins"
cp "$HERE/wae-http-smoke.php" "$WP_PATH/wp-content/mu-plugins/wae-http-smoke.php"
if [[ ! -e "$WP_PATH/wp-content/plugins/wp-aware-errors" ]]; then
    ln -s "$PLUGIN_DIR" "$WP_PATH/wp-content/plugins/wp-aware-errors"
fi

start_server() {
    local env_type="$1"
    local port="$2"
    local home="http://127.0.0.1:${port}"
    WP_ENVIRONMENT_TYPE="$env_type" WP_HOME="$home" WP_SITEURL="$home" \
        php -S "127.0.0.1:${port}" -t "$WP_PATH" >"$TMP_DIR/server-${port}.log" 2>&1 &
    PIDS+=("$!")
}

wait_for_server() {
    local url="$1"
    for _ in $(seq 1 50); do
        if curl -sS -o /dev/null "$url" 2>/dev/null; then
            return 0
        fi
        sleep 0.1
    done
    echo "Server did not start: $url" >&2
    return 1
}

assert_contains() {
    local file="$1"
    local needle="$2"
    grep -qF "$needle" "$file" || {
        echo "Expected '$needle' in $file" >&2
        exit 1
    }
}

assert_not_contains() {
    local file="$1"
    local needle="$2"
    if grep -qF "$needle" "$file"; then
        echo "Secret/detail leaked: '$needle' in $file" >&2
        exit 1
    fi
}

start_server local "$LOCAL_PORT"
start_server production "$PROD_PORT"
wait_for_server "http://127.0.0.1:${LOCAL_PORT}/?wae_http_smoke=1"
wait_for_server "http://127.0.0.1:${PROD_PORT}/?wae_http_smoke=1"

curl -sS -o "$TMP_DIR/local-anon.html" -w '%{http_code}' \
    "http://127.0.0.1:${LOCAL_PORT}/?wae_http_smoke=1" >"$TMP_DIR/local-anon.code"
curl -sS -o "$TMP_DIR/local-admin.html" -w '%{http_code}' \
    "http://127.0.0.1:${LOCAL_PORT}/?wae_http_smoke=1&wae_http_admin=1" >"$TMP_DIR/local-admin.code"
curl -sS -o "$TMP_DIR/prod-anon.html" -w '%{http_code}' \
    "http://127.0.0.1:${PROD_PORT}/?wae_http_smoke=1" >"$TMP_DIR/prod-anon.code"
curl -sS -o "$TMP_DIR/prod-admin.html" -w '%{http_code}' \
    "http://127.0.0.1:${PROD_PORT}/?wae_http_smoke=1&wae_http_admin=1" >"$TMP_DIR/prod-admin.code"
curl -sS -o "$TMP_DIR/local-wpdie.html" -w '%{http_code}' \
    "http://127.0.0.1:${LOCAL_PORT}/?wae_http_wp_die_fatal=1&wae_http_admin=1" >"$TMP_DIR/local-wpdie.code"
curl -sS -o "$TMP_DIR/prod-wpdie.html" -w '%{http_code}' \
    "http://127.0.0.1:${PROD_PORT}/?wae_http_wp_die_fatal=1" >"$TMP_DIR/prod-wpdie.code"

for code_file in "$TMP_DIR"/*.code; do
    [[ "$(cat "$code_file")" == "500" ]] || {
        echo "Expected HTTP 500 in $(basename "$code_file"): $(cat "$code_file")" >&2
        exit 1
    }
done

# Anonymous local: generic page with reference, no details.
assert_contains "$TMP_DIR/local-anon.html" "Something went wrong"
assert_contains "$TMP_DIR/local-anon.html" "WAE-"
assert_not_contains "$TMP_DIR/local-anon.html" "TEST_PASSWORD_123"
assert_not_contains "$TMP_DIR/local-anon.html" "TEST_TOKEN_123"
assert_not_contains "$TMP_DIR/local-anon.html" "RuntimeException"

# Administrator local: detailed page, but still redacted.
assert_contains "$TMP_DIR/local-admin.html" "HTTP smoke"
assert_contains "$TMP_DIR/local-admin.html" "WordPress context"
assert_not_contains "$TMP_DIR/local-admin.html" "TEST_PASSWORD_123"
assert_not_contains "$TMP_DIR/local-admin.html" "TEST_TOKEN_123"
assert_not_contains "$TMP_DIR/local-admin.html" "throw new RuntimeException"

# Production anonymous and administrator: generic only, even with an admin session.
for page in "$TMP_DIR/prod-anon.html" "$TMP_DIR/prod-admin.html"; do
    assert_contains "$page" "Something went wrong"
    assert_contains "$page" "WAE-"
    assert_not_contains "$page" "TEST_PASSWORD_123"
    assert_not_contains "$page" "TEST_TOKEN_123"
    assert_not_contains "$page" "RuntimeException"
    assert_not_contains "$page" "WordPress context"
done

# A WordPress wp_die()/WP_Error fatal is intercepted by the same policy.
assert_contains "$TMP_DIR/local-wpdie.html" "HTTP wp_die fatal"
assert_contains "$TMP_DIR/local-wpdie.html" "WordPress context"
assert_not_contains "$TMP_DIR/local-wpdie.html" "TEST_PASSWORD_123"
assert_contains "$TMP_DIR/prod-wpdie.html" "Something went wrong"
assert_not_contains "$TMP_DIR/prod-wpdie.html" "TEST_PASSWORD_123"
assert_not_contains "$TMP_DIR/prod-wpdie.html" "WordPress context"

echo "HTTP smoke tests passed."
