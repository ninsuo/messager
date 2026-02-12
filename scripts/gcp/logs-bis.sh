#!/bin/bash
if [ -z "$1" ]; then
    HOST="messager-std-bis.europe-west9-b.messager-486910"
else
    HOST="$1"
fi

ssh "$HOST" "sudo docker compose -f ~/messager/compose.yaml -f ~/messager/compose.prod.yaml -f ~/messager/compose.bis.yaml logs -f --tail=100"
