# GCP Deployment Scripts

This directory contains scripts to provision a resilient, cost-effective infrastructure on Google Cloud Platform and deploy your Symfony application.

## Architecture Overview

- **Spot VM (e2-medium)**: Saves ~70% on costs.
- **Managed Instance Group (MIG)**: Automatically restarts the VM if Google reclaims it.
- **Persistent Disk (10GB)**: A separate disk ensures your MySQL database survives VM recreations.
- **Automatic Startup**: Installs Docker, PHP 8.4, and mounts the data disk to `/mnt/data` automatically on boot.

## Prerequisites

1.  **Google Cloud SDK (`gcloud`)**: Installed and initialized (`gcloud init`).
2.  **SSH Access**: Run `gcloud compute config-ssh` once to register your keys with the project.
3.  **Project Selection**: Ensure you are in the correct project: `gcloud config set project <PROJECT_ID>`.

## Usage

### 1. Provision Infrastructure

Creates the Static IP, Firewall rules, Persistent Disk, and the Managed VM.

```bash
chmod +x scripts/gcp/*.sh
./scripts/gcp/provision.sh
```

Wait ~2-3 minutes after this script finishes for the VM to complete its internal setup (installing Docker and PHP).

### 2. Configure Secrets

We use Symfony's `.env` conventions. Docker Compose is configured to load secrets from `.env.prod.local` on the server.

1.  SSH into the VM:
    ```bash
    gcloud compute ssh $(gcloud compute instances list --filter="name~messager-group" --format="value(name)") --zone=europe-west9-a
    ```
2.  Create the secrets file:
    ```bash
    mkdir -p ~/messager
    nano ~/messager/.env.prod.local
    ```
3.  Add your production values:
    ```env
    APP_ENV=prod
    APP_SECRET=your_secret
    MYSQL_ROOT_PASSWORD=your_root_password
    MYSQL_PASSWORD=your_app_password
    DATABASE_URL="mysql://messager:your_app_password@mysql:3306/messager?serverVersion=8.0.32&charset=utf8mb4"
    ...
    ```

### 3. Deploy Application

Syncs code, builds containers, and runs migrations.

```bash
# Get the hostname/IP from gcloud compute instances list
./scripts/gcp/deploy.sh <user>@<ip_address>
# OR if using config-ssh:
./scripts/gcp/deploy.sh messager-group-xxxx.europe-west9-a.<project-id>
```

### 4. Initialize the first admin user

```bash
ssh messager-group-xxxx.europe-west9-a.<project-id>
sudo docker exec -ti messager-php-1 php bin/console user:create <your phone number> --admin
```

### 5. Cleanup (Destroy)

To delete all resources (VM, Disk, IP, Template) and stop being billed:

```bash
./scripts/gcp/destroy.sh
```

## Maintenance & Debugging

- **Host Tools**: The VM has `mysql-client` and `php8.4-cli` installed directly on the host for convenience.
- **Logs**: Check container logs with `sudo docker compose logs -f`.
- **Startup Errors**: If Docker isn't working on a fresh VM, check the startup script logs:
  `sudo journalctl -u google-startup-scripts.service`
- **DNS**: Point your `A` records for `messager.org` and `www.messager.org` to the Static IP provided by the provision script. Caddy will handle SSL automatically.