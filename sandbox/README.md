# Machine SSH « bac à sable »

Une machine Linux minimale, ouverte au public par SSH (port 2222) et par un terminal web sur le portfolio. Elle est administrée depuis le panel : *Comptes et sécurité → Machine SSH* (super-administrateurs).

## Architecture

```
Internet ──2222──▶ sandbox-agent ──(réseau interne isolé)──▶ sandbox-ssh ──http(s)──▶ sandbox-egress ──▶ Internet (si activé)
Navigateur ─wss /ssh-ws (Traefik)─▶ sandbox-agent              │
Portfolio ──API (réseau interne « portfolio-sandbox-control »)─▶ sandbox-agent
sandbox-agent ──▶ docker-socket-proxy ──▶ Docker (conteneurs + réseaux uniquement)
```

- **sandbox-ssh** : Alpine, 0,25 CPU, 128 Mo de RAM sans swap, 64 processus, fichiers de 100 Mo au plus. Toutes les capacités sont retirées sauf celles dont sshd a besoin, avec `no-new-privileges`. Aucun binaire setuid, donc ni `su` ni `sudo`. Tunnels et transferts de ports sont désactivés.
- **Internet** : la machine n'est que sur un réseau `internal` et n'a aucune route vers l'extérieur. Elle sort uniquement par le proxy **sandbox-egress** (Squid), que l'agent rattache au réseau `portfolio-sandbox-egress` quand internet est activé depuis le panel. Les variables `http_proxy` et `https_proxy` sont définies dans chaque session : `curl`, `git`, `pip` et `apk` fonctionnent.
  - Seuls HTTP (80) et HTTPS (443) sont autorisés. SMTP, SSH sortant et connexions TCP directes sont impossibles, ce qui empêche spam et scans.
  - Destinations interdites : hôte, réseaux Docker et privés, métadonnées cloud (169.254.169.254).
  - Débit global limité (`SANDBOX_EGRESS_RATE`, 256 Ko/s par défaut), connexions simultanées limitées, aucun cache.
  - La machine n'est jamais sur les réseaux du portfolio ni de la base.
- **sandbox-agent** (Node) : seul point d'entrée.
  - Il relaie le port SSH public. Quand l'accès public est coupé, il ferme le relais et les sessions en cours.
  - Il limite les connexions : 2 sessions par IP, 10 au total, 10 connexions par minute et par IP.
  - Il sert le terminal web, avec un ticket signé par le portfolio, valable 1 minute et à usage unique, et ferme les sessions inactives (15 min) ou trop longues (60 min).
  - Il expose l'API de contrôle, protégée par jeton et refusée à la machine elle-même.
  - Il administre la machine en SSH avec sa propre clé : root est limité à la commande forcée `sandboxctl`.
- **Le panel est la source de vérité** (accès public, internet, comptes). L'agent conserve le dernier état et le réapplique dès que la machine redémarre.
- **Limites par compte** (réglables depuis le panel) : nombre de processus (`ulimit -u`), mémoire virtuelle par processus, taille des fichiers. Un visiteur qui sature ses ressources ne bloque pas les autres. Les comptes reçoivent des UID à partir de 200 000, car la limite de processus compte tous les processus de l'UID sur l'hôte.
- **Confidentialité** : dossiers personnels en 0700, `umask 077` et dossier temporaire personnel (`$TMPDIR`). En revanche, la liste des processus reste visible de tous : masquer `/proc` (`hidepid`) exigerait de donner `CAP_SYS_ADMIN` à la machine.
- **Nettoyage** : les processus laissés par un compte déconnecté (`nohup`, `&`) sont tués automatiquement.
- **Quota par compte** : un compte qui dépasse son quota est bloqué (sessions fermées, connexion refusée) jusqu'à ce que son espace redescende sous la limite.
- **Quota strict (facultatif)** : `/home` sur une image ext4 de taille fixe (`init-home-image.sh` + `docker-compose.quota.yml`). Le noyau refuse alors toute écriture au-delà.
- **Quota** : au-delà de `SANDBOX_QUOTA_MB` dans `/home`, l'accès public est suspendu jusqu'à ce que l'espace soit libéré (réinitialisation ou restauration depuis le panel).

## Installation (production, derrière Traefik)

```sh
cd sandbox
cp .env.example .env       # SANDBOX_API_TOKEN : openssl rand -hex 32 ; APP_DOMAIN
docker compose up -d --build
```

Côté portfolio (`.env` et `docker-compose.yml`) :

