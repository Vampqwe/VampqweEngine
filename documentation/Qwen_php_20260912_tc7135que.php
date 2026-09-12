// Одна запись
$userId = $query->insert('users', [
    'name'       => 'Vampqwe',
    'email'      => 'vampqwe@example.com',
    'status'     => 'active',
    'created_at' => date('Y-m-d H:i:s'),
]);
echo "Создан пользователь с ID: $userId";

// Массовая вставка
$rows = [
    ['name' => 'User1', 'email' => 'u1@example.com'],
    ['name' => 'User2', 'email' => 'u2@example.com'],
    ['name' => 'User3', 'email' => 'u3@example.com'],
];
$inserted = $query->insertMany('users', $rows);
echo "Вставлено записей: $inserted";