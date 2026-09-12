# VampqweEngine

**Легковесный и безопасный PHP-движок** — компактное ядро для веб-проектов без тяжёлых фреймворков:
DI-контейнер, конфигурация через .env, база данных (PDO), шаблонизатор, логирование и утилиты из коробки.
Запускается локально под [OSPanel](https://ospanel.io/) (Windows), пишется на чистом PHP 8+.

> ⚠️ Движок находится в активной разработке: API может меняться без предупреждения.

[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-35c28d.svg)](LICENSE)
[![Composer](https://img.shields.io/badge/Composer-vampqwe%2Fvampqwe--engine-885630?logo=composer&logoColor=white)](composer.json)
[![Server](https://img.shields.io/badge/Server-OSPanel-222?logo=windows&logoColor=white)](.osp/project.ini)

---

## Что внутри

Ядро (`core/classes/`) построено на модульной архитектуре с DI-контейнером:

| Модуль | Путь | Назначение |
| --- | --- | --- |
| **DI Container** | `core/classes/system/DI/` | Легковесный DI-контейнер с поддержкой singleton, factory и авто-резолвинга через рефлексию |
| **Config** | `core/classes/system/Config/` | Загрузка .env файлов, доступ к переменным окружения через `getEnv()`; выбрасывает `ConfigException` |
| **DataBase / DbTable** | `core/classes/system/Database/` | Подключение к БД через PDO (композиция), работа с таблицами через Map-объекты, защита от SQL-инъекций |
| **Route** | `core/classes/system/Route/` | Управление путями проекта (basePath, core paths) |
| **Template** | `core/classes/module/Tamplate/` | Шаблонизатор с поддержкой `{key}` и `{{key}}`, экранирование XSS через `assignEscaped()` |
| **File** | `core/classes/system/File/` | Безопасные файловые операции с защитой от path traversal; выбрасывает `FileException` |
| **Logger** | `core/classes/system/Logger/` | Registry-singleton для логирования (один экземпляр на файл лога) |
| **Map** | `core/classes/system/Map/` | Обёртка над ArrayObject для типобезопасной работы с ассоциативными массивами |
| **TimeDate** | `core/classes/system/TimeDate/` | Хелпер для работы с датами и временем (зависит от Config) |
| **Helper** | `core/classes/system/Helper/` | Вспомогательные функции (генерация UUID v4 через ramsey/uuid) |
| **AccountManagementSystem** | `core/classes/module/AccountManagementSystem/` | Система управления аккаунтами: `Account`, `User`, `Session` (в разработке) |
| **PageController / PageService** | `core/classes/module/PageController/` | Контроллер и сервис для работы со страницами (в разработке) |

Все классы подключаются через Composer-автозагрузку (classmap по `core/`) — вручную `require` делать не нужно.

---

## Архитектура

### DI-контейнер

Движок использует собственный легковесный DI-контейнер с тремя режимами:

- **singleton** — один экземпляр на запрос (Config, TimeDate, Logger, DataBase)
- **factory** — новый экземпляр при каждом вызове (Template, Map, PageService)
- **auto-resolve** — автоматическое создание класса через рефлексию конструктора

```php
// Получение экземпляра из контейнера
$config = $di->get(Config::class);          // singleton
$template = $di->get(Template::class);      // factory
$db = $di->get(DataBase::class);            // singleton с авто-подстановкой зависимостей
```

Контейнер глобально доступен через `Container::getGlobal()` или `$GLOBALS['di']`.

### Конфигурация

Конфигурация хранится в `.env` файлах (не INI):

```env
# core/config.env
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=myapp
DB_LOGIN=user
DB_PASSWORD=secret
DEFAULT_TIMEZONE=Europe/Minsk
```

```php
$config = new Config();
$dbHost = $config->getEnv('DB_HOST');
```

### Логирование

Logger использует паттерн registry-singleton — один экземпляр на каждый файл лога:

```php
$logger = Logger::getInstance('app.log');
$logger->info('Приложение запущено');
$logger->error('Ошибка подключения к БД');
```

### Работа с БД

DbTable использует Map для передачи данных и защищает от SQL-инъекций:

```php
$dbTable = new DbTable();
$data = new Map(['name' => 'John', 'email' => 'john@example.com']);
$id = $dbTable->insertRow('users', $data);

$where = new Map(['id' => $id]);
$user = $dbTable->selectOne('users', $where);
```

---

## Быстрый старт

### Требования

- Windows + [OSPanel](https://ospanel.io/) (домен проекта уже описан в `.osp/project.ini`);
- PHP **8.0** или новее (идёт в комплекте с OSPanel);
- Git. Composer опционален — `vendor/` с готовой автозагрузкой уже есть в репозитории.

### Установка

```cmd
git clone https://github.com/Vampqwe/VampqweEngine.git
cd VampqweEngine
init.cmd
```

или воспользуйтесь служебным скриптом `gitClone.cmd`, который клонирует репозиторий за вас.

### Конфигурация

Создайте файл `.env` в директории `core/`:

```cmd
copy core\config.env.example core\config.env
```

Заполните переменные окружения:

```env
# База данных
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=myapp
DB_LOGIN=user
DB_PASSWORD=secret
DB_CHARSET=utf8mb4

# Дата и время
DEFAULT_TIMEZONE=Europe/Minsk
DATE_TPL=Y-m-d
TIME_TPL=H:i:s
```

### Запуск

```cmd
OSP.cmd
```

Скрипт поднимает проект под OSPanel, после чего сайт доступен по локальному домену из `.osp/project.ini`.

## Точка входа

Весь запрос проходит через [`index.php`](index.php): подключается автозагрузка, затем
`core/bootstrap.php` инициализирует DI-контейнер и регистрирует сервисы:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/core/bootstrap.php";

// Получение сервисов из контейнера
$config = $di->get(Config::class);
$dbHost = $config->getEnv('DB_HOST');

$logger = $di->get(Logger::class);
$logger->info('Приложение запущено');
```

## Структура проекта

```
VampqweEngine/
├── .osp/                       # настройки проекта для OSPanel
│   └── project.ini
├── core/                       # ядро движка
│   ├── bootstrap.php           # инициализация DI-контейнера и регистрация сервисов
│   ├── config.env              # рабочий конфиг (переменные окружения)
│   ├── config.env.example      # пример конфига
│   ├── test_config.ini         # конфиг для тестов (INI-формат)
│   └── classes/
│       ├── module/
│       │   ├── AccountManagementSystem/  # AMS: Account, User, Session (в разработке)
│       │   ├── PageController/           # PageController, PageService (в разработке)
│       │   └── Tamplate/                 # Template — шаблонизатор
│       └── system/
│           ├── Config/                   # Config, ConfigException (.env loader)
│           ├── Database/                 # DataBase (PDO wrapper), DbTable
│           ├── DI/                       # Container, ContainerException
│           ├── File/                     # File, FileException
│           ├── Helper/                   # Helper (UUID generator)
│           ├── Logger/                   # Logger (registry-singleton)
│           ├── Map/                      # Map (ArrayObject wrapper)
│           ├── Route/                    # Route (пути проекта)
│           └── TimeDate/                 # TimeDate
├── tests/
│   └── test_all.php            # все тесты (собственный раннер, не PHPUnit)
├── vendor/                     # Composer-автозагрузка (закоммичена)
├── index.php                   # входная точка
├── composer.json
├── init.cmd                    # первичная инициализация проекта
├── OSP.cmd                     # запуск проекта под OSPanel
├── gitClone.cmd                # клонирование репозитория
└── gitPush.cmd                 # коммит и отправка изменений
```

## Тесты

Тесты написаны на собственном лёгком раннере (без PHPUnit) и проверяют основные модули движка:

```cmd
php tests/test_all.php
```

### Покрытие тестов

- **File** — создание, запись, защита от path traversal, автозакрытие
- **Map** — put/get, удаление, проверка на существование ключа
- **Logger** — запись логов, санитизация переносов строк
- **TimeDate** — получение текущего времени, кэширование timezone, форматирование
- **Config** — загрузка .env, получение значений через `getEnv()`
- **Template** — замена переменных `{key}` и `{{key}}`, экранирование XSS
- **DbTable** — валидация имён таблиц, защита от SQL-инъекций
- **Route** — получение базового пути и путей core
- **DataBase** — singleton-подключение через DI

Для тестов используется отдельный конфиг `core/test_config.ini` (INI-формат).

## Служебные скрипты

| Скрипт | Что делает |
| --- | --- |
| `init.cmd` | Первичная инициализация проекта после клонирования |
| `OSP.cmd` | Запуск и управление проектом в OSPanel |
| `gitClone.cmd` | Клонирование репозитория в рабочую папку |
| `gitPush.cmd` | Коммит и отправка текущих изменений в GitHub |

## Планы

Модуль **AccountManagementSystem** (`core/classes/module/AccountManagementSystem/`) находится в разработке:

- `Account` — работа с данными аккаунта (email, пароль)
- `User` — создание и получение пользователей из БД
- `Session` — управление сессиями с кастомным обработчиком (Redis)
- `AccountManagementSystem` — фасад для регистрации, авторизации, аутентификации

Следующие шаги: завершение реализации AMS, покрытие тестами, документация API.

## Лицензия

Проект распространяется под лицензией **MIT** — подробности в [`composer.json`](composer.json).

---

Автор — [Vampqwe](https://github.com/Vampqwe)
