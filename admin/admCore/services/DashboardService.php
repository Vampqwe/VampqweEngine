<?php
declare(strict_types=1);
/**
 * DashboardService — сервис для главной страницы админки
 *
 * Наследует Service из ядра — использует safeQuery(), getCached().
 */
class DashboardService extends Service
{
    /**
     * Возвращает статистику для дашборда
     */
    public function getStats(): array
    {
        return $this->safeQuery(function (): array {
            return [
                'pages_total'     => $this->db->count('pages'),
                'pages_published' => $this->db->count('pages', ['status' => 'published']),
                'pages_draft'     => $this->db->count('pages', ['status' => 'draft']),
                'users_total'     => $this->db->count('users'),
                'schemas_total'   => $this->db->count('schema_org'),
                'authors_total'   => $this->db->count('authors'),
            ];
        }, 'Ошибка получения статистики дашборда', [
            'pages_total' => 0,
            'pages_published' => 0,
            'pages_draft' => 0,
            'users_total' => 0,
            'schemas_total' => 0,
            'authors_total' => 0,
        ]);
    }
}