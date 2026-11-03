# Machine SSH « bac à sable » du portfolio : isolée, bridée, administrée par
# sandbox-agent (voir ../README.md). Aucun accès root pour les utilisateurs.
FROM alpine:3.20

RUN apk add --no-cache openssh-server openssh-sftp-server bash bash-completion jq tar coreutils procps shadow \
        nano vim less git curl wget htop tree file python3 \
    && addgroup -S sandbox \
    && addgroup -S sandboxadm \
    # Compte système « guest » d'Alpine : remplacé par le compte invité du bac à sable
    && deluser guest \
    && mkdir -p /etc/ssh/keys /etc/sandbox-agent /etc/sandbox/admin-keys /run/sshd \
    && rm -f /etc/motd /etc/profile.d/*.sh.apk-new \
    # Aucune élévation de privilèges possible (pas de setuid exploitable)
    && find / -xdev -perm /6000 -type f ! -path '/proc/*' -exec chmod a-s {} + 2>/dev/null || true

COPY sshd_config /etc/ssh/sshd_config
COPY sandboxctl /usr/local/sbin/sandboxctl
COPY entrypoint.sh /usr/local/sbin/entrypoint.sh
COPY agent-keys /usr/local/sbin/agent-keys
COPY session /usr/local/sbin/session
COPY motd /etc/motd
# Défis Linux copiés dans le dossier de chaque nouveau compte
COPY defis/build.sh /tmp/defis-build.sh
COPY defis/defis /usr/local/bin/defis
RUN sh /tmp/defis-build.sh && rm /tmp/defis-build.sh && chmod 0755 /usr/local/bin/defis
RUN chmod 0755 /usr/local/sbin/sandboxctl /usr/local/sbin/entrypoint.sh /usr/local/sbin/agent-keys /usr/local/sbin/session \
    && cp /etc/motd /etc/motd.default \
    && chmod 0644 /etc/motd /etc/motd.default /etc/ssh/sshd_config

EXPOSE 22 2200
HEALTHCHECK --interval=30s --timeout=5s CMD nc -z 127.0.0.1 22 || exit 1
ENTRYPOINT ["/usr/local/sbin/entrypoint.sh"]
