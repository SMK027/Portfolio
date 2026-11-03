<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SandboxAccount;
use App\Services\AuditTrail;
use App\Services\Sandbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Machine SSH bac à sable (super-administrateurs) : voir App\Services\Sandbox. */
class SandboxController extends Controller
{
    public function __construct(protected Sandbox $sandbox, protected AuditTrail $audit)
    {
    }

    public function show(): View
    {
        $status = $backups = null;
        $error = null;
        if ($this->sandbox->isConfigured()) {
            try {
                $status = $this->sandbox->status();
                $backups = $this->sandbox->backups();
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.sandbox.show', [
            'configured' => $this->sandbox->isConfigured(),
            'settings'   => $this->sandbox->settings(),
            'accounts'   => SandboxAccount::ordered()->get(),
            'status'     => $status,
            'backups'    => $backups,
            'error'      => $error,
            'sshCommand' => $this->sandbox->sshCommand(),
            'effectivePublic' => $this->sandbox->effectivePublic(),
            'connections' => \App\Models\SandboxConnection::latest('connected_at')->paginate(30, pageName: 'connexions'),
        ]);
    }

    /** Accès, horaires et internet. */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'public'           => ['nullable', 'boolean'],
            'internet'         => ['nullable', 'boolean'],
            'internet_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'schedule_enabled' => ['nullable', 'boolean'],
            'schedule_days'    => ['array'],
            'schedule_days.*'  => ['integer', 'between:1,7'],
            'schedule_start'   => ['required', 'date_format:H:i'],
            'schedule_end'     => ['required', 'date_format:H:i', 'different:schedule_start'],
        ]);

        $settings = $this->sandbox->settings();
        $internet = $request->boolean('internet');
        $minutes = (int) $data['internet_minutes'];
        // Minuteur d'internet relancé à chaque activation (ou changement de durée)
        $restartTimer = $internet && (! $settings['internet'] || $minutes !== (int) $settings['internet_minutes']);

        $this->sandbox->saveSettings([
            'public'           => $request->boolean('public'),
            'internet'         => $internet,
            'internet_minutes' => $minutes,
            'internet_until'   => ! $internet || $minutes === 0 ? null
                : ($restartTimer ? now()->addMinutes($minutes)->toIso8601String() : $settings['internet_until']),
            'schedule_enabled' => $request->boolean('schedule_enabled'),
            'schedule_days'    => array_values(array_unique(array_map('intval', $data['schedule_days'] ?? []))),
            'schedule_start'   => $data['schedule_start'],
            'schedule_end'     => $data['schedule_end'],
        ]);

        return $this->pushed('Réglages d\'accès enregistrés.');
    }

    /** Ouverture temporaire (démo), même hors horaires ou accès fermé. */
    public function openTemporarily(Request $request): RedirectResponse
    {
        $minutes = (int) $request->validate(['minutes' => ['required', 'integer', 'min:0', 'max:1440']])['minutes'];
        $this->sandbox->saveSettings(['open_until' => $minutes ? now()->addMinutes($minutes)->toIso8601String() : null]);

        return $this->pushed($minutes ? 'Machine ouverte jusqu\'à '.now()->addMinutes($minutes)->format('H:i').'.' : 'Ouverture temporaire annulée.');
    }

    /** Ressources de la machine, limites par compte et de connexion. */
    public function updateResources(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'resources.cpus'            => ['required', 'numeric', 'min:0.05', 'max:4'],
            'resources.memory_mb'       => ['required', 'integer', 'min:32', 'max:4096'],
            'resources.pids'            => ['required', 'integer', 'min:16', 'max:4096'],
            'limits.nproc'              => ['required', 'integer', 'min:4', 'max:256'],
            'limits.memory_mb'          => ['required', 'integer', 'min:32', 'max:1024'],
            'limits.file_mb'            => ['required', 'integer', 'min:1', 'max:1024'],
            'limits.user_quota_mb'      => ['required', 'integer', 'min:1', 'max:4096'],
            'connections.max'           => ['required', 'integer', 'min:1', 'max:200'],
            'connections.per_ip'        => ['required', 'integer', 'min:1', 'max:20'],
            'connections.per_minute'    => ['required', 'integer', 'min:1', 'max:120'],
        ]);
        $this->sandbox->saveSettings($data);

        return $this->pushed('Ressources et limites enregistrées.');
    }

    /** Message d'accueil, sauvegardes et maintenance automatiques. */
    public function updateMaintenance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'motd'             => ['nullable', 'string', 'max:2000'],
            'ephemeral_sessions' => ['nullable', 'boolean'],
            'auto_backup'      => ['nullable', 'boolean'],
            'keep'             => ['required', 'integer', 'min:1', 'max:60'],
            'reset_frequency'  => ['required', Rule::in(array_keys(Sandbox::FREQUENCIES))],
            'reference_backup' => ['nullable', 'regex:/^[a-f0-9]{16}$/'],
            'guest_rotation'   => ['required', Rule::in(array_keys(Sandbox::FREQUENCIES))],
        ]);
        $this->sandbox->saveSettings([
            ...$data,
            'motd'             => (string) ($data['motd'] ?? ''),
            'auto_backup'      => $request->boolean('auto_backup'),
            'ephemeral_sessions' => $request->boolean('ephemeral_sessions'),
            'keep'             => (int) $data['keep'],
            'reference_backup' => $data['reference_backup'] ?? null,
        ]);

        return $this->pushed('Réglages de maintenance enregistrés.');
    }

    public function rotateGuest(): RedirectResponse
    {
        $password = $this->sandbox->rotateGuestPassword(force: true);
        if (! $password) {
            return back()->with('error', 'Aucun compte invité.');
        }
        $this->audit->record('sandbox.guest_rotated', force: true);

        return back()->with('success', "Nouveau mot de passe invité : {$password}");
    }

    public function upgrade(): RedirectResponse
    {
        try {
            $output = $this->sandbox->upgrade();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->audit->record('sandbox.upgraded', force: true);

        return back()->with('success', 'Paquets mis à jour.')->with('upgrade_output', $output);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $this->validatedAccount($request);
        $isGuest = $request->boolean('is_guest');
        if ($isGuest && SandboxAccount::guest()) {
            return back()->withErrors(['is_guest' => 'Un compte invité existe déjà.'])->withInput();
        }

        $password = ($data['password'] ?? null) ?: SandboxAccount::generatePassword();
        $account = new SandboxAccount([...$data, 'is_guest' => $isGuest, 'is_enabled' => true]);
        $account->setPassword($password);
        $account->save();

        return $this->pushed("Compte « {$account->username} » créé. Mot de passe : {$password}");
    }

    public function updateAccount(Request $request, SandboxAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'label'      => ['nullable', 'string', 'max:150'],
            'is_enabled' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'password'   => ['nullable', 'string', 'min:8', 'max:72'],
            'regenerate' => ['nullable', 'boolean'],
        ]);

        $account->fill([
            'label'      => $data['label'] ?? null,
            'is_enabled' => $request->boolean('is_enabled'),
            'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
        ]);
        $password = $data['password'] ?? ($request->boolean('regenerate') ? SandboxAccount::generatePassword() : null);
        if ($password) {
            $account->setPassword($password);
        }
        $account->save();

        return $this->pushed("Compte « {$account->username} » mis à jour.".($password ? " Nouveau mot de passe : {$password}" : ''));
    }

    public function destroyAccount(SandboxAccount $account): RedirectResponse
    {
        $account->delete();

        return $this->pushed("Compte « {$account->username} » supprimé (sessions fermées, dossier conservé jusqu'à la prochaine réinitialisation).");
    }

    public function killSessions(Request $request): RedirectResponse
    {
        $username = $request->validate(['username' => ['nullable', 'regex:'.SandboxAccount::USERNAME_PATTERN]])['username'] ?? null;

        return $this->action(fn () => $this->sandbox->killSessions($username), 'sandbox.sessions_killed', ['compte' => $username ?? 'tous'],
            $username ? "Sessions de « {$username} » fermées." : 'Toutes les sessions ont été fermées.');
    }

    public function restart(): RedirectResponse
    {
        return $this->action(fn () => $this->sandbox->restart(), 'sandbox.restarted', null, 'Machine redémarrée : les comptes sont réappliqués dans les secondes qui suivent.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:REINITIALISER']], ['confirm.in' => 'Tapez REINITIALISER pour confirmer.']);

        return $this->action(fn () => $this->sandbox->reset(), 'sandbox.reset', null, 'Dossiers personnels vidés.');
    }

    public function storeBackup(Request $request): RedirectResponse
    {
        $label = $request->validate(['label' => ['nullable', 'string', 'max:120']])['label'] ?? null;

        return $this->action(fn () => $this->sandbox->backup($label ?: 'Manuelle du '.now()->format('d/m/Y H:i')), 'sandbox.backup_created', ['libellé' => $label], 'Sauvegarde créée.');
    }

    public function restoreBackup(string $backup): RedirectResponse
    {
        return $this->action(fn () => $this->sandbox->restore($backup), 'sandbox.backup_restored', ['sauvegarde' => $backup],
            'Sauvegarde restaurée : sessions fermées, dossiers personnels remplacés.');
    }

    public function destroyBackup(string $backup): RedirectResponse
    {
        return $this->action(fn () => $this->sandbox->deleteBackup($backup), 'sandbox.backup_deleted', ['sauvegarde' => $backup], 'Sauvegarde supprimée.');
    }

    public function downloadBackup(string $backup): StreamedResponse|RedirectResponse
    {
        try {
            $body = $this->sandbox->download($backup)->toPsrResponse()->getBody();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return response()->streamDownload(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(65536);
            }
        }, "machine-ssh-{$backup}.tar.gz", ['Content-Type' => 'application/gzip']);
    }

    /** @return array<string, mixed> */
    protected function validatedAccount(Request $request): array
    {
        return $request->validate([
            'username'   => ['required', 'string', 'regex:'.SandboxAccount::USERNAME_PATTERN, 'not_regex:/^tmp-/', Rule::notIn(SandboxAccount::RESERVED), 'unique:sandbox_accounts,username'],
            'label'      => ['nullable', 'string', 'max:150'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'password'   => ['nullable', 'string', 'min:8', 'max:72'],
            'is_guest'   => ['nullable', 'boolean'],
        ], [
            'username.regex'  => 'De 3 à 32 caractères : lettres minuscules, chiffres, « - » et « _ », en commençant par une lettre.',
            'username.not_in' => 'Ce nom est réservé par le système.',
            'username.not_regex' => 'Le préfixe « tmp- » est réservé aux sessions jetables.',
        ]);
    }

    /** Pousse l'état à l'agent après une modification enregistrée. */
    protected function pushed(string $message): RedirectResponse
    {
        try {
            $error = $this->sandbox->sync();
        } catch (RuntimeException $e) {
            return back()->with('warning', $message.' — Non appliqué sur la machine pour l\'instant : '.$e->getMessage());
        }

        return back()->with($error ? 'warning' : 'success', $error ? "{$message} — Erreur de la machine : {$error}" : $message);
    }

    protected function action(callable $callback, string $event, ?array $meta, string $message): RedirectResponse
    {
        try {
            $callback();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->audit->record($event, meta: $meta, force: true);

        return back()->with('success', $message);
    }
}
