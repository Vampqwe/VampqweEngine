<?php
declare(strict_types = 1);
/**
 * VampqweEngine — Proprietary Software
 * Copyright (c) 2024-2026. All rights reserved.
 * Unauthorized copying or distribution is prohibited.
 */
class FileException extends RuntimeException {
    public function __construct(string $message = 'Файловая операция не удалась', int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}