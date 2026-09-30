<?php

namespace App\Services;

use RuntimeException;

/**
 * Adresse refusée pour raison de sécurité (réseau interne, schéma ou port interdit).
 */
class UnsafeUrlException extends RuntimeException
{
}
