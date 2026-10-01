<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Historisation des opérations d'administration.
 *
 * Auteur : utilisateur connecté (panel), compte de service (API) ou console.
 * Sans auteur, l'action relève du site public et n'est pas enregistrée
 * (sauf événements de sécurité forcés : échec de connexion…).
 */
class AuditTrail
{
    /** Attributs jamais enregistrés. */
    public const SECRET = ['password', 'token_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'public_key', 'credential_id', 'preview_token'];

    /** Attributs ignorés dans les différences (techniques, renouvelés automatiquement). */
    public const IGNORED = ['created_at', 'updated_at', 'last_used_at', 'last_used_ip', 'remember_token', 'recaptcha_score', 'two_factor_last_step', 'sign_count'];

    /** Au-delà, une valeur est résumée (contenus Editor.js, Markdown…). */
    protected const MAX_LENGTH = 300;

    protected ?User $actor = null;

    protected ?string $via = null;

    protected bool $paused = false;

    /** Compte de service authentifié par l'API. */
    public function actingThroughApi(User $account): void
    {
        $this->actor = $account;
        $this->via = 'api';
    }

    public function actor(): ?User
    {
        return $this->actor ?? auth()->user();
    }

    public function via(): string
    {
        if ($this->via) {
            return $this->via;
        }

        return app()->runningInConsole() && ! app()->runningUnitTests() ? 'console' : 'web';
    }

    /** Exécute un traitement sans journaliser chaque modification (ex. : import, résumé à part). */
    public function withoutRecording(callable $callback): mixed
    {
        $previous = $this->paused;
        $this->paused = true;
        try {
            return $callback();
        } finally {
            $this->paused = $previous;
        }
    }

    /**
     * @param  array<string, mixed>|null  $changes
     * @param  array<string, mixed>|null  $meta
     */
    public function record(string $action, ?Model $subject = null, ?array $changes = null, ?array $meta = null, bool $force = false, ?string $label = null): void
    {
        if ($this->paused && ! $force) {
            return;
        }

        $actor = $this->actor();
        $via = $this->via();

        if (! $actor && $via !== 'console' && ! $force) {
            return; // action publique
        }

        try {
            AuditLog::create([
                'user_id'       => $actor?->id,
                'actor_name'    => $actor?->name ?? ($via === 'console' ? 'Console' : null),
                'actor_role'    => $actor?->global_role,
                'via'           => $via,
                'action'        => $action,
                'subject_type'  => $subject ? class_basename($subject) : null,
                'subject_id'    => is_numeric($subject?->getKey()) ? $subject->getKey() : null,
                'subject_label' => $label ?? ($subject ? Str::limit(self::labelOf($subject), 250) : null),
                'changes'       => $changes ?: null,
                'meta'          => $meta ?: null,
                'ip_address'    => $via === 'console' ? null : request()->ip(),
                'user_agent'    => $via === 'console' ? null : Str::limit((string) request()->userAgent(), 490),
                'created_at'    => now(),
            ]);
        } catch (Throwable $e) {
            // L'historisation ne doit jamais bloquer une opération.
            report($e);
        }
    }

    /**
     * Enregistre les liens modifiés (thèmes, co-auteurs, compétences…) d'un élément.
     *
     * @param  array<string, list<string>>  $before
     * @param  array<string, list<string>>  $after
     */
    public function recordRelations(Model $subject, array $before, array $after): void
    {
        $changes = [];
        foreach ($after as $relation => $values) {
            $old = array_values($before[$relation] ?? []);
            $new = array_values($values);
            sort($old);
            sort($new);
            if ($old !== $new) {
                $changes[$relation] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes) {
            $this->record(Str::snake(class_basename($subject)).'.relations_updated', $subject, $changes);
        }
    }

    public static function labelOf(Model $model): string
    {
        if (method_exists($model, 'auditLabel')) {
            return (string) $model->auditLabel();
        }

        foreach (['title', 'name', 'key', 'original_name', 'email'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return '#'.$model->getKey();
    }

    /** Valeur lisible et compacte pour le journal. */
    public static function summarize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);

            return mb_strlen((string) $json) > self::MAX_LENGTH ? '(contenu de '.mb_strlen((string) $json).' caractères)' : $value;
        }

        if (is_string($value) && mb_strlen($value) > self::MAX_LENGTH) {
            return Str::limit($value, self::MAX_LENGTH).' ('.mb_strlen($value).' caractères)';
        }

        return $value;
    }
}
