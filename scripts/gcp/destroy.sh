#!/bin/bash

# --- Configuration (Must match provision.sh) ---
PROJECT_ID=$(gcloud config get-value project)
ZONE="europe-west9-a"
REGION="europe-west9"
TEMPLATE_NAME="messager-template"
GROUP_NAME="messager-group"
DISK_NAME="messager-data"
STATIC_IP_NAME="messager-ip"
FIREWALL_RULE_NAME="allow-http-https"

echo "!!! WARNING: DESTRUCTIVE ACTION !!!"
echo "This will DELETE the VM, the Instance Group, the Template, the Static IP, AND THE PERSISTENT DISK."
echo "All data in the database will be PERMANENTLY LOST."
read -p "Are you sure you want to continue? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

echo "Destroying infrastructure in Project: $PROJECT_ID, Zone: $ZONE"

# 1. Delete Managed Instance Group
# This will also stop and delete the running VM instance
if gcloud compute instance-groups managed describe $GROUP_NAME --zone=$ZONE --quiet > /dev/null 2>&1; then
    echo "Deleting Managed Instance Group: $GROUP_NAME..."
    gcloud compute instance-groups managed delete $GROUP_NAME --zone=$ZONE --quiet
else
    echo "Instance Group $GROUP_NAME not found."
fi

# 2. Delete Instance Template
if gcloud compute instance-templates describe $TEMPLATE_NAME --quiet > /dev/null 2>&1; then
    echo "Deleting Instance Template: $TEMPLATE_NAME..."
    gcloud compute instance-templates delete $TEMPLATE_NAME --quiet
else
    echo "Template $TEMPLATE_NAME not found."
fi

# 3. Delete Persistent Disk
if gcloud compute disks describe $DISK_NAME --zone=$ZONE --quiet > /dev/null 2>&1; then
    echo "Deleting Persistent Disk: $DISK_NAME..."
    gcloud compute disks delete $DISK_NAME --zone=$ZONE --quiet
else
    echo "Disk $DISK_NAME not found."
fi

# 4. Delete Static IP
if gcloud compute addresses describe $STATIC_IP_NAME --region=$REGION --quiet > /dev/null 2>&1; then
    echo "Deleting Static IP: $STATIC_IP_NAME..."
    gcloud compute addresses delete $STATIC_IP_NAME --region=$REGION --quiet
else
    echo "Static IP $STATIC_IP_NAME not found."
fi

# 5. Delete Firewall Rules
if gcloud compute firewall-rules describe $FIREWALL_RULE_NAME --quiet > /dev/null 2>&1; then
    echo "Deleting Firewall Rule: $FIREWALL_RULE_NAME..."
    gcloud compute firewall-rules delete $FIREWALL_RULE_NAME --quiet
else
    echo "Firewall Rule $FIREWALL_RULE_NAME not found."
fi

echo "--------------------------------------------------------"
echo "Destruction complete. All resources have been removed."
echo "--------------------------------------------------------"
