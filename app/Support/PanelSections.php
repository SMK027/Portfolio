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
        'Contenu' => [
            ['admin.profile.edit', 'Présentation', 'user', 'admin.profile.*', 'profile.read|profile.write'],
            ['admin.formations.index', 'Formations', 'academic-cap', 'admin.formations.*', 'educations.read|educations.write|educations.delete'],
            ['admin.experiences.index', 'Expériences', 'briefcase', 'admin.experiences.*', 'experiences.read|experiences.write|experiences.delete'],
            ['admin.diplomes.index', 'Diplômes', 'diploma', 'admin.diplomes.*', 'diplomas.read|diplomas.write|diplomas.delete'],
            ['admin.certifications.index', 'Certifications', 'badge', 'admin.certifications.*', 'certifications.read|certifications.write|certifications.delete'],
            ['admin.competences.index', 'Compétences', 'sparkles', 'admin.competences.*', 'skills.read|skills.write|skills.delete'],
            ['admin.loisirs.index', 'Loisirs', 'heart', 'admin.loisirs.*', 'hobbies.read|hobbies.write|hobbies.delete'],
            ['admin.themes.index', 'Thèmes', 'tag', 'admin.themes.*', 'themes.read|themes.write|themes.delete'],
            ['admin.projets.index', 'Projets', 'folder', 'admin.projets.*', 'projects.read|projects.write|projects.delete'],
            ['admin.articles.index', 'Veille', 'newspaper', 'admin.articles.*', 'articles.read|articles.write'],
        ],
        'Site' => [
            ['admin.statistics', 'Statistiques', 'squares', 'admin.statistics', 'stats.read'],
            ['admin.annonces.index', 'Annonces', 'megaphone', 'admin.annonces.*', 'announcements.read|announcements.write|announcements.delete'],
            ['admin.messages.index', 'Messages', 'inbox', 'admin.messages.*', 'messages.read'],
            ['admin.appointments.index', 'Rendez-vous', 'calendar', 'admin.appointments.*', 'appointments.read|appointments.write|appointments.delete'],
            ['admin.pages.index', 'Pages & visibilité', 'eye', 'admin.pages.*', 'pages.read|pages.write'],
            ['admin.transfer.index', 'Import / export', 'arrows-updown', 'admin.transfer.*', 'content.export|content.import'],
            ['admin.seo.edit', 'Référencement', 'globe', 'admin.seo.*', 'seo.read|seo.write'],
            ['admin.maintenance.edit', 'Maintenance', 'wrench', 'admin.maintenance.*', 'maintenance.read|maintenance.manage'],
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
