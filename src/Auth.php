<?php

declare(strict_types=1);

final class Auth {
    public function __construct(private Database $db, private array $config) { $this->seedAdmin(); }
    private function seedAdmin(): void {
        $count = $this->db->one('SELECT COUNT(*) AS c FROM users');
        if ((int)($count['c'] ?? 0) === 0) {
            $email = strtolower(trim((string)($this->config['admin_email'] ?? 'admin@example.com')));
            $password = (string)($this->config['admin_password'] ?? 'change-me-now');
            $this->db->exec('INSERT INTO users(email,password_hash,created_at) VALUES(?,?,?)', [$email,password_hash($password,PASSWORD_DEFAULT),now_iso()]);
        }
    }
    public function check(): bool { return !empty($_SESSION['user_id']); }
    public function id(): ?int { return $this->check() ? (int)$_SESSION['user_id'] : null; }
    public function require(): void { if (!$this->check()) redirect('/login'); }
    public function attempt(string $email, string $password): bool {
        $u=$this->db->one('SELECT * FROM users WHERE email=?',[strtolower(trim($email))]);
        if (!$u || !password_verify($password,$u['password_hash'])) return false;
        session_regenerate_id(true); $_SESSION['user_id']=(int)$u['id']; return true;
    }
    public function logout(): void { $_SESSION=[]; if (session_status()===PHP_SESSION_ACTIVE) session_destroy(); }
    public function verifyApiKey(?string $token): bool {
        if (!$token) return false;
        $row=$this->db->one('SELECT * FROM api_keys WHERE token_hash=?',[hash('sha256',$token)]);
        if (!$row) return false;
        $this->db->exec('UPDATE api_keys SET last_used_at=? WHERE id=?',[now_iso(),$row['id']]);
        return true;
    }
}
