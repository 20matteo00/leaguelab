<?php

class DB
{
    private static ?PDO $pdo = null;

    private static array $config = [
        'host'    => '127.0.0.1',
        'dbname'  => 'leaguelab',
        'user'    => 'root',
        'pass'    => 'Matteo00',
        'charset' => 'utf8mb4',
    ];

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dsn = "mysql:host=" . self::$config['host']
                . ";dbname=" . self::$config['dbname']
                . ";charset=" . self::$config['charset'];

            self::$pdo = new PDO($dsn, self::$config['user'], self::$config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$pdo;
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(self::connect(), $table);
    }
}


class QueryBuilder
{
    private PDO    $pdo;
    private string $table;

    private string  $select    = '*';
    private array   $where     = [];   // ogni elemento: ['AND'|'OR', 'sql condition']
    private array   $params    = [];
    private array   $orderBy   = [];
    private ?int    $limit     = null;
    private ?int    $offset    = null;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo   = $pdo;
        $this->table = $table;
    }

    // -------------------------------------------------------------------------
    // SELECT
    // -------------------------------------------------------------------------

    /**
     * Specifica le colonne da selezionare.
     * Esempio: ->select('id, name, email')
     */
    public function select(string $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    /**
     * SELECT grezzo, utile per aggregati o espressioni.
     * Esempio: ->selectRaw('SUM(price) as total, COUNT(*) as qty')
     */
    public function selectRaw(string $sql): self
    {
        $this->select = $sql;
        return $this;
    }

    // -------------------------------------------------------------------------
    // WHERE
    // -------------------------------------------------------------------------

    /**
     * Aggiunge una condizione WHERE con AND (default).
     * Esempio: ->where('age', '>=', 18)
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $param          = 'w_' . count($this->params);
        $this->where[]  = ['AND', "`$column` $operator :$param"];
        $this->params[$param] = $value;
        return $this;
    }

    /**
     * Aggiunge una condizione WHERE con OR.
     * Esempio: ->where('role','=','admin')->orWhere('role','=','moderator')
     */
    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $param          = 'w_' . count($this->params);
        $this->where[]  = ['OR', "`$column` $operator :$param"];
        $this->params[$param] = $value;
        return $this;
    }

    /**
     * WHERE colonna IN (lista valori).
     * Esempio: ->whereIn('status', ['active', 'pending'])
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];
        foreach ($values as $value) {
            $param = 'in_' . count($this->params);
            $placeholders[]       = ":$param";
            $this->params[$param] = $value;
        }
        $this->where[] = ['AND', "`$column` IN (" . implode(',', $placeholders) . ")"];
        return $this;
    }

    /**
     * WHERE colonna NOT IN (lista valori).
     * Esempio: ->whereNotIn('id', [3, 7, 12])
     */
    public function whereNotIn(string $column, array $values): self
    {
        $placeholders = [];
        foreach ($values as $value) {
            $param = 'nin_' . count($this->params);
            $placeholders[]       = ":$param";
            $this->params[$param] = $value;
        }
        $this->where[] = ['AND', "`$column` NOT IN (" . implode(',', $placeholders) . ")"];
        return $this;
    }

    /**
     * WHERE colonna IS NULL.
     * Esempio: ->whereNull('deleted_at')
     */
    public function whereNull(string $column): self
    {
        $this->where[] = ['AND', "`$column` IS NULL"];
        return $this;
    }

    /**
     * WHERE colonna IS NOT NULL.
     * Esempio: ->whereNotNull('email')
     */
    public function whereNotNull(string $column): self
    {
        $this->where[] = ['AND', "`$column` IS NOT NULL"];
        return $this;
    }

    /**
     * WHERE colonna BETWEEN valore1 AND valore2.
     * Esempio: ->whereBetween('age', 18, 65)
     */
    public function whereBetween(string $column, mixed $from, mixed $to): self
    {
        $p1 = 'bt1_' . count($this->params);
        $this->params[$p1] = $from;
        $p2 = 'bt2_' . count($this->params);
        $this->params[$p2] = $to;
        $this->where[] = ['AND', "`$column` BETWEEN :$p1 AND :$p2"];
        return $this;
    }

    /**
     * WHERE grezzo, per condizioni complesse.
     * Esempio: ->whereRaw('YEAR(created_at) = :y', ['y' => 2024])
     */
    public function whereRaw(string $sql, array $params = []): self
    {
        $this->where[]  = ['AND', $sql];
        $this->params   = array_merge($this->params, $params);
        return $this;
    }

    // -------------------------------------------------------------------------
    // ORDER / LIMIT / OFFSET
    // -------------------------------------------------------------------------

    /**
     * Ordina i risultati.
     * Esempio: ->orderBy('created_at', 'DESC')
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "`$column` $direction";
        return $this;
    }

    /**
     * ORDER BY grezzo.
     * Esempio: ->orderByRaw('RAND()')   ->orderByRaw('FIELD(status,"active","pending")')
     */
    public function orderByRaw(string $sql): self
    {
        $this->orderBy[] = $sql;
        return $this;
    }

    /**
     * Limita il numero di righe restituite.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Salta le prime N righe (usato per la paginazione).
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    // -------------------------------------------------------------------------
    // BUILD INTERNO
    // -------------------------------------------------------------------------

    private function buildWhereClause(): string
    {
        if (empty($this->where)) return '';

        $parts = '';
        foreach ($this->where as $i => [$type, $condition]) {
            $parts .= ($i === 0 ? '' : " $type ") . $condition;
        }
        return " WHERE $parts";
    }

    private function buildSelect(): array
    {
        $sql  = "SELECT {$this->select} FROM `{$this->table}`";
        $sql .= $this->buildWhereClause();

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

    // -------------------------------------------------------------------------
    // READ — recupero dati
    // -------------------------------------------------------------------------

    /**
     * Restituisce tutte le righe come array di array associativi.
     */
    public function get(): array
    {
        [$sql, $params] = $this->buildSelect();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Restituisce solo la prima riga, o null se non esiste.
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Restituisce il valore di una singola colonna dalla prima riga.
     * Esempio: ->where('id','=',5)->value('email')  →  'mario@example.com'
     */
    public function value(string $column): mixed
    {
        $this->select($column);
        $result = $this->first();
        return $result[$column] ?? null;
    }

    /**
     * Restituisce un array piatto con i valori di una sola colonna.
     * Esempio: ->pluck('email')  →  ['a@a.com', 'b@b.com', ...]
     */
    public function pluck(string $column): array
    {
        $this->select($column);
        return array_column($this->get(), $column);
    }

    /**
     * Restituisce i risultati come array indicizzato su una colonna chiave.
     * Esempio: ->keyBy('id')  →  [3 => ['id'=>3,'name'=>'...'], ...]
     */
    public function keyBy(string $keyColumn): array
    {
        $results = $this->get();
        $out     = [];
        foreach ($results as $row) {
            $out[$row[$keyColumn]] = $row;
        }
        return $out;
    }

    /**
     * Conta le righe che soddisfano i WHERE.
     */
    public function count(): int
    {
        $this->selectRaw('COUNT(*) as _count');
        $result = $this->first();
        return (int) ($result['_count'] ?? 0);
    }

    /**
     * Restituisce true se esiste almeno una riga con i WHERE impostati.
     */
    public function exists(): bool
    {
        $sql  = "SELECT 1 FROM `{$this->table}`";
        $sql .= $this->buildWhereClause();
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Processa grandi quantità di righe a blocchi senza caricarle tutte in memoria.
     * Esempio:
     *   DB::table('orders')->chunk(200, function($rows) {
     *       foreach ($rows as $row) { ... }
     *   });
     */
    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        do {
            $results = (clone $this)->limit($size)->offset($offset)->get();
            if (empty($results)) break;

            $callback($results);
            $offset += $size;
        } while (count($results) === $size);
    }

    /**
     * Restituisce la query SQL come stringa (utile per debug).
     * Esempio: echo DB::table('users')->where('id','=',1)->toSql();
     */
    public function toSql(): string
    {
        [$sql, $params] = $this->buildSelect();
        foreach ($params as $key => $val) {
            $sql = str_replace(":$key", is_numeric($val) ? $val : "'$val'", $sql);
        }
        return $sql;
    }

    // -------------------------------------------------------------------------
    // WRITE — scrittura dati
    // -------------------------------------------------------------------------

    /**
     * Inserisce una riga e restituisce l'ID generato.
     * Esempio: DB::table('users')->insert(['name'=>'Mario','email'=>'...'])
     */
    public function insert(array $data): int
    {
        $columns      = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($data)));

        $sql  = "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Inserisce più righe in un'unica query (molto più veloce di N insert singoli).
     * Esempio: DB::table('logs')->insertMany([['msg'=>'a'],['msg'=>'b']])
     */
    public function insertMany(array $rows): int
    {
        if (empty($rows)) return 0;

        $columns      = implode(',', array_keys($rows[0]));
        $params       = [];
        $placeholders = [];

        foreach ($rows as $i => $row) {
            $rowPlaceholders = [];
            foreach ($row as $key => $value) {
                $param = "{$key}_{$i}";
                $rowPlaceholders[]  = ":$param";
                $params[$param]     = $value;
            }
            $placeholders[] = '(' . implode(',', $rowPlaceholders) . ')';
        }

        $sql  = "INSERT INTO `{$this->table}` ($columns) VALUES " . implode(',', $placeholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Aggiorna le righe che soddisfano i WHERE. Richiede almeno un WHERE.
     * Esempio: DB::table('users')->where('id','=',5)->update(['name'=>'Luca'])
     */
    public function update(array $data): int
    {
        if (empty($this->where)) {
            throw new Exception("UPDATE senza WHERE non consentito");
        }

        $set    = [];
        $params = $this->params;

        foreach ($data as $key => $value) {
            $param = "set_$key";
            $set[]        = "`$key` = :$param";
            $params[$param] = $value;
        }

        $sql  = "UPDATE `{$this->table}` SET " . implode(', ', $set);
        $sql .= $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Cancella le righe che soddisfano i WHERE. Richiede almeno un WHERE.
     * Esempio: DB::table('users')->where('id','=',5)->delete()
     */
    public function delete(): int
    {
        if (empty($this->where)) {
            throw new Exception("DELETE senza WHERE non consentito");
        }

        $sql  = "DELETE FROM `{$this->table}`";
        $sql .= $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);

        return $stmt->rowCount();
    }

    /**
     * INSERT o UPDATE se la chiave duplicata esiste (ON DUPLICATE KEY UPDATE).
     * Esempio:
     *   DB::table('settings')->insertOrUpdate(
     *       ['key'=>'theme','value'=>'dark'],
     *       ['value']   // colonne da aggiornare in caso di duplicato
     *   );
     */
    public function insertOrUpdate(array $data, array $updateColumns): int
    {
        $columns      = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($data)));
        $update       = implode(',', array_map(fn($k) => "`$k`=VALUES(`$k`)", $updateColumns));

        $sql  = "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders) ";
        $sql .= "ON DUPLICATE KEY UPDATE $update";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->rowCount();
    }
}