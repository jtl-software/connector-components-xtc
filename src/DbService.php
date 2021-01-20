<?php


namespace Jtl\Connector\XtcComponents;

class DbService
{
    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * DbService constructor.
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return \PDO
     */
    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    /**
     * @param string $table
     * @param array $row
     * @return int
     */
    public function insert(string $table, array $row): int
    {
        $columns = '`' . implode('`,', array_keys($row)) . '`';
        $values = implode('\',', implode(array_fill(0, count($row), '?')));
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $columns, $values);

        $statement = $this->pdo->prepare($sql);

        $this->prepareDatabaseValues($statement, $row);
    }

    protected function prepareDatabaseValues(\PDOStatement $statement, array $row): array
    {
        $prepared = [];
        foreach (array_values($row) as $index => $value) {
            $paramType = \PDO::PARAM_STR;
            switch (gettype($value)) {
                case 'boolean':
                    $paramType = \PDO::PARAM_BOOL;
                    break;
                case 'integer':
                    $paramType = \PDO::PARAM_INT;
                    break;
                case 'NULL':
                    $paramType = \PDO::PARAM_NULL;
                    break;
                case 'object':
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format(\DateTimeInterface::ATOM);
                    }
                    break;
            }

            $statement->bindParam($index, $value, $paramType);
        }
    }

    /**
     * @param string $table
     * @param array $identifiers
     * @param array $row
     * @return int
     *
     */
    public function update(string $table, array $identifiers, array $row): int
    {
    }

    /**
     * @param string $table
     * @param array $identifiers
     * @param array $row
     */
    public function upsert(string $table, array $identifiers, array $row): void
    {
        try {
            $this->insert($table, $row);
        } catch (\PDOException $ex) {
            $this->update($table, $identifiers, $row);
        }
    }

    /**
     * @param string $table
     * @param array $identifiers
     */
    public function delete(string $table, array $identifiers)
    {
    }

    /**
     * @param string $table
     * @param array $identifiers
     * @param array $row
     */
    public function deleteInsert(string $table, array $identifiers, array $row)
    {
        $this->delete($table, $identifiers);
        $this->insert($table, $row);
    }

    /**
     * @param $table
     * @param array $rows
     * @throws \Throwable
     */
    public function multiInsert($table, array $rows): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $this->insert($table, $row);
            }
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        $this->pdo->commit();
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return false|mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (is_callable([$this->pdo, $name])) {
            return call_user_func_array([$this->pdo, $name], $arguments);
        }
        throw new \BadMethodCallException(sprintf('Method with name %s does not exist.', $name));
    }
}
