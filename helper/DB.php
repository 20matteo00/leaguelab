<?php

class DB
{
    private static ?PDO $pdo = null;

    private static array $config = [
        'host' => '127.0.0.1',
        'dbname' => 'leaguelab',
        'user' => 'root',
        'pass' => 'Matteo00',
        'charset' => 'utf8mb4',
    ];

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dsn = "mysql:host=" . self::$config['host'] .
                ";dbname=" . self::$config['dbname'] .
                ";charset=" . self::$config['charset'];

            self::$pdo = new PDO($dsn, self::$config['user'], self::$config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$pdo;
    }

    // 👉 Entry point
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(self::connect(), $table);
    }
}


class QueryBuilder
{
    private PDO $pdo;
    private string $table;

    private string $select = '*';
    private array $where = [];
    private array $params = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    // 🔍 SELECT
    public function select(string $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    // 📌 WHERE base
    public function where(string $column, string $operator, $value): self
    {
        $param = "w_" . count($this->params);

        $this->where[] = "`$column` $operator :$param";
        $this->params[$param] = $value;

        return $this;
    }

    // 🔗 WHERE IN
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];

        foreach ($values as $i => $value) {
            $param = "in_" . count($this->params);
            $placeholders[] = ":$param";
            $this->params[$param] = $value;
        }

        $this->where[] = "`$column` IN (" . implode(',', $placeholders) . ")";
        return $this;
    }

    // WHERE NULL
    public function whereNull(string $column): self
    {
        $this->where[] = "`$column` IS NULL";
        return $this;
    }

    // WHERE RAW
    public function whereRaw(string $sql, array $params = []): self
    {
        $this->where[] = $sql;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    // 📊 ORDER BY
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "`$column` $direction";
        return $this;
    }

    // 🔢 LIMIT
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    // 📄 OFFSET
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    // 🚀 BUILD QUERY
    private function buildSelect(): array
    {
        $sql = "SELECT {$this->select} FROM `{$this->table}`";

        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return [$sql, $this->params];
    }

    // 📥 GET
    public function get(): array
    {
        [$sql, $params] = $this->buildSelect();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // 📌 FIRST
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    // 🔢 COUNT
    public function count(): int
    {
        $this->select("COUNT(*) as count");
        $result = $this->first();
        return (int) ($result['count'] ?? 0);
    }

    // ➕ INSERT
    public function insert(array $data): int
    {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($data)));

        $sql = "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    // ✏️ UPDATE
    public function update(array $data): int
    {
        if (empty($this->where)) {
            throw new Exception("UPDATE senza WHERE non consentito");
        }

        $set = [];
        $params = $this->params;

        foreach ($data as $key => $value) {
            $param = "set_$key";
            $set[] = "`$key` = :$param";
            $params[$param] = $value;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $set)
            . " WHERE " . implode(' AND ', $this->where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    // ❌ DELETE
    public function delete(): int
    {
        if (empty($this->where)) {
            throw new Exception("DELETE senza WHERE non consentito");
        }

        $sql = "DELETE FROM `{$this->table}` WHERE " . implode(' AND ', $this->where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);

        return $stmt->rowCount();
    }
}
