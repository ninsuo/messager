# Messager - Gemini CLI Project Context

This file contains core project information and deployment instructions for the Gemini CLI agent.

## Project Overview

- **Stack**: PHP 8.4, Symfony 8.0, Doctrine ORM 3.x
- **Database**: MySQL 8.0 (utf8mb4)
- **Web Server**: Caddy (Production uses HTTPS via Let's Encrypt)
- **Messaging**: Twilio (SMS and Voice)
- **Frontend**: Asset Mapper, Stimulus 3.2, Turbo 7.3, Bootstrap 5.3

## GCP Deployment (Production)

The application is deployed on Google Cloud Platform using a cost-optimized, resilient architecture.

### Architecture
- **Region**: `europe-west9` (Paris, France)
- **Instance**: `e2-medium` (Spot VM) - managed by a **Managed Instance Group (MIG)** for auto-restart.
- **Data Persistence**: 10GB Persistent Disk mounted at `/mnt/data` on the VM.
- **Docker**: Production stack runs via `docker compose -f compose.yaml -f compose.prod.yaml`.
- **Static IP**: Reserved static IP (`34.155.205.113`) used for `messager.org` and `www.messager.org`.

### Deployment Scripts (`scripts/gcp/`)
- `provision.sh`: Creates the full GCP infrastructure (IP, Firewall, Disk, Template, Instance Group).
- `deploy.sh`: Prepares assets locally, syncs code/secrets, builds containers on the VM, and runs migrations.
- `destroy.sh`: Deletes all GCP resources (Warning: Deletes data disk!).
- `startup.sh`: Automated VM setup (Installs Docker, PHP 8.4, MySQL client, mounts disk).

### Secret Management
Secrets are stored in `~/messager/.env.prod.local` on the VM. This file is synced from the local machine during `deploy.sh` (even if ignored by git) and is used by Docker Compose via the `--env-file` flag.

## Key Development Commands

Run these inside Docker to ensure environment parity:

```bash
# Build and Run
docker compose up -d

# Verification & Quality
docker compose exec php php bin/console lint:container
docker compose exec php vendor/bin/phpstan analyse src/ --level=6
docker compose exec php vendor/bin/phpunit

# Database
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console doctrine:fixtures:load -n
```

## Production Deployment Workflow

1.  **Configure SSH**: `gcloud compute config-ssh`
2.  **Verify Host**: Check current instance name with `gcloud compute instances list --filter="name~messager-group"`.
3.  **Deploy**: `./scripts/gcp/deploy.sh <hostname>`

## Frontend Conventions
- Stimulus controllers in `assets/controllers/`.
- Assets compiled via `php bin/console asset-map:compile`.
- CSS custom properties prefixed with `--ms-`.

## Language Policy
- **User Interface**: French (templates, labels, messages).
- **Code & Docs**: English (variables, comments, commit messages).
