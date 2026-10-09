#!/bin/sh

# Update Composer dependencies on develop, release them to main and deploy
# stage, then live.
#
# Stops at the first failing step — a broken update never reaches live.

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/.."

BRANCH_DEVELOP="${GIT_BRANCH_NAME_DEVELOP:-develop}"
BRANCH_MAIN="${GIT_BRANCH_NAME_MAIN:-main}"

# The script commits and merges on its own; unrelated local changes must not
# slip into that commit.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Error: the working tree has uncommitted changes. Commit or stash them first." >&2
    exit 1
fi

ddev start

git checkout "$BRANCH_DEVELOP"
git pull --ff-only
ddev composer update -W
ddev composer code-quality

if git diff --quiet -- composer.lock; then
    echo "composer.lock unchanged — nothing to release."
    exit 0
fi

git add composer.lock
git commit -m "[TASK] Update Composer dependencies"
git push

git checkout "$BRANCH_MAIN"
git pull --ff-only
git merge --no-ff "$BRANCH_DEVELOP" -m "[TASK] Merge branch '$BRANCH_DEVELOP'"
git push

git checkout "$BRANCH_DEVELOP"

vendor/bin/dep deploy stage
vendor/bin/dep deploy live
