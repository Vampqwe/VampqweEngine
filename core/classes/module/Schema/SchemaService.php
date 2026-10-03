<?php
declare(strict_types=1);
/**
 * VampqweEngine — проприетарный PHP-движок
 *
 * @copyright  Copyright (c) 2024-2026. Все права защищены.
 * @author     [Дедюля Александр Иванович / VampqweEngine]
 * @license    Proprietary
 * @link       https://github.com/Vampqwe/VampqweEngine.git
 * @email	   doktor_try@mail.ru
 *
 * Данный код является интеллектуальной собственностью автора.
 * Любое копирование, распространение, модификация или использование
 * без письменного разрешения правообладателя строго запрещено.
 *
 * This software is proprietary and confidential.
 * Unauthorized copying, distribution, or use is strictly prohibited.
 */
/**
 * SchemaService — сервис для работы с данными Schema.org
 *
 * Наследует базовый класс Service, используя:
 *  - safeQuery() — вместо try/catch + логирование в каждом методе
 *  - getCached() — для кэширования схем по ID
 *
 * Отвечает за:
 *  - Получение схем из БД (один оптимизированный запрос)
 *  - CRUD-операции (для админки)
 *  - Валидацию JSON-LD
 *  - Форматирование данных
 *
 * НЕ отвечает за:
 *  - Рендеринг HTML (это делает Schema)
 */
class SchemaService extends Service
{
    // =========================================================================
    // Собственные зависимости (сверх базовых DbQuery + Logger)
    // =========================================================================
    private Validator $validator;

    public function __construct(DbQuery $db, Logger $logger, Validator $validator)
    {
        parent::__construct($db, $logger);
        $this->validator = $validator;
    }

    // =========================================================================
    // Получение схем (для фронтенда)
    // =========================================================================
    /**
     * Получает все схемы для страницы (один SQL-запрос вместо трёх)
     *
     * Объединяет:
     *  - Глобальные (route = '*')
     *  - Привязанные к page_id
     *  - Привязанные к route/slug
     *
     * @param array $pageData данные страницы из PageService
     * @return array отформатированные схемы
     */
    public function getSchemasForPage(array $pageData): array
    {
        return $this->safeQuery(
            function () use ($pageData) {
                $rows = $this->fetchSchemasForPage($pageData);
                return $this->formatSchemas($rows);
            },
            'Ошибка получения схем',
            []
        );
    }

    /**
     * Получает все активные схемы (для админки)
     */
    public function getAllSchemas(): array
    {
        return $this->safeQuery(
            fn() => $this->db->select(
                'schema_org',
                [],
                ['orderBy' => 'priority DESC, id DESC']
            ),
            'Ошибка получения списка схем',
            []
        );
    }

    /**
     * Получает схему по ID (с кэшированием)
     */
    public function getSchemaById(int $id): ?array
    {
        return $this->getCached("schema:$id", fn() =>
            $this->safeQuery(
                fn() => $this->db->find('schema_org', $id) ?: null,
                "Ошибка получения схемы ID=$id",
                null
            )
        );
    }

    // =========================================================================
    // CRUD (для админки)
    // =========================================================================
    /**
     * Создаёт новую схему
     *
     * @return int ID созданной записи
     * @throws RuntimeException если валидация не прошла
     */
    public function createSchema(array $data): int
    {
        $this->validateSchemaData($data);
        $this->checkUniqueness($data);

        return $this->safeQuery(
            fn() => $this->db->insert('schema_org', [
                'route'       => $data['route'] ?? null,
                'page_id'     => $data['page_id'] ?? null,
                'schema_type' => $data['schema_type'],
                'data'        => is_string($data['data']) ? $data['data'] : json_encode($data['data']),
                'priority'    => $data['priority'] ?? 0,
                'is_active'   => $data['is_active'] ?? 1,
            ]),
            'Ошибка создания схемы',
            0
        );
    }

