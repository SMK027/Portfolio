#!/bin/sh
# Débit (octets/s) et connexions simultanées réglables par variables d'environnement.
set -e
export SANDBOX_EGRESS_RATE="${SANDBOX_EGRESS_RATE:-262144}"
export SANDBOX_EGRESS_MAX_CONNECTIONS="${SANDBOX_EGRESS_MAX_CONNECTIONS:-20}"
envsubst '$SANDBOX_EGRESS_RATE $SANDBOX_EGRESS_MAX_CONNECTIONS' < /etc/squid/squid.conf.template > /etc/squid/squid.conf
exec squid -N -d 1 -f /etc/squid/squid.conf
