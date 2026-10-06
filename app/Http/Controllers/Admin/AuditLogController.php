<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Consultation du journal d'activité (super-administrateurs).
 */
class AuditLogController extends Controller
{
    public const CATEGORIES = [
        'auth'     => 'Authentification (connexions, bannissements IP)',
        'api'      => 'API',
        'article'  => 'Articles',
        'project'  => 'Projets',
        'content'  => 'Import / export',
        'user'     => 'Comptes',
        'service'  => 'Comptes de service',
        'setting'  => 'Réglages (maintenance, référencement…)',
        'other'    => 'Autres contenus',
    ];

    public function index(Request $request): View
    {
        Gate::authorize('view-audit-log');

        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'user'     => ['nullable', 'integer'],
            'via'      => ['nullable', 'in:web,api,console'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(self::CATEGORIES))],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('subject_label', 'like', "%{$term}%")
                ->orWhere('actor_name', 'like', "%{$term}%")
                ->orWhere('action', 'like', "%{$term}%")
                ->orWhere('ip_address', 'like', "%{$term}%")))
            ->when($filters['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['via'] ?? null, fn ($q, $via) => $q->where('via', $via))
            ->when($filters['category'] ?? null, fn ($q, $category) => $this->applyCategory($q, $category))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs'       => $logs,
            'filters'    => $filters,
            'categories' => self::CATEGORIES,
            'users'      => User::whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(),
        ]);
    }

    public function show(AuditLog $log): View
    {
        Gate::authorize('view-audit-log');

        return view('admin.audit.show', ['log' => $log->load('user')]);
    }

    protected function applyCategory($query, string $category): void
    {
        match ($category) {
            'auth'    => $query->where(fn ($q) => $q->where('action', 'like', 'auth.%')->orWhere('action', 'like', 'ip_ban.%')),
            'api'     => $query->where('action', 'like', 'api.%'),
            'article' => $query->where(fn ($q) => $q->where('action', 'like', 'article.%')->orWhere('action', 'like', 'article_file.%')),
            'project' => $query->where(fn ($q) => $q->where('action', 'like', 'project.%')->orWhere('action', 'like', 'project_file.%')),
            'content' => $query->where('action', 'like', 'content.%'),
            'user'    => $query->where('action', 'like', 'user.%'),
            'service' => $query->where(fn ($q) => $q->where('action', 'like', 'service_token.%')->orWhere('actor_role', 'service')->orWhere('action', 'like', 'service_account.%')),
            'setting' => $query->where('action', 'like', 'setting.%'),
            default   => $query->where(fn ($q) => $q
                ->where('action', 'not like', 'auth.%')->where('action', 'not like', 'ip_ban.%')->where('action', 'not like', 'api.%')
                ->where('action', 'not like', 'article%')->where('action', 'not like', 'project%')
                ->where('action', 'not like', 'content.%')->where('action', 'not like', 'user.%')
                ->where('action', 'not like', 'setting.%')->where('action', 'not like', 'service%')),
        };
    }
}
