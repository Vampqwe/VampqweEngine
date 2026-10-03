<?php
declare(strict_types = 1);
/**
 * VampqweEngine — Proprietary Software
 * Copyright (c) 2024-2026. All rights reserved.
 * Unauthorized copying or distribution is prohibited.
 */
class ConfigException extends RuntimeException {
    public function __construct(string $message = 'Ошибка конфигурации', int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}