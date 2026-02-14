# 🚀 Provisionnement de l'Infrastructure Messager (GCP)

Ce document récapitule les étapes pour recréer les instances de **Production** et de **Secours (Bis)** à partir de zéro.

## 1. Configuration SSH Locale

Avant toute chose, configurer les alias dans `~/.ssh/config` pour simplifier les commandes.

```ssh
# ~/.ssh/config
Host messager-std-prod
    HostName <IP_PUBLIQUE_PROD>
    User ninsuo
    IdentityFile ~/.ssh/google_compute_engine

Host messager-std-bis
    HostName <IP_PUBLIQUE_BIS>
    User ninsuo
    IdentityFile ~/.ssh/google_compute_engine

```

---

## 2. Création des Ressources GCP

### Production (Zone A)

```bash
# Instance
gcloud compute instances create messager-std-prod \
    --zone=europe-west9-a \
    --machine-type=e2-standard-2 \
    --network-interface=network-tier=PREMIUM,subnet=default \
    --maintenance-policy=MIGRATE \
    --boot-disk-size=50GB \
    --boot-disk-type=pd-standard

# Disque de données
gcloud compute disks create messager-data --size=50GB --zone=europe-west9-a --type=pd-balanced
gcloud compute instances attach-disk messager-std-prod --disk=messager-data --zone=europe-west9-a

```

### Secours (Zone B)

```bash
# Instance
gcloud compute instances create messager-std-bis \
    --zone=europe-west9-b \
    --machine-type=e2-medium \
    --boot-disk-size=50GB

# Disque de données
gcloud compute disks create messager-data-bis --size=50GB --zone=europe-west9-b --type=pd-balanced
gcloud compute instances attach-disk messager-std-bis --disk=messager-data-bis --zone=europe-west9-b

```

---

## 3. Configuration de l'OS (Sur chaque instance)

Connectez-vous en SSH et lancez les installations de base :

```bash
# Mise à jour et outils système
sudo apt-get update && sudo apt-get install -y rsync cloud-guest-utils

# Installation de Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

```

### Préparation du Disque de Données

Si le disque est vierge :

```bash
# Formater le disque (Attention: seulement si vierge !)
sudo mkfs.ext4 -m 0 -E lazy_itable_init=0,lazy_journal_init=0,discard /dev/sdb

# Montage persistant
sudo mkdir -p /mnt/data
sudo mount /dev/sdb /mnt/data
sudo chmod 777 /mnt/data

# Ajout à fstab pour le reboot
echo '/dev/sdb /mnt/data ext4 defaults,nofail 0 2' | sudo tee -a /etc/fstab

```

---

## 4. Authentification Docker (Artifact Registry)

C'est l'étape cruciale pour permettre au `sudo docker compose` de télécharger les images privées.

```bash
# Authentifier l'utilisateur courant
gcloud auth configure-docker europe-west9-docker.pkg.dev

# Authentifier le compte ROOT (utilisé par le script de déploiement)
sudo gcloud auth configure-docker europe-west9-docker.pkg.dev

```

---

## 5. Maintenance des volumes (Resize)

Si vous augmentez la taille d'un disque dans la console GCP, lancez ces commandes pour refléter le changement dans l'OS :

**Pour le disque système (`/`) :**

```bash
sudo growpart /dev/sda 1
sudo resize2fs /dev/sda1

```

**Pour le disque de données (`/mnt/data`) :**

```bash
sudo resize2fs /dev/sdb

```

---

## 6. Premier Déploiement

Depuis votre machine locale :

```bash
# Déployer sur la prod
make deploy-prod

# Vérifier les logs Caddy (Certificat SSL)
ssh messager-std-prod "sudo docker compose -f ~/messager/compose.yaml -f ~/messager/compose.prod.yaml logs -f caddy"
```
