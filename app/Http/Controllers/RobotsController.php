<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;

/**
 * robots.txt généré selon le réglage d'indexation.
 *
 * Quand le site est désindexé, les pages restent explorables afin que les
 * moteurs voient la consigne "noindex" et retirent les pages déjà indexées
 * (bloquer l'exploration les empêcherait de la lire). Seul /storage/, servi
 * directement par le serveur web sans en-tête, est bloqué.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        // L'administration et la connexion ne sont pas bloquées ici : elles portent
        // déjà l'en-tête "noindex", qu'un blocage empêcherait les moteurs de lire.
        $lines = ['User-agent: *'];
        $lines[] = Setting::siteIsIndexable() ? 'Disallow:' : 'Disallow: /storage/';

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
