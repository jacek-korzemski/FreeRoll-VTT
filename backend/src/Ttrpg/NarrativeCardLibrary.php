<?php

namespace FreeRoll\Ttrpg;

use PDO;

final class NarrativeCardLibrary
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function make(): self
    {
        return new self(Database::pdo());
    }

    /**
     * @return array<int, array{id: int, src: string, title: string, body: string, createdAt: int}>
     */
    public function listCards(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, src, title, body, created_at FROM narrative_card_library ORDER BY created_at DESC, id DESC'
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map($row);
        }
        return $out;
    }

    /**
     * @return array{id: int, src: string, title: string, body: string, createdAt: int}
     */
    public function create(string $src, string $title, string $body): array
    {
        $src = $this->clipSrc($src);
        $title = $this->clipText($title, 120);
        $body = $this->clipText($body, 4000);
        $now = time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO narrative_card_library (src, title, body, created_at)
             VALUES (:src, :title, :body, :created)'
        );
        $stmt->execute([
            ':src' => $src,
            ':title' => $title,
            ':body' => $body,
            ':created' => $now,
        ]);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'src' => $src,
            'title' => $title,
            'body' => $body,
            'createdAt' => $now,
        ];
    }

    /**
     * @return array{id: int, src: string, title: string, body: string, createdAt: int}|null
     */
    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, src, title, body, created_at FROM narrative_card_library WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        return $this->map($row);
    }

    public function delete(int $id): bool
    {
        if ($id < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM narrative_card_library WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, src: string, title: string, body: string, createdAt: int}
     */
    private function map(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'src' => (string) $row['src'],
            'title' => (string) $row['title'],
            'body' => (string) $row['body'],
            'createdAt' => (int) $row['created_at'],
        ];
    }

    private function clipText(string $value, int $max): string
    {
        $text = strip_tags($value);
        $text = str_replace("\0", '', $text);
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }
        return substr($text, 0, $max);
    }

    private function clipSrc(string $value): string
    {
        $src = str_replace(["\0", "\r", "\n"], '', $value);
        if (preg_match('/^\s*javascript:/i', $src)) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($src, 0, 500);
        }
        return substr($src, 0, 500);
    }
}
