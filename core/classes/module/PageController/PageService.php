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
 * PageService — сервис для работы со страницами
 *
 * Наследует базовый класс Service, используя:
 *  - safeQuery() — вместо try/catch + логирование в каждом методе
 *  - getCached() — вместо отдельных массивов $cacheBySlug, $cacheById и т.д.
 *
 * Оптимизирован для:
 *  - Минимизации запросов к БД
 *  - In-memory кэширования (повторные обращения — без БД)
 *  - Нормализации URI через класс Url
 *  - Чёткой логики поиска (home → slug → 404 → fallback)
 */
class PageService extends Service
{
    // =========================================================================
    // Константы (нет magic strings)
    // =========================================================================
    private const TYPE_HOME    = 'home';
    private const TYPE_HUB     = 'hub';
    private const TYPE_ARTICLE = 'article';
    private const TYPE_404     = '404';
    private const TYPE_500     = '500';
    private const STATUS_PUBLISHED = 'published';

    // =========================================================================
    // Собственные зависимости (сверх базовых DbQuery + Logger)
    // =========================================================================
    private Config $config;

    public function __construct(DbQuery $db, Logger $logger, Config $config)
    {
        parent::__construct($db, $logger);
        $this->config = $config;
    }

    // =========================================================================
    // ПУБЛИЧНЫЙ API
    // =========================================================================
    /**
     * Находит страницу по URI запроса.
     *
     * Логика:
     *  - Пустой URI → getHomePage()
     *  - Иначе → getPageBySlug() → если null → get404Page()
     */
    public function getPageByUri(string $uri): array
    {
        $normalizedUri = Url::normalize($uri);
        if ($normalizedUri === '') {
            return $this->getHomePage();
        }

        $page = $this->getPageBySlug($normalizedUri);
        if ($page !== null) {
            return $page;
        }

        $this->logger->debug("Страница не найдена: '$normalizedUri', ищем 404");
        return $this->get404Page();
    }

    /**
     * Ищет опубликованную страницу по slug (с кэшированием)
     */
    public function getPageBySlug(string $slug): ?array
    {
        $slug = Url::normalize($slug);
        return $this->getCached("slug:$slug", fn() => $this->findPublishedPage($slug));
    }

