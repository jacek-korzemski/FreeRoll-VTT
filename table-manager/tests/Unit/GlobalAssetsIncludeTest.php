<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class GlobalAssetsIncludeTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vtt-global-'.uniqid('', true);
        foreach ([
            '/table/assets/tokens',
            '/table/assets/map',
            '/table/assets/backgrounds',
            '/table/assets/templates',
            '/table/assets/papers',
            '/library/tokens/beasts',
            '/library/map',
            '/library/backgrounds',
            '/project/table-manager/public/vtt/global/tokens',
        ] as $dir) {
            mkdir($this->tempRoot.$dir, 0777, true);
        }

        require_once dirname(__DIR__, 2).'/../backend/include/storage-quota.php';
        require_once dirname(__DIR__, 2).'/../backend/include/global-assets.php';
        vttSetAssetBackendRoot($this->tempRoot.'/table');
        vttResetGlobalAssets();
    }

    protected function tearDown(): void
    {
        vttResetGlobalAssets();
        vttSetAssetBackendRoot(null);
        if (is_dir($this->tempRoot)) {
            $this->removeDir($this->tempRoot);
        }
        parent::tearDown();
    }

    public function test_parent_segments_and_files_outside_the_library_are_rejected(): void
    {
        file_put_contents($this->tempRoot.'/secret.png', 'nope');
        file_put_contents($this->tempRoot.'/library/tokens/hero.png', 'ok');

        $this->assertNull(vttNormalizeGlobalRelativePath('../secret.png'));
        $this->assertNull(vttNormalizeGlobalRelativePath('beasts/../../secret.png'));
        $this->assertFalse(vttPathsInside(
            $this->tempRoot.'/library/tokens',
            $this->tempRoot.'/secret.png'
        ));
        $this->assertNull(vttGlobalResolvedFile($this->tempRoot.'/library', 'tokens', '../secret.png'));

        vttConfigureGlobalAssets([
            'VTT_GLOBAL_ASSETS' => '1',
            'VTT_GLOBAL_ASSETS_PATH' => $this->tempRoot.'/library',
        ]);

        $rejected = vttGlobalListFolder('tokens', '../secret.png');
        $this->assertFalse($rejected['success']);

        $resolved = vttGlobalResolvedFile($this->tempRoot.'/library', 'tokens', 'hero.png');
        $this->assertNotNull($resolved);
        $this->assertTrue(vttPathsInside($this->tempRoot.'/library/tokens', $resolved));
    }

    public function test_global_files_do_not_count_toward_table_quota(): void
    {
        file_put_contents($this->tempRoot.'/table/assets/tokens/local.png', str_repeat('a', 10));
        file_put_contents($this->tempRoot.'/library/tokens/hero.png', str_repeat('b', 40));

        vttConfigureGlobalAssets([
            'VTT_GLOBAL_ASSETS' => '1',
            'VTT_GLOBAL_ASSETS_PATH' => $this->tempRoot.'/library',
        ]);
        $list = vttGlobalListFolder('tokens', '');

        $this->assertSame(10, vttAssetUsageBytes());
        $this->assertSame('/vtt/global/tokens/hero.png', $list['files'][0]['src']);
        $this->assertSame('beasts', $list['folders'][0]['name']);
    }

    public function test_disabled_flag_lists_nothing_and_a_missing_path_does_not_use_the_dev_fallback(): void
    {
        file_put_contents($this->tempRoot.'/project/table-manager/public/vtt/global/tokens/shared.png', 'x');

        vttConfigureGlobalAssets([
            'VTT_GLOBAL_ASSETS' => '1',
            'VTT_GLOBAL_ASSETS_PATH' => $this->tempRoot.'/missing-library',
        ], $this->tempRoot.'/project');

        $this->assertTrue(vttGlobalAssetsFlag());
        $this->assertSame([], vttGlobalListFolder('tokens', '')['files']);

        vttConfigureGlobalAssets([], $this->tempRoot.'/project');
        $this->assertFalse(vttGlobalAssetsFlag());
        $this->assertSame([], vttGlobalListFolder('tokens', '')['files']);
        $this->assertNotNull(vttGlobalStreamFile('tokens/shared.png'));
    }

    public function test_dev_fallback_is_used_when_the_flag_is_on_and_no_path_is_set(): void
    {
        file_put_contents($this->tempRoot.'/project/table-manager/public/vtt/global/tokens/shared.png', 'x');

        vttConfigureGlobalAssets(['VTT_GLOBAL_ASSETS' => '1'], $this->tempRoot.'/project');
        $list = vttGlobalListFolder('tokens', '');

        $this->assertSame('/vtt/global/tokens/shared.png', $list['files'][0]['src']);
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
