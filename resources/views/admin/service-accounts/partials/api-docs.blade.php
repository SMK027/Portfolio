<details class="card p-4 sm:p-6">
    <summary class="cursor-pointer font-display font-semibold text-slate-900">Documentation de l'API</summary>
    <div class="mt-4 space-y-4 text-sm text-slate-700">
        <p>Base : <code>{{ url('/api/v1') }}</code> — authentification par en-tête <code>Authorization: Bearer &lt;code&gt;</code>. Réponses en JSON ; 120 requêtes par minute et par code.</p>
        <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">curl -H "Authorization: Bearer pfs_…" -H "Accept: application/json" {{ url('/api/v1/me') }}

curl -X POST {{ url('/api/v1/articles') }} \
  -H "Authorization: Bearer pfs_…" -H "Content-Type: application/json" \
  -d '{"title":"Mon article","content_markdown":"## Intro\n\nTexte **gras**","themes":["Réseau"],"submit_for_review":true}'</pre>
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Requête</th><th>Autorisation</th><th>Remarques</th></tr></thead>
                <tbody class="divide-y divide-slate-100 font-normal">
                    @foreach ([
                        ['GET /me', '—', 'Compte, autorisations et code utilisés'],
                        ['GET /articles', 'articles.read', 'Filtres : status (draft, pending_review, scheduled, published), q, per_page'],
                        ['GET /articles/{id}', 'articles.read', 'Contenu Editor.js et Markdown'],
                        ['POST /articles', 'articles.write', 'title, excerpt, content | content_markdown | content_html, themes (noms), author, coauthors (e-mails), submit_for_review'],
                        ['PATCH /articles/{id}', 'articles.write', 'Mêmes champs, tous facultatifs'],
                        ['status, published_at, is_pinned', 'articles.publish', 'Champs de publication acceptés par POST / PATCH'],
                        ['POST /articles/{id}/publish · /unpublish', 'articles.publish', 'published_at facultatif (date future = programmation)'],
                        ['DELETE /articles/{id}', 'articles.delete', ''],
                        ['GET /projects · /projects/{id}', 'projects.read', ''],
                        ['POST /projects · PATCH /projects/{id}', 'projects.write', 'title, published_on, description | description_markdown | description_html, themes, skills (noms), links [{label, url}]'],
                        ['DELETE /projects/{id}', 'projects.delete', ''],
                        ['GET /announcements · /announcements/{id}', 'announcements.read', ''],
                        ['POST · PATCH /announcements', 'announcements.write', 'title, message, style, link_url, link_label, is_active, starts_at, ends_at…'],
                        ['GET /messages · /messages/{id}', 'messages.read', 'Filtre unread ; chaque lecture est journalisée'],
                        ['GET /export', 'content.export', 'sections[] facultatif ; même format que la page Import / export'],
                        ['POST /import', 'content.import', 'data (fichier d\'export), sections, dry_run (obligatoire : true = simulation)'],
                        ['DELETE /announcements/{id}', 'announcements.delete', ''],
                        ['GET /maintenance', 'maintenance.read ou maintenance.manage', ''],
                        ['PUT /maintenance', 'maintenance.manage', 'enabled, ends_at, reason'],
                    ] as [$route, $permission, $notes])
                        <tr><td class="whitespace-nowrap font-mono text-xs">{{ $route }}</td><td class="font-mono text-xs">{{ $permission }}</td><td class="text-xs text-slate-500">{{ $notes }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-500">Thèmes et compétences doivent exister (sinon erreur 422). Les fichiers (images, pièces jointes) se gèrent depuis le panel.</p>
    </div>
</details>
