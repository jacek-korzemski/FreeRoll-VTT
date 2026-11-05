<?php

namespace Tests\Feature;

use App\Exceptions\TableUpdateException;
use App\Livewire\TablesDashboard;
use App\Models\User;
use App\Models\VttTable;
use App\Services\TableProvisioner;
use App\Services\TableUpdater;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TableUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = storage_path('framework/testing/vtt-update-'.uniqid('', true));
        $this->source = $this->tempRoot.DIRECTORY_SEPARATOR.'source';
        $tables = $this->tempRoot.DIRECTORY_SEPARATOR.'tables';

        File::ensureDirectoryExists($this->source.DIRECTORY_SEPARATOR.'assets');
        File::ensureDirectoryExists($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor');
        File::ensureDirectoryExists($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Ttrpg');
        File::ensureDirectoryExists($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include');
        File::ensureDirectoryExists($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data');
        File::ensureDirectoryExists($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map');
        File::ensureDirectoryExists($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'audio');

        File::put($this->source.DIRECTORY_SEPARATOR.'index.php', "<?php echo 'package-index';\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'index.js', "package-js\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php', "<?php echo 'package-vendor';\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Ttrpg'.DIRECTORY_SEPARATOR.'Actions.php', "<?php echo 'package-actions';\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'fresh.php', "<?php echo 'package-include';\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'api.php', "<?php echo 'package-api';\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json', '{"from":"source"}');
        File::put($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map'.DIRECTORY_SEPARATOR.'from-source.png', 'from-source');
        File::put($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'audio'.DIRECTORY_SEPARATOR.'.gitkeep', '');
        File::put($this->source.DIRECTORY_SEPARATOR.'VERSION', "1\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'.env', "VTT_PASSWORD=package\n");
        File::put($this->source.DIRECTORY_SEPARATOR.'themes.json', json_encode([
            'default' => 'violet',
            'themes' => [
                'violet' => ['name' => ['en' => 'Violet', 'pl' => 'Fiolet'], 'tokens' => []],
            ],
        ], JSON_UNESCAPED_UNICODE));

        config([
            'vtt.source_path' => $this->source,
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

    public function test_update_replaces_code_and_keeps_player_data(): void
    {
        [$table, $dir, $env, $state] = $this->legacyTable();
        $updater = app(TableUpdater::class);

        $updater->update($table);

        $this->assertSame($env, File::get($dir.DIRECTORY_SEPARATOR.'.env'));
        $this->assertSame($state, File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json'));
        $this->assertSame('user-rolls', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'rolls.json'));
        $this->assertSame('390', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.version'));
        $this->assertSame('sqlite-user', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'ttrpg.sqlite'));
        $this->assertSame('session-user', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'sessions'.DIRECTORY_SEPARATOR.'sess_keep'));
        $this->assertSame('keep-me', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'keep.txt'));
        $this->assertSame('hero-bytes', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map'.DIRECTORY_SEPARATOR.'hero.png'));
        $this->assertSame('pdf-bytes', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'papers'.DIRECTORY_SEPARATOR.'sheet.pdf'));
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map'.DIRECTORY_SEPARATOR.'from-source.png');
        $this->assertDirectoryExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'audio');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'audio'.DIRECTORY_SEPARATOR.'.gitkeep');
        $this->assertSame('{"from":"source"}', File::get($this->source.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json'));

        $this->assertSame("<?php echo 'package-index';\n", File::get($dir.DIRECTORY_SEPARATOR.'index.php'));
        $this->assertSame("package-js\n", File::get($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'index.js'));
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'old-chunk.js');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'legacy.php');
        $this->assertSame("<?php echo 'package-include';\n", File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'fresh.php'));
        $this->assertSame(1, $updater->installedVersion($table));
        $this->assertSame('wnagr5o15i', $table->slug);
        $this->assertDirectoryExists($dir);
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'update.lock');
        $this->assertNoSwapArtifacts($dir);

        $before = $this->snapshot($dir);

        try {
            $updater->update($table->fresh('user'));
            $this->fail('A second update at the same version should be refused.');
        } catch (TableUpdateException $e) {
            $this->assertSame('Stół jest już w tej wersji.', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot($dir));
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'old-chunk.js');
    }

    public function test_interrupted_swap_restores_code_and_keeps_state(): void
    {
        [$table, $dir, $env, $state] = $this->legacyTable();
        $updater = new class(app(TableProvisioner::class)) extends TableUpdater
        {
            protected function movePath(string $from, string $to): void
            {
                $target = str_replace('\\', '/', $to);
                if (str_ends_with($target, '/VERSION')) {
                    throw new TableUpdateException('forced');
                }

                parent::movePath($from, $to);
            }
        };

        try {
            $updater->update($table);
            $this->fail('The interrupted update should fail.');
        } catch (TableUpdateException $e) {
            $this->assertStringContainsString('Zamknij stół', $e->getMessage());
        }

        $this->assertSame($env, File::get($dir.DIRECTORY_SEPARATOR.'.env'));
        $this->assertSame($state, File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json'));
        $this->assertSame('sqlite-user', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'ttrpg.sqlite'));
        $this->assertSame('hero-bytes', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map'.DIRECTORY_SEPARATOR.'hero.png'));
        $this->assertSame("<?php echo 'table-index';\n", File::get($dir.DIRECTORY_SEPARATOR.'index.php'));
        $this->assertSame("table-js\n", File::get($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'index.js'));
        $this->assertSame("old-chunk\n", File::get($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'old-chunk.js'));
        $this->assertSame("<?php echo 'table-include';\n", File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'legacy.php'));
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'fresh.php');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'VERSION');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'update.lock');
        $this->assertNoSwapArtifacts($dir);
    }

    public function test_fresh_lock_blocks_update_without_touching_files(): void
    {
        [$table, $dir, $env, $state] = $this->legacyTable();
        File::put($dir.DIRECTORY_SEPARATOR.'update.lock', (string) time());

        try {
            app(TableUpdater::class)->update($table);
            $this->fail('A fresh lock should block the update.');
        } catch (TableUpdateException $e) {
            $this->assertSame('Aktualizacja tego stołu już trwa. Spróbuj za chwilę.', $e->getMessage());
        }

        $this->assertSame($state, File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json'));
        $this->assertSame($env, File::get($dir.DIRECTORY_SEPARATOR.'.env'));
        $this->assertSame("<?php echo 'table-index';\n", File::get($dir.DIRECTORY_SEPARATOR.'index.php'));
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'VERSION');
        $this->assertFileExists($dir.DIRECTORY_SEPARATOR.'update.lock');
    }

    public function test_stale_lock_can_be_taken_over(): void
    {
        [$table, $dir] = $this->legacyTable();
        File::put($dir.DIRECTORY_SEPARATOR.'update.lock', (string) (time() - TableUpdater::LOCK_TTL_SECONDS - 1));

        app(TableUpdater::class)->update($table);

        $this->assertSame(1, app(TableUpdater::class)->installedVersion($table));
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'update.lock');
        $this->assertSame('hero-bytes', File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map'.DIRECTORY_SEPARATOR.'hero.png'));
    }

    public function test_older_package_does_not_replace_the_table(): void
    {
        [$table, $dir, $env, $state] = $this->legacyTable();
        File::put($dir.DIRECTORY_SEPARATOR.'VERSION', "2\n");

        try {
            app(TableUpdater::class)->update($table);
            $this->fail('An older package must not replace the table.');
        } catch (TableUpdateException $e) {
            $this->assertSame('Stół jest już w tej wersji.', $e->getMessage());
        }

        $this->assertSame("2\n", File::get($dir.DIRECTORY_SEPARATOR.'VERSION'));
        $this->assertSame("<?php echo 'table-index';\n", File::get($dir.DIRECTORY_SEPARATOR.'index.php'));
        $this->assertSame($state, File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json'));
        $this->assertSame($env, File::get($dir.DIRECTORY_SEPARATOR.'.env'));
    }

    public function test_livewire_updates_only_after_confirmation(): void
    {
        [$table, $dir, $env, $state] = $this->legacyTable();

        Livewire::actingAs($table->user)
            ->test(TablesDashboard::class)
            ->assertSee('Wersja 0')
            ->assertSee('dostępna 1')
            ->assertSee('Aktualizuj stół')
            ->call('confirmUpdate', $table->id)
            ->assertSee('notatki w przeglądarce')
            ->call('updateTable')
            ->assertHasNoErrors()
            ->assertSee('Stół został zaktualizowany')
            ->assertSee('Wersja 1')
            ->assertDontSee('Aktualizuj stół');

        $this->assertSame($state, File::get($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json'));
        $this->assertSame($env, File::get($dir.DIRECTORY_SEPARATOR.'.env'));
        $this->assertSame(1, app(TableUpdater::class)->installedVersion($table));
    }

    public function test_another_user_cannot_update_the_table(): void
    {
        [$table] = $this->legacyTable();
        $other = User::factory()->create(['username' => 'someone-else']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($other)
            ->test(TablesDashboard::class)
            ->call('confirmUpdate', $table->id);
    }

    /**
     * @return array{0: VttTable, 1: string, 2: string, 3: string}
     */
    private function legacyTable(): array
    {
        $user = User::factory()->create(['username' => 'katsu']);
        $table = $user->vttTables()->create([
            'name' => 'Stary stół',
            'slug' => 'wnagr5o15i',
            'player_password' => 'player-secret',
            'gm_password' => 'gm-secret',
            'language' => 'pl',
            'color_template' => 'violet',
            'global_assets' => false,
        ]);
        $table->setRelation('user', $user);

        $dir = $table->absolutePath();
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'assets');
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor');
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Ttrpg');
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include');
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'sessions');
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map');
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'papers');

        $env = "VTT_PASSWORD=player-secret\n"
            ."VTT_GM_PASSWORD=gm-secret\n"
            ."VTT_BASE_PATH=/vtt/user/katsu/wnagr5o15i/\n"
            ."VTT_LANGUAGE=pl\n"
            ."VTT_ENABLE_L5R=true\n"
            ."VTT_COLOR_TEMPLATE=violet\n"
            ."VTT_TABLE_UPLOAD_QUOTA_MB=400\n"
            ."ALLOWED_ORIGINS=*\n";
        $state = '{"version":390,"from":"user"}';

        File::put($dir.DIRECTORY_SEPARATOR.'.env', $env);
        File::put($dir.DIRECTORY_SEPARATOR.'index.php', "<?php echo 'table-index';\n");
        File::put($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'index.js', "table-js\n");
        File::put($dir.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'old-chunk.js', "old-chunk\n");
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php', "<?php echo 'table-vendor';\n");
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Ttrpg'.DIRECTORY_SEPARATOR.'Actions.php', "<?php echo 'table-actions';\n");
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'include'.DIRECTORY_SEPARATOR.'legacy.php', "<?php echo 'table-include';\n");
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'api.php', "<?php echo 'table-api';\n");
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json', $state);
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'rolls.json', 'user-rolls');
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.version', '390');
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'ttrpg.sqlite', 'sqlite-user');
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'sessions'.DIRECTORY_SEPARATOR.'sess_keep', 'session-user');
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'keep.txt', 'keep-me');
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'map'.DIRECTORY_SEPARATOR.'hero.png', 'hero-bytes');
        File::put($dir.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'papers'.DIRECTORY_SEPARATOR.'sheet.pdf', 'pdf-bytes');

        return [$table, $dir, $env, $state];
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(string $dir): array
    {
        $paths = [
            '.env',
            'index.php',
            'VERSION',
            'assets/index.js',
            'backend/data/state.json',
            'backend/data/ttrpg.sqlite',
            'backend/assets/map/hero.png',
            'backend/include/fresh.php',
        ];
        $snapshot = [];
        foreach ($paths as $relative) {
            $full = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $snapshot[$relative] = File::get($full);
        }

        return $snapshot;
    }

    private function assertNoSwapArtifacts(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $this->assertDoesNotMatchRegularExpression(
                '/\.__(?:new|old|discard)$/',
                $entry->getFilename(),
                'Swap artifact left behind: '.$entry->getPathname()
            );
        }
    }
}
