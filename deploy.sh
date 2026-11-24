#!/usr/bin/env bash
# Deploy the latest main to this server. Run it ON the server, from anywhere:
#   ./deploy.sh              back up, pull, rebuild, migrate, restart workers
#   ./deploy.sh --no-backup  skip the pre-deploy backup
#   ./deploy.sh --no-pull    redeploy what is already checked out
set -euo pipefail

cd "$(dirname "$0")"

BRANCH=main
BACKUP=1
PULL=1
for arg in "$@"; do
    case "$arg" in
        --no-backup) BACKUP=0 ;;
        --no-pull)   PULL=0 ;;
        -h|--help)   sed -n '2,6p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

step() { printf '\n==> %s\n' "$*"; }

[ -f .env ] || { echo "No .env here. Copy .env.example and fill it in first." >&2; exit 1; }
grep -q '^APP_KEY=base64:' .env || { echo "APP_KEY is not set in .env." >&2; exit 1; }

if [ "$PULL" = 1 ]; then
    step "Checking the working tree"
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        echo "Uncommitted changes on the server; refusing to pull over them:" >&2
        git status --short --untracked-files=no >&2
        exit 1
    fi
    [ "$(git rev-parse --abbrev-ref HEAD)" = "$BRANCH" ] || { echo "Not on $BRANCH." >&2; exit 1; }
fi

PREVIOUS=$(git rev-parse --short HEAD)

if [ "$BACKUP" = 1 ]; then
    step "Backing up database and photos"
    if docker compose ps --status running --services 2>/dev/null | grep -qx app; then
        docker compose exec -T app bash scripts/backup.sh
    else
        echo "App container is not running; skipping backup."
    fi
fi

if [ "$PULL" = 1 ]; then
    step "Pulling $BRANCH"
    git pull --ff-only origin "$BRANCH"
fi

step "Building images and starting containers"
docker compose up -d --build --remove-orphans

step "Waiting for the app to finish migrating"
for _ in $(seq 1 60); do
    state=$(docker inspect -f '{{.State.Health.Status}}' recipes-app 2>/dev/null || echo missing)
    [ "$state" = healthy ] && break
    sleep 2
done
[ "${state:-}" = healthy ] || { echo "recipes-app is not healthy. Check: docker compose logs app" >&2; exit 1; }

# src/ is bind-mounted, so a pull changes code without recreating the container.
# Run the bootstrap explicitly (composer, migrate, assets, caches) and restart the
# workers so they stop running the old code.
step "Updating dependencies, schema and assets"
docker compose exec -T app app-bootstrap

step "Restarting workers"
docker compose restart queue scheduler

step "Checking the site answers"
PORT=$(grep -E '^APP_PORT=' .env | cut -d= -f2 | tr -d '"' || true)
PORT=${PORT:-8086}
for _ in $(seq 1 15); do
    code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/up" || true)
    [ "$code" = 200 ] && break
    sleep 2
done
if [ "${code:-}" != 200 ]; then
    echo "Site did not return 200 (got ${code:-none}). Previous version was $PREVIOUS." >&2
    echo "Roll back with: git checkout $PREVIOUS && ./deploy.sh --no-pull --no-backup" >&2
    exit 1
fi

step "Deployed $(git rev-parse --short HEAD) (was $PREVIOUS)"
