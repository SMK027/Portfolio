<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\IpBan;
use App\Models\PageView;
use App\Services\AuditTrail;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Historique d'une adresse IP (super-administrateurs) : pages vues, messages,
 * rendez-vous, opérations du journal et bannissements, en une chronologie.
 * Limité aux données encore conservées (IP des visites effacées après 3 mois).
 */
class IpHistoryController extends Controller
{
    /** Événements chargés par source. */
    public const LIMIT = 500;

    public function __invoke(Request $request): View
    {
        $ip = trim((string) $request->query('ip'));
        $valid = $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
        if ($valid) {
            $ip = inet_ntop(inet_pton($ip)); // forme canonique (IPv6 abrégée)
            app(AuditTrail::class)->record('ip_history.viewed', meta: ['ip' => $ip]);
        }

        $views = $valid ? PageView::where('ip_address', $ip)->latest('id')->limit(self::LIMIT)->get() : collect();

        return view('admin.ip-history.index', [
            'ip'       => $ip,
            'valid'    => $valid,
            'views'    => $views,
            'summary'  => $valid ? $this->summary($views) : null,
            'events'   => $valid ? $this->timeline($ip, $views) : collect(),
            'bans'     => $valid ? IpBan::where('ip_address', $ip)->latest('id')->get() : collect(),
            'truncated' => $views->count() >= self::LIMIT,
        ]);
    }

    protected function summary(Collection $views): array
    {
        $located = $views->first(fn ($v) => $v->city || $v->country);

        return [
            'first'    => $views->min('created_at'),
            'last'     => $views->max('created_at'),
            'pages'    => $views->count(),
            'visits'   => $views->pluck('session_id')->filter()->unique()->count(),
            'visitors' => $views->pluck('visitor_id')->filter()->unique()->count(),
            'location' => $located ? collect([$located->city, $located->region])->filter()->join(', ') : null,
            'country'  => $located?->country,
            'devices'  => $views->map(fn ($v) => collect([UserAgent::DEVICES[$v->device] ?? null, $v->browser, $v->os])->filter()->join(' · '))
                ->filter()->countBy()->sortDesc(),
        ];
    }

    /** @return Collection<int, array{at: \Carbon\CarbonInterface, type: string, label: string, detail: ?string, url: ?string}> */
    protected function timeline(string $ip, Collection $views): Collection
    {
        $events = $views->map(fn (PageView $v) => [
            'at'     => $v->created_at,
            'type'   => 'Page vue',
            'label'  => $v->path,
            'detail' => collect([
                $v->duration ? $v->duration.' s de lecture' : null,
                $v->referrer_host ? 'depuis '.$v->referrer_host : null,
                $v->session_id ? 'visite '.Str::limit($v->session_id, 8, '') : null,
            ])->filter()->join(' · ') ?: null,
            'url'    => url($v->path),
        ]);

        $events = $events->concat(ContactMessage::where('ip_address', $ip)->latest('id')->limit(self::LIMIT)->get()->map(fn ($m) => [
            'at'     => $m->created_at,
            'type'   => 'Message de contact',
            'label'  => $m->subject ?: '(sans objet)',
            'detail' => trim($m->first_name.' '.$m->last_name).' <'.$m->email.'>',
            'url'    => route('admin.messages.show', $m),
        ]));

        $events = $events->concat(Appointment::where('ip_address', $ip)->latest('id')->limit(self::LIMIT)->get()->map(fn ($a) => [
            'at'     => $a->created_at,
            'type'   => 'Demande de rendez-vous',
            'label'  => $a->starts_at?->translatedFormat('j F Y à H:i') ?? '—',
            'detail' => $a->name.' <'.$a->email.'> · '.$a->statusLabel(),
            'url'    => route('admin.appointments.index'),
        ]));

        $events = $events->concat(AuditLog::where('ip_address', $ip)->latest('id')->limit(self::LIMIT)->get()->map(fn ($l) => [
            'at'     => $l->created_at,
            'type'   => 'Journal d\'activité',
            'label'  => $l->actionLabel(),
            'detail' => collect([$l->actor_name, $l->subject_label])->filter()->join(' · ') ?: null,
            'url'    => route('admin.audit.show', $l),
        ]));

        return $events->sortByDesc('at')->values();
    }
}
