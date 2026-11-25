<?php

namespace App\Achievements;

/**
 * Déclencheurs codés des succès. Un succès (table « achievements ») choisit un
 * déclencheur et un seuil : il se débloque quand le compteur du visiteur l'atteint.
 *
 * - « distinct » : le compteur est le nombre de valeurs différentes vues (pages, jours…) ;
 * - « count » : simple compteur d'actions.
 */
final class Triggers
{
    public const CATEGORIES = [
        'exploration' => ['Exploration', 'globe'],
        'interaction' => ['Interaction', 'sparkles'],
        'secret'      => ['Secrets', 'lock'],
        'loyalty'     => ['Fidélité', 'heart'],
        'sandbox'     => ['Machine SSH', 'terminal'],
    ];

    /** [libellé, catégorie suggérée, type, unité du seuil] */
    public const ALL = [
        'sections_visited'    => ['Sections différentes visitées', 'exploration', 'distinct', 'sections'],
        'projects_viewed'     => ['Projets différents consultés', 'exploration', 'distinct', 'projets'],
        'articles_viewed'     => ['Articles de veille différents lus', 'exploration', 'distinct', 'articles'],
        'search_used'         => ['Recherches effectuées', 'interaction', 'count', 'recherches'],
        'palette_opened'      => ['Ouverture de la palette (Ctrl+K)', 'interaction', 'count', 'ouvertures'],
        'theme_toggled'       => ['Changements de thème clair / sombre', 'interaction', 'count', 'changements'],
        'article_reacted'     => ['Articles différents ayant reçu une réaction', 'interaction', 'distinct', 'articles'],
        'contact_sent'        => ['Messages de contact envoyés', 'interaction', 'count', 'messages'],
        'appointment_booked'  => ['Demandes de rendez-vous', 'interaction', 'count', 'demandes'],
        'cv_downloaded'       => ['Téléchargements du CV', 'interaction', 'count', 'téléchargements'],
        'terminal_opened'     => ['Ouvertures du mode terminal', 'interaction', 'count', 'ouvertures'],
        'easter_egg_found'    => ['Accès à la page secrète', 'secret', 'count', 'accès'],
        'easter_eggs_unlocked' => ['Éléments secrets déverrouillés', 'secret', 'distinct', 'éléments'],
        'night_visit'         => ['Visite entre minuit et 5 h', 'secret', 'count', 'pages vues'],
        'achievements_page'   => ['Consultation de la page des succès', 'secret', 'count', 'consultations'],
        'sandbox_terminal_opened' => ['Connexions au terminal web de la machine SSH', 'sandbox', 'count', 'connexions'],
        'sandbox_challenges'  => ['Défis Linux de la machine SSH validés', 'sandbox', 'distinct', 'défis'],
        'visit_days'          => ['Jours de visite différents', 'loyalty', 'distinct', 'jours'],
        'minutes_spent'       => ['Minutes passées sur le site', 'loyalty', 'count', 'minutes'],
        'achievements_unlocked' => ['Autres succès débloqués', 'loyalty', 'total', 'succès'],
    ];

    /** Événements que le navigateur peut signaler lui-même (sans effet de bord). */
    public const CLIENT_EVENTS = ['palette_opened', 'theme_toggled', 'minutes_spent'];

    public static function options(): array
    {
        return array_map(fn (array $t) => $t[0], self::ALL);
    }

    public static function label(string $trigger): string
    {
        return self::ALL[$trigger][0] ?? $trigger;
    }

    public static function unit(string $trigger): string
    {
        return self::ALL[$trigger][3] ?? '';
    }

    public static function isDistinct(string $trigger): bool
    {
        return (self::ALL[$trigger][2] ?? null) === 'distinct';
    }

