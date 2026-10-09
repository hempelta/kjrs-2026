#!/bin/bash

# Sync the local DDEV system to the public development system

set -e  # Exit on error

LOCAL_BASE_PATH="/var/www/html/"

REMOTE_HOST=
SSH_PORT=22
REMOTE_USER=www-data

REMOTE_BASE_PATH="/var/www/typo3"

if [ -z "$REMOTE_HOST" ]; then
    echo "REMOTE_HOST is empty — set the target host at the top of $0 first." >&2
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

sync_files() {
    local SUB_PATH=$1
    local DELETE_OPTION=${2:-}
    shift $(( $# < 2 ? $# : 2 ))
    local EXCLUDES=("$@")

    local LOCAL_SUB_PATH="${SUB_PATH%/}"

    # A trailing slash syncs the directory's contents; a file must not get one
    local LOCAL_SOURCE="${LOCAL_BASE_PATH}${LOCAL_SUB_PATH}"
    if [ -d "$LOCAL_SOURCE" ]; then
        LOCAL_SOURCE="${LOCAL_SOURCE}/"
    fi

    local DELETE_FLAG=""
    if [ "$DELETE_OPTION" == "--delete" ]; then
        DELETE_FLAG="--delete"
    fi

    # Build exclude options
    local EXCLUDE_OPTS=()
    for exclude in "${EXCLUDES[@]}"; do
        EXCLUDE_OPTS+=(--exclude="$exclude")
    done

    log_info "Syncing ${LOCAL_SUB_PATH} to ${REMOTE_HOST}:${REMOTE_BASE_PATH}/${SUB_PATH}"

    if ! rsync -avzP $DELETE_FLAG -e "ssh -p ${SSH_PORT}" "${EXCLUDE_OPTS[@]}" \
        "$LOCAL_SOURCE" \
        "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_BASE_PATH}/${SUB_PATH}"; then
        log_error "Failed to sync ${LOCAL_SUB_PATH}"
        return 1
    fi

    log_info "Successfully synced ${LOCAL_SUB_PATH}"
}

# Main sync process
log_info "Starting deployment to ${REMOTE_HOST}"

# Sync directories
sync_files "Build/" "--delete"
sync_files "packages/" "--delete" "ot_febuild"
sync_files "vendor/" "--delete"
sync_files "composer.json"
sync_files "composer.lock"

# Post-sync actions on remote server
log_info "Running post-deployment tasks on remote server"

ssh -p ${SSH_PORT} "${REMOTE_USER}@${REMOTE_HOST}" << 'ENDSSH'
    cd /var/www/typo3

    echo "[Remote] Regenerating Composer autoloader..."
    composer dump-autoload --no-dev --classmap-authoritative

    echo "[Remote] Flushing TYPO3 cache..."
    php vendor/bin/typo3 cache:flush

    echo "[Remote] Post-deployment tasks completed"
ENDSSH

if [ $? -eq 0 ]; then
    log_info "Deployment completed successfully!"
else
    log_error "Post-deployment tasks failed"
    exit 1
fi
