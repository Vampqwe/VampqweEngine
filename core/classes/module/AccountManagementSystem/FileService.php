<?php
declare(strict_types=1);

class FileService
{
    private DbQuery $db;
    private Config $config;
    private Logger $logger;

    public function __construct(DbQuery $db, Config $config, Logger $logger)
    {
        $this->db = $db;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function upload(int $userId, array $uploadedFile, string $bucket = 'uploads'): array
    {
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            throw new InvalidArgumentException('Файл не загружен');
        }

        $fileName = bin2hex(random_bytes(16)) . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '', basename((string)$uploadedFile['name']));
        $storageRoot = rtrim((string)$this->config->getEnv('FILE_STORAGE_PATH', __DIR__ . '/../../../../storage/uploads'), '/\\');
        $targetDir = $storageRoot . '/' . $bucket . '/' . date('Y') . '/' . date('m');

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Не удалось создать каталог хранения файла');
        }

        $targetPath = $targetDir . '/' . $fileName;
        if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            throw new RuntimeException('Не удалось сохранить файл');
        }

        $hash = hash_file('sha256', $targetPath);
        $size = filesize($targetPath);

        $this->db->insert('user_files', [
            'user_id' => $userId,
            'bucket' => $bucket,
            'file_name' => $fileName,
            'original_name' => basename((string)$uploadedFile['name']),
            'mime_type' => mime_content_type($targetPath) ?: 'application/octet-stream',
            'size_bytes' => (string)$size,
            'sha256' => $hash,
            'storage_path' => $targetPath,
            'visibility' => 'private',
        ]);

        return [
            'id' => (int)$this->db->lastInsertId(),
            'user_id' => $userId,
            'bucket' => $bucket,
            'file_name' => $fileName,
            'size_bytes' => (int)$size,
            'sha256' => $hash,
            'storage_path' => $targetPath,
        ];
    }

    public function delete(int $fileId, int $userId): bool
    {
        $file = $this->db->selectOne('user_files', ['id' => $fileId, 'user_id' => $userId]);

        if (!$file) {
            return false;
        }

        if (is_file($file['storage_path'])) {
            @unlink($file['storage_path']);
        }

        $this->db->delete('user_files', ['id' => $fileId, 'user_id' => $userId]);

        return true;
    }

    public function listByUser(int $userId): array
    {
        return $this->db->select('user_files', ['user_id' => $userId], ['orderBy' => 'created_at DESC']);
    }
}
