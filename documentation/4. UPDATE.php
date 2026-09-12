// Обновление по условиям
$affected = $query->update('users', [
    'status'     => 'inactive',
    'updated_at' => date('Y-m-d H:i:s'),
], ['id' => 5]);

// Обновление по ID
$query->updateById('users', 5, [
    'name' => 'Новое имя',
]);

// Increment / Decrement
$query->increment('users', 'login_count', 1, ['id' => 5]);
$query->decrement('products', 'stock', 1, ['id' => 10]);