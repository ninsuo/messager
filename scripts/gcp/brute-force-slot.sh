#!/bin/bash

# --- Configuration ---
ZONE="europe-west9-a"
NEW_VM_NAME="messager-std-final"
MACHINE_TYPE="e2-standard-2"
ATTEMPT=1

echo "🚀 Lancement du mode Brute-Force pour le slot Standard..."
echo "Zone: $ZONE | Machine: $MACHINE_TYPE"

while true; do
    echo "------------------------------------------"
    echo "🕒 Tentative n°$ATTEMPT - $(date +%H:%M:%S)"

    # On tente la création
    # On redirige les erreurs vers un fichier pour ne pas polluer le terminal
    if gcloud compute instances create $NEW_VM_NAME \
        --zone=$ZONE \
        --machine-type=$MACHINE_TYPE \
        --provisioning-model=STANDARD \
        --tags=http-server,https-server \
        --metadata-from-file startup-script=scripts/gcp/startup.sh \
        --boot-disk-size=20GB 2> last_error.txt; then

        echo -e "\n\a" # Petit bip sonore du terminal
        echo "✅ SUCCÈS ! Le slot a été sécurisé sur $NEW_VM_NAME."
        echo "👉 Tu peux maintenant lancer 'sh scripts/gcp/switch-data.sh'."
        exit 0
    else
        # On vérifie si c'est bien une erreur de stock
        if grep -q "ZONE_RESOURCE_POOL_EXHAUSTED" last_error.txt; then
            echo "❌ Zone toujours saturée. Pause de 30s..."
        else
            echo "⚠️  Autre erreur détectée :"
            cat last_error.txt
            echo "Arrêt par sécurité."
            exit 1
        fi
    fi

    ATTEMPT=$((ATTEMPT + 1))
    sleep 30
done