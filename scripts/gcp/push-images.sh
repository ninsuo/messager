#!/bin/bash
set -e

REGISTRY="europe-west9-docker.pkg.dev/messager-486910/messager-repo"
PLATFORM="linux/amd64"

# Create a buildx builder if it doesn't exist
if ! docker buildx inspect messager-builder > /dev/null 2>&1; then
  echo "🔧 Creating new buildx builder..."
  docker buildx create --name messager-builder --use
fi

echo "🚀 Building and pushing images with Buildx cache..."

# Helper function to build and push with cache
build_and_push() {
  local target="$1"
  local tag="$2"
  local dockerfile="$3"
  
  echo "📦 Building $tag (target: $target)..."
  docker buildx build \
    --platform "$PLATFORM" \
    --target "$target" \
    --cache-from "type=registry,ref=$REGISTRY/$tag:cache" \
    --cache-to "type=registry,ref=$REGISTRY/$tag:cache,mode=max" \
    -t "$REGISTRY/$tag:latest" \
    -f "$dockerfile" \
    --push .
}

# Build and Push PHP targets
build_and_push "base" "php-base" "docker/php/Dockerfile.prod"
build_and_push "worker" "php-worker" "docker/php/Dockerfile.prod"
build_and_push "cron" "php-cron" "docker/php/Dockerfile.prod"

# Build and Push Caddy
echo "📦 Building caddy..."
docker buildx build \
  --platform "$PLATFORM" \
  --cache-from "type=registry,ref=$REGISTRY/caddy:cache" \
  --cache-to "type=registry,ref=$REGISTRY/caddy:cache,mode=max" \
  -t "$REGISTRY/caddy:latest" \
  -f "docker/caddy/Dockerfile.prod" \
  --push .

echo "✅ All images built and pushed with cache!"
