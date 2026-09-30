<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use App\Support\Mojibake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EncodingRepairTest extends TestCase
{
    use RefreshDatabase;

    /** Texte correct → même texte abîmé (UTF-8 relu en Windows-1252). */
    protected function break(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    public function test_the_reported_sentence_is_repaired(): void
    {
        $broken = 'Ce stage a Ã©tÃ© lâ€™occasion de dÃ©couvrir le dÃ©veloppement en environnement professionnel, avec des pratiques '
            .'comme la revue de code, le dÃ©bogage et lâ€™intÃ©gration de bibliothÃ¨ques internes.';

        $this->assertSame(
            'Ce stage a été l’occasion de découvrir le développement en environnement professionnel, avec des pratiques '
            .'comme la revue de code, le débogage et l’intégration de bibliothèques internes.',
            Mojibake::fix($broken)
        );
    }

    /** @return array<string, array{string}> */
    public static function frenchTexts(): array
    {
        return [
            'accents'         => ['Ça été une expérience déterminante à Besançon, où j’ai appris « l’essentiel » — en équipe.'],
            'majuscules'      => ['À PROPOS : ÉCOLE, ÊTRE, ÎLE, ÔTER, ÙÛÜ, Œuvre, Ÿ'],
            'symboles'        => ['Prix : 12 € … 50 % • 3° ™ ©'],
            'autres langues'  => ['São Paulo, Ñandú, Ærø, Größe, Ångström'],
        ];
    }

    #[DataProvider('frenchTexts')]
    public function test_correct_text_is_never_modified(string $text): void
    {
        $this->assertSame($text, Mojibake::fix($text));
    }

    #[DataProvider('frenchTexts')]
    public function test_broken_text_is_restored(string $text): void
    {
        $this->assertSame($text, Mojibake::fix($this->break($text)));
        $this->assertSame($text, Mojibake::fix($this->break($this->break($text))), 'double mauvais encodage');
    }

    public function test_mixed_correct_and_broken_segments(): void
    {
        // « à » abîmé avec son espace insécable (U+00A0), puis avec une espace normale
        $this->assertSame('Déjà vu et déjà corrigé', Mojibake::fix("Déjà vu et dÃ©jÃ\u{00A0} corrigÃ©"));
        $this->assertSame('Déjà vu et déjà corrigé', Mojibake::fix('Déjà vu et dÃ©jÃ  corrigÃ©'));
        $this->assertSame('Rendez-vous à la réunion.', Mojibake::fix('Rendez-vous Ã  la rÃ©union.'));
        $this->assertSame('Rendez-vous à la réunion, voilà.', Mojibake::fix('Rendez-vous Ã la rÃ©union, voilÃ.'));
    }

    public function test_capital_a_tilde_is_kept_without_other_broken_sequences(): void
    {
        $this->assertSame('IRMÃ ET SÃO', Mojibake::fix('IRMÃ ET SÃO'));
    }

    public function test_command_simulates_then_repairs_plain_and_json_columns(): void
    {
        DB::table('experiences')->insert([
            'title' => 'DÃ©veloppeur', 'company' => 'SociÃ©tÃ©', 'date_precision' => 'year', 'start_date' => '2024-01-01',
            // Contenu Editor.js enregistré avec échappement unicode (Ã©…)
            'description' => json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'lâ€™intÃ©gration']]]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('portfolio:repair-encoding')->expectsOutputToContain('Simulation : 3 champ(s)')->assertSuccessful();
        $this->assertSame('DÃ©veloppeur', DB::table('experiences')->value('title'));

        $this->artisan('portfolio:repair-encoding --apply')->expectsOutputToContain('3 champ(s) réparé(s)')->assertSuccessful();

        $experience = Experience::sole();
        $this->assertSame('Développeur', $experience->title);
        $this->assertSame('Société', $experience->company);
        $this->assertSame('l’intégration', $experience->description['blocks'][0]['data']['text']);

        $this->artisan('portfolio:repair-encoding')->expectsOutput('Aucun texte mal encodé trouvé.')->assertSuccessful();
    }

    public function test_import_repairs_broken_files_and_reports_it(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $payload = ['sections' => ['experiences' => [[
            'title' => 'Stagiaire', 'company' => 'Entreprise', 'start_date' => '2024',
            'description' => 'Ce stage a Ã©tÃ© lâ€™occasion de dÃ©couvrir le dÃ©bogage.',
        ]]]];

        $this->actingAs($admin)->post(route('admin.transfer.preview'), [
            'file' => UploadedFile::fake()->createWithContent('i.json', json_encode($payload)), 'sections' => ['experiences'],
        ]);
        $this->actingAs($admin)->get(route('admin.transfer.index'))->assertSee('accents mal encodés');
        $this->actingAs($admin)->post(route('admin.transfer.import'));

        $this->assertSame('Ce stage a été l’occasion de découvrir le débogage.', Experience::sole()->description['blocks'][0]['data']['text']);
    }
}
