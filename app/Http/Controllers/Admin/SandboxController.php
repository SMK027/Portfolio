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
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'public'      => ['nullable', 'boolean'],
            'internet'    => ['nullable', 'boolean'],
            'auto_backup' => ['nullable', 'boolean'],
            'keep'        => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $this->sandbox->saveSettings([
            'public'      => $request->boolean('public'),
            'internet'    => $request->boolean('internet'),
            'auto_backup' => $request->boolean('auto_backup'),
            'keep'        => (int) $data['keep'],
        ]);

        return $this->pushed('Réglages enregistrés.');
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
            'username'   => ['required', 'string', 'regex:'.SandboxAccount::USERNAME_PATTERN, Rule::notIn(SandboxAccount::RESERVED), 'unique:sandbox_accounts,username'],
            'label'      => ['nullable', 'string', 'max:150'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'password'   => ['nullable', 'string', 'min:8', 'max:72'],
            'is_guest'   => ['nullable', 'boolean'],
        ], [
            'username.regex'  => 'De 3 à 32 caractères : lettres minuscules, chiffres, « - » et « _ », en commençant par une lettre.',
            'username.not_in' => 'Ce nom est réservé par le système.',
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
