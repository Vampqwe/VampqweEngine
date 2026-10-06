# Интеграционный план для модуля аккаунтов

## 1. Где внедрять модуль

Сейчас в проекте уже есть базовые заготовки:
- [core/classes/module/AccountManagementSystem/AccountManagementSystem.php](../../../../core/classes/module/AccountManagementSystem/AccountManagementSystem.php)
- [core/classes/module/AccountManagementSystem/User.php](../../../../core/classes/module/AccountManagementSystem/User.php)
- [core/classes/module/AccountManagementSystem/Account.php](../../../../core/classes/module/AccountManagementSystem/Account.php)
- [core/classes/module/AccountManagementSystem/Session.php](../../../../core/classes/module/AccountManagementSystem/Session.php)

Нужно развивать именно эти классы, а не создавать отдельный независимый auth-блок в корне проекта.

## 2. Как подключить в bootstrap

В [core/bootstrap.php](../../../../core/bootstrap.php) можно добавить регистрацию сервисов:

```php
$di->factory(SessionService::class, fn(Container $c) => new SessionService($c->get(Config::class)));
$di->factory(AuthService::class, fn(Container $c) => new AuthService(
    $c->get(Config::class),
    $c->get(DbQuery::class),
    $c->get(Logger::class),
    $c->get(SessionService::class)
));
$di->factory(FileService::class, fn(Container $c) => new FileService(
    $c->get(DbQuery::class),
    $c->get(Config::class),
    $c->get(Logger::class)
));
```

## 3. Как использовать в контроллерах

- AuthController
  - `register()`
  - `login()`
  - `logout()`
  - `profile()`

- UserProfileController
  - `getProfile()`
  - `updateProfile()`
  - `uploadAvatar()`

- FileController
  - `upload()`
  - `delete()`
  - `download()`

## 4. Схема перехода

1. Создать таблицы из [core/classes/module/AccountManagementSystem/account_system_schema.sql](account_system_schema.sql)
2. Подключить Redis сессии через SessionService
3. Добавить регистрацию / вход / logout
4. Добавить профиль пользователя и файл-метаданные
5. Добавить ограничения доступа по role / status
6. Привязать cookie- и session-политику к config.env

## 5. Ключевые настройки конфигурации

В [core/config.env.example](../../../../core/config.env.example) добавить:

```ini
SESSION.COOKIE_LIFETIME=3600
SESSION.COOKIE_HTTPONLY=1
SESSION.COOKIE_SECURE=1
SESSION.COOKIE_SAMESITE=strict
SAVE_SESSION_HANDLER=redis
SAVE_SESSION_PATH_HOST=127.0.0.1
SAVE_SESSION_PATH_PORT=6379
FILE_STORAGE_PATH="/var/www/project/storage/uploads"
```

## 6. Что важно

- хранить в Redis только сессию и meta, не пароли
- хранить файлы в storage, а метаданные в MySQL
- хранить профили отдельным слоем от login/account
- не смешивать auth, session и user profile в одном объекте

## 7. Рекомендуемое следующее улучшение

После базовой реализации добавить:
- email verification
- password reset flow
- role-based access control
- CSRF protection
- rate limiting для login endpoint
