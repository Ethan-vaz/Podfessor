#!/usr/bin/env bash
# Runs the API tests against a fresh test database.
#
# Needs a MySQL or MariaDB server. Set these if your setup differs from the defaults:
#   TEST_DB_HOST (127.0.0.1)  TEST_DB_PORT (3306)  TEST_DB_NAME (podfessor_test)
#   TEST_DB_USER (root)       TEST_DB_PASS (empty)
#
# Usage: tests/run.sh

set -euo pipefail
cd "$(dirname "$0")/.."

export DB_HOST="${TEST_DB_HOST:-127.0.0.1}"
export DB_PORT="${TEST_DB_PORT:-3306}"
export DB_NAME="${TEST_DB_NAME:-podfessor_test}"
export DB_USER="${TEST_DB_USER:-root}"
export DB_PASS="${TEST_DB_PASS:-}"
export OPENAI_API_KEY="test-key"
export OPENAI_BASE_URL="http://127.0.0.1:8091"
export DAILY_EPISODE_LIMIT=3
export AUDIO_DIR="$(mktemp -d)"
export MOCK_LOG="$(mktemp)"
export API_URL="http://127.0.0.1:8090/api"

mysql_args=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER")
[ -n "$DB_PASS" ] && mysql_args+=(-p"$DB_PASS")

echo "Resetting $DB_NAME..."
mysql "${mysql_args[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`"
mysql "${mysql_args[@]}" "$DB_NAME" < database/schema.sql
mysql "${mysql_args[@]}" "$DB_NAME" < database/seed.sql

php -S 127.0.0.1:8091 tests/mock_openai.php > /dev/null 2>&1 &
mock_pid=$!
php -S 127.0.0.1:8090 router.php > /dev/null 2>&1 &
server_pid=$!
trap 'kill $mock_pid $server_pid 2>/dev/null; rm -rf "$MOCK_LOG" "$AUDIO_DIR"' EXIT

# Wait for the server to start
for _ in $(seq 1 30); do
    curl -s -o /dev/null "$API_URL/semesters.php" && break
    sleep 0.2
done

php tests/api_test.php
