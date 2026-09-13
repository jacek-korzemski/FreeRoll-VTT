<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StorageQuotaIncludeTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vtt-quota-'.uniqid('', true);
        mkdir($this->tempRoot.'/assets/tokens', 0777, true);
        mkdir($this->tempRoot.'/assets/map', 0777, true);
        mkdir($this->tempRoot.'/assets/backgrounds', 0777, true);
        mkdir($this->tempRoot.'/assets/templates', 0777, true);
        mkdir($this->tempRoot.'/assets/papers', 0777, true);

        require_once dirname(__DIR__, 2).'/../backend/include/storage-quota.php';
        vttSetAssetBackendRoot($this->tempRoot);
        vttSetUploadQuotaBytes(100);
    }

    protected function tearDown(): void
    {
        vttSetAssetBackendRoot(null);
        vttSetUploadQuotaBytes(VTT_DEFAULT_UPLOAD_QUOTA_BYTES);
        if (is_dir($this->tempRoot)) {
            $this->removeDir($this->tempRoot);
        }
        parent::tearDown();
    }

    public function test_usage_counts_uploaded_files_and_skips_gitkeep(): void
    {
        file_put_contents($this->tempRoot.'/assets/tokens/.gitkeep', '');
        file_put_contents($this->tempRoot.'/assets/tokens/hero.png', str_repeat('a', 40));
        file_put_contents($this->tempRoot.'/assets/papers/rules.pdf', str_repeat('b', 15));

        $this->assertSame(55, vttAssetUsageBytes());
        $this->assertFalse(vttWouldExceedQuota(45));
        $this->assertTrue(vttWouldExceedQuota(46));
    }

    public function test_overwrite_allows_smaller_or_equal_replacement(): void
    {
        file_put_contents($this->tempRoot.'/assets/templates/sheet.html', str_repeat('x', 80));

        $this->assertFalse(vttWouldExceedQuota(80, 80));
        $this->assertFalse(vttWouldExceedQuota(100, 80));
        $this->assertTrue(vttWouldExceedQuota(101, 80));
    }

    public function test_zero_quota_is_unlimited(): void
    {
        file_put_contents($this->tempRoot.'/assets/map/tile.png', str_repeat('c', 50));
        vttSetUploadQuotaBytes(0);

        $this->assertFalse(vttWouldExceedQuota(10_000_000));
    }

    public function test_parse_quota_from_env(): void
    {
        $this->assertSame(52428800, vttParseQuotaFromEnv([]));
        $this->assertSame(10485760, vttParseQuotaFromEnv(['VTT_TABLE_UPLOAD_QUOTA_MB' => '10']));
        $this->assertSame(1234, vttParseQuotaFromEnv(['VTT_TABLE_UPLOAD_QUOTA_BYTES' => '1234']));
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
