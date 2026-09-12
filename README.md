# VampqweEngine

**Легковесный и безопасный PHP-движок** — компактное ядро для веб-проектов без тяжёлых фреймворков:
конфигурация, маршрутизация, база данных, шаблонизатор, логирование и утилиты из коробки.
Запускается локально под [OSPanel](https://ospanel.io/) (Windows), пишется на чистом PHP 8+.

> ⚠️ Движок находится в активной разработке: API может меняться без предупреждения.

[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.0-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-35c28d.svg)](LICENSE)
[![Composer](https://img.shields.io/badge/Composer-vampqwe%2Fvampqwe--engine-885630?logo=composer&logoColor=white)](composer.json)
[![Server](https://img.shields.io/badge/Server-OSPanel-222?logo=windows&logoColor=white)](.osp/project.ini)

---

## Что внутри

Ядро (`core/`) собирается из небольших самостоятельных модулей:

| Модуль | Путь | Назначение |
| --- | --- | --- |
| **Config** | `core/classes/system/Config/` | Загрузка INI-конфигов и доступ к значениям по ключу вида `секция.параметр`; выбрасывает `ConfigException` |
| **DataBase / DbTable** | `core/classes/system/Database/` | Подключение к базе данных и работа с таблицами |
| **Route** | `core/classes/system/Route/` | Разбор запроса и маршрутизация |
| **Template** | `core/classes/module/Tamplate/` | Шаблонизатор для рендеринга страниц |
| **File** | `core/classes/system/File/` | Файловые операции с собственным `FileException` |
| **Logger** | `core/classes/system/Logger/` | Логирование событий движка |
| **Map** | `core/classes/system/Map/` | Структура данных «ключ — значение» с удобным доступом |
| **TimeDate** | `core/classes/system/TimeDate/` | Хелпер для работы с датами и временем |

Все классы подключаются через Composer-автозагрузку (classmap по `core/`) — вручную `require` делать не нужно.

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

Скопируйте пример конфига и заполните свои значения:

```cmd
copy core\config.example.ini core\config.ini
```

Основные параметры — в секции `dataBase` (логин, пароль, имя базы и т. д.).
Полный список ключей смотрите в [`core/config.example.ini`](core/config.example.ini).

### Запуск

```cmd
OSP.cmd
```

Скрипт поднимает проект под OSPanel, после чего сайт доступен по локальному домену из `.osp/project.ini`.

## Точка входа

Весь запрос проходит через [`index.php`](index.php): подключается автозагрузка, затем
`core/bootstrap.php` поднимает движок, и становятся доступны его классы:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/core/bootstrap.php";

$config = new Config();

// Доступ к значению конфига по ключу «секция.параметр»
var_dump($config->getConfig('dataBase.db_login'));
```

## Структура проекта

```
VampqweEngine/
├── .osp/                       # настройки проекта для OSPanel
│   └── project.ini
├── core/                       # ядро движка
│   ├── bootstrap.php           # инициализация движка
│   ├── config.ini              # рабочий конфиг (создаётся из примера)
│   ├── config.example.ini      # пример конфига
│   ├── test_config.ini         # конфиг для тестов
│   └── classes/
│       ├── module/
│       │   └── Tamplate/       # Template — шаблонизатор
│       └── system/
│           ├── Config/         # Config, ConfigException
│           ├── Database/       # DataBase, DbTable
│           ├── File/           # File, FileException
│           ├── Logger/         # Logger
│           ├── Map/            # Map
│           ├── Route/          # Route
│           └── TimeDate/       # TimeDate
├── tests/
│   └── test_all.php            # все тесты (собственный раннер, не PHPUnit)
├── todo/                       # заготовки будущих модулей
│   └── AccountManagementSystem/
├── vendor/                     # Composer-автозагрузка (закоммичена)
├── index.php                   # входная точка
├── composer.json
├── init.cmd                    # первичная инициализация проекта
├── OSP.cmd                     # запуск проекта под OSPanel
├── gitClone.cmd                # клонирование репозитория
└── gitPush.cmd                 # коммит и отправка изменений
```

## Тесты

Тесты написаны на собственном лёгком раннере (без PHPUnit) и используют отдельный
конфиг `core/test_config.ini`:

```cmd
php tests/test_all.php
```

## Служебные скрипты

| Скрипт | Что делает |
| --- | --- |
| `init.cmd` | Первичная инициализация проекта после клонирования |
| `OSP.cmd` | Запуск и управление проектом в OSPanel |
| `gitClone.cmd` | Клонирование репозитория в рабочую папку |
| `gitPush.cmd` | Коммит и отправка текущих изменений в GitHub |

## Планы

В каталоге [`todo/`](todo/) уже лежат заготовки модуля **AccountManagementSystem** —
системы управления аккаунтами: `Account`, `Session`, `User`. Это следующий крупный шаг
в развитии движка.

## Лицензия

Проект распространяется под лицензией **MIT** — подробности в [`composer.json`](composer.json).

---

Автор — [Vampqwe](https://github.com/Vampqwe)
