<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Entrée du journal d'activité (lecture seule une fois créée).
 */
#[Fillable([
    'user_id', 'actor_name', 'actor_role', 'via', 'action', 'subject_type', 'subject_id',
    'subject_label', 'changes', 'meta', 'ip_address', 'user_agent', 'created_at',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** Libellés des types d'éléments. */
    public const SUBJECTS = [
        'Announcement'   => 'Annonce',
        'Article'        => 'Article',
        'ArticleFile'    => 'Pièce jointe d\'article',
        'Certification'  => 'Certification',
        'ContactMessage' => 'Message de contact',
        'Diploma'        => 'Diplôme',
        'Education'      => 'Formation',
        'Experience'     => 'Expérience',
        'Hobby'          => 'Loisir',
        'Page'           => 'Page',
        'Profile'        => 'Présentation',
        'Project'        => 'Projet',
        'SecurityKey'    => 'Clé de sécurité',
        'ProjectFile'    => 'Fichier de projet',
        'ServiceToken'   => 'Code d\'application',
        'ServiceAccount' => 'Compte de service',
        'Setting'        => 'Réglage',
        'Skill'          => 'Compétence',
        'Theme'          => 'Thème',
        'User'           => 'Compte',
    ];

    /** Libellés des actions (les autres sont affichées telles quelles). */
    public const ACTIONS = [
        'created'           => 'Création',
        'updated'           => 'Modification',
        'deleted'           => 'Suppression',
        'relations_updated' => 'Liens modifiés',
        'auth.login'        => 'Connexion',
        'auth.logout'       => 'Déconnexion',
        'auth.failed'       => 'Échec de connexion',
        'auth.lockout'      => 'Connexions bloquées (trop de tentatives)',
        'auth.password_reset' => 'Mot de passe réinitialisé',
        'auth.session_revoked' => 'Session coupée (code désactivé ou supprimé)',
        'auth.two_factor'   => 'Second facteur validé',
        'auth.two_factor_failed' => 'Second facteur refusé',
        'two_factor.enabled'  => 'Application d\'authentification activée',
        'two_factor.disabled' => 'Application d\'authentification désactivée',
        'two_factor.recovery_codes_regenerated' => 'Codes de secours régénérés',
        'two_factor.reset'    => 'Double authentification réinitialisée',
        'api.auth_failed'   => 'Code d\'application refusé',
        'api.forbidden'     => 'Requête API refusée (autorisation manquante)',
        'api.messages_read' => 'Messages lus via l\'API',
        'article.submitted' => 'Article soumis à validation',
        'article.approved'  => 'Article validé',
        'article.preview_shared'  => 'Lien de relecture créé',
        'article.preview_revoked' => 'Lien de relecture révoqué',
        'article.changes_requested' => 'Article renvoyé en brouillon',
        'content.exported'  => 'Export du contenu',
        'content.imported'  => 'Import du contenu',
        'editor.image_uploaded' => 'Image envoyée (éditeur)',
        'mail.test'         => 'E-mail de test',
        'encoding.repaired' => 'Réparation d\'encodage',
    ];

    public const VIAS = ['web' => 'Panel', 'api' => 'API', 'console' => 'Console'];

    protected function casts(): array
    {
        return [
            'changes'    => 'array',
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Journal inaltérable depuis l'application.
        static::updating(fn () => throw new LogicException('Le journal d\'activité ne peut pas être modifié.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'activité ne peut pas être supprimé.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        [$type, $event] = array_pad(explode('.', $this->action, 2), 2, null);

        if (isset(self::ACTIONS[$this->action])) {
            return self::ACTIONS[$this->action];
        }

        return self::ACTIONS[$event] ?? $this->action;
    }

    public function subjectLabel(): ?string
    {
        return $this->subject_type ? (self::SUBJECTS[$this->subject_type] ?? $this->subject_type) : null;
    }

    public function viaLabel(): string
    {
        return self::VIAS[$this->via] ?? $this->via;
    }
}
