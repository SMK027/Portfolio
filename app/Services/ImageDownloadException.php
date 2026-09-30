<?php

namespace App\Services;

use RuntimeException;

/**
 * Le serveur distant n'a pas fourni l'image (injoignable, erreur HTTP, protection anti-hotlink…).
 */
class ImageDownloadException extends RuntimeException
{
}
