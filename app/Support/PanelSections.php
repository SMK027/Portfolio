<?php

namespace App\Support;

use App\Models\User;

/**
 * Sections du panel et autorisations qui les ouvrent (« a|b » = l'une ou l'autre).
 * Sert au menu de navigation et à la page d'arrivée des bots.
 */
final class PanelSections
{
    /** Groupe => liste de [route, libellé, icône, motif de route actif, autorisations]. */
    public const GROUPS = [
        // Suivi : tableau de bord (administrateurs, ajouté par le menu), statistiques, recherche de stage.
        'Suivi' => [
            ['admin.statistics', 'Statistiques', 'squares', 'admin.statistics', 'stats.read'],
            ['admin.internship.index', 'Recherche de stage', 'briefcase', 'admin.internship.*', 'internship.read'],
        ],
        'Parcours' => [
            ['admin.profile.edit', 'Présentation', 'user', 'admin.profile.*', 'profile.read|profile.write'],
            ['admin.formations.index', 'Formations', 'academic-cap', 'admin.formations.*', 'educations.read|educations.write|educations.delete'],
            ['admin.experiences.index', 'Expériences', 'briefcase', 'admin.experiences.*', 'experiences.read|experiences.write|experiences.delete'],
            ['admin.diplomes.index', 'Diplômes', 'diploma', 'admin.diplomes.*', 'diplomas.read|diplomas.write|diplomas.delete'],
            ['admin.certifications.index', 'Certifications', 'badge', 'admin.certifications.*', 'certifications.read|certifications.write|certifications.delete'],
            ['admin.competences.index', 'Compétences', 'sparkles', 'admin.competences.*', 'skills.read|skills.write|skills.delete'],
            ['admin.loisirs.index', 'Loisirs', 'heart', 'admin.loisirs.*', 'hobbies.read|hobbies.write|hobbies.delete'],
        ],
        'Réalisations' => [
            ['admin.projets.index', 'Projets', 'folder', 'admin.projets.*', 'projects.read|projects.write|projects.delete'],
            ['admin.articles.index', 'Veille', 'newspaper', 'admin.articles.*', 'articles.read|articles.write'],
            ['admin.themes.index', 'Thèmes', 'tag', 'admin.themes.*', 'themes.read|themes.write|themes.delete'],
        ],
        'Échanges' => [
            ['admin.messages.index', 'Messages', 'inbox', 'admin.messages.*', 'messages.read'],
            ['admin.appointments.index', 'Rendez-vous', 'calendar', 'admin.appointments.*', 'appointments.read|appointments.write|appointments.delete'],
            ['admin.recommendations.index', 'Recommandations', 'badge', 'admin.recommendations.*', 'recommendations.read|recommendations.write|recommendations.delete'],
            ['admin.annonces.index', 'Annonces', 'megaphone', 'admin.annonces.*', 'announcements.read|announcements.write|announcements.delete'],
        ],
        'Site' => [
            ['admin.pages.index', 'Pages & visibilité', 'eye', 'admin.pages.*', 'pages.read|pages.write'],
            ['admin.seo.edit', 'Référencement', 'globe', 'admin.seo.*', 'seo.read|seo.write'],
            ['admin.easter-eggs.index', 'Easter eggs', 'sparkles', 'admin.easter-eggs.*', 'easter_eggs.read|easter_eggs.write|easter_eggs.delete'],
            ['admin.maintenance.edit', 'Maintenance', 'wrench', 'admin.maintenance.*', 'maintenance.read|maintenance.manage'],
            ['admin.transfer.index', 'Import / export', 'arrows-updown', 'admin.transfer.*', 'content.export|content.import'],
        ],
        'Comptes et sécurité' => [
            ['admin.utilisateurs.index', 'Comptes', 'users', 'admin.utilisateurs.*', 'users.read|users.write|users.delete'],
        ],
    ];

    /** Sections accessibles à l'utilisateur, par groupe (groupes vides retirés). */
    public static function for(User $user): array
    {
        return array_filter(array_map(
            fn (array $items) => array_values(array_filter($items, fn (array $item) => $user->canUsePanel($item[4]))),
            self::GROUPS
        ));
    }

    /** Première section accessible (page d'arrivée d'un bot). */
    public static function firstRouteFor(User $user): ?string
    {
        foreach (self::for($user) as $items) {
            return $items[0][0];
        }

        return null;
    }
}
