#!/bin/bash
set -e

# --- Configuration ---
PROJECT_ID=$(gcloud config get-value project)
ZONE="europe-west9-a"
REGION="europe-west9"
INSTANCE_NAME="messager-vm"
TEMPLATE_NAME="messager-template-standard"
GROUP_NAME="messager-group"
DISK_NAME="messager-data"
MACHINE_TYPE="e2-medium"
IMAGE_PROJECT="ubuntu-os-cloud"
IMAGE_FAMILY="ubuntu-2204-lts"
STATIC_IP_NAME="messager-ip"
FIREWALL_RULE_NAME="allow-http-https"

echo "Provisioning Resilient Spot Infrastructure with Persistent Disk in $ZONE"

# 1. Create Static IP
if ! gcloud compute addresses describe $STATIC_IP_NAME --region=$REGION --quiet > /dev/null 2>&1; then
    echo "Creating Static IP: $STATIC_IP_NAME..."
    gcloud compute addresses create $STATIC_IP_NAME --region=$REGION
else
    echo "Static IP $STATIC_IP_NAME already exists."
fi

IP_ADDRESS=$(gcloud compute addresses describe $STATIC_IP_NAME --region=$REGION --format='get(address)')

# 2. Create Firewall Rules
if ! gcloud compute firewall-rules describe $FIREWALL_RULE_NAME --quiet > /dev/null 2>&1; then
    echo "Creating Firewall Rule: $FIREWALL_RULE_NAME..."
    gcloud compute firewall-rules create $FIREWALL_RULE_NAME \
        --allow tcp:80,tcp:443 \
        --target-tags=http-server,https-server
else
    echo "Firewall Rule $FIREWALL_RULE_NAME already exists."
fi

# 3. Create Persistent Disk (This disk will store your MySQL data)
if ! gcloud compute disks describe $DISK_NAME --zone=$ZONE --quiet > /dev/null 2>&1; then
    echo "Creating Persistent Disk: $DISK_NAME..."
    gcloud compute disks create $DISK_NAME --zone=$ZONE --size=10GB --type=pd-standard
else
    echo "Persistent Disk $DISK_NAME already exists."
fi

# 4. Create Instance Template
# We attach the 'messager-data' disk to the template
if ! gcloud compute instance-templates describe $TEMPLATE_NAME --quiet > /dev/null 2>&1; then
    echo "Creating Standard Instance Template: $TEMPLATE_NAME..."
    gcloud compute instance-templates create $TEMPLATE_NAME \
        --machine-type=$MACHINE_TYPE \
        --image-family=$IMAGE_FAMILY \
        --image-project=$IMAGE_PROJECT \
        --tags=http-server,https-server \
        --provisioning-model=STANDARD \
        --metadata-from-file startup-script=scripts/gcp/startup.sh \
        --boot-disk-size=20GB \
        --create-disk=name=$DISK_NAME,mode=rw,device-name=$DISK_NAME,auto-delete=no
else
    echo "Template $TEMPLATE_NAME already exists."
fi

# 5. Create Managed Instance Group
if ! gcloud compute instance-groups managed describe $GROUP_NAME --zone=$ZONE --quiet > /dev/null 2>&1; then
    echo "Creating Managed Instance Group: $GROUP_NAME..."
    gcloud compute instance-groups managed create $GROUP_NAME \
        --zone=$ZONE \
        --template=$TEMPLATE_NAME \
        --size=1

    # The group will automatically use the disk defined in the template.
    # Because auto-delete=no is set in the template, the data persists.
    echo "Managed Instance Group created."
else
    echo "Instance Group $GROUP_NAME already exists."
fi

echo "--------------------------------------------------------"
echo "Infrastructure setup complete!"
echo "Your Spot VM will auto-restart and KEEP ITS DATA."
echo "Reserved IP: $IP_ADDRESS"
echo "Data Disk: $DISK_NAME (Mounted at /mnt/data inside the VM)"
echo "--------------------------------------------------------"
