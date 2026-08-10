<?php

namespace FreeRoll\Ttrpg;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function path(): string
    {
        return __DIR__ . '/../../data/ttrpg.sqlite';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dir = dirname(self::path());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . self::path(), null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrate($pdo);
        self::$pdo = $pdo;

        return self::$pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS integration (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                api_key TEXT NOT NULL,
                base_url TEXT NOT NULL,
                campaign_id INTEGER NULL,
                updated_at INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS entity_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NOT NULL,
                entity_type TEXT NOT NULL,
                remote_id INTEGER NOT NULL,
                local_ref TEXT NULL,
                meta_json TEXT NULL,
                UNIQUE(campaign_id, entity_type, remote_id)
            )'
        );
    }
}
