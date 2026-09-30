<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Réglages généraux du site, stockés en clé / valeur.
 *
 *   Setting::get('site.indexable', true)
 *   Setting::set('site.indexable', false)
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use Auditable;

    /** Le site peut-il être indexé par les moteurs de recherche ? */
    public const INDEXABLE = 'site.indexable';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** Cache des réglages pour la durée de la requête. */
    protected static ?Collection $cache = null;

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /** Libellés lisibles pour le journal d'activité. */
    public function auditLabel(): string
    {
        return [
            self::INDEXABLE             => 'Indexation du site',
            'maintenance.enabled'       => 'Maintenance : activation',
            'maintenance.ends_at'       => 'Maintenance : date de fin',
            'maintenance.reason'        => 'Maintenance : motif',
            'mail.last_error'           => 'Dernière erreur d\'envoi d\'e-mail',
            'mail.last_error_at'        => 'Date de la dernière erreur d\'e-mail',
        ][$this->key] ?? $this->key;
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        static::$cache ??= static::query()->pluck('value', 'key');

        return static::$cache->has($key) ? static::$cache->get($key) : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::flushCache();
    }

    public static function siteIsIndexable(): bool
    {
        return (bool) static::get(self::INDEXABLE, true);
    }
}