    /**
     * Обновляет схему
     *
     * @return int количество обновлённых записей
     */
    public function updateSchema(int $id, array $data): int
    {
        $this->validateSchemaData($data, $id);
        $this->checkUniqueness($data, $id);

        $result = $this->safeQuery(
            function () use ($id, $data) {
                $updateData = [];
                foreach (['route', 'page_id', 'schema_type', 'data', 'priority', 'is_active'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $updateData[$field] = $field === 'data' && is_array($data[$field])
                            ? json_encode($data[$field])
                            : $data[$field];
                    }
                }
                return $this->db->updateById('schema_org', $id, $updateData);
            },
            "Ошибка обновления схемы ID=$id",
            0
        );

        // Инвалидируем кэш для этой схемы
        $this->clearCache("schema:$id");

        return $result;
    }

    /**
     * Удаляет схему
     */
    public function deleteSchema(int $id): int
    {
        $result = $this->safeQuery(
            fn() => $this->db->deleteById('schema_org', $id),
            "Ошибка удаления схемы ID=$id",
            0
        );

        // Инвалидируем кэш
        $this->clearCache("schema:$id");

        return $result;
    }

    // =========================================================================
    // Валидация
    // =========================================================================
    /**
     * Проверяет, является ли строка валидным JSON-LD
     */
    public function validateJsonLd(string $json): bool
    {
        if ($json === '') {
            return false;
        }
        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
    }

    /**
     * Валидирует данные схемы перед сохранением
     *
     * @throws RuntimeException если валидация не прошла
     */
    private function validateSchemaData(array $data, ?int $excludeId = null): void
    {
        $this->validator->reset();
        $this->validator->setData($data);

        $this->validator->required('schema_type');

        $jsonData = $data['data'] ?? '';
        if (is_array($jsonData)) {
            $jsonData = json_encode($jsonData);
        }
        if ($jsonData !== '' && !$this->validateJsonLd($jsonData)) {
            $this->validator->addCustomError('data', 'Невалидный JSON-LD');
        }

        if (isset($data['priority']) && !is_numeric($data['priority'])) {
            $this->validator->addCustomError('priority', 'Должно быть числом');
        }

        if ($this->validator->hasErrors()) {
            throw new RuntimeException(
                'Ошибка валидации схемы: ' . implode('; ', $this->validator->getAllErrorMessages())
            );
        }
    }

    /**
     * Проверяет уникальность route/page_id
     */
    private function checkUniqueness(array $data, ?int $excludeId = null): void
    {
        if (!empty($data['route']) && $data['route'] !== '*') {
            $count = $this->db->count('schema_org', [
                'route' => $data['route'],
                'id'    => ['!=' => $excludeId ?? 0],
            ]);
            if ($count > 0) {
                throw new RuntimeException("Схема с route='{$data['route']}' уже существует");
            }
        }
    }

    // =========================================================================
    // Внутренние методы
    // =========================================================================
    /**
     * Единый SQL-запрос: глобальные + по page_id + по route
     */
    private function fetchSchemasForPage(array $pageData): array
    {
        $pageId = $pageData['id'] ?? null;
        $slug   = trim($pageData['slug'] ?? '', '/');

        $orClauses = [];
        $params    = [];

        $orClauses[] = "`route` = ?";
        $params[]    = '*';

        if ($pageId !== null) {
            $orClauses[] = "`page_id` = ?";
            $params[]    = $pageId;
        }

        if ($slug !== '') {
            $orClauses[] = "`route` = ?";
            $params[]    = $slug;
        }

        $sql = "SELECT * FROM `schema_org`
                WHERE `is_active` = 1
                AND (" . implode(' OR ', $orClauses) . ")
                ORDER BY `priority` DESC";

        return $this->db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Преобразует строки БД в массив schema.org структур
     */
    private function formatSchemas(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $data = is_string($row['data']) ? json_decode($row['data'], true) : $row['data'];
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning(
                    "Невалидный JSON в схеме ID {$row['id']}: " . json_last_error_msg()
                );
                continue;
            }
            $result[] = array_merge(
                ['@context' => 'https://schema.org', '@type' => $row['schema_type']],
                $data ?: []
            );
        }
        return $result;
    }
}