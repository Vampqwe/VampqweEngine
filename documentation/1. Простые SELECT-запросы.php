$container = $GLOBALS['container'];
$query = $container->get(DbQuery::class);

// Все пользователи
$users = $query->findAll('users');

// Активные пользователи, отсортированные по дате
$users = $query->select('users', ['status' => 'active'], [
    'orderBy' => 'created_at DESC',
    'limit'   => 10,
]);

// Один пользователь по ID
$user = $query->find('users', 5);

// Пользователь по email
$user = $query->selectOne('users', ['email' => 'test@example.com']);

// Подсчёт активных
$count = $query->count('users', ['status' => 'active']);

// Проверка существования
if ($query->exists('users', ['email' => 'test@example.com'])) {
    echo "Пользователь существует";
}