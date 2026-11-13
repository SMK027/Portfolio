<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EnvironmentCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EnvironmentCheckTest extends TestCase
{
    use RefreshDatabase;

    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/env-check-'.uniqid();
        File::makeDirectory($this->dir.'/config', recursive: true);
        File::put($this->dir.'/config/app.php', "<?php return ['x' => env('USED_IN_CODE')];");
        File::put($this->dir.'/.env.example', <<<'ENV'
            APP_ENV=local
            APP_KEY=
            APP_URL=http://localhost
            APP_DEBUG=true
            DB_CONNECTION=mysql
            DB_HOST=db
            DB_DATABASE=portfolio
            DB_USERNAME=user
            DB_PASSWORD=secret_example
            MAIL_MAILER=smtp
            MAIL_FROM_ADDRESS="portfolio@example.com"
            LOGIN_BAN_DURATION=45
            GITHUB_TOKEN=
            # CACHE_PREFIX=
            APP_PORT=8120
            ENV);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** @param array<string, string> $process variables du processus (conteneur) */
    protected function check(string $env, ?bool $production = null, array $process = []): array
    {
        File::put($this->dir.'/.env', $env);

        return (new EnvironmentCheck($this->dir, $process))->run($production);
    }

    protected function levels(array $result): array
    {
        return collect($result['issues'])->mapWithKeys(fn ($i) => [$i['key'] => $i['level']])->all();
    }

    protected const VALID = <<<'ENV'
        APP_ENV=local
        APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
        APP_URL=http://localhost
        APP_DEBUG=false
        DB_CONNECTION=mysql
        DB_HOST=db
        DB_DATABASE=portfolio
        DB_USERNAME=user
        DB_PASSWORD=autre
        MAIL_MAILER=smtp
        MAIL_FROM_ADDRESS=moi@domaine.fr
        LOGIN_BAN_DURATION=45
        ENV;

    public function test_complete_configuration_has_no_errors_or_warnings(): void
    {
        $result = $this->check(self::VALID);

        $this->assertSame(0, $result['counts']['error']);
        $this->assertSame(0, $result['counts']['warning']);
        $this->assertSame(['GITHUB_TOKEN' => 'info'], $this->levels($result)); // facultative (vide dans le modèle)
    }

    public function test_missing_critical_and_documented_variables(): void
    {
        $levels = $this->levels($this->check("APP_ENV=local\nAPP_KEY=court\nDB_PASSWORD=\nMAIL_FROM_ADDRESS=pas-une-adresse\nAPP_DEBGU=true\nCACHE_PREFIX=x\nUSED_IN_CODE=1"));

        $this->assertSame('error', $levels['DB_HOST']);          // critique absente
        $this->assertSame('error', $levels['DB_PASSWORD']);      // critique vide
        $this->assertSame('error', $levels['APP_KEY']);          // format invalide
        $this->assertSame('error', $levels['MAIL_FROM_ADDRESS']);
        $this->assertSame('warning', $levels['LOGIN_BAN_DURATION']); // documentée, absente
        $this->assertSame('info', $levels['APP_DEBGU']);          // faute de frappe
        $this->assertArrayNotHasKey('CACHE_PREFIX', $levels);     // commentée dans le modèle : connue
        $this->assertArrayNotHasKey('USED_IN_CODE', $levels);     // lue par le code : connue
        $this->assertArrayNotHasKey('APP_PORT', $levels);         // propre à Docker

        $typo = collect($this->check("APP_DEBGU=true")['issues'])->firstWhere('key', 'APP_DEBGU');
        $this->assertStringContainsString('vouliez-vous dire APP_DEBUG', $typo['message']);
    }

    public function test_production_rules(): void
    {
        $env = str_replace(['APP_ENV=local', 'DB_PASSWORD=autre', 'MAIL_MAILER=smtp', 'APP_DEBUG=false'], ['APP_ENV=production', 'DB_PASSWORD=secret_example', 'MAIL_MAILER=log', 'APP_DEBUG=true'], self::VALID);
        $result = $this->check($env);
        $levels = $this->levels($result);

        $this->assertTrue($result['production']);
        $this->assertSame('error', $levels['APP_DEBUG']);
        $this->assertSame('error', $levels['DB_PASSWORD']);       // valeur d'exemple
        $this->assertSame('error', $levels['RECAPTCHA_SITE_KEY']);
        $this->assertSame('error', $levels['MAIL_HOST']);
        $this->assertSame('warning', $levels['APP_URL']);         // pas de HTTPS
        $this->assertSame('warning', $levels['MAIL_MAILER']);
    }

    public function test_process_environment_counts_as_configuration(): void
    {
        // Production : pas de .env, variables transmises au conteneur ; elles priment sur le fichier.
        $this->assertArrayNotHasKey('DB_HOST', $this->levels($this->check('', process: ['DB_HOST' => 'db-prod'])));
        $this->assertSame('error', $this->levels($this->check(self::VALID, process: ['DB_HOST' => '']))['DB_HOST']);
    }

    public function test_command_and_diagnostics(): void
    {
        $this->artisan('portfolio:env-check')->assertExitCode(0);
        $this->artisan('portfolio:env-check --production')->assertExitCode(1);

        $this->actingAs(User::factory()->superAdmin()->create());
        $checks = collect($this->getJson(route('admin.diagnostics.data'))->json('groups'))->flatten(1)->keyBy('label');
        $this->assertArrayHasKey('Configuration (.env)', $checks);
    }
}
