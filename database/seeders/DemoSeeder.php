<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Certification;
use App\Models\Diploma;
use App\Models\Education;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Skill;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Contenu de démonstration pour visualiser le portfolio.
 * Usage : docker compose -f docker-compose.dev.yml exec -u www-data app php artisan db:seed --class=DemoSeeder
 * (n'est jamais exécuté automatiquement)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereIn('global_role', ['admin', 'superadmin'])->firstOrFail();

        Profile::current()->update([
            'first_name' => 'Camille',
            'last_name'  => 'Martin',
            'headline'   => 'Développeuse web & administratrice réseau',
            'location'   => 'Lyon, France',
            'email'      => 'camille.martin@example.com',
            'social_links' => [['name' => 'GitHub', 'url' => 'https://github.com/example']],
            'about'      => ['blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => 'Passionnée par le <b>développement web</b> et les <mark class="cdx-marker">infrastructures réseau</mark>, je conçois des applications robustes et sécurisées.']],
                ['type' => 'paragraph', 'data' => ['text' => 'Ce portfolio présente mon parcours, mes projets et ma veille technologique.']],
            ]],
        ]);

        Education::create(['title' => 'BTS SIO option SLAM', 'institution' => 'Lycée La Martinière', 'location' => 'Lyon', 'start_date' => '2024-09-01', 'end_date' => null, 'description' => "Développement d'applications, bases de données, cybersécurité."]);
        Education::create(['title' => 'Baccalauréat STI2D', 'institution' => 'Lycée Ampère', 'location' => 'Lyon', 'start_date' => '2021-09-01', 'end_date' => '2024-07-05']);
        Diploma::create(['title' => 'Baccalauréat STI2D — SIN', 'institution' => 'Académie de Lyon', 'level' => 'Niveau 4', 'mention' => 'Bien', 'obtained_at' => '2024-07-05']);
        Certification::create(['name' => 'CCNA : Introduction to Networks', 'issuer' => 'Cisco', 'issued_at' => '2025-03-15', 'credential_url' => 'https://www.credly.com/']);
        Certification::create(['name' => 'Pix — Compétences numériques', 'issuer' => 'Pix', 'issued_at' => '2024-05-10', 'expires_at' => '2027-05-10']);

        $skills = collect([
            ['PHP', 'Back-end', 4], ['Laravel', 'Back-end', 4], ['MySQL', 'Back-end', 3],
            ['JavaScript', 'Front-end', 3], ['Tailwind CSS', 'Front-end', 4],
            ['Cisco IOS', 'Réseau', 3], ['Linux', 'Système', 4], ['Docker', 'Système', 3],
        ])->map(fn ($s, $i) => Skill::create(['name' => $s[0], 'category' => $s[1], 'level' => $s[2], 'position' => $i]));

        $themes = collect(['Programmation', 'Réseau', 'Projets personnels'])
            ->map(fn ($name, $i) => Theme::create([
                'name'            => $name,
                'position'        => $i,
                'description'     => "Mes projets et articles liés au thème « $name ».",
                'background_path' => $this->image('themes', 1600, 900, $i),
            ]));

        foreach ([
            ['Portfolio Laravel', 0, [0, 1, 4, 7], 'Conception de ce portfolio : Laravel, Tailwind CSS, Editor.js, Docker.'],
            ['Maquette réseau d\'entreprise', 1, [5, 6], "Conception d'une architecture réseau multi-sites avec VLAN, routage inter-VLAN et ACL."],
            ['Application de gestion de stock', 0, [0, 2, 3], 'Application web de gestion de stock avec tableaux de bord et exports Excel.'],
            ['Serveur domotique', 2, [6, 7], 'Serveur domotique auto-hébergé sur Raspberry Pi avec Home Assistant.'],
        ] as $i => [$title, $theme, $skillIndexes, $description]) {
            $project = Project::create(['title' => $title, 'published_on' => now()->subMonths($i * 3), 'description' => $description."\n\nContexte, objectifs, réalisation et bilan du projet."]);
            $project->themes()->sync([$themes[$theme]->id]);
            $project->skills()->sync($skills->only($skillIndexes)->pluck('id'));
            $project->links()->create(['url' => 'https://github.com/example/'.str($title)->slug(), 'position' => 0]);

            foreach (range(0, 2) as $n) {
                $path = 'projects/'.$project->id.'/demo-'.$n.'.png';
                Storage::disk(ProjectFile::DISK)->put($path, $this->png(1280, 720, $i + $n));
                $file = $project->files()->create(['path' => $path, 'original_name' => "capture-$n.png", 'mime_type' => 'image/png', 'size' => Storage::disk(ProjectFile::DISK)->size($path), 'is_image' => true, 'position' => $n]);
                if ($n === 0) {
                    $project->update(['thumbnail_file_id' => $file->id]);
                }
            }
        }

        foreach ([
            ['Les nouveautés de PHP 8.4', true, 2, 0],
            ['Wi-Fi 7 : ce qui change vraiment', false, 5, 1],
            ['Sécuriser une API Laravel', false, 12, 0],
        ] as [$title, $pinned, $daysAgo, $theme]) {
            $article = Article::create([
                'title'        => $title,
                'excerpt'      => 'Un tour d\'horizon synthétique pour comprendre l\'essentiel en quelques minutes.',
                'author_id'    => $admin->id,
                'is_pinned'    => $pinned,
                'published_at' => now()->subDays($daysAgo),
                'content'      => ['blocks' => [
                    ['type' => 'header', 'data' => ['text' => 'Introduction', 'level' => 2]],
                    ['type' => 'paragraph', 'data' => ['text' => 'Un paragraphe avec du <font color="#2563eb">texte coloré</font>, du <i>italique</i> et un <a href="https://www.php.net">lien</a>.']],
                    ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [['content' => 'Premier point', 'meta' => [], 'items' => []], ['content' => 'Second point', 'meta' => [], 'items' => []]]]],
                    ['type' => 'quote', 'data' => ['text' => 'La simplicité est la sophistication suprême.', 'caption' => 'Léonard de Vinci'], 'tunes' => ['alignment' => ['alignment' => 'center']]],
                    ['type' => 'code', 'data' => ['code' => "<?php\n\necho 'Bonjour';"]],
                ]],
            ]);
            $article->themes()->sync([$themes[$theme]->id]);
        }
    }

    /** Génère une image PNG unie en dégradé (GD). */
    protected function png(int $w, int $h, int $seed): string
    {
        $palette = [[79, 70, 229], [13, 148, 136], [219, 39, 119], [234, 88, 12], [37, 99, 235], [22, 163, 74]];
        [$r, $g, $b] = $palette[$seed % count($palette)];
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y += 4) {
            $f = $y / $h;
            $color = imagecolorallocate($img, (int) ($r * (1 - $f * .6)), (int) ($g * (1 - $f * .6)), (int) ($b * (1 - $f * .6)));
            imagefilledrectangle($img, 0, $y, $w, $y + 4, $color);
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    protected function image(string $dir, int $w, int $h, int $seed): string
    {
        $path = $dir.'/demo-'.$seed.'.png';
        Storage::disk('public')->put($path, $this->png($w, $h, $seed + 2));

        return $path;
    }
}
