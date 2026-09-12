$maxPrice = $query->max('products', 'price', ['category' => 'electronics']);
$minPrice = $query->min('products', 'price');
$totalSum = $query->sum('orders', 'total', ['status' => 'paid']);