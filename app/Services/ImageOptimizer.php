<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Optimisation des images envoyées : redimensionnement (côté le plus long limité),
 * orientation EXIF appliquée, conversion en WebP (qualité 82, transparence conservée).
 *
 * Laissées telles quelles : GIF (animations), formats inconnus, images trop grandes
 * pour être décodées sans risque, et images que l'optimisation n'allège pas.
 * Sans prise en charge du WebP par GD, l'image est redimensionnée dans son format d'origine.
 */
class ImageOptimizer
{
    /** Côté le plus long par défaut (pixels) : suffisant pour un écran large en haute densité. */
    public const MAX_DIMENSION = 1920;

    /** Au-delà, l'image n'est pas décodée (mémoire : ~4 octets par pixel). */
    protected const MAX_PIXELS = 25_000_000;

    protected const QUALITY = 82;

    /**
     * Version optimisée d'une image, ou null s'il vaut mieux garder l'original.
     *
     * @return array{binary: string, extension: string, mime: string}|null
     */
    public function optimize(string $binary, int $maxDimension = self::MAX_DIMENSION): ?array
    {
        $info = @getimagesizefromstring($binary);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        try {
            $image = @imagecreatefromstring($binary);
            if (! $image instanceof GdImage) {
                return null;
            }

            if ($info[2] === IMAGETYPE_JPEG) {
                $image = $this->applyExifOrientation($image, $binary);
            }

            $resized = $this->resize($image, $maxDimension);
            [$data, $extension, $mime] = $this->encode($resized, $info[2]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        // Ni redimensionnée ni allégée : l'original est conservé.
        if ($data === null || (strlen($data) >= strlen($binary) && max($info[0], $info[1]) <= $maxDimension)) {
            return null;
        }

        return ['binary' => $data, 'extension' => $extension, 'mime' => $mime];
    }

    /**
     * Enregistre un fichier envoyé, optimisé si possible.
     *
     * @return array{path: string, mime: string|null, size: int, optimized: bool}
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public', int $maxDimension = self::MAX_DIMENSION): array
    {
        $optimized = $this->optimize((string) file_get_contents($file->getRealPath()), $maxDimension);

        if (! $optimized) {
            return ['path' => $file->store($directory, $disk), 'mime' => null, 'size' => (int) $file->getSize(), 'optimized' => false];
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$optimized['extension'];
        Storage::disk($disk)->put($path, $optimized['binary']);

        return ['path' => $path, 'mime' => $optimized['mime'], 'size' => strlen($optimized['binary']), 'optimized' => true];
    }

    public static function supportsWebp(): bool
    {
        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
    }

    protected function resize(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = $maxDimension / max($width, $height);

        if ($ratio >= 1) {
            return $image;
        }

        $target = imagecreatetruecolor(max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        return $target;
    }

    /** @return array{0: string|null, 1: string, 2: string} */
    protected function encode(GdImage $image, int $type): array
    {
        imagesavealpha($image, true);
        ob_start();

        if (self::supportsWebp()) {
            $ok = imagewebp($image, null, self::QUALITY);
            [$extension, $mime] = ['webp', 'image/webp'];
        } elseif ($type === IMAGETYPE_PNG) {
            $ok = imagepng($image, null, 9);
            [$extension, $mime] = ['png', 'image/png'];
        } else {
            $ok = imagejpeg($image, null, 85);
            [$extension, $mime] = ['jpg', 'image/jpeg'];
        }

        $data = ob_get_clean();

        return [$ok && $data !== '' ? $data : null, $extension, $mime];
    }

    /** Photos de téléphone : la rotation est indiquée dans les métadonnées EXIF, que WebP n'emporte pas. */
    protected function applyExifOrientation(GdImage $image, string $binary): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
