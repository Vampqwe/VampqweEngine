<?php
declare(strict_types=1);

class UserProfileController extends BasePageController
{
    private AuthService $authService;
    private FileService $fileService;

    public function __construct(AuthService $authService, FileService $fileService, Config $config, Logger $logger)
    {
        parent::__construct($config, $logger);
        $this->authService = $authService;
        $this->fileService = $fileService;
    }

    public function index(): void
    {
        try {
            $user = $this->authService->requireAuth();
            $files = $this->fileService->listByUser((int)$user['id']);
            $filesHtml = '';
            if ($files !== []) {
                $items = array_map(
                    fn(array $file) => '<li>' . htmlspecialchars((string)$file['original_name']) . '</li>',
                    $files
                );
                $filesHtml = '<ul>' . implode('', $items) . '</ul>';
            } else {
                $filesHtml = '<p>Файлов пока нет.</p>';
            }

            echo $this->render('auth/profile.html', [
                'pageTitle' => 'Мой профиль',
                'userEmail' => $user['email'],
                'userRole' => $user['role'],
                'userStatus' => $user['status'],
                'filesHtml' => $filesHtml,
                'error' => '',
            ]);
            return;
        } catch (Throwable $e) {
            header('Location: /login');
            exit;
        }
    }

    public function upload(): void
    {
        try {
            $user = $this->authService->requireAuth();
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                echo $this->render('auth/profile-upload.html', ['pageTitle' => 'Загрузка файла', 'error' => '']);
                return;
            }

            $uploaded = $_FILES['file'] ?? null;
            if (!$uploaded || !isset($uploaded['tmp_name'])) {
                throw new InvalidArgumentException('Файл не выбран');
            }

            $result = $this->fileService->upload((int)$user['id'], $uploaded);
            header('Location: /profile');
            exit;
        } catch (Throwable $e) {
            header('Location: /login');
            exit;
        }
    }
}
