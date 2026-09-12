// Операторы сравнения
$users = $query->select('users', [
    'age' => ['>' => 18],
]);

// IN
$users = $query->select('users', [
    'id' => ['in' => [1, 2, 3, 5]],
]);

// BETWEEN
$orders = $query->select('orders', [
    'created_at' => ['between' => ['2024-01-01', '2024-12-31']],
]);

// LIKE
$users = $query->select('users', [
    'name' => ['like' => '%Vampqwe%'],
]);

// NULL
$users = $query->select('users', [
    'deleted_at' => null,
]);

// Комбинированные условия
$users = $query->select('users', [
    'status' => 'active',
    'age'    => ['>=' => 18],
    'role'   => ['in' => ['admin', 'moderator']],
]);