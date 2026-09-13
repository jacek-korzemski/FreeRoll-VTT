<?php

namespace Tests\Feature;

use App\Livewire\TablesDashboard;
use App\Models\User;
use App\Services\StorageQuota;
use App\Services\TableProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class StorageQuotaTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = storage_path('framework/testing/vtt-quota-'.uniqid('', true));
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
        File::put($source.DIRECTORY_SEPARATOR.'.env', "VTT_ENABLE_L5R=false\n");

        config([
            'vtt.source_path' => $source,
            'vtt.tables_path' => $tables,
            'vtt.max_tables' => 3,
            'vtt.max_table_upload_mb' => 50,
            'vtt.max_user_upload_mb' => null,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempRoot) && File::isDirectory($this->tempRoot)) {
            File::deleteDirectory($this->tempRoot);
        }

        parent::tearDown();
    }

    public function test_user_limit_is_three_tables_times_table_limit(): void
    {
        $quota = app(StorageQuota::class);

        $this->assertSame(50 * 1048576, $quota->tableLimitBytes());
        $this->assertSame(150 * 1048576, $quota->userLimitBytes());
    }

    public function test_dashboard_shows_table_and_account_storage(): void
    {
        $user = User::factory()->create(['username' => 'quota-user']);
        $table = app(TableProvisioner::class)->create($user, [
            'name' => 'Sesja',
            'player_password' => 'gracze',
            'gm_password' => 'mistrz',
            'language' => 'pl',
        ]);

        $tokens = $table->absolutePath().DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'tokens';
        File::ensureDirectoryExists($tokens);
        File::put($tokens.DIRECTORY_SEPARATOR.'hero.png', str_repeat('x', 2048));

        Livewire::actingAs($user)
            ->test(TablesDashboard::class)
            ->assertSee('50 MB na stół')
            ->assertSee('Limit konta: 150 MB')
            ->assertSee('2.0 KB');
    }
}
