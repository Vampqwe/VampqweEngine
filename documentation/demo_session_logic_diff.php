--- demo_session_logic.php (原始)


+++ demo_session_logic.php (修改后)
<?php
/**
 * Демонстрация логики: UUID + Redis + Сессии
 *
 * Требования:
 * 1. Установленный Redis сервер (localhost:6379)
 * 2. Расширение PHP: php-redis (pecl install redis)
 *
 * Логика работы скрипта:
 * 1. Проверяем наличие Cookie с UUID.
 * 2. Если нет -> Генерируем новый UUID v4.
 * 3. Подключаемся к Redis.
 * 4. Читаем/Пишем данные сессии по ключу "session:{uuid}".
 * 5. Обновляем TTL (время жизни) сессии.
 */

// --- КОНФИГУРАЦИЯ ---
$redisHost = '127.0.0.1';
$redisPort = 6379;
$sessionTtl = 3600; // 1 час
$cookieName = 'vampqwe_session_uuid';

// --- 1. ГЕНЕРАЦИЯ UUID (Если нет сессии) ---
function generateUuidV4(): string {
    $data = random_bytes(16);
    assert(strlen($data) === 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Эмуляция входящих Cookie (в реальном проекте это $_COOKIE)
// Для теста можно закомментировать эту строку, чтобы скрипт создал новую сессию
// $_COOKIE[$cookieName] = 'ваш-существующий-uuid';

$sessionId = $_COOKIE[$cookieName] ?? null;
$isNewSession = false;

if (!$sessionId || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sessionId)) {
    $sessionId = generateUuidV4();
    $isNewSession = true;

    // В реальном проекте: setcookie($cookieName, $sessionId, time() + $sessionTtl, '/', '', false, true);
    echo "🆕 [СИСТЕМА] Сгенерирован новый UUID: $sessionId\n";
    echo "   (В браузере здесь установилась бы Cookie)\n";
} else {
    echo "🔄 [СИСТЕМА] Обнаружен существующий UUID: $sessionId\n";
}

// --- 2. ПОДКЛЮЧЕНИЕ К REDIS ---
try {
    $redis = new Redis();
    // Если Redis не запущен, скрипт упадет здесь.
    // Для локального теста без Redis можно закомментировать блок try-catch и использовать массив вместо Redis.
    $redis->connect($redisHost, $redisPort);

    echo "✅ [REDIS] Успешное подключение к {$redisHost}:{$redisPort}\n";

    $redisKey = "session:" . $sessionId;

    // --- 3. РАБОТА С ДАННЫМИ СЕССИИ ---

    if ($isNewSession) {
        // Инициализация новой сессии
        $sessionData = [
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => null,
            'cart' => [],
            'role' => 'guest'
        ];
        $redis->setEx($redisKey, $sessionTtl, serialize($sessionData));
        echo "💾 [REDIS] Создана новая запись с TTL {$sessionTtl} сек.\n";
    } else {
        // Чтение существующей сессии
        $rawData = $redis->get($redisKey);

        if ($rawData) {
            $sessionData = unserialize($rawData);
            echo "📖 [REDIS] Данные сессии загружены:\n";
            echo "   - Пользователь: " . ($sessionData['user_id'] ?? 'Гость') . "\n";
            echo "   - Создана: " . ($sessionData['created_at'] ?? 'Неизвестно') . "\n";

            // Продление жизни сессии (Slide Expiration)
            $redis->expire($redisKey, $sessionTtl);
            echo "⏳ [REDIS] Время жизни сессии обновлено.\n";

        } else {
            // Сессия в Redis истекла, хотя Cookie есть (очистка мусора)
            echo "⚠️ [REDIS] Сессия в хранилище не найдена (истекла). Пересоздаем...\n";
            $sessionData = ['created_at' => date('Y-m-d H:i:s'), 'user_id' => null, 'cart' => []];
            $redis->setEx($redisKey, $sessionTtl, serialize($sessionData));
        }
    }

    // --- 4. СИМУЛЯЦИЯ ДЕЙСТВИЙ ПОЛЬЗОВАТЕЛЯ ---
    echo "\n🛒 [ЛОГИКА] Добавляем товар в корзину...\n";
    $sessionData['cart'][] = 'Товар #' . rand(100, 999);
    $sessionData['last_action'] = date('Y-m-d H:i:s');

    // Сохранение изменений
    $redis->setEx($redisKey, $sessionTtl, serialize($sessionData));
    echo "✅ [ЛОГИКА] Корзина обновлена. Товаров: " . count($sessionData['cart']) . "\n";

    // Пример авторизации (симуляция)
    if (empty($sessionData['user_id'])) {
        echo "\n🔐 [ЛОГИКА] Симуляция входа пользователя...\n";
        $sessionData['user_id'] = 42;
        $sessionData['role'] = 'admin';
        $redis->setEx($redisKey, $sessionTtl, serialize($sessionData));
        echo "✅ [ЛОГИКА] Пользователь ID 42 авторизован.\n";
    }

    echo "\n📊 [ИТОГ] Текущее состояние сессии в Redis:\n";
    echo json_encode($sessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (Exception $e) {
    echo "❌ [ОШИБКА] Не удалось подключиться к Redis: " . $e->getMessage() . "\n";
    echo "💡 Совет: Убедитесь, что Redis запущен (command: redis-server) и порт 6379 открыт.\n";
    exit(1);
}

echo "\n🏁 Скрипт завершен. Проверьте Redis CLI: GET session:$sessionId\n";
