<?php

declare(strict_types=1);
final class Csrf {
    public function token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=random_token(32); return $_SESSION['_csrf']; }
    public function field(): string { return '<input type="hidden" name="_csrf" value="'.e($this->token()).'">'; }
    public function verify(?string $token): bool { return is_string($token) && hash_equals($this->token(),$token); }
    public function enforce(): void { if (!$this->verify($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) { http_response_code(419); exit('Invalid CSRF token'); } }
}
