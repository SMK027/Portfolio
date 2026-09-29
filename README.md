# Portfolio

Portfolio personnel construit sur Laravel 13 (Blade, Alpine.js, Tailwind CSS), avec un back-office complet.

## Fonctionnalités

**Site public** (responsive mobile / PC)
- **Accueil** : présentation (photo, accroche, texte « À propos », liens, CV), aperçus des compétences, projets et articles
- **Formations**, **Diplômes**, **Certifications**, **Compétences**
- **Projets** classés par thèmes (cartes avec image de fond). Chaque projet a un titre, une date, une description, des liens et dépôts GitHub, des fichiers (images en carrousel, PDF, Word, Excel, PowerPoint, LibreOffice), une miniature, des compétences et des thèmes
- **Veille technologique** : articles épinglés en tête, puis triés du plus récent au plus ancien. Chaque article a un auteur, des co-auteurs, des thèmes, une miniature, des pièces jointes (images en carrousel, documents à télécharger, comme pour les projets) et un contenu mis en forme avec [Editor.js](https://github.com/codex-team/editor.js) (titres, listes, citations, code, tableaux, images, vidéos, couleurs, surlignage, alignement)
- **Contact** : nom, prénom, e-mail, objet, message et consentement obligatoire. Le formulaire est protégé par Google reCAPTCHA v3, un champ piège et une limite de débit. Chaque message est enregistré en base et notifié par e-mail

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

**Maintenance** : *Administration → Maintenance* met le site en maintenance, avec une date de désactivation automatique et un motif, tous deux facultatifs. Les visiteurs voient alors une page de maintenance (motif, date de retour, compte à rebours) à la place des pages et des fichiers. Elle est renvoyée avec un **statut HTTP 200** pour ne pas fausser la surveillance de disponibilité. Les administrateurs connectés continuent de naviguer et de modifier le site, et la page de connexion reste accessible.

**Rôles**
| Rôle | Droits |
|---|---|
| Super-administrateur | Tout, y compris la gestion des comptes |
| Administrateur | Gestion du contenu, accès aux pages privées |
| Contributeur | Peut être auteur ou co-auteur d'un article, sans accès à l'administration |

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

Les ports se modifient via `APP_PORT`, `PMA_PORT`, `DB_FORWARD_PORT`, `MAILPIT_UI_PORT` et `MAILPIT_SMTP_PORT` dans `.env`.

Au démarrage, le conteneur :
- génère la clé de l'application si besoin ;
- applique les migrations ;
- crée le premier compte **`admin@app.local` / `password`** s'il n'existe encore aucun administrateur. **Changez ce mot de passe dès la première connexion** (*Mon compte*).

Le conteneur `portfolio_node` recompile les assets à chaque modification (`vite build --watch`).

**Données de démonstration** (facultatif) :
```bash
docker exec -u www-data portfolio_web php artisan db:seed --class=DemoSeeder
```

## Configuration

| Variable | Rôle |
|---|---|
| `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY` | Clés reCAPTCHA **v3** ([console Google](https://www.google.com/recaptcha/admin)) |
| `RECAPTCHA_MIN_SCORE` | Score minimal accepté (0.5 par défaut) |
| `APP_TIMEZONE` | Fuseau horaire des dates saisies et affichées (`Europe/Paris` par défaut) |
| `CONTACT_RECIPIENT` | Destinataire des messages. Par défaut : l'e-mail de la présentation, sinon `MAIL_FROM_ADDRESS` |

Sans clés reCAPTCHA, la vérification est **ignorée en local et en test** (avec un avertissement dans les logs) et **les envois sont refusés en production**.

Pour changer la couleur principale du site, modifiez `primary` / `accent` dans `tailwind.config.js`.

## Tests

```bash
docker exec -u www-data -e HOME=/tmp portfolio_web php artisan test
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
