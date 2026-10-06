<?php
declare(strict_types=1);

class SessionService
{
    private Config $config;
    private ?Redis $redis = null;

    public function __construct(Config $config)
    {
        $this->config = $config;

        if (class_exists('Redis')) {
            $this->redis = new Redis();
            $host = (string)$config->getEnv('SAVE_SESSION_PATH_HOST', '127.0.0.1');
            $port = (int)$config->getEnv('SAVE_SESSION_PATH_PORT', 6379);
            $auth = (string)$config->getEnv('SAVE_SESSION_PATH_AUTH', '');

            $this->redis->connect($host, $port, 2.0);
            if ($auth !== '') {
                $this->redis->auth($auth);
            }
        }
    }

    public function initSessionHandler(): void
    {
        $handler = (string)$this->config->getEnv('SAVE_SESSION_HANDLER', 'files');
        $method = (string)$this->config->getEnv('SAVE_SESSION_PATH_METHOD', 'tcp');
        $host = (string)$this->config->getEnv('SAVE_SESSION_PATH_HOST', '127.0.0.1');
        $port = (string)$this->config->getEnv('SAVE_SESSION_PATH_PORT', '6379');

        if ($handler === 'redis' && $this->redis instanceof Redis) {
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path', $method . '://' . $host . ':' . $port);
        } else {
            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', sys_get_temp_dir());
        }

        ini_set('session.gc_maxlifetime', (string)$this->config->getEnv('SESSION.GC_MAXLIFETIME', '3600'));
        ini_set('session.cookie_lifetime', (string)$this->config->getEnv('SESSION.COOKIE_LIFETIME', '3600'));
        ini_set('session.cookie_httponly', (string)$this->config->getEnv('SESSION.COOKIE_HTTPONLY', '1'));
        ini_set('session.cookie_secure', (string)$this->config->getEnv('SESSION.COOKIE_SECURE', '1'));
        ini_set('session.cookie_samesite', (string)$this->config->getEnv('SESSION.COOKIE_SAMESITE', 'strict'));
    }

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function createSessionForUser(int $userId, array $meta = []): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $key = 'session:' . $sessionId;

        $payload = [
            'user_id' => $userId,
            'meta' => $meta,
            'created_at' => time(),
            'expires_at' => time() + (int)$this->config->getEnv('SESSION.COOKIE_LIFETIME', 3600),
        ];

        if ($this->redis instanceof Redis) {
            $this->redis->setex($key, (int)$this->config->getEnv('SESSION.GC_MAXLIFETIME', 3600), json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['session_meta'] = $meta;

        $this->setCookie($sessionId);

        return $sessionId;
    }

    public function getCurrentUserId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        return is_numeric($userId) ? (int)$userId : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->getCurrentUserId() !== null;
    }

    public function destroyCurrentSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionId = $_SESSION['session_id'] ?? null;
            if (is_string($sessionId) && $this->redis instanceof Redis) {
                $this->redis->del('session:' . $sessionId);
            }
            session_unset();
            session_destroy();
        }

        $this->clearCookie();
    }

    public function setCookie(string $sessionId): void
    {
        setcookie(
            'session_id',
            $sessionId,
            [
                'expires' => time() + (int)$this->config->getEnv('SESSION.COOKIE_LIFETIME', 3600),
                'path' => '/',
                'secure' => ((string)$this->config->getEnv('SESSION.COOKIE_SECURE', '1') === '1'),
                'httponly' => ((string)$this->config->getEnv('SESSION.COOKIE_HTTPONLY', '1') === '1'),
                'samesite' => (string)$this->config->getEnv('SESSION.COOKIE_SAMESITE', 'strict'),
            ]
        );
    }

    public function clearCookie(): void
    {
        setcookie('session_id', '', time() - 3600, '/', '', true, true);
    }
}
