<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\TableDiskReader;
use App\Services\TableProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TableDiskReaderTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = storage_path('framework/testing/vtt-disk-'.uniqid('', true));
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
        File::put($source.DIRECTORY_SEPARATOR.'.env', "VTT_ENABLE_L5R=true\n");

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

    public function test_reader_lists_assets_and_telemetry(): void
    {
        $user = User::factory()->create(['username' => 'disk-user']);
        $table = app(TableProvisioner::class)->create($user, [
            'name' => 'Stół analityczny',
            'player_password' => 'gracze',
            'gm_password' => 'mistrz',
            'language' => 'pl',
        ]);
        $table->load('user');

        $tokens = $table->absolutePath().DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'tokens';
        File::ensureDirectoryExists($tokens);
        File::put($tokens.DIRECTORY_SEPARATOR.'hero.png', 'fake-png');

        $tel = $table->absolutePath().DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'telemetry';
        File::ensureDirectoryExists($tel);
        $now = time();
        File::put($tel.DIRECTORY_SEPARATOR.'presence.json', json_encode([
            'clients' => [
                'client-aaaa-bbbb' => [
                    'clientId' => 'client-aaaa-bbbb',
                    'role' => 'player',
                    'firstSeen' => $now - 10,
                    'lastSeen' => $now,
                    'playerName' => 'Janek',
                ],
            ],
            'openSession' => [
                'startedAt' => $now - 10,
                'peakClients' => 1,
                'clientIds' => ['client-aaaa-bbbb'],
            ],
        ]));
        File::put($tel.DIRECTORY_SEPARATOR.'events.jsonl', json_encode([
            'type' => 'login',
            'ts' => $now,
            'role' => 'player',
            'clientId' => 'client-aaaa-bbbb',
        ])."\n".json_encode([
            'type' => 'interaction',
            'ts' => $now,
            'action' => 'move-token',
            'role' => 'player',
        ])."\n");
        File::put(
            $table->absolutePath().DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'state.json',
            '{"version":4,"lastUpdate":'.$now.'}'
        );

        $reader = app(TableDiskReader::class);
        $assets = $reader->listAssets($table);
        $this->assertNotEmpty($assets);
        $this->assertSame('tokens/hero.png', $assets[0]['relative']);

        $telemetry = $reader->telemetry($table, $now);
        $this->assertSame(1, $telemetry['onlineCount']);
        $this->assertSame(1, $telemetry['uniqueClients']);
        $this->assertSame(1, $telemetry['logins']['player']);
        $this->assertSame(1, $telemetry['interactions24h']);

        $state = $reader->readStateFile($table, 'state.json');
        $this->assertTrue($state['exists']);
        $this->assertSame(4, $state['json']['version']);

        $this->assertTrue($reader->deleteAsset($table, 'tokens/hero.png'));
        $this->assertNull($reader->resolveAssetPath($table, 'tokens/hero.png'));
        $this->assertNull($reader->resolveAssetPath($table, '../.env'));
    }
}
