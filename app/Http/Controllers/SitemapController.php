<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Page;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Theme;
use Illuminate\Http\Response;

/**
 * sitemap.xml : pages publiques, projets, thèmes et articles publiés.
 * Vide lorsque le site est désindexé.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect();

        if (Setting::siteIsIndexable()) {
            $pages = Page::visibleTo(null);
            $urls = $pages->map(fn (Page $page) => ['loc' => $page->url(), 'lastmod' => $page->updated_at]);

            if ($pages->contains('key', 'projets')) {
                $urls = $urls
                    ->merge(Project::orderByDesc('published_on')->get()->map(fn (Project $p) => ['loc' => route('projects.show', $p), 'lastmod' => $p->updated_at]))
                    ->merge(Theme::whereHas('projects')->get()->map(fn (Theme $t) => ['loc' => route('projects.theme', $t), 'lastmod' => $t->updated_at]));
            }

            if ($pages->contains('key', 'veille')) {
                $urls = $urls->merge(Article::published()->orderByDesc('published_at')->get()
                    ->map(fn (Article $a) => ['loc' => route('articles.show', $a), 'lastmod' => $a->updated_at]));
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>'.($url['lastmod'] ? '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>' : '')."</url>\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
