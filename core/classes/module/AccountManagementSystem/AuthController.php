<?php
declare(strict_types=1);

class AuthController extends BasePageController
{
    private AuthService $authService;
    private SessionService $sessionService;

    public function __construct(AuthService $authService, SessionService $sessionService, Config $config, Logger $logger)
    {
        parent::__construct($config, $logger);
        $this->authService = $authService;
        $this->sessionService = $sessionService;
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] ?? 'GET' !== 'POST') {
            echo $this->render('auth/register.html', ['error' => '', 'pageTitle' => 'Регистрация']);
            return;
        }

        try {
            $result = $this->authService->register($_POST);
            echo $this->render('auth/register-success.html', [
                'email' => $result['email'],
                'pageTitle' => 'Регистрация успешно создана',
            ]);
            return;
        } catch (Throwable $e) {
            echo $this->render('auth/register.html', [
                'error' => $e->getMessage(),
                'pageTitle' => 'Регистрация',
            ]);
        }
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] ?? 'GET' !== 'POST') {
            echo $this->render('auth/login.html', ['error' => '', 'pageTitle' => 'Вход']);
            return;
        }

        try {
            $email = (string)($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $result = $this->authService->login($email, $password);
            header('Location: /profile');
            exit;
        } catch (Throwable $e) {
            echo $this->render('auth/login.html', [
                'error' => $e->getMessage(),
                'pageTitle' => 'Вход',
            ]);
        }
    }

    public function logout(): void
    {
        try {
            $this->authService->logout();
            header('Location: /login');
            exit;
        } catch (Throwable $e) {
            echo $this->render('auth/logout.html', [
                'error' => $e->getMessage(),
                'pageTitle' => 'Выход',
            ]);
        }
    }
}
