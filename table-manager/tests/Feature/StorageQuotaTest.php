<?php

namespace Tests\Feature;

use App\Livewire\Admin\TableShow;
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
        $user = User::factory()->create();

        $this->assertSame(50 * 1048576, $quota->tableLimitBytes());
        $this->assertSame(150 * 1048576, $quota->userLimitBytes($user));
    }

    public function test_overrides_raise_table_and_account_limits(): void
    {
        $user = User::factory()->create([
            'username' => 'grant-user',
            'max_tables' => 5,
        ]);
        $table = app(TableProvisioner::class)->create($user, [
            'name' => 'Sesja',
            'player_password' => 'gracze',
            'gm_password' => 'mistrz',
            'language' => 'pl',
        ]);
        $table->upload_quota_mb = 200;
        $table->save();

        $quota = app(StorageQuota::class);

        $this->assertSame(200 * 1048576, $quota->tableLimitBytes($table->fresh()));
        $this->assertSame((200 + 4 * 50) * 1048576, $quota->userLimitBytes($user->fresh()));
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

    public function test_dashboard_shows_granted_table_quota(): void
    {
        $user = User::factory()->create([
            'username' => 'meter-user',
            'max_tables' => 5,
        ]);
        $table = app(TableProvisioner::class)->create($user, [
            'name' => 'Sesja',
            'player_password' => 'gracze',
            'gm_password' => 'mistrz',
            'language' => 'pl',
        ]);
        $table->upload_quota_mb = 200;
        $table->save();

        Livewire::actingAs($user)
            ->test(TablesDashboard::class)
            ->assertSee('200.0 MB')
            ->assertSee('Limit konta: 400 MB');
    }

    public function test_admin_can_set_and_clear_limits(): void
    {
        $user = User::factory()->create(['username' => 'admin-grant']);
        $table = app(TableProvisioner::class)->create($user, [
            'name' => 'Sesja',
            'player_password' => 'gracze',
            'gm_password' => 'mistrz',
            'language' => 'pl',
        ]);
        $envPath = $table->absolutePath().DIRECTORY_SEPARATOR.'.env';

        session(['admin_authenticated' => true]);

        Livewire::test(TableShow::class, ['table' => $table])
            ->set('ownerMaxTables', '4')
            ->set('uploadQuotaMb', '200')
            ->call('saveLimits')
            ->assertHasNoErrors()
            ->assertSet('ownerMaxTables', '4')
            ->assertSet('uploadQuotaMb', '200');

        $this->assertSame(4, $user->fresh()->max_tables);
        $this->assertSame(200, $table->fresh()->upload_quota_mb);
        $this->assertStringContainsString('VTT_TABLE_UPLOAD_QUOTA_MB=200', File::get($envPath));

        Livewire::test(TableShow::class, ['table' => $table->fresh()])
            ->set('ownerMaxTables', '')
            ->set('uploadQuotaMb', '')
            ->call('saveLimits')
            ->assertHasNoErrors()
            ->assertSet('ownerMaxTables', '')
            ->assertSet('uploadQuotaMb', '');

        $this->assertNull($user->fresh()->max_tables);
        $this->assertNull($table->fresh()->upload_quota_mb);
        $this->assertStringContainsString('VTT_TABLE_UPLOAD_QUOTA_MB=50', File::get($envPath));
    }
}
