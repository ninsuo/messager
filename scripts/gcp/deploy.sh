#!/bin/bash
set -e

# Usage: ./scripts/gcp/deploy-final.sh <ssh-host>
# Recommandé : ./scripts/gcp/deploy-final.sh messager-prod

if [ -z "$1" ]; then
    echo "❌ Usage: $0 <ssh-host>"
    exit 1
fi

DESTINATION="$1"
APP_DIR="~/messager"
# On utilise la config PROD standard
COMPOSE_FILES="-f compose.yaml -f compose.prod.yaml"

echo "🚀 DEPLOYMENT : Envoi vers $DESTINATION..."

# 1. Sync Orchestration Files
# We use a whitelist approach: only sync what's needed for docker-compose to run.
# --delete-excluded ensures that any files on the remote not matching our includes are removed.
echo "📦 Synchronisation des fichiers (Whitelist)..."
rsync -avz --delete --delete-excluded \
    --include='/compose.yaml' \
    --include='/compose.prod.yaml' \
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
    --exclude='*' \
    . "$DESTINATION:$APP_DIR"

# 2. Sync Secrets (Double check in case .env.prod.local was not in the main rsync)
if [ -f .env.prod.local ]; then
    echo "🔑 Vérification des secrets (.env.prod.local)..."
    rsync -avz .env.prod.local "$DESTINATION:$APP_DIR/.env.prod.local"
fi

# 3. SSH : Pull & Up
# On ajoute le mkdir -p par sécurité et on utilise --env-file proprement
echo "🐳 Pulling images and starting services..."
ssh "$DESTINATION" "mkdir -p $APP_DIR && cd $APP_DIR && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES pull && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES down --remove-orphans && \
    sudo docker volume rm messager_asset_data || true && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES up -d"

# 4. Run Migrations
# On garde les 15 secondes de 'bis' pour laisser MySQL respirer sur le disque de 50Go
echo "⏳ Attente de la base de données (15s)..."

sleep 15

# 4. Run Migrations
echo "💉 Exécution des migrations..."
ssh "$DESTINATION" "cd $APP_DIR && \
    sudo docker compose --env-file .env --env-file .env.prod.local $COMPOSE_FILES exec -T php bin/console doctrine:migrations:migrate --no-interaction"

echo "✨ Déploiement terminé avec succès sur $DESTINATION !"
echo "🌐 Vérifiez les logs de Caddy pour le certificat SSL :"
echo "   ssh $DESTINATION 'sudo docker compose logs -f caddy'"