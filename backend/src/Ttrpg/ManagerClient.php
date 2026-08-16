<?php

namespace FreeRoll\Ttrpg;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

final class ManagerClient
{
    private Client $http;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            // Keep short so a dead/revoked Manager API cannot block PHP workers
            // (and thus block local actions like disconnect) for a long time.
            'timeout' => 12,
            'connect_timeout' => 4,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
        ]);
    }

    public static function fromStore(IntegrationStore $store): self
    {
        $row = $store->get();
        if (!$row) {
            throw new \RuntimeException('TTRPG Manager is not configured');
        }

        return new self($row['base_url'], $row['api_key']);
    }

    /**
     * Validate credentials against GET /api/v1/me.
     *
     * @return array{ok: bool, status: int, body: mixed, error?: string}
     */
    public static function validateKey(string $baseUrl, string $apiKey): array
    {
        $client = new self($baseUrl, $apiKey);
        return $client->request('GET', 'api/v1/me');
    }

    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $json
     * @param array<string, string> $extraHeaders
     * @return array{ok: bool, status: int, body: mixed, error?: string}
     */
    public function request(
        string $method,
        string $path,
        ?array $query = null,
        ?array $json = null,
        array $extraHeaders = [],
        ?array $multipart = null,
    ): array {
        $path = ltrim($path, '/');
        $options = [
            'headers' => $extraHeaders,
        ];
        if ($query) {
            $options['query'] = $query;
        }
        if ($multipart !== null) {
            $options['multipart'] = $multipart;
            // PDF / asset uploads need more time than normal JSON proxy calls.
            $options['timeout'] = 120;
            $options['connect_timeout'] = 10;
        } elseif ($json !== null) {
            $options['json'] = $json;
            $options['headers']['Content-Type'] = 'application/json';
        }

        try {
            $response = $this->http->request(strtoupper($method), $path, $options);
            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();
            $decoded = $raw === '' ? null : json_decode($raw, true);
            if ($raw !== '' && $decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $decoded = ['raw' => $raw];
            }

            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => $decoded,
            ];
        } catch (RequestException $e) {
            return [
                'ok' => false,
                'status' => $e->getResponse()?->getStatusCode() ?? 0,
                'body' => null,
                'error' => $e->getMessage(),
            ];
        } catch (GuzzleException $e) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalize and validate a relative API path (no scheme, no ..).
     * Returns path under api/v1/... or null if forbidden.
     */
    public static function normalizePath(string $path): ?string
    {
        $path = trim($path);
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, '://')) {
            return null;
        }
        if (!str_starts_with($path, 'api/v1/')) {
            if (str_starts_with($path, 'v1/')) {
                $path = 'api/' . $path;
            } elseif (str_starts_with($path, 'gm/') || $path === 'me' || str_starts_with($path, 'me/')) {
                $path = 'api/v1/' . $path;
            } else {
                return null;
            }
        }

        $relative = substr($path, strlen('api/v1/'));
        if ($relative === false || $relative === '') {
            return null;
        }
        if (str_starts_with($relative, 'auth/') || str_starts_with($relative, 'gm/tokens')) {
            return null;
        }
        if ($relative !== 'me' && !str_starts_with($relative, 'gm/')) {
            return null;
        }

        return $path;
    }

    public static function isPlayerAllowed(string $method, string $normalizedPath): bool
    {
        $method = strtoupper($method);
        if (in_array($method, ['GET', 'HEAD'], true)) {
            return true;
        }
        // FTS search is a read operation expressed as POST
        if ($method === 'POST' && $normalizedPath === 'api/v1/gm/handbooks/search') {
            return true;
        }

        return false;
    }

    /**
     * Strip gm_only items from list/detail responses for non-GM FreeRoll clients.
     *
     * @param mixed $body
     * @return mixed
     */
    public static function filterGmOnly(mixed $body, string $path): mixed
    {
        if (!is_array($body)) {
            return $body;
        }

        $needsFilter = (bool) preg_match(
            '#api/v1/gm/campaigns/\d+/(contents|maps|trackers)(/|\?|$)#',
            $path
        ) || (bool) preg_match(
            '#api/v1/gm/campaigns/\d+/maps/\d+/pins#',
            $path
        );

        if (!$needsFilter) {
            return $body;
        }

        if (isset($body['data']) && is_array($body['data'])) {
            // Collection
            if (array_is_list($body['data'])) {
                $body['data'] = array_values(array_filter(
                    $body['data'],
                    static fn ($item) => !is_array($item) || ($item['visibility'] ?? 'players') !== 'gm_only'
                ));
            } elseif (($body['data']['visibility'] ?? null) === 'gm_only') {
                return [
                    'message' => 'Forbidden',
                    'filtered' => true,
                ];
            } elseif (isset($body['data']['pins']) && is_array($body['data']['pins'])) {
                $body['data']['pins'] = array_values(array_filter(
                    $body['data']['pins'],
                    static fn ($item) => !is_array($item) || ($item['visibility'] ?? 'players') !== 'gm_only'
                ));
            } elseif (isset($body['data']['entries']) && is_array($body['data']['entries'])) {
                // entries have no visibility — leave as-is when parent tracker was visible
            }
        }

        return $body;
    }
}
