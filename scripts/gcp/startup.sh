#!/bin/bash
set -e

# 1. Update and install dependencies
apt-get update
apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common

# 2. Set up Docker
mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
systemctl enable docker
systemctl start docker

# 3. Mount Persistent Disk
# The disk is attached as 'messager-data' (see provision.sh)
DISK_PATH="/dev/disk/by-id/google-messager-data"
MOUNT_POINT="/mnt/data"

# Wait for disk to be attached
while [ ! -b $DISK_PATH ]; do
  echo "Waiting for disk $DISK_PATH..."
  sleep 5
done

# Format disk if it doesn't have a filesystem
if ! blkid $DISK_PATH; then
  echo "Formatting disk $DISK_PATH..."
  mkfs.ext4 -m 0 -E lazy_itable_init=0,lazy_journal_init=0,discard $DISK_PATH
fi

# Create mount point and mount
mkdir -p $MOUNT_POINT
mount -o discard,defaults $DISK_PATH $MOUNT_POINT

# Add to fstab if not already there
if ! grep -qs "$MOUNT_POINT" /etc/fstab; then
  echo "$DISK_PATH $MOUNT_POINT ext4 discard,defaults,nofail 0 2" >> /etc/fstab
fi

# Ensure permissions for the data directory (for docker containers)

mkdir -p $MOUNT_POINT/mysql

chmod -R 777 $MOUNT_POINT/mysql
