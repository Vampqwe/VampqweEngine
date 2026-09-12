// Вариант 1: ручное управление
$query->beginTransaction();
try {
    $query->update('accounts', ['balance' => 900], ['id' => 1]);
    $query->update('accounts', ['balance' => 1100], ['id' => 2]);
    $query->commit();
} catch (Throwable $e) {
    $query->rollback();
    throw $e;
}

// Вариант 2: через callback (рекомендуется)
$query->transaction(function(DbQuery $q) {
    $q->update('accounts', ['balance' => 900], ['id' => 1]);
    $q->update('accounts', ['balance' => 1100], ['id' => 2]);
    
    if ($q->count('accounts', ['balance' => ['<' => 0]]) > 0) {
        throw new RuntimeException("Отрицательный баланс!");
    }
});
// При исключении — автоматический rollback