<?php

namespace App\Services;

use App\Models\SecurityKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn as Library;
use lbuchs\WebAuthn\WebAuthnException;

/**
 * Clés de sécurité (WebAuthn / FIDO2) : enregistrement et vérification.
 * Le défi est conservé en session et ne sert qu'une fois.
 */
class WebAuthn
{
    protected const SESSION_KEY = 'webauthn.challenge';

    protected const TIMEOUT = 60;

    public function __construct(protected Request $request)
    {
    }

    /** Options de navigator.credentials.create(). */
    public function registrationOptions(User $user): object
    {
        $library = $this->library();
        $options = $library->getCreateArgs(
            (string) $user->id, $user->email, $user->name, self::TIMEOUT,
            false, 'preferred', null,
            $user->securityKeys()->pluck('credential_id')->map(fn ($id) => self::decode($id))->all(),
        );
        $this->remember($library);

        return $options;
    }

    /** Enregistre la clé après la réponse du navigateur. */
    public function register(User $user, array $credential, string $name): SecurityKey
    {
        $challenge = $this->pullChallenge()
            ?? throw ValidationException::withMessages(['security_key' => 'Délai dépassé : recommencez l\'enregistrement de la clé.']);

        try {
            $data = $this->library()->processCreate(
                self::decode($credential['response']['clientDataJSON'] ?? ''),
                self::decode($credential['response']['attestationObject'] ?? ''),
                $challenge,
                false, true, false,
            );
        } catch (WebAuthnException $e) {
            throw ValidationException::withMessages(['security_key' => 'Clé refusée : '.$e->getMessage()]);
        }

        $credentialId = self::encode($data->credentialId);
        if (SecurityKey::where('credential_id', $credentialId)->exists()) {
            throw ValidationException::withMessages(['security_key' => 'Cette clé est déjà enregistrée.']);
        }

        return $user->securityKeys()->create([
            'name'          => $name,
            'credential_id' => $credentialId,
            'public_key'    => $data->credentialPublicKey,
            'sign_count'    => (int) $data->signatureCounter,
        ]);
    }

    /** Options de navigator.credentials.get() pour les clés du compte. */
    public function authenticationOptions(User $user): object
    {
        $library = $this->library();
        $options = $library->getGetArgs(
            $user->securityKeys()->pluck('credential_id')->map(fn ($id) => self::decode($id))->all(),
            self::TIMEOUT, true, true, true, true, true, 'preferred',
        );
        $this->remember($library);

        return $options;
    }

    /** Vérifie la signature produite par l'une des clés du compte. */
    public function verify(User $user, array $credential): bool
    {
        $key = $user->securityKeys()->where('credential_id', (string) ($credential['id'] ?? ''))->first();
        $challenge = $this->pullChallenge();
        if (! $key || ! $challenge) {
            return false;
        }

        try {
            $library = $this->library();
            $library->processGet(
                self::decode($credential['response']['clientDataJSON'] ?? ''),
                self::decode($credential['response']['authenticatorData'] ?? ''),
                self::decode($credential['response']['signature'] ?? ''),
                $key->public_key, $challenge, $key->sign_count ?: null, false, true,
            );
        } catch (WebAuthnException) {
            return false;
        }

        $key->forceFill(['sign_count' => (int) $library->getSignatureCounter(), 'last_used_at' => now()])->saveQuietly();

        return true;
    }

    protected function library(): Library
    {
        // Encodage base64url des données binaires dans le JSON envoyé au navigateur.
        return new Library(config('app.name'), $this->request->getHost(), null, true);
    }

    protected function remember(Library $library): void
    {
        $this->request->session()->put(self::SESSION_KEY, [
            'challenge' => $library->getChallenge()->getHex(),
            'expires'   => now()->addSeconds(self::TIMEOUT * 2)->getTimestamp(),
        ]);
    }

    protected function pullChallenge(): ?ByteBuffer
    {
        $stored = $this->request->session()->pull(self::SESSION_KEY);
        if (! $stored || $stored['expires'] < now()->getTimestamp()) {
            return null;
        }

        return ByteBuffer::fromHex($stored['challenge']);
    }

    public static function encode(string|ByteBuffer $binary): string
    {
        $binary = $binary instanceof ByteBuffer ? $binary->getBinaryString() : $binary;

        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function decode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }
}