    /**
     * Ищет страницу по ID (с кэшированием)
     */
    public function getPageById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->getCached("id:$id", fn() =>
            $this->safeQuery(
                fn() => $this->db->find('pages', $id) ?: null,
                "Ошибка поиска страницы id=$id",
                null
            )
        );
    }

    /**
     * Возвращает главную страницу (с кэшированием)
     */
    public function getHomePage(): array
    {
        return $this->getCached('home', fn() =>
            $this->findHomePage() ?? $this->getDefault404Page()
        );
    }

    /**
     * Возвращает страницу 404 (с кэшированием)
     */
    public function get404Page(): array
    {
        return $this->getCached('404', fn() =>
            $this->find404Page() ?? $this->getDefault404Page()
        );
    }

    /**
     * Возвращает страницу 500 (с кэшированием)
     */
    public function get500Page(): array
    {
        return $this->getCached('500', fn() =>
            $this->find500Page() ?? $this->getDefault500Page()
        );
    }

    /**
     * Возвращает родительскую страницу по ID
     */
    public function getParentPage(?int $parentId): ?array
    {
        if ($parentId === null) {
            return null;
        }
        return $this->getPageById($parentId);
    }

    /**
     * Строит цепочку хлебных крошек для страницы
     *
     * @param int $pageId ID страницы
     * @return array массив страниц от корня до текущей
     */
    public function getBreadcrumbs(int $pageId): array
    {
        $breadcrumbs = [];
        $currentId   = $pageId;
        $visited     = [];
        $maxDepth    = 10;

        while ($currentId !== null && !isset($visited[$currentId]) && $maxDepth-- > 0) {
            $visited[$currentId] = true;
            $page = $this->getPageById($currentId);
            if (!$page) {
                break;
            }
            array_unshift($breadcrumbs, $page);
            $currentId = $page['parent_id'] ?? null;
        }

        return $breadcrumbs;
    }

    // =========================================================================
    // ПРИВАТНЫЕ МЕТОДЫ — поиск в БД
    // =========================================================================
    private function findPublishedPage(string $slug): ?array
    {
        return $this->safeQuery(
            fn() => $this->db->selectOne('pages', [
                'slug'   => $slug,
                'status' => self::STATUS_PUBLISHED
            ]) ?: null,
            "Ошибка поиска страницы по slug '$slug'",
            null
        );
    }

    private function findHomePage(): ?array
    {
        return $this->safeQuery(
            fn() => $this->db->selectOne('pages', [
                'page_type' => self::TYPE_HOME,
                'status'    => self::STATUS_PUBLISHED
            ]) ?: null,
            'Ошибка поиска главной страницы',
            null
        );
    }

    private function find404Page(): ?array
    {
        return $this->safeQuery(function () {
            // Сначала ищем по slug='404'
            $page = $this->db->selectOne('pages', [
                'slug'   => '404',
                'status' => self::STATUS_PUBLISHED
            ]);
            if ($page) {
                return $page;
            }
            // Fallback: по page_type='404'
            $page = $this->db->selectOne('pages', [
                'page_type' => self::TYPE_404,
                'status'    => self::STATUS_PUBLISHED
            ]);
            return $page ?: null;
        }, 'Ошибка поиска страницы 404', null);
    }

    private function find500Page(): ?array
    {
        return $this->safeQuery(function () {
            $page = $this->db->selectOne('pages', [
                'slug'   => '500',
                'status' => self::STATUS_PUBLISHED
            ]);
            if ($page) {
                return $page;
            }
            $page = $this->db->selectOne('pages', [
                'page_type' => self::TYPE_500,
                'status'    => self::STATUS_PUBLISHED
            ]);
            return $page ?: null;
        }, 'Ошибка поиска страницы 500', null);
    }

    // =========================================================================
    // Fallback-страницы (хардкод, если БД недоступна)
    // =========================================================================
    private function getDefault404Page(): array
    {
        return [
            'id'             => 0,
            'title'          => 'Страница не найдена',
            'description'    => 'Запрошенная страница не существует',
            'keywords'       => '',
            'canonical'      => '',
            'og_title'       => '404 — Не найдено',
            'og_description' => 'Запрошенная страница не существует',
            'meta_robots'    => 'noindex, nofollow',
            'og_image'       => '',
            'slug'           => '404',
            'content'        => '<h1>404 — Страница не найдена</h1>'
                . '<p>Извините, но запрашиваемая вами страница не существует или была удалена.</p>',
            'page_type'      => self::TYPE_404,
            'parent_id'      => null,
            'status'         => self::STATUS_PUBLISHED,
            'sort_order'     => 0,
            'is_in_menu'     => 0,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
            'published_at'   => null,
        ];
    }

    private function getDefault500Page(): array
    {
        return [
            'id'             => 0,
            'title'          => 'Ошибка сервера',
            'description'    => 'Внутренняя ошибка сервера',
            'keywords'       => '',
            'canonical'      => '',
            'og_title'       => '500 — Ошибка сервера',
            'og_description' => 'Внутренняя ошибка сервера',
            'meta_robots'    => 'noindex, nofollow',
            'og_image'       => '',
            'slug'           => '500',
            'content'        => '<h1>500 — Внутренняя ошибка сервера</h1>'
                . '<p>Извините, на сервере произошла ошибка. Пожалуйста, попробуйте позже.</p>',
            'page_type'      => self::TYPE_500,
            'parent_id'      => null,
            'status'         => self::STATUS_PUBLISHED,
            'sort_order'     => 0,
            'is_in_menu'     => 0,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
            'published_at'   => null,
        ];
    }
}