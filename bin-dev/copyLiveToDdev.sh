#!/bin/bash

# Sync the live system to the local DDEV system

REMOTE_HOST=
SSH_PORT=22
REMOTE_USER=www-data

REMOTE_BASE_PATH="/var/www/typo3/current/"
LOCAL_BASE_PATH="/var/www/html"

if [ -z "$REMOTE_HOST" ]; then
    echo "REMOTE_HOST is empty — set the target host at the top of $0 first." >&2
    exit 1
fi

sync_files() {
    local SUB_PATH=$1
    local DELETE_OPTION=$2
    local EXCLUDES=$3

    local LOCAL_SUB_PATH="${SUB_PATH%/}"

    local DELETE_FLAG=""

    if [ "$DELETE_OPTION" == "--delete" ]; then
        DELETE_FLAG="--delete"
    fi

    # Optional:
    # --delete
    # --dry-run
    rsync -avzP $DELETE_FLAG -e "ssh -p ${SSH_PORT}" $EXCLUDES \
        "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_BASE_PATH}/${SUB_PATH}" \
        "${LOCAL_BASE_PATH}/${LOCAL_SUB_PATH}"
}

# Example with delete & exclude
# sync_files "data/"
# sync_files "packages/" "" "--exclude=ot_febuild/ --exclude=ot_bootstrap5/"
# sync_files "public/" "--delete"

sync_files "public/fileadmin/"
sync_files "data/"
