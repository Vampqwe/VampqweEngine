<?php
declare(strict_types=1);

class AuthService
{
    private Config $config;
    private DbQuery $db;
    private Logger $logger;
    private SessionService $sessionService;

    public function __construct(Config $config, DbQuery $db, Logger $logger, SessionService $sessionService)
    {
        $this->config = $config;
        $this->db = $db;
        $this->logger = $logger;
        $this->sessionService = $sessionService;
    }

    public function register(array $data): array
    {
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Некорректный email');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Пароль должен быть не меньше 8 символов');
        }

        $existing = $this->db->selectOne('users', ['email' => $email], ['columns' => ['id']]);
        if ($existing) {
            throw new RuntimeException('Пользователь с таким email уже существует');
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        $userId = $this->db->insert('users', [
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'user',
            'status' => 'pending',
            'email_verified' => 0,
        ]);

        $this->db->insert('user_profiles', [
            'user_id' => $userId,
            'timezone' => (string)$this->config->getEnv('DEFAULT_TIMEZONE', 'Europe/Minsk'),
        ]);

        return [
            'user_id' => (int)$userId,
            'email' => $email,
            'status' => 'pending',
        ];
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $user = $this->db->selectOne('users', ['email' => $email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Неверный email или пароль');
        }

        if (in_array((string)$user['status'], ['blocked', 'deleted'], true)) {
            throw new RuntimeException('Аккаунт заблокирован');
        }

        $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => (int)$user['id']]);

        $sessionId = $this->sessionService->createSessionForUser((int)$user['id'], ['email' => $email]);

        return [
            'user_id' => (int)$user['id'],
            'email' => $email,
            'session_id' => $sessionId,
        ];
    }

    public function logout(): void
    {
        $this->sessionService->destroyCurrentSession();
    }

    public function getCurrentUser(): ?array
    {
        $userId = $this->sessionService->getCurrentUserId();
        if ($userId === null) {
            return null;
        }

        return $this->db->selectOne('users', ['id' => $userId]);
    }

    public function requireAuth(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('Требуется авторизация');
        }

        return $user;
    }
}
