<?php

namespace FreeRoll\Ttrpg;

use PDO;

final class IntegrationStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::pdo());
    }

    /**
     * @return array{api_key: string, base_url: string, campaign_id: ?int, updated_at: int}|null
     */
    public function get(): ?array
    {
        $row = $this->pdo->query('SELECT api_key, base_url, campaign_id, updated_at FROM integration WHERE id = 1')->fetch();
        if (!$row) {
            return null;
        }

        return [
            'api_key' => (string) $row['api_key'],
            'base_url' => (string) $row['base_url'],
            'campaign_id' => $row['campaign_id'] !== null ? (int) $row['campaign_id'] : null,
            'updated_at' => (int) $row['updated_at'],
        ];
    }

    /**
     * Public snapshot for state/check — never includes api_key.
     *
     * @return array{configured: bool, baseUrl: ?string, campaignId: ?int}
     */
    public function publicStatus(): array
    {
        $row = $this->get();
        if (!$row) {
            return [
                'configured' => false,
                'baseUrl' => null,
                'campaignId' => null,
            ];
        }

        return [
            'configured' => true,
            'baseUrl' => $row['base_url'],
            'campaignId' => $row['campaign_id'],
        ];
    }

    public function setKey(string $apiKey, string $baseUrl): void
    {
        $existing = $this->get();
        $campaignId = $existing['campaign_id'] ?? null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO integration (id, api_key, base_url, campaign_id, updated_at)
             VALUES (1, :api_key, :base_url, :campaign_id, :updated_at)
             ON CONFLICT(id) DO UPDATE SET
               api_key = excluded.api_key,
               base_url = excluded.base_url,
               updated_at = excluded.updated_at'
        );
        $stmt->execute([
            ':api_key' => $apiKey,
            ':base_url' => rtrim($baseUrl, '/'),
            ':campaign_id' => $campaignId,
            ':updated_at' => time(),
        ]);
    }

    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM integration WHERE id = 1');
    }

    public function setCampaignId(?int $campaignId): void
    {
        $row = $this->get();
        if (!$row) {
            throw new \RuntimeException('TTRPG Manager is not configured');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE integration SET campaign_id = :campaign_id, updated_at = :updated_at WHERE id = 1'
        );
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':updated_at' => time(),
        ]);
    }
}
