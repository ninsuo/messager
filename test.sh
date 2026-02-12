#!/bin/bash

# --- Configuration ---
ZONE="europe-west9-a"
VM_NAME="messager-std-final"
# On utilise start car l'instance existe déjà (on a juste changé son type)
# Si tu veux créer une NOUVELLE instance, remplace 'start' par 'create' dans la commande plus bas.
ATTEMPT=1
WAIT_TIME=5

echo "🚀 Mode Survie Activé : Harcèlement du pool de ressources Google..."
echo "Cible: $VM_NAME | Zone: $ZONE"
echo "------------------------------------------"

# Fonction pour le compte à rebours visuel
countdown() {
    local seconds=$1
    while [ $seconds -gt 0 ]; do
        printf "\r⏳ Prochaine tentative dans %2d secondes... " $seconds
        sleep 1
        : $((seconds--))
    done
    printf "\r" # Nettoie la ligne
}

while true; do
    echo -e "\n🕒 Tentative n°$ATTEMPT - $(date +%H:%M:%S)"

    # Tentative de START (puisque l'instance est déjà configurée)
    # On capture l'erreur pour analyse
    if gcloud compute instances start $VM_NAME --zone=$ZONE 2> last_error.txt; then
        echo -e "\n\a" # Bip sonore !
        echo "✅ VICTOIRE ! L'instance $VM_NAME a démarré."
        echo "👉 Tu peux maintenant te connecter : ssh $VM_NAME"
        exit 0
    else
        # Analyse de l'erreur
        if grep -q "ZONE_RESOURCE_POOL_EXHAUSTED" last_error.txt; then
            echo "❌ Parking complet (ZONE_RESOURCE_POOL_EXHAUSTED)."
        elif grep -q "already running" last_error.txt; then
            echo "ℹ️  L'instance tourne déjà !"
            exit 0
        else
            echo "⚠️  Erreur inattendue :"
            cat last_error.txt
        fi
    fi

    ATTEMPT=$((ATTEMPT + 1))
    countdown $WAIT_TIME
done
