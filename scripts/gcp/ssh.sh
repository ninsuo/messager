#!/bin/bash
set -e

gcloud compute ssh $(gcloud compute instances list --filter="name~messager-group" --format="value(name)") --zone=europe-west9-a
