<?php

declare(strict_types=1);

final class Database {
    private PDO $pdo;

    public function __construct(string $path) {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    }
    public function pdo(): PDO { return $this->pdo; }
    public function one(string $sql, array $params=[]): ?array { $s=$this->pdo->prepare($sql); $s->execute($params); $r=$s->fetch(); return $r ?: null; }
    public function all(string $sql, array $params=[]): array { $s=$this->pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    public function exec(string $sql, array $params=[]): bool { $s=$this->pdo->prepare($sql); return $s->execute($params); }
    public function id(): int { return (int)$this->pdo->lastInsertId(); }

    public function migrate(): void {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 email TEXT NOT NULL UNIQUE,
 password_hash TEXT NOT NULL,
 created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS forms (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL,
 slug TEXT NOT NULL UNIQUE,
 secret_key TEXT NOT NULL UNIQUE,
 success_url TEXT,
 allowed_domains TEXT NOT NULL DEFAULT '[]',
 required_fields TEXT NOT NULL DEFAULT '[]',
 honeypot_field TEXT NOT NULL DEFAULT '_gotcha',
 rate_limit INTEGER NOT NULL DEFAULT 30,
 webhook_url TEXT,
 is_active INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL,
 updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS submissions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 form_id INTEGER NOT NULL,
 payload TEXT NOT NULL,
 ip TEXT,
 user_agent TEXT,
 referer TEXT,
 status TEXT NOT NULL DEFAULT 'new',
 created_at TEXT NOT NULL,
 FOREIGN KEY(form_id) REFERENCES forms(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_submissions_form_date ON submissions(form_id, created_at DESC);
CREATE TABLE IF NOT EXISTS api_keys (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL,
 token_hash TEXT NOT NULL UNIQUE,
 prefix TEXT NOT NULL,
 created_at TEXT NOT NULL,
 last_used_at TEXT
);
CREATE TABLE IF NOT EXISTS rate_limits (
 form_id INTEGER NOT NULL,
 ip TEXT NOT NULL,
 bucket TEXT NOT NULL,
 hits INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(form_id, ip, bucket)
);
CREATE TABLE IF NOT EXISTS webhook_logs (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 form_id INTEGER NOT NULL,
 submission_id INTEGER NOT NULL,
 url TEXT NOT NULL,
 status_code INTEGER,
 response_body TEXT,
 error TEXT,
 attempts INTEGER NOT NULL DEFAULT 1,
 delivered_at TEXT,
 created_at TEXT NOT NULL,
 FOREIGN KEY(form_id) REFERENCES forms(id) ON DELETE CASCADE,
 FOREIGN KEY(submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);
SQL);
    }
}
