#!/bin/bash

# Mirror fileadmin from a remote target (live or stage) into the local project.
#
# Usage:
#   bin-dev/sync-fileadmin.sh           # live (default, uses .env)
#   bin-dev/sync-fileadmin.sh stage     # stage (uses .env.staging)
#
# Required in the env file: REMOTE_SSH_ALIAS, REMOTE_PROJECT_ROOT
#
# --delete: local files that do not exist on the remote are removed.

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

set +u
# shellcheck source=/dev/null
source "$ENV_FILE"
set -u

if [ -z "${REMOTE_SSH_ALIAS:-}" ] || [ -z "${REMOTE_PROJECT_ROOT:-}" ]; then
    echo "Error: REMOTE_SSH_ALIAS and REMOTE_PROJECT_ROOT must be set in $ENV_FILE" >&2
    exit 1
fi

# No trailing slash on the source: rsync creates public/fileadmin itself
rsync -avz --progress --delete -e ssh \
    "$REMOTE_SSH_ALIAS:$REMOTE_PROJECT_ROOT/shared/public/fileadmin" \
    "$PROJECT_ROOT/public/"
