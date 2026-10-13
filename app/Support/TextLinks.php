<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Texte libre échappé dont les adresses web deviennent des liens courts et cliquables. */
final class TextLinks
{
    public static function render(?string $text): HtmlString
    {
        $html = preg_replace_callback('~https?://[^\s<>"]+~i', function (array $match) {
            // Ponctuation finale (parenthèse fermante, point…) laissée hors du lien
            $url = rtrim($match[0], '.,;:!?)');
            $rest = substr($match[0], strlen($url));
            $raw = html_entity_decode($url, ENT_QUOTES);
            $label = preg_replace('~^www\.~', '', (string) parse_url($raw, PHP_URL_HOST)).Str::limit((string) parse_url($raw, PHP_URL_PATH), 28);

            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer" title="'.$url.'" class="font-medium text-primary-600 underline decoration-primary-300 underline-offset-2 hover:decoration-primary-600">'.e($label).'</a>'.$rest;
        }, e((string) $text));

        return new HtmlString($html);
    }
}
