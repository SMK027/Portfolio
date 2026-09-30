<?php

namespace App\Support;

/**
 * Autorisations attribuables aux comptes de service (API).
 */
final class ServicePermissions
{
    public const GROUPS = [
        'Articles de veille' => [
            'articles.read'    => 'Lire les articles (brouillons compris)',
            'articles.write'   => 'Créer et modifier des articles (brouillons, soumission à validation)',
            'articles.publish' => 'Publier, programmer, dépublier et épingler des articles',
            'articles.delete'  => 'Supprimer des articles',
        ],
        'Projets' => [
            'projects.read'   => 'Lire les projets',
            'projects.write'  => 'Créer et modifier des projets',
            'projects.delete' => 'Supprimer des projets',
        ],
        'Annonces' => [
            'announcements.read'  => 'Lire les annonces',
            'announcements.write' => 'Créer, modifier et supprimer des annonces',
        ],
        'Messages' => [
            'messages.read' => 'Lire les messages de contact (données personnelles)',
        ],
        'Contenu' => [
            'content.export' => 'Exporter le contenu (JSON)',
            'content.import' => 'Importer du contenu (JSON)',
        ],
        'Site' => [
            'maintenance.manage' => 'Consulter et piloter le mode maintenance',
        ],
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
}
