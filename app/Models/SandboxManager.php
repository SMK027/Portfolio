<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compte de gestion de la machine SSH (UID 0, clé SSH uniquement), lié à un
 * super-administrateur. Il n'est appliqué sur la machine que tant que son
 * propriétaire est super-administrateur et actif.
 */
#[Fillable(['user_id', 'username', 'public_keys'])]
class SandboxManager extends Model
{
    use Auditable;

    public const USERNAME_PATTERN = '/^adm-[a-z0-9_-]{2,27}$/';

    /** Types de clés acceptés (DSA et RSA < 2048 bits refusés). */
    public const KEY_TYPES = ['ssh-ed25519', 'ssh-rsa', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521',
        'sk-ssh-ed25519@openssh.com', 'sk-ecdsa-sha2-nistp256@openssh.com'];

    public const MAX_KEYS = 10;

    protected function casts(): array
    {
        return [
            'public_keys' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Appliqué sur la machine : propriétaire super-administrateur, actif, avec au moins une clé. */
    public function isEligible(): bool
    {
        return $this->user?->isSuperAdmin() && $this->user->isActive() && ! empty($this->public_keys);
    }

    /**
     * Analyse une clé publique OpenSSH : null si elle est invalide ou refusée.
     *
     * @return array{type: string, key: string, comment: string, fingerprint: string, bits: ?int}|null
     */
    public static function parseKey(string $line): ?array
    {
        $parts = preg_split('/\s+/', trim($line), 3);
        if (count($parts) < 2 || ! in_array($parts[0], self::KEY_TYPES, true)) {
            return null;
        }
        $blob = base64_decode($parts[1], true);
        if ($blob === false || strlen($blob) < 20) {
            return null;
        }

        // Le blob commence par le type, encodé en chaîne SSH (longueur sur 4 octets)
        $fields = self::sshStrings($blob);
        if (($fields[0] ?? null) !== $parts[0]) {
            return null;
        }
        $bits = null;
        if ($parts[0] === 'ssh-rsa') {
            $modulus = ltrim($fields[2] ?? '', "\0");
            $bits = strlen($modulus) * 8;
            if ($bits < 2048) {
                return null;
            }
        }

        return [
            'type'        => $parts[0],
            'key'         => $parts[0].' '.$parts[1],
            'comment'     => mb_substr(preg_replace('/[^\P{C}]/u', '', $parts[2] ?? ''), 0, 100),
            'fingerprint' => 'SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '='),
            'bits'        => $bits,
        ];
    }

    /** @return list<string> */
    protected static function sshStrings(string $blob): array
    {
        $fields = [];
        $offset = 0;
        while ($offset + 4 <= strlen($blob) && count($fields) < 4) {
            $length = unpack('N', substr($blob, $offset, 4))[1];
            if ($length > strlen($blob) - $offset - 4) {
                break;
            }
            $fields[] = substr($blob, $offset + 4, $length);
            $offset += 4 + $length;
        }

        return $fields;
    }

    /** Lignes envoyées à la machine (« type base64 commentaire »). @return list<string> */
    public function keyLines(): array
    {
        return array_values(array_map(
            fn (array $k) => trim($k['key'].' '.preg_replace('/[^A-Za-z0-9@._:+-]/', '-', (string) ($k['comment'] ?? ''))),
            $this->public_keys ?? [],
        ));
    }

    public function auditLabel(): string
    {
        return $this->username;
    }
}
