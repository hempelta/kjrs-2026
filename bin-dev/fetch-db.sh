#!/bin/bash

# Fetch a database dump from a remote target (live or stage) and import it into DDEV.
#
# Usage:
#   bin-dev/fetch-db.sh           # live (default, uses .env)
#   bin-dev/fetch-db.sh live      # explicit live (uses .env)
#   bin-dev/fetch-db.sh stage     # stage (uses .env.staging)
#
# Required in the env file: REMOTE_SSH_ALIAS, REMOTE_PROJECT_ROOT, DB_NAME, DB_USER, DB_PASS

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

TARGET="${1:-live}"

case "$TARGET" in
    live)
        ENV_FILE="$PROJECT_ROOT/.env"
        ;;
    stage)
        ENV_FILE="$PROJECT_ROOT/.env.staging"
        ;;
    *)
        echo "Usage: $0 [live|stage]" >&2
        exit 1
        ;;
esac

if [ ! -f "$ENV_FILE" ]; then
    echo "Error: env file not found at $ENV_FILE" >&2
    exit 1
fi

# Disable nounset while sourcing: env files contain values like argon2 hashes
# with "$" characters that are not meant as variable expansions.
set +u
# shellcheck source=/dev/null
source "$ENV_FILE"
set -u

if [ -z "${REMOTE_SSH_ALIAS:-}" ] || [ -z "${REMOTE_PROJECT_ROOT:-}" ] \
    || [ -z "${DB_NAME:-}" ] || [ -z "${DB_USER:-}" ] || [ -z "${DB_PASS:-}" ]; then
    echo "Error: REMOTE_SSH_ALIAS, REMOTE_PROJECT_ROOT, DB_NAME, DB_USER and DB_PASS must be set in $ENV_FILE" >&2
    exit 1
fi

DDEV_PROJECT_NAME="${DDEV_PROJECT_NAME:-kjrs-2026}"
REMOTE_SQL_DIR="$REMOTE_PROJECT_ROOT/sql"
LOCAL_SQL_DIR="$PROJECT_ROOT/sql"

mkdir -p "$LOCAL_SQL_DIR"

is_ddev_running() {
    ddev list | grep -q "$DDEV_PROJECT_NAME.*running"
}

DDEV_WAS_RUNNING="false"

echo ">>> $(echo "$TARGET" | tr '[:lower:]' '[:upper:]') <<<"

echo "Create database dump..."
ssh "$REMOTE_SSH_ALIAS" "mkdir -p $REMOTE_SQL_DIR"

# Credentials go over stdin into an option file with mode 600 — never onto a
# command line, where anyone on a shared server could read them with `ps`.
printf '[client]\nuser=%s\npassword=%s\n' "$DB_USER" "$DB_PASS" \
    | ssh "$REMOTE_SSH_ALIAS" "umask 177 && cat > $REMOTE_SQL_DIR/.my.cnf"
ssh "$REMOTE_SSH_ALIAS" "mysqldump --defaults-extra-file=$REMOTE_SQL_DIR/.my.cnf --single-transaction --quick '$DB_NAME' > $REMOTE_SQL_DIR/db.dump.sql; rc=\$?; rm -f $REMOTE_SQL_DIR/.my.cnf; exit \$rc"

# MariaDB >= 10.5.25 writes a sandbox-mode marker as the first line, which
# older clients reject. Remove exactly that line, nothing else.
ssh "$REMOTE_SSH_ALIAS" "sed -i '1{/enable the sandbox mode/d}' $REMOTE_SQL_DIR/db.dump.sql"

echo "Download dump..."
scp "$REMOTE_SSH_ALIAS:$REMOTE_SQL_DIR/db.dump.sql" "$LOCAL_SQL_DIR"
ssh "$REMOTE_SSH_ALIAS" "rm -f $REMOTE_SQL_DIR/db.dump.sql"

echo "Check whether DDEV is running..."
if is_ddev_running; then
    echo "DDEV is already running."
    DDEV_WAS_RUNNING="true"
else
    echo "Start DDEV..."
    ddev start
fi

echo "Import dump..."
ddev exec TYPO3_CONTEXT=Development/Local vendor/bin/typo3 database:import < "$LOCAL_SQL_DIR/db.dump.sql"

echo "Update database schema..."
ddev exec TYPO3_CONTEXT=Development/Local vendor/bin/typo3 database:updateschema

echo "Flush TYPO3 caches..."
ddev exec TYPO3_CONTEXT=Development/Local vendor/bin/typo3 cache:flush -g system -g di

# Stop DDEV only if it was not running at the start
if [ "$DDEV_WAS_RUNNING" = "false" ]; then
    echo "Stop DDEV..."
    ddev stop
fi
