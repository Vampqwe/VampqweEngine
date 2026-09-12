<?php
declare(strict_types = 1);

/**
 * Класс для работы с таблицами базы данных
 * Использует подготовленные выражения для защиты от SQL-инъекций
 */
class DbTable extends DataBase {

    /**
     * Добавляет строку в таблицу.
     * $data — Map: ключ = колонка, значение = значение
     * @return string id вставленной строки
     * @throws InvalidArgumentException если имена таблиц/колонок некорректны
     */
    public function insertRow(string $table, Map $data): string {
        $columns = $data->getKeys();
        $this->checkIdentifier($table);
        array_map([$this, 'checkIdentifier'], $columns);

        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`)
                VALUES (:" . implode(', :', $columns) . ")";
        $prep = $this->prepare($sql);
        foreach ($data->getArrayObject() as $column => $value) {
            $prep->bindValue(':' . $column, $value, $this->getParamType($value));
        }
        $prep->execute();
        return (string)$this->lastInsertId();
    }

    /**
     * Обновляет строки таблицы.
     * $data — Map с новыми значениями, $where — Map с условиями (через AND)
     * @return int количество изменённых строк
     * @throws InvalidArgumentException если имена таблиц/колонок некорректны
     */
    public function updateRows(string $table, Map $data, Map $where): int {
        $this->checkIdentifier($table);
        array_map([$this, 'checkIdentifier'], $data->getKeys());
        array_map([$this, 'checkIdentifier'], $where->getKeys());

        $set = [];
        foreach ($data->getKeys() as $column) {
            $set[] = "`$column` = :set_$column";
        }
        $sql = "UPDATE `$table` SET " . implode(', ', $set) . $this->buildWhere($where);
        $prep = $this->prepare($sql);
        foreach ($data->getArrayObject() as $column => $value) {
            $prep->bindValue(':set_' . $column, $value, $this->getParamType($value));
        }
        $this->bindWhere($prep, $where);
        $prep->execute();
        return $prep->rowCount();
    }

    /**
     * Удаляет строки таблицы по условию $where — Map (через AND).
     * @return int количество удалённых строк
     * @throws InvalidArgumentException если имена таблиц/колонок некорректны
     */
    public function deleteRows(string $table, Map $where): int {
        $this->checkIdentifier($table);
        array_map([$this, 'checkIdentifier'], $where->getKeys());

        $sql = "DELETE FROM `$table`" . $this->buildWhere($where);
        $prep = $this->prepare($sql);
        $this->bindWhere($prep, $where);
        $prep->execute();
        return $prep->rowCount();
    }

    /**
     * Выбирает строки таблицы. $where не задан или пустой — вся таблица.
     * @return Map строки: ключ = номер строки, значение = Map колонок
     * @throws InvalidArgumentException если имена таблиц/колонок некорректны
     */
    public function selectRows(string $table, ?Map $where = null): Map {
        $where ??= new Map();
        $this->checkIdentifier($table);
        array_map([$this, 'checkIdentifier'], $where->getKeys());

        $sql = "SELECT * FROM `$table`" . $this->buildWhere($where);
        $prep = $this->prepare($sql);
        $this->bindWhere($prep, $where);
        $prep->execute();

        $rows = new Map();
        foreach ($prep->fetchAll() as $index => $row) {
            $rowMap = new Map();
            $rowMap->addNewArr($row);
            $rows->put($index, $rowMap);
        }
        return $rows;
    }

    /**
     * Выбирает одну строку из таблицы по условию.
     * @return Map|null строка или null если ничего не найдено
     */
    public function selectOne(string $table, Map $where): ?Map {
        $result = $this->selectRows($table, $where);
        if ($result->isEmpty()) {
            return null;
        }
        return $result->getValueByKey(0);
    }

    /**
     * Считает количество строк, удовлетворяющих условию.
     * @return int количество строк
     */
    public function countRows(string $table, ?Map $where = null): int {
        $where ??= new Map();
        $this->checkIdentifier($table);
        array_map([$this, 'checkIdentifier'], $where->getKeys());

        $sql = "SELECT COUNT(*) as cnt FROM `$table`" . $this->buildWhere($where);
        $prep = $this->prepare($sql);
        $this->bindWhere($prep, $where);
        $prep->execute();
        $result = $prep->fetch();
        return (int)($result['cnt'] ?? 0);
    }

    private function buildWhere(Map $where): string {
        if ($where->isEmpty()) {
            return '';
        }
        $conditions = [];
        foreach ($where->getKeys() as $column) {
            $conditions[] = "`$column` = :w_$column";
        }
        return ' WHERE ' . implode(' AND ', $conditions);
    }

    private function bindWhere(PDOStatement $prep, Map $where): void {
        foreach ($where->getArrayObject() as $column => $value) {
            $prep->bindValue(':w_' . $column, $value, $this->getParamType($value));
        }
    }

    /**
     * Определяет тип параметра для PDO
     */
    private function getParamType(mixed $value): int {
        return match (gettype($value)) {
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'NULL' => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Разрешает в именах таблиц/колонок только буквы, цифры и _ (защита от инъекций).
     * Усиленная валидация: имя должно начинаться с буквы.
     * @throws InvalidArgumentException если имя некорректно
     */
    private function checkIdentifier(string $name): void {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Недопустимое имя таблицы или колонки: $name");
        }
    }
}
