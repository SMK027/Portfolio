<?php

namespace App\Support;

/**
 * Autorisations attribuables aux comptes de service (API) et aux bots (panel),
 * regroupées dans l'ordre du menu de navigation.
 */
final class ServicePermissions
{
    public const GROUPS = [
        'Présentation' => [
            'profile.read'  => 'Consulter la présentation (identité, à propos, réseaux)',
            'profile.write' => 'Modifier la présentation',
        ],
        'Formations' => self::CRUD['educations'],
        'Expériences' => self::CRUD['experiences'],
        'Diplômes' => self::CRUD['diplomas'],
        'Certifications' => self::CRUD['certifications'],
        'Compétences' => self::CRUD['skills'],
        'Loisirs' => self::CRUD['hobbies'],
        'Thèmes' => self::CRUD['themes'],
        'Projets' => [
            'projects.read'   => 'Lire les projets',
            'projects.write'  => 'Créer et modifier des projets',
            'projects.delete' => 'Supprimer des projets',
        ],
        'Articles de veille' => [
            'articles.read'    => 'Lire les articles (brouillons compris)',
            'articles.write'   => 'Créer et modifier des articles (brouillons, soumission à validation)',
            'articles.publish' => 'Publier, programmer, dépublier et épingler des articles',
            'articles.delete'  => 'Supprimer des articles',
        ],
        'Annonces' => [
            'announcements.read'   => 'Lire les annonces',
            'announcements.write'  => 'Créer et modifier des annonces',
            'announcements.delete' => 'Supprimer des annonces',
        ],
        'Messages' => [
            'messages.read'   => 'Lire les messages de contact (données personnelles)',
            'messages.delete' => 'Supprimer des messages de contact',
        ],
        'Pages & visibilité' => [
            'pages.read'  => 'Consulter les pages et leur visibilité',
            'pages.write' => 'Modifier les pages (titres, introductions, public / privé)',
        ],
        'Import / export' => [
            'content.export' => 'Exporter le contenu (JSON)',
            'content.import' => 'Importer du contenu (JSON)',
        ],
        'Référencement' => [
            'seo.read'  => 'Consulter les réglages de référencement',
            'seo.write' => 'Modifier le référencement (indexation par les moteurs)',
        ],
        'Maintenance' => [
            'maintenance.read'   => 'Consulter l\'état du mode maintenance',
            'maintenance.manage' => 'Activer, programmer et désactiver le mode maintenance',
        ],
        'Comptes' => [
            'users.read'   => 'Consulter la liste des comptes',
            'users.write'  => 'Créer et modifier des comptes contributeurs (jamais d\'administrateurs)',
            'users.delete' => 'Supprimer des comptes contributeurs',
        ],
    ];

    /** Sections de contenu à lecture / écriture / suppression. */
    private const CRUD = [
        'educations'     => ['educations.read' => 'Lire les formations', 'educations.write' => 'Créer et modifier des formations', 'educations.delete' => 'Supprimer des formations'],
        'experiences'    => ['experiences.read' => 'Lire les expériences', 'experiences.write' => 'Créer et modifier des expériences', 'experiences.delete' => 'Supprimer des expériences'],
        'diplomas'       => ['diplomas.read' => 'Lire les diplômes', 'diplomas.write' => 'Créer et modifier des diplômes', 'diplomas.delete' => 'Supprimer des diplômes'],
        'certifications' => ['certifications.read' => 'Lire les certifications', 'certifications.write' => 'Créer et modifier des certifications', 'certifications.delete' => 'Supprimer des certifications'],
        'skills'         => ['skills.read' => 'Lire les compétences', 'skills.write' => 'Créer et modifier des compétences', 'skills.delete' => 'Supprimer des compétences'],
        'hobbies'        => ['hobbies.read' => 'Lire les loisirs', 'hobbies.write' => 'Créer et modifier des loisirs', 'hobbies.delete' => 'Supprimer des loisirs'],
        'themes'         => ['themes.read' => 'Lire les thèmes', 'themes.write' => 'Créer et modifier des thèmes', 'themes.delete' => 'Supprimer des thèmes'],
    ];

    /** Autorisations utilisables via l'API ; les autres ne servent qu'aux bots (panel). */
    public const API = [
        'articles.read', 'articles.write', 'articles.publish', 'articles.delete',
        'projects.read', 'projects.write', 'projects.delete',
        'announcements.read', 'announcements.write', 'announcements.delete',
        'messages.read', 'content.export', 'content.import', 'maintenance.read', 'maintenance.manage',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::GROUPS)));
    }

    public static function label(string $permission): string
    {
        return array_merge(...array_values(self::GROUPS))[$permission] ?? $permission;
    }

    public static function isApi(string $permission): bool
    {
        return in_array($permission, self::API, true);
    }
}
