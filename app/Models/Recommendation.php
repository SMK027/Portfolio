<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Recommandation rédigée par un tiers via un lien d'invitation personnel,
 * publiée après validation (statut « approved »).
 */
#[Fillable([
    'label', 'token_expires_at', 'author_name', 'author_role', 'author_company', 'relationship', 'author_url',
    'content', 'status', 'is_featured', 'position',
])]
class Recommendation extends Model
{
    use Auditable;

    public const INVITED = 'invited';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    /** Statut => [libellé, classe du badge]. */
    public const STATUSES = [
        self::INVITED  => ['Invitation envoyée', 'badge-slate'],
        self::PENDING  => ['À modérer', 'badge-amber'],
        self::APPROVED => ['Publiée', 'badge-green'],
        self::REJECTED => ['Refusée', 'badge-slate'],
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'consented_at'     => 'datetime',
            'submitted_at'     => 'datetime',
            'approved_at'      => 'datetime',
            'is_featured'      => 'boolean',
        ];
    }

    public static function invite(string $label, ?\DateTimeInterface $expiresAt): self
    {
        $recommendation = new self(['label' => $label, 'token_expires_at' => $expiresAt, 'status' => self::INVITED]);
        $recommendation->token = Str::random(48);
        $recommendation->save();

        return $recommendation;
    }

    /** Invitation encore utilisable pour ce jeton, ou null. */
    public static function findByToken(string $token): ?self
    {
        $recommendation = static::where('token', $token)->first();

        return $recommendation?->invitationIsOpen() ? $recommendation : null;
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('approved_at')->orderByDesc('id');
    }

    /** Le lien reste ouvert tant que la recommandation n'est pas publiée ou refusée (l'auteur peut se relire). */
    public function invitationIsOpen(): bool
    {
        return $this->token !== null
            && in_array($this->status, [self::INVITED, self::PENDING], true)
            && ! ($this->token_expires_at?->isPast() ?? false);
    }

    public function inviteUrl(): ?string
    {
        return $this->token ? route('recommendations.invite', $this->token) : null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'badge-slate';
    }

    /** « Développeur · Société X » */
    public function authorTitle(): string
    {
        return collect([$this->author_role, $this->author_company])->filter()->join(' · ');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->author_name)))->filter()->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') ?: '?';
    }

    public function auditLabel(): string
    {
        return $this->author_name ?: $this->label;
    }
}
