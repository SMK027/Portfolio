<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Application installable (PWA) : manifeste, icônes générées à partir de la
 * photo de présentation (ou des initiales) et page affichée hors connexion.
 * Le service worker est public/sw.js.
 */
class PwaController extends Controller
{
    public const ICON_SIZES = [192, 512];

    protected const THEME_COLOR = '#4f46e5';

    protected const BACKGROUND_COLOR = '#0f172a';

    public function manifest(): JsonResponse
    {
        $profile = Profile::current();
        $version = $this->iconVersion($profile);

        $icons = [];
        foreach (self::ICON_SIZES as $size) {
            $icons[] = ['src' => route('pwa.icon', ['size' => $size, 'v' => $version]), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];
        }
        $icons[] = ['src' => route('pwa.icon', ['size' => 512, 'maskable' => 1, 'v' => $version]), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];

        return response()->json([
            'id'               => '/',
            'name'             => $profile->fullName(),
            'short_name'       => Str::limit($profile->first_name ?: $profile->fullName(), 12, ''),
            'description'      => $profile->headline,
            'lang'             => config('app.locale'),
            'start_url'        => '/?source=pwa',
            'scope'            => '/',
            'display'          => 'standalone',
            'background_color' => self::BACKGROUND_COLOR,
            'theme_color'      => self::THEME_COLOR,
            'icons'            => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function icon(int $size): Response
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);
        $profile = Profile::current();
        $maskable = request()->boolean('maskable');

        $png = Cache::remember("pwa.icon.{$size}.".(int) $maskable.'.'.$this->iconVersion($profile), now()->addMonth(),
            fn () => base64_encode($this->render($profile, $size, $maskable)));

        return response(base64_decode($png), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    /** Page de secours du service worker, quand une page n'a pas été lue auparavant. */
    public function offline(): View
    {
        return view('public.offline', ['profile' => Profile::current()]);
    }

    /** Change avec la photo ou le nom : renouvelle les icônes en cache. */
    protected function iconVersion(Profile $profile): string
    {
        return substr(md5($profile->photo_path.'|'.$profile->initials()), 0, 8);
    }

    /** Photo recadrée au carré ; à défaut, initiales sur un dégradé. Marge de sécurité pour l'icône « maskable ». */
    protected function render(Profile $profile, int $size, bool $maskable): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, true);
        [$r1, $g1, $b1] = sscanf(self::THEME_COLOR, '#%02x%02x%02x');
        [$r2, $g2, $b2] = [20, 184, 166]; // teal-500
        for ($y = 0; $y < $size; $y++) {
            $t = $y / max(1, $size - 1);
            imageline($image, 0, $y, $size, $y, imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }

        $inner = $maskable ? (int) round($size * 0.8) : $size; // zone sûre : 80 % du centre
        $offset = intdiv($size - $inner, 2);
        $photo = $this->photo($profile);

        if ($photo) {
            $side = min(imagesx($photo), imagesy($photo));
            imagecopyresampled($image, $photo, $offset, $offset, intdiv(imagesx($photo) - $side, 2), intdiv(imagesy($photo) - $side, 2), $inner, $inner, $side, $side);
            imagedestroy($photo);
        } else {
            $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
            $text = $profile->initials();
            $white = imagecolorallocate($image, 255, 255, 255);
            if (is_file($font)) {
                $fontSize = $inner * 0.3;
                $box = imagettfbbox($fontSize, 0, $font, $text);
                $x = (int) (($size - ($box[2] - $box[0])) / 2 - $box[0]);
                $y = (int) (($size - ($box[1] - $box[7])) / 2 - $box[7]);
                imagettftext($image, $fontSize, 0, $x, $y, $white, $font, $text);
            } else {
                imagestring($image, 5, intdiv($size, 2) - 9, intdiv($size, 2) - 8, $text, $white);
            }
        }

        ob_start();
        imagepng($image, null, 9);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    protected function photo(Profile $profile): ?\GdImage
    {
        try {
            if (! $profile->photo_path || ! Storage::disk('public')->exists($profile->photo_path)) {
                return null;
            }

            return imagecreatefromstring(Storage::disk('public')->get($profile->photo_path)) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
