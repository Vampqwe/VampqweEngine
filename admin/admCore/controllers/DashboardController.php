<?php
declare(strict_types=1);
/**
 * DashboardController — главная страница админки
 *
 * Наследует BasePageController из ядра — использует те же
 * render(), redirect(), json() и т.д.
 */
class DashboardController extends BasePageController
{
    private DashboardService $dashboardService;

    public function __construct(
        DashboardService $dashboardService,
        Config $config,
        Logger $logger
    ) {
        parent::__construct($config, $logger);
        $this->dashboardService = $dashboardService;
        $this->templatesPath = Route::getPathRoot() . '/admin/templates/';
    }

    /**
     * Главная страница админки
     */
    public function index(): void
    {
        $stats = $this->dashboardService->getStats();

        $this->display('layout.html', [
            'title'    => 'Панель управления',
            'content'  => $this->render('dashboard.html', $stats),
            // ⭐ Меню — статичный шаблон с кэшированием
            'menu'     => $this->renderCached('partials/menu.html'),
        ]);
    }
}