1. Ajoutez le réseau `portfolio-sandbox-control` (externe) au service `app`.
2. Renseignez `SANDBOX_API_URL=http://sandbox-agent:8080` et `SANDBOX_API_TOKEN` (même valeur que ci-dessus). Le port et l'adresse affichés aux visiteurs viennent de `sandbox/.env` (`SANDBOX_SSH_PUBLIC_PORT`, `SANDBOX_SSH_PUBLIC_HOST`, par défaut `APP_DOMAIN`) : l'agent les transmet au portfolio. `SANDBOX_SSH_PORT` et `SANDBOX_SSH_HOST` dans le `.env` du portfolio ne servent qu'à les forcer.
3. Ouvrez le port `SANDBOX_SSH_PUBLIC_PORT` (2222 par défaut) dans le pare-feu de l'hôte.

**Comptes de gestion (root)** : chaque super-administrateur peut créer depuis le panel un compte `adm-…`, à l'UID 0, authentifié uniquement par clé SSH.
- Connexion par un port dédié, toujours ouvert même quand l'accès public est fermé : `ssh -p 2224 adm-…@domaine` (`SANDBOX_SSH_MANAGER_PORT`). L'agent le relaie vers un second port sshd interne (2200).
- Le port public refuse ces comptes, et le port de gestion refuse les visiteurs et tout mot de passe.
- Ce sont des root *du conteneur* : capacités réduites, `no-new-privileges`, aucun accès à l'hôte ni au portfolio. Ils peuvent lire tous les dossiers des visiteurs et modifier le système jusqu'au prochain redémarrage.
- Un compte n'est appliqué que tant que son propriétaire est super-administrateur et actif. Il est retiré dans la minute qui suit, sessions fermées, dès que ce n'est plus le cas ou qu'il est révoqué.
- Clés acceptées : ed25519 (recommandé), ECDSA, clés matérielles `sk-`, RSA de 2048 bits au moins. DSA est refusé. Créer un compte ou ajouter une clé exige de reconfirmer son mot de passe.
- Chaque connexion root est inscrite au journal d'activité et signalée sur Discord (événement « Machine SSH »). Les bannissements et les limites de connexion s'appliquent aussi à ce port.

**Bannissements partagés** : les échecs d'authentification (SSH et terminal web) remontent au portfolio, qui applique ses règles habituelles (`LOGIN_BAN_*`). Les adresses bannies par le portfolio sont refusées par l'agent. Pour le SSH, l'agent retrouve l'IP réelle d'un échec grâce au port source de la connexion relayée, lu dans le journal de sshd. Les adresses privées, comme la passerelle Docker, ne sont jamais bannies. Les pays bloqués s'appliquent au terminal web, car le ticket est délivré par le portfolio, mais pas au port SSH direct.

Adresses IP réelles : le journal des connexions et les limites par IP utilisent l'adresse vue par l'agent. Avec le proxy « userland » de Docker (réglage par défaut), toutes les connexions SSH semblent venir de la passerelle Docker. Pour voir les vraies adresses, désactivez-le : `"userland-proxy": false` dans `/etc/docker/daemon.json`, puis redémarrez Docker. Le terminal web, lui, reçoit l'IP réelle par le ticket du portfolio.

Le terminal web passe par `https://APP_DOMAIN/ssh-ws` (routeur Traefik de l'agent, prioritaire sur le portfolio).

## Sauvegardes

Une sauvegarde est une archive des dossiers personnels (`/home`), stockée dans le volume de l'agent. Avec `SANDBOX_S3_*` (fournisseur compatible S3 : AWS, Backblaze B2, Scaleway, OVH, MinIO), chaque sauvegarde est aussi copiée hors du serveur. La copie distante est supprimée avec la sauvegarde. Un échec d'envoi est signalé dans le panel, sans annuler la sauvegarde locale. Le système est reconstruit depuis l'image, et les comptes depuis le panel. Une restauration ferme les sessions, remplace `/home` puis réapplique les comptes. Des sauvegardes automatiques quotidiennes peuvent être activées dans le panel.

## Surveillance

L'agent expose `GET /healthz` (sans jeton), utilisé par le `HEALTHCHECK` Docker. La machine a aussi le sien. Le portfolio relève l'état chaque minute et alerte sur Discord quand l'agent devient injoignable ou que la machine s'arrête.

## Tests

```sh
cd sandbox/agent && npm ci && npm test   # tests unitaires (tickets, limites, journal sshd, signature S3)
sandbox/tests/integration.sh             # pile Docker réelle : accès, isolation, internet filtré,
                                         # sauvegarde/restauration, quotas, bannissement, terminal web
```

Le test d'intégration démarre une pile dédiée sur des ports locaux (`SANDBOX_TEST_PORT_BASE`, 23000 par défaut), puis la supprime. Il nécessite docker, sshpass, node 22, openssl et curl. Le workflow GitHub `.github/workflows/sandbox.yml` lance les deux à chaque modification de `sandbox/`.
