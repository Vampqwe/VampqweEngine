// Удаление по условиям
$deleted = $query->delete('users', ['status' => 'deleted']);

// Удаление по ID
$query->deleteById('users', 5);