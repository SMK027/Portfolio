#!/bin/sh
# Crée l'image disque ext4 de taille fixe qui sert de /home à la machine :
# le noyau refuse toute écriture au-delà de sa taille (quota strict).
#
#   sudo ./init-home-image.sh /srv/portfolio-sandbox/home.img 512M
#   puis, dans .env : SANDBOX_HOME_IMAGE=/srv/portfolio-sandbox/home.img
#   et : docker compose -f docker-compose.yml -f docker-compose.quota.yml up -d
set -eu
IMAGE="${1:?chemin de l'image attendu}"
SIZE="${2:-512M}"

if [ -e "$IMAGE" ]; then
    echo "$IMAGE existe déjà : rien à faire (supprimez-le pour le recréer, après sauvegarde)." >&2
    exit 1
fi
mkdir -p "$(dirname "$IMAGE")"
truncate -s "$SIZE" "$IMAGE"
mkfs.ext4 -q -m 0 -L sandbox-home "$IMAGE"
chmod 600 "$IMAGE"
echo "Image créée : $IMAGE ($SIZE)"
