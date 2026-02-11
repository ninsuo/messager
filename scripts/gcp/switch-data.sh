#!/bin/bash
set -e

ZONE="europe-west9-a"
DISK_NAME="messager-data"
NEW_VM_NAME="messager-std-final"
IP_NAME="messager-ip"

# 1. On trouve l'ancienne instance (Spot)
OLD_VM=$(gcloud compute instances list --filter="name~messager-group" --format="value(name)")

echo "🛑 Arrêt de l'ancien monde ($OLD_VM)..."
gcloud compute instance-groups managed resize messager-group --size=0 --zone=$ZONE

echo "🔌 Bascule de l'IP statique..."
gcloud compute instances delete-access-config $OLD_VM --zone=$ZONE --access-config-name="external-nat" || true
gcloud compute instances add-access-config $NEW_VM_NAME --zone=$ZONE --address=$IP_NAME

echo "🔗 Transfert du disque de données..."
gcloud compute instances attach-disk $NEW_VM_NAME \
    --disk=$DISK_NAME \
    --zone=$ZONE \
    --device-name=$DISK_NAME

echo "🚀 Redémarrage de la nouvelle instance..."
gcloud compute instances reset $NEW_VM_NAME --zone=$ZONE

echo "✅ Migration terminée ! L'app va remonter sur la VM Standard."