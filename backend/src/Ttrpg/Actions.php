<?php

namespace FreeRoll\Ttrpg;

final class Actions
{
    /**
     * @return array{configured: bool, baseUrl: ?string, campaignId: ?int}
     */
    public static function publicStatus(): array
    {
        return IntegrationStore::make()->publicStatus();
    }

    /**
     * @return array{success: bool, ttrpgManager?: array, error?: string, me?: mixed}
     */
    public static function status(): array
    {
        return [
            'success' => true,
            'ttrpgManager' => self::publicStatus(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, ttrpgManager?: array, error?: string, me?: mixed}
     */
    public static function setKey(array $input): array
    {
        $apiKey = trim((string) ($input['apiKey'] ?? ''));
        $baseUrl = trim((string) ($input['baseUrl'] ?? 'http://localhost:8000'));
        if ($apiKey === '') {
            return ['success' => false, 'error' => 'apiKey is required'];
        }
        if ($baseUrl === '' || !preg_match('#^https?://#i', $baseUrl)) {
            return ['success' => false, 'error' => 'baseUrl must be http(s) URL'];
        }
        if (str_contains($baseUrl, '..')) {
            return ['success' => false, 'error' => 'Invalid baseUrl'];
        }

        $check = ManagerClient::validateKey($baseUrl, $apiKey);
        if (!$check['ok']) {
            $msg = is_array($check['body']) ? ($check['body']['message'] ?? null) : null;
            return [
                'success' => false,
                'error' => $msg ?: ($check['error'] ?? 'Failed to validate API key'),
                'status' => $check['status'],
            ];
        }

        $store = IntegrationStore::make();
        $store->setKey($apiKey, $baseUrl);

        return [
            'success' => true,
            'ttrpgManager' => $store->publicStatus(),
            'me' => $check['body']['data'] ?? $check['body'],
        ];
    }

    /**
     * @return array{success: bool, ttrpgManager?: array, error?: string}
     */
    public static function clearKey(): array
    {
        IntegrationStore::make()->clear();
        return [
            'success' => true,
            'ttrpgManager' => self::publicStatus(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, ttrpgManager?: array, error?: string}
     */
    public static function selectCampaign(array $input): array
    {
        $campaignId = $input['campaignId'] ?? null;
        if ($campaignId !== null && $campaignId !== '') {
            $campaignId = (int) $campaignId;
            if ($campaignId <= 0) {
                return ['success' => false, 'error' => 'Invalid campaignId'];
            }
        } else {
            $campaignId = null;
        }

        try {
            $store = IntegrationStore::make();
            $store->setCampaignId($campaignId);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return [
            'success' => true,
            'ttrpgManager' => self::publicStatus(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, status?: int, data?: mixed, error?: string}
     */
    public static function proxy(array $input, bool $isGameMaster): array
    {
        $method = strtoupper((string) ($input['method'] ?? 'GET'));
        $path = (string) ($input['path'] ?? '');
        $normalized = ManagerClient::normalizePath($path);
        if ($normalized === null) {
            return ['success' => false, 'error' => 'Path not allowed'];
        }
        if (!in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return ['success' => false, 'error' => 'Method not allowed'];
        }
        if (!$isGameMaster && !ManagerClient::isPlayerAllowed($method, $normalized)) {
            http_response_code(403);
            return ['success' => false, 'error' => 'Forbidden'];
        }

        $query = isset($input['query']) && is_array($input['query']) ? $input['query'] : null;
        $json = array_key_exists('json', $input) ? (is_array($input['json']) || $input['json'] === null ? $input['json'] : null) : null;
        $extraHeaders = [];
        if (isset($input['headers']) && is_array($input['headers'])) {
            foreach ($input['headers'] as $k => $v) {
                if (is_string($k) && is_string($v) && strcasecmp($k, 'Authorization') !== 0) {
                    $extraHeaders[$k] = $v;
                }
            }
        }

        try {
            $client = ManagerClient::fromStore(IntegrationStore::make());
        } catch (\RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $result = $client->request($method, $normalized, $query, $json, $extraHeaders);
        $body = $result['body'];
        if (!$isGameMaster && is_array($body)) {
            $body = ManagerClient::filterGmOnly($body, $normalized);
            if (is_array($body) && ($body['filtered'] ?? false) === true) {
                http_response_code(403);
                return ['success' => false, 'error' => 'Forbidden', 'status' => 403];
            }
        }

        if (!$result['ok']) {
            http_response_code($result['status'] > 0 ? $result['status'] : 502);
            return [
                'success' => false,
                'status' => $result['status'],
                'error' => $result['error'] ?? (is_array($body) ? ($body['message'] ?? 'Upstream error') : 'Upstream error'),
                'data' => $body,
            ];
        }

        return [
            'success' => true,
            'status' => $result['status'],
            'data' => $body,
        ];
    }

    /**
     * Multipart upload for campaign image asset.
     *
     * @return array{success: bool, status?: int, data?: mixed, error?: string}
     */
    public static function uploadAsset(int $campaignId, array $file): array
    {
        if ($campaignId <= 0) {
            return ['success' => false, 'error' => 'campaignId required'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed'];
        }

        try {
            $client = ManagerClient::fromStore(IntegrationStore::make());
        } catch (\RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $result = $client->request(
            'POST',
            "api/v1/gm/campaigns/{$campaignId}/assets",
            null,
            null,
            [],
            [
                [
                    'name' => 'image',
                    'contents' => fopen($file['tmp_name'], 'r'),
                    'filename' => $file['name'] ?? 'image.jpg',
                    'headers' => [
                        'Content-Type' => $file['type'] ?: 'application/octet-stream',
                    ],
                ],
            ]
        );

        if (!$result['ok']) {
            http_response_code($result['status'] > 0 ? $result['status'] : 502);
            return [
                'success' => false,
                'status' => $result['status'],
                'error' => $result['error'] ?? 'Upload failed',
                'data' => $result['body'],
            ];
        }

        return ['success' => true, 'status' => $result['status'], 'data' => $result['body']];
    }

    /**
     * Multipart upload for handbook PDF.
     *
     * @param array<string, mixed> $fields
     * @return array{success: bool, status?: int, data?: mixed, error?: string}
     */
    public static function uploadHandbook(array $file, array $fields): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed'];
        }
        $title = trim((string) ($fields['title'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'error' => 'title required'];
        }

        try {
            $client = ManagerClient::fromStore(IntegrationStore::make());
        } catch (\RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $parts = [
            [
                'name' => 'pdf',
                'contents' => fopen($file['tmp_name'], 'r'),
                'filename' => $file['name'] ?? 'handbook.pdf',
                'headers' => [
                    'Content-Type' => $file['type'] ?: 'application/pdf',
                ],
            ],
            [
                'name' => 'title',
                'contents' => $title,
            ],
        ];
        if (!empty($fields['language'])) {
            $parts[] = ['name' => 'language', 'contents' => (string) $fields['language']];
        }
        if (isset($fields['campaign_id']) && $fields['campaign_id'] !== '' && $fields['campaign_id'] !== null) {
            $parts[] = ['name' => 'campaign_id', 'contents' => (string) (int) $fields['campaign_id']];
        }

        $result = $client->request('POST', 'api/v1/gm/handbooks', null, null, [], $parts);

        if (!$result['ok']) {
            http_response_code($result['status'] > 0 ? $result['status'] : 502);
            return [
                'success' => false,
                'status' => $result['status'],
                'error' => $result['error'] ?? 'Upload failed',
                'data' => $result['body'],
            ];
        }

        return ['success' => true, 'status' => $result['status'], 'data' => $result['body']];
    }
}
