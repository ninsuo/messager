#!/bin/bash
set -e

# 1. Nettoyage des résidus des tentatives précédentes
echo "🧹 Nettoyage des anciens dépôts..."
rm -f /etc/apt/sources.list.d/docker.list
rm -f /etc/apt/keyrings/docker.gpg

# 2. Configuration du SWAP (2 Go)
if [ ! -f /swapfile ]; then
  echo "🚀 Création du SWAP..."
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# 3. Détection dynamique de l'OS (Debian ou Ubuntu)
# On utilise /etc/os-release qui est toujours là
. /etc/os-release
OS_ID=$ID              # Sera 'debian' ou 'ubuntu'
OS_CODENAME=$VERSION_CODENAME  # Sera 'bookworm', 'jammy', etc.

echo "🔍 OS détecté : $OS_ID ($OS_CODENAME)"

# 4. Installation des pré-requis
apt-get update
apt-get install -y ca-certificates curl gnupg lsb-release

# 5. Configuration Docker avec la BONNE URL
mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/$OS_ID/gpg | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$OS_ID $OS_CODENAME stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

# 6. Installation finale
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin rsync
systemctl enable docker
systemctl start docker

# 7. Montage du disque de secours
DISK_PATH="/dev/disk/by-id/google-messager-data-bis"
MOUNT_POINT="/mnt/data"

if [ -b $DISK_PATH ]; then
  if ! blkid $DISK_PATH; then
    mkfs.ext4 -m 0 -E lazy_itable_init=0,lazy_journal_init=0,discard $DISK_PATH
  fi
  mkdir -p $MOUNT_POINT
  mount -o discard,defaults $DISK_PATH $MOUNT_POINT
  grep -qs "$MOUNT_POINT" /etc/fstab || echo "$DISK_PATH $MOUNT_POINT ext4 discard,defaults,nofail 0 2" >> /etc/fstab
fi

mkdir -p $MOUNT_POINT/mysql
chmod -R 777 $MOUNT_POINT/mysql

echo "✅ Tout est OK sur $OS_ID !"