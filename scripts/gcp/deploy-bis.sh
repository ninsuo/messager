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

# 1. Sync Files
echo "Syncing files..."
rsync -avz --exclude-from='.gitignore' \
    --exclude='.git/' \
    --exclude='.claude/' \
    --exclude='.idea/' \
    --exclude='.phpunit.cache/' \
    --exclude='var/' \
    --exclude='node_modules/' \
    --exclude='docker-data/' \
    --exclude='.env.local' \
    --exclude='.env.test' \
    --exclude='.env.prod.local' \
    --exclude='.env.bis.local' \
    --exclude='CLAUDE.md' \
    . "$DESTINATION:$APP_DIR"

# 2. Sync and Merge Secrets
echo "Syncing and merging secrets..."
if [ -f .env.prod.local ]; then
    rsync -avz .env.prod.local "$DESTINATION:$APP_DIR/.env.prod.local"
else
    echo "⚠️ Warning: .env.prod.local not found locally."
fi

if [ -f .env.bis.local ]; then
    echo "Appending .env.bis.local to .env.prod.local on remote..."
    rsync -avz .env.bis.local "$DESTINATION:$APP_DIR/.env.bis.local"
    ssh "$DESTINATION" "cat $APP_DIR/.env.bis.local >> $APP_DIR/.env.prod.local && rm $APP_DIR/.env.bis.local"
else
    echo "⚠️ Warning: .env.bis.local not found locally."
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
    sudo docker compose --env-file .env --env-file .env.prod.local exec -T php bin/console doctrine:migrations:migrate --no-interaction"

echo "Survival deployment complete!"
