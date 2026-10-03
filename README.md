# Достойный уход (usyplenie.103vet.by)

Информационный сайт ветеринарной клиники **паллиативного ухода**, построенный на лёгком собственном PHP-движке **VampqweEngine**.

- 🌐 Живой сайт: <https://usyplenie.103vet.by/>
- ✉️ Поддержка: care@103vet.by
- 🐛 Баг-трекер: <https://github.com/Vampqwe/usyplenie.103vet/issues>

## Технологии

| Компонент | Описание |
|---|---|
| **PHP** | >= 8.0, `declare(strict_types=1)` везде |
| **MySQL** | через PDO (`ext-pdo`, `ext-pdo_mysql`) |
| **Redis** | хранение сессий (рекомендуется для продакшена) |
| **Composer** | зависимости (`ramsey/uuid`) и classmap-автозагрузка |
| **Apache** | front controller + `.htaccess` (rewrite, защита служебных папок) |
| **Schema.org** | автоматическая JSON-LD разметка страниц |

## Структура проекта

```
.
├── index.php              # Front Controller публичной части (точка входа)
├── sitemap.php            # Динамическая генерация sitemap.xml из БД
├── .htaccess              # Маршрутизация Apache, HTTPS/redirects, защита core/vendor
├── init.cmd               # Windows-скрипт инициализации git-репозитория
├── composer.json          # Зависимости и autoload (classmap)
│
├── core/                  # Ядро VampqweEngine
│   ├── bootstrap.php      # Инициализация: автозагрузка, DI, Config, Logger, Session
│   ├── config.env         # Реальные секреты (НЕ коммитить!)
│   ├── config.env.example # Шаблон конфигурации
│   ├── functions/spl.php  # Резервная автозагрузка без Composer
│   ├── classes/
│   │   ├── system/        # Системные классы: Config, Container (DI), Database/DbQuery,
│   │   │                  # Route, File, Logger, Map, Service, TimeDate, BasePageController
│   │   └── module/        # Прикладные модули: PageController, Schema (JSON-LD),
│   │                      # Template, Url, Helper/Validator, AccountManagementSystem
│   └── log/app.log        # Лог приложения
│
├── admin/                 # Админ-панель
│   ├── admin.php          # Front Controller админки (отдельный логгер, свой роутинг)
│   ├── admCore/           # Контроллеры (Dashboard, PageSettings) и сервисы
│   ├── assets/            # CSS/JS админки
│   └── templates/         # HTML-шаблоны: dashboard, login, pages, users, partials
│
├── templates/             # Шаблоны публичной части (layout, head-full, nav, footer, 500-fallback)
└── vendor/                # Зависимости Composer
```

## Быстрый старт

### Требования

- PHP >= 8.0 с расширениями: `pdo_mysql`, `mbstring`, `json` (и `redis` — опционально)
- MySQL 5.7+/8.0
- Composer
- Apache с `mod_rewrite` (или другой веб-сервер с аналогичной маршрутизацией)

### Установка

```bash
# 1. Клонировать репозиторий
git clone https://github.com/Vampqwe/usyplenie.103vet.git
cd usyplenie.103vet

# 2. Установить зависимости (post-install автоматически выполнит dump-autoload -o)
composer install

# 3. Создать конфигурацию из примера и заполнить свои значения
cp core/config.env.example core/config.env
```

### Конфигурация (`core/config.env`)

Основные переменные:

```ini
# База данных
DB_LOGIN="your_db_user"
DB_PASSWORD="your_db_password"
DB_HOST="127.0.0.1"
DB_NAME="vetMinsk_usyplenie"

# Режим работы
ACTIVE_MODE=MODE_DB

# Сессии (redis рекомендуется для продакшена)
SAVE_SESSION_HANDLER=redis
SAVE_SESSION_PATH_HOST=127.0.0.1
SAVE_SESSION_PATH_PORT=6379

# Сайт
SITE_URL="https://usyplenie.103vet.by/"
SITE_NAME="Достойный уход"

# Отладка: true — показывать стек-трейсы, false/пусто — продакшен
APP_DEBUG=false
```

> ⚠️ Файл `core/config.env` содержит секреты и **не должен попадать в git**.

### Запуск

Укажите корень документа Apache на папку проекта — все запросы через `.htaccess`
маршрутизируются в `index.php` (публичная часть) и `admin/admin.php` (админ-панель).

Локально можно использовать встроенный сервер PHP:

```bash
php -S localhost:8000 index.php
```

## Как это работает

- **Front Controller** (`index.php`): нормализует URI через `Url::getRequestUri()`,
  обрабатывает служебные URL (`sitemap.xml`, `robots.txt`), затем отдаёт страницу
  через `Route` → `PageController` → `Template`.
- **DI-контейнер** (`Container`): все системные сервисы (Config, Logger, DbQuery и др.)
  регистрируются как синглтоны в `core/bootstrap.php`.
- **Страницы** хранятся в таблице `pages` (статус `published`, `slug`, `sort_order`)
  и рендерятся с шаблонами из `templates/`.
- **SEO**: `sitemap.php` генерирует `sitemap.xml` из БД; модуль `Schema` добавляет
  JSON-LD разметку Schema.org по типу страницы.
- **Безопасность**: `.htaccess` закрывает прямой доступ к `core/`, `vendor/`,
  логам и служебным файлам; сессии — cookie-only, HTTPOnly, Secure, SameSite=strict.

## Полезные команды

```bash
composer dump-autoload -o   # пересобрать оптимизированный автозагрузчик
```

`init.cmd` — интерактивный Windows-скрипт для первичной инициализации git
(`git init` → `add` → `commit` → `branch -M` → `remote add origin` → `push`).

## Лицензия

Проект распространяется под лицензией **MIT** (см. [LICENSE.txt](LICENSE.txt)).
Ядро **VampqweEngine** является проприетарным кодом автора — см. заголовки файлов
в `core/`.

## Авторы

- **Vampqwe** — разработка: <https://github.com/Vampqwe>
