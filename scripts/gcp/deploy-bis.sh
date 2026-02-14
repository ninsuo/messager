#!/bin/bash
set -e

# Usage: ./scripts/gcp/deploy-bis.sh <ssh-host>

if [ -z "$1" ]; then
    echo "Usage: $0 <ssh-host>"
    exit 1
fi

DESTINATION="$1"
APP_DIR="~/messager"
COMPOSE_FILES="-f compose.yaml -f compose.prod.yaml -f compose.bis.yaml"

echo "Deploying to SURVIVAL instance $DESTINATION..."

# 1. Sync Orchestration Files
echo "Syncing orchestration files (Whitelist)..."
rsync -avz --delete --delete-excluded \
    --include='/compose.yaml' \
    --include='/compose.prod.yaml' \
    --include='/compose.bis.yaml' \
    --include='/.env' \
    --include='/docker/' \
    --exclude='/docker/*/data/' \
    --exclude='/docker/*/data/**' \
    --exclude='/docker/*/logs/' \
    --exclude='/docker/*/logs/**' \
    --exclude='/docker/*/config/' \
    --exclude='/docker/*/config/**' \
    --include='/docker/**' \
    --include='/.env.prod.local' \
    --include='/.env.bis.local' \
    --exclude='*' \
    . "$DESTINATION:$APP_DIR"

# 2. Sync and Merge Secrets
echo "Merging secrets..."
if [ -f .env.bis.local ]; then
    echo "Appending .env.bis.local to .env.prod.local on remote..."
    ssh "$DESTINATION" "cat $APP_DIR/.env.bis.local >> $APP_DIR/.env.prod.local && rm $APP_DIR/.env.bis.local"
fi

# 3. SSH and Pull/Deploy
echo "Pulling images and starting services..."
ssh "$DESTINATION" "cd $APP_DIR && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES pull && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES down --remove-orphans && \
    sudo docker volume rm messager_asset_data || true && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES up -d"

# 4. Run Migrations
echo "Waiting for stack to stabilize..."
sleep 15
echo "Running migrations..."
ssh "$DESTINATION" "cd $APP_DIR && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES exec -T php bin/console doctrine:migrations:migrate --no-interaction"

echo "Survival deployment complete!"
