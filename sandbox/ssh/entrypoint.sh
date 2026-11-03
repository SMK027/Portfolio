#!/bin/sh
# Clés d'hôte persistantes (volume) : pas d'alerte « host key changed » après un redémarrage.
set -e
for type in ed25519 rsa; do
    [ -f "/etc/ssh/keys/ssh_host_${type}_key" ] || ssh-keygen -q -t "$type" -N '' -f "/etc/ssh/keys/ssh_host_${type}_key"
done
chmod 600 /etc/ssh/keys/*_key
# Journal d'authentification lu (puis vidé) par l'agent : échecs rattachés à l'IP réelle du visiteur.
exec /usr/sbin/sshd -D -E /run/sshd-auth.log
