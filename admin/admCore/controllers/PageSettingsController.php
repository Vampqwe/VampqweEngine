<?php
declare(strict_types=1);
/**
 * PageSettingsController — управление страницами в админке
 *
 * Реализует CRUD для таблицы pages + управление схемами Schema.org
 */
class PageSettingsController extends BasePageController
{
    private PageService   $pageService;
    private SchemaService $schemaService;
    private Validator     $validator;
    private DbQuery       $db;

    public function __construct(
        PageService $pageService,
        SchemaService $schemaService,
        Validator $validator,
        DbQuery $db,
        Config $config,
        Logger $logger
    ) {
        parent::__construct($config, $logger);
        $this->pageService   = $pageService;
        $this->schemaService = $schemaService;
        $this->validator     = $validator;
        $this->db            = $db;
        $this->templatesPath = Route::getPathRoot() . '/admin/templates/';
    }

    // =========================================================================
    // Список страниц
    // =========================================================================
    public function index(): void
    {
        $search     = trim($_GET['search'] ?? '');
        $status     = $_GET['status'] ?? '';
        $pageType   = $_GET['type'] ?? '';
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = 20;
        $offset     = ($page - 1) * $perPage;

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(title LIKE ? OR slug LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($status !== '') {
            $where[]  = "status = ?";
            $params[] = $status;
        }
        if ($pageType !== '') {
            $where[]  = "page_type = ?";
            $params[] = $pageType;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->db->query(
            "SELECT COUNT(*) FROM pages $whereSql",
            $params
        )->fetchColumn();

        $pages = $this->db->query(
            "SELECT id, title, slug, page_type, status, sort_order, updated_at
             FROM pages $whereSql
             ORDER BY sort_order ASC, id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);

        $alertHtml = $this->renderAlerts();

        $this->display('layout.html', [
            'title'         => 'Страницы',
            'menu'          => $this->renderCached('partials/menu.html'),
            'content'       => $alertHtml . $this->render('pages/list.html', [
                'pages'         => $this->renderPagesRows($pages),
                'search_query'  => htmlspecialchars($search),
                'status'        => $status,
                'type'          => $pageType,
                'pagination'    => $this->renderPagination($page, $perPage, (int)$total, $search, $status, $pageType),
                'total'         => (int)$total,
            ]),
        ]);
    }

    // =========================================================================
    // Создание страницы
    // =========================================================================
    public function create(): void
    {
        $this->display('layout.html', [
            'title'   => 'Новая страница',
            'menu'    => $this->renderCached('partials/menu.html'),
            'content' => $this->render('pages/form.html', $this->getFormData()),
        ]);
    }

    public function store(): void
    {
        $data = $this->preparePostData();

        try {
            $this->validatePageData($data);
            $pageId = $this->db->insert('pages', $data);
            $this->logger->info("Создана страница ID=$pageId: {$data['title']}");

            // ⭐ Сохраняем схемы для новой страницы
            $this->syncSchemas((int)$pageId, $_POST['schemas'] ?? []);

            $this->redirect('/admin/pages?created=1');
        } catch (Throwable $e) {
            $this->logger->error('Ошибка создания страницы: ' . $e->getMessage());
            $this->display('layout.html', [
                'title'   => 'Новая страница',
                'menu'    => $this->renderCached('partials/menu.html'),
                'content' => $this->render('pages/form.html', $this->getFormData($data, $e->getMessage())),
            ]);
        }
    }

    // =========================================================================
    // Редактирование страницы
    // =========================================================================
    public function edit(int $id = 0): void
    {
        $page = $this->pageService->getPageById($id);
        if (!$page) {
            $this->redirect('/admin/pages?error=not_found');
            return;
        }

        // ⭐ Получаем схемы, привязанные к этой странице
        $pageSchemas = $this->getPageSchemas($id);

        $this->display('layout.html', [
            'title'   => 'Редактирование: ' . htmlspecialchars($page['title']),
            'menu'    => $this->renderCached('partials/menu.html'),
            'content' => $this->render('pages/form.html', $this->getFormData($page, '', $pageSchemas)),
        ]);
    }

    public function update(int $id = 0): void
    {
        $page = $this->pageService->getPageById($id);
        if (!$page) {
            $this->redirect('/admin/pages?error=not_found');
            return;
        }

        $data = $this->preparePostData();

        try {
            $this->validatePageData($data, $id);
            $this->db->updateById('pages', $id, $data);
            $this->pageService->clearCache();
            $this->logger->info("Обновлена страница ID=$id: {$data['title']}");

            // ⭐ Синхронизируем схемы
            $this->syncSchemas($id, $_POST['schemas'] ?? []);

            $this->redirect('/admin/pages?updated=1');
        } catch (Throwable $e) {
            $this->logger->error('Ошибка обновления страницы: ' . $e->getMessage());
            $data['id'] = $id;
            $pageSchemas = $this->getPageSchemas($id);
            $this->display('layout.html', [
                'title'   => 'Редактирование',
                'menu'    => $this->renderCached('partials/menu.html'),
                'content' => $this->render('pages/form.html', $this->getFormData($data, $e->getMessage(), $pageSchemas)),
            ]);
        }
    }

    // =========================================================================
    // Удаление страницы
    // =========================================================================
    public function delete(int $id = 0): void
    {
        $protected = ['home', '404', '500'];
        $page = $this->pageService->getPageById($id);

        if (!$page) {
            $this->redirect('/admin/pages?error=not_found');
            return;
        }

        if (in_array($page['page_type'], $protected, true)) {
            $this->redirect('/admin/pages?error=protected');
            return;
        }

        try {
            // ⭐ Схемы удалятся каскадно через FOREIGN KEY (fk_schema_page)
            $this->db->deleteById('pages', $id);
            $this->pageService->clearCache();
            $this->logger->info("Удалена страница ID=$id: {$page['title']}");

            $this->redirect('/admin/pages?deleted=1');
        } catch (Throwable $e) {
            $this->logger->error('Ошибка удаления страницы: ' . $e->getMessage());
            $this->redirect('/admin/pages?error=delete_failed');
        }
    }

    // =========================================================================
    // ⭐ Работа со схемами Schema.org
    // =========================================================================
    /**
     * Получает все схемы, привязанные к странице
     */
    private function getPageSchemas(int $pageId): array
    {
        try {
            return $this->db->select('schema_org', ['page_id' => $pageId], [
                'orderBy' => 'priority DESC, id ASC'
            ]);
        } catch (Throwable $e) {
            $this->logger->error("Ошибка получения схем для страницы ID=$pageId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Синхронизирует схемы страницы с данными из формы
     *
     * Логика:
     *  - Если у схемы есть id — обновляем существующую
     *  - Если id пустой — создаём новую
     *  - Схемы, которых нет в POST, но есть в БД — удаляем
     */
    private function syncSchemas(int $pageId, array $schemasData): void
    {
        // Получаем текущие схемы из БД
        $existingSchemas = $this->getPageSchemas($pageId);
        $existingIds     = array_column($existingSchemas, 'id');
        $processedIds    = [];

        foreach ($schemasData as $schemaData) {
            // Пропускаем пустые блоки
            if (empty($schemaData['schema_type']) && empty($schemaData['data'])) {
                continue;
            }

            $schemaId = !empty($schemaData['id']) ? (int)$schemaData['id'] : null;

            // Валидация JSON-LD
            $jsonData = $schemaData['data'] ?? '';
            if (is_array($jsonData)) {
                $jsonData = json_encode($jsonData, JSON_UNESCAPED_UNICODE);
            }

            if ($jsonData !== '' && !$this->schemaService->validateJsonLd($jsonData)) {
                throw new RuntimeException("Невалидный JSON-LD в схеме: {$schemaData['schema_type']}");
            }

            $schemaRow = [
                'page_id'     => $pageId,
                'route'       => null,
                'schema_type' => trim($schemaData['schema_type'] ?? 'Article'),
                'data'        => $jsonData,
                'priority'    => (int)($schemaData['priority'] ?? 0),
                'is_active'   => isset($schemaData['is_active']) ? 1 : 0,
            ];

            if ($schemaId !== null && in_array($schemaId, $existingIds, true)) {
                // Обновляем существующую схему
                $this->db->updateById('schema_org', $schemaId, $schemaRow);
                $this->schemaService->clearCache("schema:$schemaId");
                $processedIds[] = $schemaId;
            } else {
                // Создаём новую схему
                $newId = $this->db->insert('schema_org', $schemaRow);
                $processedIds[] = $newId;
            }
        }

        // Удаляем схемы, которых нет в POST
        $toDelete = array_diff($existingIds, $processedIds);
        foreach ($toDelete as $schemaId) {
            $this->db->deleteById('schema_org', (int)$schemaId);
            $this->schemaService->clearCache("schema:$schemaId");
        }

        if (!empty($processedIds)) {
            $this->logger->info("Синхронизировано " . count($processedIds) . " схем для страницы ID=$pageId");
        }
    }

    // =========================================================================
    // Вспомогательные методы
    // =========================================================================
    private function preparePostData(): array
    {
        $status = $_POST['status'] ?? 'draft';

        return [
            'title'          => trim($_POST['title'] ?? ''),
            'slug'           => trim($_POST['slug'] ?? ''),
            'description'    => trim($_POST['description'] ?? ''),
            'keywords'       => trim($_POST['keywords'] ?? ''),
            'content'        => $_POST['content'] ?? '',
            'page_type'      => $_POST['page_type'] ?? 'article',
            'parent_id'      => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'status'         => $status,
            'canonical'      => trim($_POST['canonical'] ?? ''),
            'og_title'       => trim($_POST['og_title'] ?? ''),
            'og_description' => trim($_POST['og_description'] ?? ''),
            'og_image'       => trim($_POST['og_image'] ?? ''),
            'meta_robots'    => trim($_POST['meta_robots'] ?? 'index, follow'),
            'scripts'        => !empty($_POST['scripts']) ? trim($_POST['scripts']) : null,
            'author_id'      => !empty($_POST['author_id']) ? (int)$_POST['author_id'] : null,
            'sort_order'     => (int)($_POST['sort_order'] ?? 0),
            'is_in_menu'     => isset($_POST['is_in_menu']) ? 1 : 0,
            'published_at'   => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ];
    }

    private function validatePageData(array $data, ?int $excludeId = null): void
    {
        $this->validator->reset();
        $this->validator->setData($data);

        $this->validator
            ->required('title', 'Заголовок обязателен')
            ->required('slug', 'Slug обязателен')
            ->required('content', 'Контент обязателен')
            ->regex('slug', '/^[a-z0-9\-_\/]+$/', 'Slug может содержать только латинские буквы, цифры, дефис, подчёркивание и слэш');

        $slug = $data['slug'] ?? '';
        if ($slug !== '') {
            $where = ['slug' => $slug];
            if ($excludeId !== null) {
                $where['id'] = ['!=' => $excludeId];
            }
            $exists = $this->db->count('pages', $where);
            if ($exists > 0) {
                $this->validator->addCustomError('slug', 'Такой slug уже существует');
            }
        }

        if (!empty($data['scripts'])) {
            $decoded = json_decode($data['scripts'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $this->validator->addCustomError('scripts', 'Должен быть валидный JSON-массив');
            }
        }

        if ($this->validator->hasErrors()) {
            throw new RuntimeException(implode('; ', $this->validator->getAllErrorMessages()));
        }
    }

    /**
     * Собирает данные для формы
     *
     * @param array $page данные страницы
     * @param string $error сообщение об ошибке
     * @param array $pageSchemas схемы, привязанные к странице
     */
    private function getFormData(array $page = [], string $error = '', array $pageSchemas = []): array
    {
        $authors = $this->db->select('authors', ['is_active' => 1], ['orderBy' => 'name ASC']);
        $parents = $this->db->select('pages', [], [
            'columns' => ['id', 'title', 'slug'],
            'orderBy' => 'title ASC',
        ]);

        $currentId = $page['id'] ?? null;
        if ($currentId) {
            $parents = array_filter($parents, fn($p) => (int)$p['id'] !== (int)$currentId);
        }

        // Options для select'ов
        $authorOptions = '<option value="">— Не указан —</option>';
        foreach ($authors as $a) {
            $selected = ((int)($page['author_id'] ?? 0) === (int)$a['id']) ? ' selected' : '';
            $authorOptions .= '<option value="' . $a['id'] . '"' . $selected . '>'
                . htmlspecialchars($a['name']) . '</option>';
        }

        $parentOptions = '<option value="">— Нет —</option>';
        foreach ($parents as $p) {
            $selected = ((int)($page['parent_id'] ?? 0) === (int)$p['id']) ? ' selected' : '';
            $parentOptions .= '<option value="' . $p['id'] . '"' . $selected . '>'
                . htmlspecialchars($p['title']) . ' (/' . htmlspecialchars($p['slug']) . ')</option>';
        }

        $pageTypeOptions = '';
        foreach (['article', 'home', 'hub', 'page', 'contact', 'about', '404', '500'] as $t) {
            $selected = (($page['page_type'] ?? 'article') === $t) ? ' selected' : '';
            $pageTypeOptions .= '<option value="' . $t . '"' . $selected . '>' . ucfirst($t) . '</option>';
        }

        $statusOptions = '';
        foreach (['draft', 'published', 'archived'] as $s) {
            $selected = (($page['status'] ?? 'draft') === $s) ? ' selected' : '';
            $statusOptions .= '<option value="' . $s . '"' . $selected . '>' . ucfirst($s) . '</option>';
        }

        $metaRobotsOptions = '';
        foreach (['index, follow', 'noindex, nofollow', 'noindex, follow', 'index, nofollow'] as $r) {
            $selected = (($page['meta_robots'] ?? 'index, follow') === $r) ? ' selected' : '';
            $metaRobotsOptions .= '<option value="' . htmlspecialchars($r) . '"' . $selected . '>'
                . htmlspecialchars($r) . '</option>';
        }

        // ⭐ Генерируем HTML блоков схем
        $schemasHtml = $this->renderSchemasBlocks($pageSchemas);

        $isEdit = !empty($page['id']);
        $formAction = $isEdit
            ? '/admin/pages/update/' . (int)$page['id']
            : '/admin/pages/store';

        return [
            // Данные формы
            'id'                  => $page['id'] ?? '',
            'title'               => htmlspecialchars($page['title'] ?? ''),
            'slug'                => htmlspecialchars($page['slug'] ?? ''),
            'description'         => htmlspecialchars($page['description'] ?? ''),
            'keywords'            => htmlspecialchars($page['keywords'] ?? ''),
            'content'             => htmlspecialchars($page['content'] ?? ''),
            'canonical'           => htmlspecialchars($page['canonical'] ?? ''),
            'og_title'            => htmlspecialchars($page['og_title'] ?? ''),
            'og_description'      => htmlspecialchars($page['og_description'] ?? ''),
            'og_image'            => htmlspecialchars($page['og_image'] ?? ''),
            'scripts'             => htmlspecialchars($page['scripts'] ?? ''),
            'sort_order'          => (int)($page['sort_order'] ?? 0),
            'is_in_menu_checked'  => !empty($page['is_in_menu']) ? 'checked' : '',

            // Options
            'author_options'      => $authorOptions,
            'parent_options'      => $parentOptions,
            'page_type_options'   => $pageTypeOptions,
            'status_options'      => $statusOptions,
            'meta_robots_options' => $metaRobotsOptions,

            // ⭐ Схемы
            'schemas_blocks'      => $schemasHtml,

            // Служебное
            'form_action'         => $formAction,
            'form_title'          => $isEdit ? 'Редактирование страницы' : 'Новая страница',
            'submit_text'         => $isEdit ? '💾 Сохранить изменения' : '✨ Создать страницу',
            'error_alert'         => $error !== ''
                ? '<div class="alert alert-danger"><span class="alert-icon">❌</span><div class="alert-content"><strong>Ошибка валидации!</strong> ' . htmlspecialchars($error) . '</div></div>'
                : '',
        ];
    }

    /**
     * ⭐ Генерирует HTML-блоки схем для формы
     */
    private function renderSchemasBlocks(array $schemas): string
    {
        if (empty($schemas)) {
            return '';
        }

        $html = '';
        $index = 0;
        foreach ($schemas as $schema) {
            $id        = (int)($schema['id'] ?? 0);
            $type      = htmlspecialchars($schema['schema_type'] ?? 'Article');
            $data      = htmlspecialchars($schema['data'] ?? '');
            $priority  = (int)($schema['priority'] ?? 0);
            $isActive  = !empty($schema['is_active']) ? 'checked' : '';

            $html .= '<div class="schema-block" data-schema-index="' . $index . '">
                <input type="hidden" name="schemas[' . $index . '][id]" value="' . $id . '">
                <div class="d-flex justify-between align-center mb-2">
                    <h6 class="mb-0">Схема #' . ($index + 1) . ' <span class="badge badge-info">' . $type . '</span></h6>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.schema-block\').remove()">✕ Удалить</button>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Тип схемы</label>
                        <select name="schemas[' . $index . '][schema_type]" class="form-control">';

            foreach (['Article', 'WebPage', 'MedicalWebPage', 'FAQPage', 'VeterinaryCare', 'Organization', 'Person', 'LocalBusiness', 'BreadcrumbList'] as $t) {
                $selected = $type === $t ? ' selected' : '';
                $html .= '<option value="' . $t . '"' . $selected . '>' . $t . '</option>';
            }

            $html .= '       </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Приоритет</label>
                        <input type="number" name="schemas[' . $index . '][priority]" class="form-control" value="' . $priority . '">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Активна</label>
                        <label class="form-check mt-2">
                            <input type="checkbox" name="schemas[' . $index . '][is_active]" ' . $isActive . '>
                            <span>Да</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">JSON-LD данные</label>
                    <textarea name="schemas[' . $index . '][data]" class="form-control" rows="5">' . $data . '</textarea>
                    <small class="form-hint">Валидный JSON. @context и @type добавятся автоматически.</small>
                </div>
            </div>';

            $index++;
        }

        return $html;
    }

    private function renderPagesRows(array $pages): string
    {
        if (empty($pages)) {
            return '<tr><td colspan="7" class="text-center text-muted" style="padding: 40px;">Страницы не найдены</td></tr>';
        }

        $html = '';
        foreach ($pages as $p) {
            $id          = (int)$p['id'];
            $title       = htmlspecialchars($p['title']);
            $slug        = htmlspecialchars($p['slug']);
            $type        = htmlspecialchars($p['page_type']);
            $status      = htmlspecialchars($p['status']);
            $statusClass = $this->getStatusBadgeClass($status);
            $statusText  = $this->getStatusText($status);
            $updatedAt   = htmlspecialchars($p['updated_at'] ?? '');

            $html .= '<tr>
                <td class="text-muted">' . $id . '</td>
                <td><strong>' . $title . '</strong></td>
                <td><code style="font-size: 12px;">/' . $slug . '</code></td>
                <td><span class="badge badge-info">' . $type . '</span></td>
                <td><span class="badge badge-dot badge-' . $statusClass . '">' . $statusText . '</span></td>
                <td class="text-muted">' . $updatedAt . '</td>
                <td>
                    <div class="table-actions">
                        <a href="/admin/pages/edit/' . $id . '" class="btn btn-outline btn-sm" title="Редактировать">✏️</a>
                        <a href="/' . $slug . '" target="_blank" class="btn btn-ghost btn-sm" title="Просмотр">👁️</a>
                        <form method="POST" action="/admin/pages/delete/' . $id . '" style="display:inline;"
                              onsubmit="return confirm(\'Удалить страницу «' . addslashes($p['title']) . '»? Это действие необратимо.\')">
                            <button type="submit" class="btn btn-danger btn-sm" title="Удалить">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>';
        }
        return $html;
    }

    private function renderPagination(int $currentPage, int $perPage, int $total, string $search, string $status, string $type): string
    {
        $totalPages = (int)ceil($total / $perPage);
        if ($totalPages <= 1) return '';

        $qs = [];
        if ($search !== '') $qs[] = 'search=' . urlencode($search);
        if ($status !== '') $qs[] = 'status=' . urlencode($status);
        if ($type !== '')   $qs[] = 'type=' . urlencode($type);
        $qsStr = $qs ? '&' . implode('&', $qs) : '';

        $html = '<div class="pagination">';
        if ($currentPage > 1) {
            $html .= '<a href="/admin/pages?page=' . ($currentPage - 1) . $qsStr . '" class="pagination-item">← Назад</a>';
        } else {
            $html .= '<span class="pagination-item disabled">← Назад</span>';
        }
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === $currentPage ? ' active' : '';
            $html .= '<a href="/admin/pages?page=' . $i . $qsStr . '" class="pagination-item' . $active . '">' . $i . '</a>';
        }
        if ($currentPage < $totalPages) {
            $html .= '<a href="/admin/pages?page=' . ($currentPage + 1) . $qsStr . '" class="pagination-item">Вперёд →</a>';
        } else {
            $html .= '<span class="pagination-item disabled">Вперёд →</span>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderAlerts(): string
    {
        $alerts = [];
        if (isset($_GET['created'])) {
            $alerts[] = '<div class="alert alert-success" data-autohide><span class="alert-icon">✅</span><div class="alert-content"><strong>Успешно!</strong> Страница создана.</div></div>';
        }
        if (isset($_GET['updated'])) {
            $alerts[] = '<div class="alert alert-success" data-autohide><span class="alert-icon">✅</span><div class="alert-content"><strong>Успешно!</strong> Страница обновлена.</div></div>';
        }
        if (isset($_GET['deleted'])) {
            $alerts[] = '<div class="alert alert-success" data-autohide><span class="alert-icon">🗑️</span><div class="alert-content"><strong>Успешно!</strong> Страница удалена.</div></div>';
        }
        if (isset($_GET['error'])) {
            $messages = [
                'not_found'     => 'Страница не найдена',
                'protected'     => 'Системную страницу (home/404/500) нельзя удалить',
                'delete_failed' => 'Не удалось удалить страницу',
            ];
            $msg = $messages[$_GET['error']] ?? 'Произошла ошибка';
            $alerts[] = '<div class="alert alert-danger"><span class="alert-icon">❌</span><div class="alert-content"><strong>Ошибка!</strong> ' . htmlspecialchars($msg) . '</div></div>';
        }
        return implode("\n", $alerts);
    }

    private function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'published' => 'success',
            'draft'     => 'warning',
            'archived'  => 'neutral',
            default     => 'neutral',
        };
    }

    private function getStatusText(string $status): string
    {
        return match ($status) {
            'published' => 'Опубликовано',
            'draft'     => 'Черновик',
            'archived'  => 'В архиве',
            default     => $status,
        };
    }
}