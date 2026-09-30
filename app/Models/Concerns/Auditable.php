<?php

namespace App\Models\Concerns;

use App\Services\AuditTrail;
use Illuminate\Support\Str;

/**
 * Journalise les créations, modifications (avec les champs modifiés) et
 * suppressions du modèle, lorsqu'elles sont faites depuis l'administration.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->audit('created', $model->auditValues($model->getAttributes())));

        static::updated(function ($model) {
            $changes = [];
            foreach (array_keys($model->getChanges()) as $attribute) {
                if (in_array($attribute, AuditTrail::IGNORED, true)) {
                    continue;
                }
                $changes[$attribute] = in_array($attribute, AuditTrail::SECRET, true)
                    ? ['old' => '••••', 'new' => '••••']
                    : ['old' => AuditTrail::summarize($model->getOriginal($attribute)), 'new' => AuditTrail::summarize($model->getAttribute($attribute))];
            }

            if ($changes) {
                $model->audit('updated', $changes);
            }
        });

        static::deleted(fn ($model) => $model->audit('deleted'));
    }

    public function audit(string $event, ?array $changes = null, ?array $meta = null): void
    {
        app(AuditTrail::class)->record(Str::snake(class_basename($this)).'.'.$event, $this, $changes, $meta);
    }

    /** Valeurs initiales enregistrées à la création (secrets et horodatages exclus). */
    protected function auditValues(array $attributes): array
    {
        $values = [];
        foreach ($attributes as $attribute => $value) {
            if (in_array($attribute, [...AuditTrail::SECRET, ...AuditTrail::IGNORED, 'id'], true) || $value === null || $value === '') {
                continue;
            }
            $values[$attribute] = ['new' => AuditTrail::summarize($this->getAttribute($attribute))];
        }

        return $values;
    }
}
