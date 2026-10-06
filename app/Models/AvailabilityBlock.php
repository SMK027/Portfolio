<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Horaire bloqué (ex. : mardi 14 octobre, 10:00–11:00) : rendez-vous pris par
 * un autre moyen de communication, ou modification de dernière minute.
 */
class AvailabilityBlock extends Model
{
    use Auditable;

    public const TYPES = [
        'appointment' => 'Rendez-vous pris par un autre moyen',
        'change'      => 'Modification de dernière minute',
    ];

    public const CHANNELS = [
        'email'    => 'E-mail',
        'phone'    => 'Appel téléphonique',
        'sms'      => 'SMS / messagerie',
        'inperson' => 'En personne',
        'other'    => 'Autre',
    ];

    protected $fillable = ['starts_at', 'ends_at', 'type', 'channel', 'note'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Libellé court affiché dans le calendrier. */
    public function title(): string
    {
        $title = $this->type === 'appointment'
            ? 'RDV'.($this->channel ? ' ('.mb_strtolower(self::CHANNELS[$this->channel] ?? $this->channel).')' : '')
            : 'Indisponible';

        return $title.($this->note ? ' — '.$this->note : '');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function auditLabel(): string
    {
        return $this->starts_at->format('d/m/Y H:i').'–'.$this->ends_at->format('H:i').' — '.$this->title();
    }
}
