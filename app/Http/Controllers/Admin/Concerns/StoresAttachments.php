<?php

namespace App\Http\Controllers\Admin\Concerns;

use Closure;
use finfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Téléversement et suppression des pièces jointes (images et documents)
 * d'un projet ou d'un article. Le modèle de fichier utilise IsAttachment.
 */
trait StoresAttachments
{
    /**
     * Règles de validation des champs "files[]" et "delete_files[]".
     *
     * @param  class-string  $fileClass
     * @return array<string, mixed>
     */
    protected function attachmentRules(string $fileClass): array
    {
        return [
            'files'          => ['nullable', 'array', 'max:30'],
            'files.*'        => ['file', 'max:20480', 'extensions:'.implode(',', $fileClass::allowedExtensions()), $this->imageContentRule($fileClass)],
            'delete_files'   => ['nullable', 'array'],
            'delete_files.*' => ['integer'],
        ];
    }

    /**
     * Supprime les fichiers cochés puis enregistre les nouveaux.
     * Retourne les fichiers créés, indexés comme dans la requête.
     *
     * @param  class-string  $fileClass
     * @return array<int, Model>
     */
    protected function syncAttachments(Request $request, HasMany $files, string $fileClass, string $directory): array
    {
        $toDelete = array_filter((array) $request->input('delete_files', []), 'is_numeric');
        if ($toDelete) {
            (clone $files)->whereIn('id', $toDelete)->get()->each->delete();
        }

        $created = [];
        $position = (int) (clone $files)->max('position');

        foreach ($request->file('files', []) as $index => $upload) {
            $isImage = in_array(strtolower($upload->getClientOriginalExtension()), $fileClass::IMAGE_EXTENSIONS, true);

            $created[$index] = $files->create([
                'path'          => $upload->store($directory, $fileClass::DISK),
                'original_name' => mb_substr(basename($upload->getClientOriginalName()), 0, 255),
                'mime_type'     => $this->detectMime($upload),
                'size'          => $upload->getSize(),
                'is_image'      => $isImage,
                'position'      => ++$position,
            ]);
        }

        return $created;
    }

    /** Type MIME déterminé à partir du contenu réel du fichier. */
    protected function detectMime(UploadedFile $file): string
    {
        return (string) ((new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: 'application/octet-stream');
    }

    /**
     * Un fichier portant une extension d'image doit réellement être une image
     * (le contenu est vérifié, pas seulement l'extension).
     *
     * @param  class-string  $fileClass
     */
    protected function imageContentRule(string $fileClass): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($fileClass) {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $isImageExtension = in_array(strtolower($value->getClientOriginalExtension()), $fileClass::IMAGE_EXTENSIONS, true);

            if ($isImageExtension && ! in_array($this->detectMime($value), $fileClass::IMAGE_MIMES, true)) {
                $fail('Le fichier « '.$value->getClientOriginalName().' » n\'est pas une image valide.');
            }
        };
    }
}
