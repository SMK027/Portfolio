# Sortie internet filtrée de la machine : seul chemin vers l'extérieur, et
# seulement quand l'agent le rattache au réseau de sortie (internet activé).
FROM alpine:3.20
RUN apk add --no-cache squid gettext \
    && mkdir -p /run/squid && chown squid:squid /run/squid
COPY squid.conf.template /etc/squid/squid.conf.template
COPY entrypoint.sh /entrypoint.sh
RUN chmod 0755 /entrypoint.sh
EXPOSE 3128
ENTRYPOINT ["/entrypoint.sh"]
