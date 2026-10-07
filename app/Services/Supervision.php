<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supervision des opérations : une requête refusée faute d'habilitation est
 * mise en attente (session), validée par un superviseur (identifiant + PIN),
 * puis rejouée avec un bypass à usage unique (cache, jamais en session).
 */
class Supervision
{
    public const PENDING_KEY = 'supervision.pending';

    public const BYPASS_FIELD = '_supervision_bypass';

    /** Opérations (habilitations) mises en jeu par une vérification d'autorisation refusée. */
    public static function operationsFor(string $ability, array $arguments): array
    {
        $article = collect($arguments)->first(fn ($a) => $a instanceof Article || $a === Article::class);
        if ($article) {
            return match ($ability) {
                'viewAny', 'view'           => ['articles.read'],
                'create', 'update', 'submit' => ['articles.write'],
                'publish', 'review'          => ['articles.publish'],
                'delete'                     => ['articles.delete'],
                default                      => [],
            };
        }

        return match ($ability) {
            'panel'          => array_values(array_filter(explode('|', (string) ($arguments[0] ?? '')))),
            'write-articles' => ['articles.read', 'articles.write'],
            'use-editor'     => ['articles.write', 'projects.write', 'experiences.write', 'profile.write'],
            // Jamais de bypass pour modifier un compte administrateur (élévation de privilèges).
            'manage-users'   => ($arguments[0] ?? null) instanceof User && $arguments[0]->exists && $arguments[0]->global_role !== 'user'
                ? [] : [$arguments[1] ?? 'users.write'],
            default          => [],
        };
    }

    /** Comptes soumis à la supervision : personnes non administratrices et bots. */
    public static function concerns(?User $user): bool
    {
        return $user && ! $user->isAdmin() && ! $user->isService();
    }

    /** Réponse au refus : écran superviseur si l'opération est débloquable, sinon null (403 habituel). */
    public function challenge(Request $request): ?Response
    {
        $operations = (array) $request->attributes->get('supervision.denied', []);
        $user = $request->user();

        if (! $operations || ! self::concerns($user) || $request->expectsJson()
            || $request->attributes->has('supervision.granted') || $request->is('supervision', 'supervision/*')
            || $this->targetsPrivilegedAccount($request)
            || ! $this->anySupervisorFor($operations)) {
            return null;
        }

        $id = Str::random(32);
        $this->discardPending($request);
        $request->session()->put(self::PENDING_KEY, [
            'id'         => $id,
            'user_id'    => $user->id,
            'method'     => $request->method(),
            'url'        => $request->fullUrl(),
            'path'       => $request->path(),
            'referer'    => $request->headers->get('referer'),
            'input'      => $request->isMethod('GET') ? [] : Arr::except($request->input(), ['_token', '_method', self::BYPASS_FIELD]),
            'files'      => $this->storeFiles($request, $id),
            'operations' => $operations,
            'expires'    => now()->addMinutes(15)->getTimestamp(),
        ]);

        return redirect()->route('supervision.show');
    }

    /** Requête visant un compte autre qu'un contributeur : jamais débloquable. */
    protected function targetsPrivilegedAccount(Request $request): bool
    {
        $target = $request->route('user');

        return $target instanceof User && $target->global_role !== 'user';
    }

    public function anySupervisorFor(array $operations): bool
    {
        return Supervisor::with('user')->where('is_active', true)->get()
            ->contains(fn (Supervisor $s) => $s->isUsable() && $s->grantable($operations));
    }

    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (! $pending || $pending['expires'] < now()->getTimestamp() || $pending['user_id'] !== $request->user()?->id) {
            if ($pending) {
                $this->discardPending($request);
            }

            return null;
        }

        return $pending;
    }

    public function discardPending(Request $request, bool $keepFiles = false): void
    {
        $pending = $request->session()->pull(self::PENDING_KEY);
        if ($pending && ! $keepFiles) {
            Storage::disk('local')->deleteDirectory('supervision/'.$pending['id']);
        }
    }

    /** Bypass à usage unique pour la requête en attente ; renvoie le jeton. */
    public function grant(Request $request, array $pending, Supervisor $supervisor): string
    {
        $token = Str::random(48);
        Cache::put('supervision.bypass.'.$token, [
            'user_id'    => $pending['user_id'],
            'method'     => $pending['method'],
            'path'       => $pending['path'],
            'operations' => $supervisor->grantable($pending['operations']),
            'files'      => $pending['files'],
            'pending_id' => $pending['id'],
            'referer'    => $pending['referer'],
            'supervisor' => $supervisor->id,
        ], now()->addMinutes(2));
        $this->discardPending($request, keepFiles: true);

        return $token;
    }

    /** Consomme le bypass joint à la requête (une seule fois) et l'applique à cette requête uniquement. */
    public function applyBypass(Request $request): void
    {
        $token = $request->query(self::BYPASS_FIELD) ?? $request->request->get(self::BYPASS_FIELD);
        if (! is_string($token) || $token === '') {
            return;
        }
        $request->query->remove(self::BYPASS_FIELD);
        $request->request->remove(self::BYPASS_FIELD);

        $bypass = Cache::pull('supervision.bypass.'.$token);
        if (! $bypass || $bypass['user_id'] !== $request->user()?->id
            || $bypass['method'] !== $request->method() || $bypass['path'] !== $request->path()) {
            return;
        }

        $request->attributes->set('supervision.granted', $bypass['operations']);
        if ($bypass['referer']) {
            $request->headers->set('referer', $bypass['referer']); // back() après une erreur de validation
        }
        if ($bypass['files']) {
            $files = [];
            foreach ($bypass['files'] as $key => [$path, $name, $mime]) {
                $files[$key] = new UploadedFile(Storage::disk('local')->path($path), $name, $mime, null, true);
            }
            $request->files->add(Arr::undot($files));
            app()->terminating(fn () => Storage::disk('local')->deleteDirectory('supervision/'.$bypass['pending_id']));
        }
    }

    /** Fichiers du formulaire conservés le temps de la validation. */
    protected function storeFiles(Request $request, string $id): array
    {
        $stored = [];
        foreach (Arr::dot($request->allFiles()) as $key => $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $stored[$key] = [$file->storeAs('supervision/'.$id, Str::random(16), 'local'), $file->getClientOriginalName(), $file->getClientMimeType()];
            }
        }

        return $stored;
    }

    /** Champs à plat pour le formulaire de rejeu : [« a[b][0] » => valeur]. */
    public static function flatten(array $input, string $prefix = ''): array
    {
        $fields = [];
        foreach ($input as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';
            if (is_array($value)) {
                $fields += self::flatten($value, $name);
            } elseif ($value !== null) {
                $fields[$name] = is_bool($value) ? (int) $value : (string) $value;
            }
        }

        return $fields;
    }
}
