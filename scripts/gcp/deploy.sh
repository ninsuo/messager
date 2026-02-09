#!/bin/bash
set -e

# Make sure you ran:
# gcloud compute config-ssh

# And run this script with:
# ./deploy.sh <ssh-host>

if [ -z "$1" ]; then
    echo "Usage: $0 <ssh-host>"
    echo "Example: $0 messager-vm.europe-west9-a.my-project"
    exit 1
fi

DESTINATION="$1"
APP_DIR="~/messager"

echo "Deploying to $DESTINATION..."

# 1. Sync Files
echo "Syncing files..."
rsync -avz --exclude-from='.gitignore' \
    --exclude='.git/' \
    --exclude='.claude/' \
    --exclude='.idea/' \
    --exclude='.phpunit.cache/' \
    --exclude='var/' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='docker-data/' \
    --exclude='.env.local' \
    --exclude='.env.test' \
    --exclude='scripts/' \
    . "$DESTINATION:$APP_DIR"

# 2. Sync Secrets (specifically include .env.prod.local if it exists locally)
if [ -f .env.prod.local ]; then
    echo "Syncing .env.prod.local..."
    rsync -avz .env.prod.local "$DESTINATION:$APP_DIR/.env.prod.local"
fi

# 3. SSH and Build/Deploy
echo "Building and starting services..."
ssh "$DESTINATION" "mkdir -p $APP_DIR && cd $APP_DIR && if [ -f .env.prod.local ]; then sudo docker compose --env-file .env --env-file .env.prod.local -f compose.yaml -f compose.prod.yaml up -d --build --remove-orphans; else sudo docker compose -f compose.yaml -f compose.prod.yaml up -d --build --remove-orphans; fi"

# 4. Run Migrations
echo "Running migrations..."
ssh "$DESTINATION" "cd $APP_DIR && if [ -f .env.prod.local ]; then sudo docker compose --env-file .env --env-file .env.prod.local exec -T php bin/console doctrine:migrations:migrate --no-interaction; else sudo docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction; fi"

echo "Deployment complete!"
