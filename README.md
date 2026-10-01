# Portfolio

Portfolio personnel construit sur Laravel 13 (Blade, Alpine.js, Tailwind CSS), avec un back-office complet.

## Fonctionnalités

**Site public** (responsive mobile / PC)
- **Accueil** : présentation (photo, accroche, texte « À propos », liens, CV), aperçus des compétences, projets et articles
- **Formations**, **Diplômes**, **Certifications**, **Compétences**
- **Projets** classés par thèmes (cartes avec image de fond). Chaque projet a un titre, une date, une description mise en forme avec l'éditeur de texte (comme les articles), des liens et dépôts GitHub, des fichiers (images en carrousel, PDF, Word, Excel, PowerPoint, LibreOffice, ZIP), une miniature, des compétences et des thèmes
- **Veille technologique** : articles épinglés en tête, puis triés du plus récent au plus ancien. Chaque article a un auteur, des co-auteurs, des thèmes, une miniature, des pièces jointes (images en carrousel, documents à télécharger, comme pour les projets) et un contenu mis en forme avec [Editor.js](https://github.com/codex-team/editor.js) (titres, listes, citations, code, tableaux, images, vidéos, couleurs, surlignage, alignement)
- **Contact** : nom, prénom, e-mail, objet, message et consentement obligatoire. Le formulaire est protégé par Google reCAPTCHA v3, un champ piège et une limite de débit. Chaque message est enregistré en base et notifié par e-mail

**Import / export** (*Administration → Import / export*) :
- **Export** : JSON de tout ou partie du contenu (présentation, pages, thèmes, compétences, formations, expériences, diplômes, certifications, loisirs, projets, articles, annonces). Il sert de sauvegarde ou de base de migration.
- **Import** en deux temps : une simulation détaille ce qui sera créé, mis à jour ou ignoré, puis l'import est confirmé. Un élément déjà présent (même slug, même titre…) est mis à jour et non dupliqué ; relancer un import est donc sans risque.
- **Format souple** (voir le **fichier modèle** téléchargeable) :
  - dates `2024`, `2024-09` ou `2024-09-15`, la précision étant déduite du format ;
  - thèmes et compétences désignés par leur nom, et créés s'ils manquent ;
  - auteurs désignés par leur e-mail ;
  - contenu d'article en HTML (`content_html`) accepté et converti en blocs Editor.js, avec récupération possible des images distantes.
- **Non inclus** : les fichiers (photos, images de fond, badges, pièces jointes).

**Éditeur visuel ou Markdown** (articles, projets et missions des expériences) : un sélecteur permet d'écrire avec l'éditeur visuel (Editor.js) ou en Markdown (GitHub Flavored Markdown), avec une barre de mise en forme et un aperçu en direct.
- Le contenu est converti automatiquement à chaque changement d'éditeur ; le choix d'éditeur et le texte Markdown saisi sont conservés.
- Le contenu est toujours enregistré au même format (blocs Editor.js), ce qui garantit un rendu identique sur le site quel que soit l'éditeur.
- Ce que Markdown ne sait pas exprimer (couleurs, surlignage, soulignement, alignement, options d'image, avertissements, vidéos) apparaît en HTML dans le Markdown et n'est jamais perdu. La conversion aller-retour est testée sur tous les types de blocs.

**Éditeur de texte (Editor.js)** : le contenu collé est conservé.
- Images collées (balise `<img>`, capture en base64, lien direct) : elles sont récupérées et hébergées sur le site. Le téléchargement est protégé contre les requêtes vers le réseau interne (SSRF).
- Vidéos intégrées (`<iframe>` YouTube, Vimeo, CodePen) : elles deviennent des blocs vidéo ; les autres iframes deviennent un lien.
- Listes imbriquées : leur structure est conservée.
- Galerie d'un projet ou d'un article : le bouton « Insérer dans le texte » place une image déjà envoyée dans le contenu.

**Annonces** : des bandeaux s'affichent sous le menu, sur toutes les pages publiques (recherche de stage ou d'alternance, disponibilité, actualité…). Depuis *Administration → Annonces*, chaque annonce se règle ainsi :
- titre, message et style (mise en avant, recherche / disponibilité, information, important) ;
- lien facultatif avec un bouton ;
- période de diffusion (début et fin programmables) et ordre d'affichage ;
- masquage possible par le visiteur. Une annonce masquée réapparaît si vous la modifiez.

**Visibilité des pages** : chaque page peut être rendue privée depuis *Administration → Pages & visibilité*. Une page privée :
- disparaît du menu et renvoie une erreur 404 aux visiteurs ;
- reste visible, avec un cadenas, pour les administrateurs connectés.

Les fichiers de projets sont servis par l'application et suivent la visibilité de la page « Projets ». Vous pouvez aussi renommer les pages, les réordonner et modifier leur introduction.

**Référencement** : *Administration → Référencement* permet de désindexer tout le site. Les pages reçoivent alors une balise `noindex` et toutes les réponses l'en-tête `X-Robots-Tag: noindex, nofollow`. Le `robots.txt`, généré dynamiquement, bloque les images publiques (`/storage/`) mais laisse les pages explorables, pour que les moteurs lisent la consigne et retirent les pages déjà indexées. L'administration et la connexion ne sont jamais indexées.

**SEO et performances** :
- `sitemap.xml` liste les pages publiques, les projets, les thèmes et les articles publiés (vide quand le site est désindexé) ; le `robots.txt` y renvoie ;
- chaque page publique porte une URL canonique, les balises Open Graph et Twitter, et des données structurées JSON-LD (`Person` sur l'accueil, `BlogPosting` sur les articles) ;
- les images hors écran et les vidéos intégrées sont chargées à la demande (`loading="lazy"`), l'image principale de chaque page est prioritaire (`fetchpriority="high"`) ; les polices ne bloquent plus l'affichage ; les scripts sont des modules (différés) et Editor.js n'est chargé que dans l'éditeur ;
- Apache compresse les réponses texte et met en cache les assets versionnés (`/build/assets/`, 1 an) et les fichiers publics (`/storage/`, 7 jours).

**Maintenance** : *Administration → Maintenance* met le site en maintenance, avec une date de désactivation automatique et un motif, tous deux facultatifs. Les visiteurs voient alors une page de maintenance (motif, date de retour, compte à rebours) à la place des pages et des fichiers. Elle est renvoyée avec un **statut HTTP 200** pour ne pas fausser la surveillance de disponibilité. Les administrateurs connectés continuent de naviguer et de modifier le site, et la page de connexion reste accessible. Les collaborateurs (contributeurs) conservent l'accès au panel d'administration pour la rédaction d'articles (avec leurs pièces jointes et « Mon compte ») ; le site public leur reste masqué.

**E-mails tolérants aux pannes** : une panne SMTP, une configuration `MAIL_*` invalide ou un compte de messagerie désactivé ne bloquent jamais le site. Dans ce cas :
- les messages de contact restent enregistrés et consultables dans l'admin, avec la mention « E-mail non envoyé » ;
- la réinitialisation de mot de passe affiche un message au lieu d'une erreur ;
- un délai SMTP court (`MAIL_TIMEOUT`) évite les pages bloquées ;
- le tableau de bord signale la dernière erreur d'envoi, et un bouton « Tester l'envoi d'e-mails » (page Messages) permet de vérifier la configuration.

**Rôles**
| Rôle | Droits |
|---|---|
| Super-administrateur | Tout, y compris la gestion des comptes |
| Administrateur | Gestion du contenu, accès aux pages privées |
| Contributeur | Accès à la rédaction d'articles uniquement : consulte tous les articles (brouillons compris), modifie ceux dont il est auteur ou co-auteur, crée des brouillons et les soumet à validation |

**Auteur des articles** : l'auteur principal peut être n'importe quel compte actif (personne, bot ou compte de service) ; les co-auteurs, qui obtiennent le droit de modifier l'article, sont toujours des personnes. Peuvent le changer :
- les administrateurs ;
- un contributeur, pour les articles dont il est l'auteur principal : il reste alors co-auteur ;
- les bots et les clients API disposant de l'autorisation `articles.write` (champs `author` et `coauthors` de l'API ; `author` accepte l'e-mail ou l'identifiant du compte).

Dans le formulaire, l'auteur et les co-auteurs se choisissent par auto-complétion (nom, identifiant ou e-mail).

**Double authentification** (*Mon compte → Double authentification*, comptes humains uniquement — jamais les bots ni les comptes de service) :
- **clés de sécurité** (WebAuthn / FIDO2 : YubiKey, Titan, empreinte ou visage de l'appareil…) et/ou **application d'authentification** (codes TOTP à 6 chiffres : Google Authenticator, Authy, 1Password…) ;
- à l'activation du premier facteur, 8 **codes de secours** à usage unique sont affichés une seule fois (seules leurs empreintes sont conservées) ; ils peuvent être régénérés ;
- à la connexion, le mot de passe est vérifié puis le second facteur demandé, **avant** l'ouverture de la session ; 5 erreurs bloquent l'étape 5 minutes ; tout est journalisé ;
- désactiver un facteur ou régénérer les codes demande le mot de passe actuel ; un super-administrateur peut réinitialiser la double authentification d'un compte (*Comptes → Modifier*) ;
- les clés de sécurité exigent le HTTPS (ou `localhost` en développement) et sont liées au nom de domaine du site.

**Comptes de service, bots et API** (*Sécurité → Comptes de service et bots*, super-administrateurs uniquement) :
- un compte de service n'accède jamais au panel : il utilise l'API `/api/v1` avec des **codes d'application** (`Authorization: Bearer pfs_…`) ;
- un **bot** se connecte au panel sur `/login/bot` avec un code d'application (jamais par mot de passe). Il ne voit que les sections couvertes par ses autorisations (menu « Sections autorisées »), n'a pas de page « Mon compte » et n'utilise pas l'API. Il garde l'accès au panel pendant une maintenance ; avec l'autorisation « Accéder aux pages publiques pendant une maintenance » (`maintenance.bypass`), il voit aussi le site public (hors pages privées) ;
- chaque code a un intitulé ; il n'est affiché qu'une fois, et seule son empreinte SHA-256 est conservée. Un code peut être **désactivé / réactivé**, ou **supprimé définitivement** en cas de compromission. L'effet est immédiat : l'API le refuse et toute session de bot ouverte avec ce code est coupée dès la requête suivante (de même si le compte est désactivé) ;
- chaque compte reçoit des autorisations précises, section par section du menu : **consulter**, **modifier** et **supprimer** pour la présentation, les formations, expériences, diplômes, certifications, compétences, loisirs, thèmes, projets, articles (plus « publier »), annonces, messages, pages, référencement, maintenance et comptes, ainsi que l'export et l'import. Toute requête hors autorisation est refusée et journalisée ;
- avec la seule consultation, les formulaires s'affichent en lecture seule et les boutons de création et de suppression sont masqués. Un bot autorisé sur les comptes ne gère que des **contributeurs** : il ne peut ni créer, ni modifier, ni supprimer un administrateur ;
- les autorisations marquées « bots uniquement » (sections sans route d'API) sont sans effet pour un compte de service ;
- l'API est limitée à 120 requêtes par minute et par code. La documentation des routes se trouve dans la page des comptes de service.

**Journal d'activité** (*Sécurité → Journal d'activité*, super-administrateurs) : toutes les opérations d'administration faites depuis le panel, l'API ou la console sont enregistrées :
- connexions, déconnexions, échecs et blocages ;
- créations, modifications (avec les champs avant / après) et suppressions ;
- validations d'articles, imports et exports, codes créés ou révoqués, refus d'accès à l'API, lectures de messages via l'API…

Les secrets ne sont jamais enregistrés et les actions du site public sont ignorées. Le journal ne peut être ni modifié ni supprimé depuis l'application.

**Validation des articles des contributeurs** :
- les articles créés par un contributeur restent en brouillon ; il les **soumet pour validation** quand ils sont prêts ;
- un administrateur les relit, puis les **publie** (immédiatement ou à une date programmée) ou les **renvoie en brouillon** avec un commentaire ;
- chaque étape déclenche un e-mail (soumission aux administrateurs, décision à l'auteur), et les articles à valider sont signalés dans le menu et sur le tableau de bord ;
- un contributeur ne peut ni publier, ni programmer, ni épingler, ni changer l'auteur principal, ni supprimer un article, ni créer de thème. Il peut corriger un article déjà publié dont il est auteur ou co-auteur, qui reste alors publié.

L'inscription publique est désactivée : les comptes se créent depuis l'administration.

## Démarrage (Docker, développement)

```bash
cp .env.example .env
docker compose -f docker-compose.dev.yml up -d --build
```

| Service | URL |
|---|---|
| Site | http://localhost:8120 |
| Administration | http://localhost:8120/admin |
| phpMyAdmin | http://localhost:8121 |
| Mailpit (e-mails reçus) | http://localhost:8032 |
| MariaDB (depuis l'hôte) | `localhost:3322` |

Les conteneurs, le réseau et les routeurs Traefik (production) sont nommés d'après le **dossier d'installation** (nom de projet Compose), par exemple `portfoliov2-app-1` pour le dossier `Portfolio v2`. Plusieurs copies du projet peuvent donc tourner côte à côte ; pensez seulement à leur attribuer des ports différents. Pour imposer un nom, définissez `COMPOSE_PROJECT_NAME` dans `.env`.

Les ports se modifient via `APP_PORT`, `PMA_PORT`, `DB_FORWARD_PORT`, `MAILPIT_UI_PORT` et `MAILPIT_SMTP_PORT` dans `.env`.

Les fichiers `docker-compose*.yml` utilisent **les mêmes noms de variables que Laravel** (`DB_DATABASE`, `MAIL_HOST`, `MAIL_SCHEME`…). Le fichier `.env` est donc l'unique source de configuration, pour l'application comme pour Docker.

## Production (`docker-compose.yml`)

La production **n'embarque pas de base de données**. L'application utilise un serveur MariaDB/MySQL centralisé, joignable via le réseau Docker externe **`db_internal`**. Le conteneur rejoint aussi le réseau externe `proxy` de Traefik.

Prérequis :
- les réseaux externes `db_internal` et `proxy` existent ;
- la base et l'utilisateur sont créés sur le serveur central.

Variables obligatoires (le démarrage est refusé sans elles) :

| Variable | Rôle |
|---|---|
| `DB_HOST` | Nom du conteneur (ou alias) du serveur central sur le réseau `db_internal` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Base et identifiants sur le serveur central |

Variables facultatives :
- `DB_PORT` (3306 par défaut) ;
- `DB_WAIT_TIMEOUT` : au démarrage, le conteneur attend jusqu'à ce délai (60 s par défaut) que la base réponde avant de lancer les migrations ;
- `APP_DOMAIN` : domaine routé par Traefik ;
- `APP_PORT` : port publié sur l'hôte.

L'image n'embarque pas `.env` : toutes les variables utiles à Laravel sont transmises par `docker-compose.yml`.

```bash
docker compose -f docker-compose.yml up -d --build
```

En développement, `docker-compose.dev.yml` fournit toujours son propre MariaDB, avec phpMyAdmin et Mailpit.

L'image de production compile elle-même les assets (étape Node du `Dockerfile`) : aucun `npm run build` n'est nécessaire sur le serveur. Laravel fait confiance aux en-têtes `X-Forwarded-*` de Traefik (réseaux Docker privés uniquement), ce qui lui permet de générer des URL en `https://`.

### Dépannage : accents mal encodés (« Ã© » au lieu de « é »)

Des textes importés depuis une source mal décodée (ancien site, fichier converti) peuvent contenir « Ã© », « â€™ »… La commande suivante les détecte dans tout le contenu et affiche un aperçu, sans rien modifier :

```bash
docker compose exec app php artisan portfolio:repair-encoding           # simulation
docker compose exec app php artisan portfolio:repair-encoding --apply   # réparation
```

La réparation est faite séquence par séquence : les accents corrects d'un même texte ne sont pas touchés. L'import JSON répare aussi automatiquement ces textes et le signale dans la simulation.

### Dépannage : « Bad Gateway » (502)

Traefik a trouvé le routeur (le certificat est émis), mais rien ne répond sur le port 80 du conteneur. Apache ne démarre qu'**après** l'attente de la base et les migrations : pendant cette phase, ou si le conteneur redémarre en boucle, Docker affiche « Up » alors que Traefik renvoie 502.

```bash
docker compose ps                        # « Restarting » ou « Up » depuis quelques secondes seulement ?
docker compose logs app --tail 50        # messages [entrypoint] : base injoignable, APP_KEY vide, migration en erreur…
curl -I http://127.0.0.1:${APP_PORT:-8089}    # l'application répond-elle en direct, sans Traefik ?
docker network inspect proxy --format '{{range .Containers}}{{.Name}} {{end}}'        # Traefik ET l'app doivent y figurer
docker network inspect db_internal --format '{{range .Containers}}{{.Name}} {{end}}'  # l'app ET le serveur de base
```

Causes fréquentes :
- `DB_HOST` ne correspond pas au nom du conteneur de base sur `db_internal` ;
- l'utilisateur MariaDB n'est pas autorisé depuis le réseau Docker (hôte `%`) : l'erreur est `[1045] Access denied for user '…'@'172.x.x.x'` ;
- le mot de passe contient `$` : dans `.env`, Docker Compose remplace `$…` par une variable. Entourez alors la valeur d'apostrophes simples (`DB_PASSWORD='mot$de$passe'`) ;
- `APP_KEY` est vide ;
- Traefik n'est pas relié au réseau `proxy`.

L'entrypoint distingue les erreurs définitives (identifiants refusés, base inexistante, droits manquants), signalées immédiatement avec une piste de correction, d'un serveur pas encore prêt, attendu jusqu'à `DB_WAIT_TIMEOUT`.

Création de l'utilisateur sur le serveur central :
```sql
CREATE DATABASE IF NOT EXISTS portfolio_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'portfolio'@'%' IDENTIFIED BY 'mot_de_passe';
ALTER USER 'portfolio'@'%' IDENTIFIED BY 'mot_de_passe';   -- si l'utilisateur existait déjà
GRANT ALL PRIVILEGES ON portfolio_db.* TO 'portfolio'@'%';
FLUSH PRIVILEGES;
```


Au démarrage, le conteneur :
- génère la clé de l'application si besoin ;
- applique les migrations ;
- crée le premier compte **`admin@app.local` / `password`** s'il n'existe encore aucun administrateur. **Changez ce mot de passe dès la première connexion** (*Mon compte*).

Le service `node` recompile les assets à chaque modification (`vite build --watch`).

**Données de démonstration** (facultatif) :
```bash
docker compose -f docker-compose.dev.yml exec -u www-data app php artisan db:seed --class=DemoSeeder
```

## Configuration

| Variable | Rôle |
|---|---|
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | Clés reCAPTCHA **v3** ([console Google](https://www.google.com/recaptcha/admin)) |
| `RECAPTCHA_MIN_SCORE` | Score minimal accepté (0.5 par défaut) |
| `APP_TIMEZONE` | Fuseau horaire des dates saisies et affichées (`Europe/Paris` par défaut) |
| `MAIL_TIMEOUT` | Délai maximal de connexion SMTP en secondes (5 par défaut) |
| `CONTACT_RECIPIENT` | Destinataire des messages. Par défaut : l'e-mail de la présentation, sinon `MAIL_FROM_ADDRESS` |

Sans clés reCAPTCHA, la vérification est **ignorée en local et en test** (avec un avertissement dans les logs) et **les envois sont refusés en production**.

Pour changer la couleur principale du site, modifiez `primary` / `accent` dans `tailwind.config.js`.

## Tests

```bash
docker compose -f docker-compose.dev.yml exec -u www-data -e HOME=/tmp app php artisan test
```

Lancez les tests dans le conteneur : au démarrage, celui-ci attribue `storage/` à `www-data`, que l'utilisateur de l'hôte ne peut plus écrire. Les tests utilisent toujours une base SQLite en mémoire (`force="true"` dans `phpunit.xml`), jamais la base de développement.

Les tests couvrent notamment la visibilité des pages, les accès à l'administration, le formulaire de contact et reCAPTCHA, les projets et leurs fichiers, les articles et le rendu sécurisé d'Editor.js.

## Architecture

| Élément | Emplacement |
|---|---|
| Visibilité des pages | `app/Http/Middleware/EnsurePageIsAccessible.php` (`->middleware('page:projets')`), table `pages` |
| Accès administration | `app/Http/Middleware/EnsureUserIsAdmin.php` |
| Rendu Editor.js → HTML | `app/Services/EditorJsRenderer.php`, directive Blade `@editorjs(...)`. Chaque bloc est rendu explicitement et le texte enrichi passe par HTMLPurifier |
| Vérification reCAPTCHA | `app/Services/Recaptcha.php` |
| Éditeur (JS) | `resources/js/editor.js`, chargé uniquement sur les pages qui en contiennent un |
| Carrousel, formulaire de contact | `resources/js/components/` |
| Vues publiques / admin | `resources/views/public/`, `resources/views/admin/` |

Stockage des fichiers :
- **disque `public`** : photo, CV, badges, fonds de thèmes, miniatures et illustrations d'articles ;
- **disque `local`** (privé) : pièces jointes des projets et des articles, servies par `ProjectFileController` et `ArticleFileController`. Elles suivent la visibilité de la page parente et, pour les articles, leur statut (brouillon, programmé). Le code est partagé via `IsAttachment` (modèles), `StoresAttachments` (upload) et `ServesAttachments` (envoi).
