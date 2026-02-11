#!/bin/bash
set -e

ZONE="europe-west9-a"
NEW_VM_NAME="messager-std-final"
MACHINE_TYPE="e2-medium"

echo "🎯 Tentative de réservation d'un slot Standard..."

gcloud compute instances create $NEW_VM_NAME \
    --zone=$ZONE \
    --machine-type=$MACHINE_TYPE \
    --provisioning-model=STANDARD \
    --tags=http-server,https-server \
    --metadata-from-file startup-script=scripts/gcp/startup.sh \
    --boot-disk-size=20GB

echo "✅ Slot Standard sécurisé sur $NEW_VM_NAME."
