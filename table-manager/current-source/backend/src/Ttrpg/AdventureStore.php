<?php

namespace FreeRoll\Ttrpg;

use PDO;
use PDOException;

final class AdventureStore
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
     * @return array<int, array{id: int, title: string, sortOrder: int, updatedAt: int}>
     */
    public function listEntries(): array
    {
        $this->ensureSeeded();
        return $this->summaries();
    }

    /**
     * @return array{id: int, title: string, sortOrder: int, updatedAt: int, bodyHtml: string}|null
     */
    public function get(int $id): ?array
    {
        $this->ensureSeeded();
        $stmt = $this->pdo->prepare(
            'SELECT id, title, body_html, sort_order, updated_at FROM adventure_entries WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return $this->map($row, true);
    }

    /**
     * @return array{id: int, title: string, sortOrder: int, updatedAt: int, bodyHtml: string}
     */
    public function create(string $title): array
    {
        $this->ensureSeeded();
        $title = $this->cleanTitle($title);
        $sort = (int) $this->pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM adventure_entries')->fetchColumn();
        $now = time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO adventure_entries (title, body_html, sort_order, updated_at)
             VALUES (:title, :body, :sort, :updated)'
        );
        $stmt->execute([
            ':title' => $title,
            ':body' => '',
            ':sort' => $sort,
            ':updated' => $now,
        ]);
        $created = $this->get((int) $this->pdo->lastInsertId());
        if ($created === null) {
            throw new PDOException('Failed to create adventure entry');
        }
        return $created;
    }

    /**
     * @return array{id: int, title: string, sortOrder: int, updatedAt: int, bodyHtml: string}|null
     */
    public function save(int $id, ?string $title, ?string $bodyHtml): ?array
    {
        $this->ensureSeeded();
        $existing = $this->get($id);
        if ($existing === null) {
            return null;
        }
        $nextTitle = $title !== null ? $this->cleanTitle($title) : $existing['title'];
        $nextBody = $bodyHtml !== null ? AdventureHtml::sanitize($bodyHtml) : $existing['bodyHtml'];
        $now = time();
        $stmt = $this->pdo->prepare(
            'UPDATE adventure_entries SET title = :title, body_html = :body, updated_at = :updated WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $nextTitle,
            ':body' => $nextBody,
            ':updated' => $now,
            ':id' => $id,
        ]);
        return $this->get($id);
    }

    public function delete(int $id): bool
    {
        $this->ensureSeeded();
        $stmt = $this->pdo->prepare('DELETE FROM adventure_entries WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function clearAll(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM adventure_entries');
            $this->pdo->exec('INSERT OR REPLACE INTO adventure_meta (id, seeded) VALUES (1, 1)');
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, array{id: int, title: string, sortOrder: int, updatedAt: int, bodyHtml: string}>
     */
    public function allWithBodies(): array
    {
        $this->ensureSeeded();
        $rows = $this->pdo->query(
            'SELECT id, title, body_html, sort_order, updated_at FROM adventure_entries ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map($row, true);
        }
        return $out;
    }

    /**
     * Replace every entry. Body HTML may still use stable keys in data-entry-id.
     *
     * @param array<int, array{key: string, title: string, bodyHtml: string}> $entries
     * @return array{entries: array<int, array{id: int, title: string, sortOrder: int, updatedAt: int}>, idByKey: array<string, int>}
     */
    public function replaceMapped(array $entries): array
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM adventure_entries');
            $now = time();
            $insert = $this->pdo->prepare(
                'INSERT INTO adventure_entries (title, body_html, sort_order, updated_at)
                 VALUES (:title, :body, :sort, :updated)'
            );
            $keyToId = [];
            $sort = 0;
            foreach ($entries as $entry) {
                $insert->execute([
                    ':title' => $this->cleanTitle((string) ($entry['title'] ?? '')),
                    ':body' => '',
                    ':sort' => $sort,
                    ':updated' => $now,
                ]);
                $keyToId[(string) $entry['key']] = (int) $this->pdo->lastInsertId();
                $sort++;
            }
            $update = $this->pdo->prepare(
                'UPDATE adventure_entries SET body_html = :body WHERE id = :id'
            );
            foreach ($entries as $entry) {
                $key = (string) $entry['key'];
                $html = AdventureHtml::remapEntryIds((string) ($entry['bodyHtml'] ?? ''), $keyToId);
                $update->execute([
                    ':body' => AdventureHtml::sanitize($html),
                    ':id' => $keyToId[$key],
                ]);
            }
            $this->pdo->exec('INSERT OR REPLACE INTO adventure_meta (id, seeded) VALUES (1, 1)');
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return [
            'entries' => $this->summaries(),
            'idByKey' => $keyToId,
        ];
    }

    /**
     * @param int[] $ids
     * @return array<int, array{id: int, title: string, sortOrder: int, updatedAt: int}>
     */
    public function reorder(array $ids): array
    {
        $this->ensureSeeded();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE adventure_entries SET sort_order = :sort WHERE id = :id');
            $sort = 0;
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id < 1) {
                    continue;
                }
                $stmt->execute([':sort' => $sort, ':id' => $id]);
                $sort++;
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $this->summaries();
    }

    /**
     * @return array<int, array{id: int, title: string, sortOrder: int, updatedAt: int}>
     */
    private function summaries(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, title, body_html, sort_order, updated_at FROM adventure_entries ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map($row, false);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, title: string, sortOrder: int, updatedAt: int, bodyHtml?: string}
     */
    private function map(array $row, bool $withBody): array
    {
        $entry = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'sortOrder' => (int) $row['sort_order'],
            'updatedAt' => (int) $row['updated_at'],
        ];
        if ($withBody) {
            $entry['bodyHtml'] = (string) $row['body_html'];
        }
        return $entry;
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 80);
        } else {
            $title = substr($title, 0, 80);
        }
        if ($title === '') {
            return 'Wpis';
        }
        return $title;
    }

    private function ensureSeeded(): void
    {
        $this->pdo->beginTransaction();
        try {
            $meta = $this->pdo->query('SELECT seeded FROM adventure_meta WHERE id = 1')->fetch();
            if ($meta && (int) $meta['seeded'] === 1) {
                $this->pdo->commit();
                return;
            }
            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM adventure_entries')->fetchColumn();
            if ($count === 0) {
                $this->insertSeed();
            }
            $this->pdo->exec('INSERT OR REPLACE INTO adventure_meta (id, seeded) VALUES (1, 1)');
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function insertSeed(): void
    {
        $now = time();
        $insert = $this->pdo->prepare(
            'INSERT INTO adventure_entries (title, body_html, sort_order, updated_at)
             VALUES (:title, :body, :sort, :updated)'
        );
        $insert->execute([':title' => 'Przygoda', ':body' => '', ':sort' => 0, ':updated' => $now]);
        $adventureId = (int) $this->pdo->lastInsertId();
        $insert->execute([':title' => 'Miasto', ':body' => '', ':sort' => 1, ':updated' => $now]);
        $cityId = (int) $this->pdo->lastInsertId();
        $insert->execute([':title' => 'Postać', ':body' => '', ':sort' => 2, ':updated' => $now]);
        $characterId = (int) $this->pdo->lastInsertId();

        $update = $this->pdo->prepare('UPDATE adventure_entries SET body_html = :body WHERE id = :id');
        $update->execute([
            ':id' => $adventureId,
            ':body' => AdventureHtml::sanitize(
                '<p>Na trakcie pada nazwa <a href="#" class="adv-entry-link" data-entry-id="' . $cityId . '">miasto</a>. Strażnicy żądają rzutu 2d4+2.</p>'
            ),
        ]);
        $update->execute([
            ':id' => $cityId,
            ':body' => AdventureHtml::sanitize(
                '<p>Przy bramie czeka <a href="#" class="adv-entry-link" data-entry-id="' . $characterId . '">Postać</a>.</p>'
            ),
        ]);
        $update->execute([
            ':id' => $characterId,
            ':body' => AdventureHtml::sanitize(
                '<p>Strażniczka o spokojnym głosie. W razie sporu rzuca d20.</p>'
            ),
        ]);
    }
}
