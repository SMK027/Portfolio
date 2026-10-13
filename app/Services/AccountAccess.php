<?php

namespace App\Services;

use App\Mail\AccountAccessMail;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/** E-mails d'accès au compte envoyés depuis l'administration (voir AccountAccessMail). */
class AccountAccess
{
    public const BROKER = 'account_setup';

    public function __construct(private SafeMailer $mailer, private AuditTrail $audit)
    {
    }

    /** Envoie l'e-mail ; retourne false si l'envoi est d'emblée impossible. */
    public function send(User $user, string $event): bool
    {
        $url = null;
        $expiresIn = null;

        // Invitation et réinitialisation : lien à usage unique (remplace un lien précédent)
        if (in_array($event, [AccountAccessMail::INVITATION, AccountAccessMail::RESET], true)) {
            $token = Password::broker(self::BROKER)->createToken($user);
            $url = route('account.setup', ['token' => $token, 'email' => $user->email]);
            $expiresIn = (int) config('auth.passwords.'.self::BROKER.'.expire');
        }

        $sent = $this->mailer->queue($user->email, new AccountAccessMail($user, $event, $url, $expiresIn), 'e-mail d\'accès au compte');
        $this->audit->record('user.access_mail', $user, meta: ['type' => $event, 'sent' => $sent]);

        return $sent;
    }
}