    /** Succès de la machine SSH (ajoutés par la migration 2026_11_25_000002). */
    public static function sandboxDefaults(): array
    {
        $row = fn ($title, $description, $icon, $trigger, $threshold, $secret = false) => compact('title', 'description', 'icon', 'trigger', 'threshold') + ['category' => 'sandbox', 'is_secret' => $secret];

        return [
            $row('Bienvenue à bord', 'Se connecter à la machine SSH depuis le navigateur.', 'terminal', 'sandbox_terminal_opened', 1),
            $row('Habitué du shell', 'Se connecter 5 fois au terminal web.', 'clock', 'sandbox_terminal_opened', 5),
            $row('Premier défi', 'Valider un défi Linux de la machine SSH.', 'check', 'sandbox_challenges', 1),
            $row('Apprenti administrateur', 'Valider 3 défis Linux.', 'academic-cap', 'sandbox_challenges', 3),
            $row('Maître du shell', 'Valider les 5 défis Linux.', 'trophy', 'sandbox_challenges', 5),
        ];
    }

    /** Succès créés à l'installation (modifiables ensuite en administration). */
    public static function defaults(): array
    {
        $row = fn ($title, $description, $icon, $category, $trigger, $threshold = 1, $secret = false) => compact('title', 'description', 'icon', 'category', 'trigger', 'threshold') + ['is_secret' => $secret];

        return [
            $row('Premiers pas', 'Visiter 3 sections différentes du portfolio.', 'home', 'exploration', 'sections_visited', 3),
            $row('Grand explorateur', 'Visiter 8 sections différentes du portfolio.', 'globe', 'exploration', 'sections_visited', 8),
            $row('Curieux', 'Consulter un projet.', 'folder', 'exploration', 'projects_viewed', 1),
            $row('Recruteur averti', 'Consulter 5 projets différents.', 'briefcase', 'exploration', 'projects_viewed', 5),
            $row('Lecteur', 'Lire un article de veille.', 'newspaper', 'exploration', 'articles_viewed', 1),
            $row('Rat de bibliothèque', 'Lire 5 articles de veille différents.', 'academic-cap', 'exploration', 'articles_viewed', 5),
            $row('Fouineur', 'Utiliser la recherche.', 'search', 'interaction', 'search_used', 1),
            $row('Raccourci clavier', 'Ouvrir la palette avec Ctrl+K.', 'squares', 'interaction', 'palette_opened', 1),
            $row('Côté obscur', 'Changer de thème clair / sombre.', 'moon', 'interaction', 'theme_toggled', 1),
            $row('Réactif', 'Réagir à un article.', 'heart', 'interaction', 'article_reacted', 1),
            $row('Prise de contact', 'Envoyer un message.', 'mail', 'interaction', 'contact_sent', 1),
            $row('Rendez-vous pris', 'Demander un rendez-vous.', 'calendar', 'interaction', 'appointment_booked', 1),
            $row('Dossier complet', 'Télécharger le CV.', 'download', 'interaction', 'cv_downloaded', 1),
            $row('Hacker', 'Ouvrir le mode terminal.', 'terminal', 'interaction', 'terminal_opened', 1),
            $row('Chasseur de secrets', 'Trouver la page secrète.', 'sparkles', 'secret', 'easter_egg_found', 1, true),
            $row('Perceur de codes', 'Déverrouiller un élément secret.', 'lock', 'secret', 'easter_eggs_unlocked', 1, true),
            $row('Oiseau de nuit', 'Visiter le site entre minuit et 5 h.', 'moon', 'secret', 'night_visit', 1, true),
            $row('Méta', 'Consulter la page des succès.', 'trophy', 'secret', 'achievements_page', 1, true),
            $row('De retour', 'Revenir sur le site un autre jour.', 'clock', 'loyalty', 'visit_days', 2),
            $row('Habitué', 'Visiter le site 5 jours différents.', 'badge', 'loyalty', 'visit_days', 5),
            $row('Pause café', 'Passer 10 minutes sur le site.', 'clock', 'loyalty', 'minutes_spent', 10),
            $row('Collectionneur', 'Débloquer 10 autres succès.', 'trophy', 'loyalty', 'achievements_unlocked', 10),
        ];
    }
}
