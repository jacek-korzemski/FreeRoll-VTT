<?php

namespace Tests\Feature;

use App\Exceptions\VttSourceMissingException;
use App\Livewire\TablesDashboard;
use App\Models\User;
use App\Services\TableProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class TableProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = storage_path('framework/testing/vtt-'.uniqid('', true));
        $source = $this->tempRoot.DIRECTORY_SEPARATOR.'source';
        $tables = $this->tempRoot.DIRECTORY_SEPARATOR.'tables';

        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'assets');
        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data');
        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor');
        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Ttrpg');
        File::put($source.DIRECTORY_SEPARATOR.'index.php', "<?php echo 'vtt';\n");
        File::put($source.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'index.js', "console.log('ok');\n");
        File::put($source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php', "<?php\n");
        File::put($source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Ttrpg'.DIRECTORY_SEPARATOR.'Actions.php', "<?php\n");
        File::put($source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json', '{"version":1}');
        File::put($source.DIRECTORY_SEPARATOR.'.env', "VTT_ENABLE_L5R=true\nVTT_PASSWORD=old\n");

        config([
            'vtt.source_path' => $source,
            'vtt.tables_path' => $tables,
            'vtt.max_tables' => 3,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempRoot) && File::isDirectory($this->tempRoot)) {
            File::deleteDirectory($this->tempRoot);
        }

        parent::tearDown();
    }

    public function test_create_copies_source_writes_env_and_resets_state(): void
    {
        $user = User::factory()->create(['username' => 'janek']);
        $provisioner = app(TableProvisioner::class);

        $table = $provisioner->create($user, [
            'name' => 'Sesja 1',
            'player_password' => 'gracze',
            'gm_password' => 'mistrz',
            'language' => 'pl',
        ]);

        $dir = $table->absolutePath();
        $this->assertFileExists($dir.DIRECTORY_SEPARATOR.'index.php');
        $this->assertFileExists($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'index.js');
        $this->assertFileExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json');

        $env = File::get($dir.DIRECTORY_SEPARATOR.'.env');
        $this->assertStringContainsString('VTT_PASSWORD=gracze', $env);
        $this->assertStringContainsString('VTT_GM_PASSWORD=mistrz', $env);
        $this->assertStringContainsString('VTT_BASE_PATH=/vtt/user/janek/'.$table->slug.'/', $env);
        $this->assertStringContainsString('VTT_LANGUAGE=pl', $env);
        $this->assertStringContainsString('VTT_ENABLE_L5R=true', $env);
    }

    public function test_user_cannot_create_more_than_three_tables(): void
    {
        $user = User::factory()->create(['username' => 'limit-user']);
        $provisioner = app(TableProvisioner::class);
        $payload = [
            'name' => 'Stół',
            'player_password' => 'p',
            'gm_password' => 'g',
            'language' => 'en',
        ];

        $provisioner->create($user, $payload);
        $provisioner->create($user, $payload);
        $provisioner->create($user, $payload);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $provisioner->create($user, $payload);
    }

    public function test_destroy_removes_files_and_record(): void
    {
        $user = User::factory()->create(['username' => 'deleter']);
        $provisioner = app(TableProvisioner::class);
        $table = $provisioner->create($user, [
            'name' => 'Do skasowania',
            'player_password' => 'p',
            'gm_password' => 'g',
            'language' => 'pl',
        ]);
        $dir = $table->absolutePath();
        $this->assertDirectoryExists($dir);

        $provisioner->destroy($table);

        $this->assertDirectoryDoesNotExist($dir);
        $this->assertDatabaseMissing('vtt_tables', ['id' => $table->id]);
    }

    public function test_missing_source_throws(): void
    {
        config(['vtt.source_path' => $this->tempRoot.DIRECTORY_SEPARATOR.'missing']);
        $user = User::factory()->create(['username' => 'nosource']);

        $this->expectException(VttSourceMissingException::class);
        app(TableProvisioner::class)->create($user, [
            'name' => 'X',
            'player_password' => 'p',
            'gm_password' => 'g',
            'language' => 'pl',
        ]);
    }

    public function test_source_without_vendor_is_not_ready(): void
    {
        File::delete($this->tempRoot.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');

        $this->assertFalse(app(TableProvisioner::class)->sourceIsReady());
    }

    public function test_livewire_can_create_a_table(): void
    {
        $user = User::factory()->create(['username' => 'livewire-user']);

        Livewire::actingAs($user)
            ->test(TablesDashboard::class)
            ->set('name', 'Kampania')
            ->set('player_password', 'players')
            ->set('gm_password', 'gmsecret')
            ->set('language', 'en')
            ->call('createTable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vtt_tables', [
            'user_id' => $user->id,
            'name' => 'Kampania',
            'language' => 'en',
        ]);
    }

    public function test_create_form_renders_native_inputs(): void
    {
        $user = User::factory()->create(['username' => 'inputs']);

        $html = Livewire::actingAs($user)
            ->test(TablesDashboard::class)
            ->html();

        $this->assertStringContainsString('wire:model="name"', $html);
        $this->assertStringContainsString('<input', $html);
        $this->assertStringNotContainsString('<x-text-input', $html);
    }

    public function test_create_table_shows_polish_validation_errors(): void
    {
        $user = User::factory()->create(['username' => 'walidacja']);

        Livewire::actingAs($user)
            ->test(TablesDashboard::class)
            ->call('createTable')
            ->assertHasErrors(['name', 'player_password', 'gm_password'])
            ->assertSee('Podaj nazwę stołu.')
            ->assertSee('Podaj hasło gracza.')
            ->assertSee('Podaj hasło Mistrza Gry.');
    }
}
