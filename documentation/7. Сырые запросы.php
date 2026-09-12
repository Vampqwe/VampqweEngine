// Сложный запрос с JOIN
$orders = $query->queryAll(
    "SELECT o.*, u.name AS user_name 
     FROM orders o 
     JOIN users u ON u.id = o.user_id 
     WHERE o.status = ? AND o.total > ?
     ORDER BY o.created_at DESC",
    ['paid', 1000]
);

// Одна строка
$user = $query->queryOne(
    "SELECT * FROM users WHERE email = ?",
    ['test@example.com']
);