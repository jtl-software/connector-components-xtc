<?php


namespace Jtl\Connector\XtcComponents;

/**
 * Class DbService
 * @package Jtl\Connector\XtcComponents
 *
 * @mixin \PDO
 */
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
     * @return void
     */
    public function insert(string $table, array $row): void
    {
        $columns = '`' . implode('`,`', array_keys($row)) . '`';
        $values = implode(',', array_fill(0, count($row), '?'));
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $columns, $values);

        $statement = $this->prepare($sql);
        $this->bindParams($statement, $row);

        $statement->execute();
    }

    /**
     * @param string $table
     * @param array $row
     * @param array $identifier
     * @return integer
     */
    public function update(string $table, array $row, array $identifier): int
    {
        $set = [];
        foreach ($row as $column => $value) {
            $set[] = sprintf('`%s` = ?', $column);
        }

        $wheres = [];
        foreach ($identifier as $column => $value) {
            $wheres[] = sprintf('`%s` = ?', $column);
        }

        if (count($wheres) === 0) {
            $wheres[] = '1';
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $wheres));

        $stmt = $this->prepare($sql);
        $this->bindParams($stmt, array_merge(array_values($row), array_values($identifier)));
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * @param string $table
     * @param array<mixed> $row
     * @param array<mixed> $identifier
     */
    public function upsert(string $table, array $row, array $identifier): void
    {
        try {
            $this->insert($table, $row);
        } catch (\PDOException $ex) {
            if ($ex->errorInfo[1] !== 1062) {
                throw $ex;
            }

            $this->update($table, $row, $identifier);
        }
    }

    /**
     * @param string $table
     * @param array<mixed> $identifier
     * @return integer
     */
    public function delete(string $table, array $identifier): int
    {
        $wheres = [];
        foreach ($identifier as $column => $value) {
            $wheres[] = sprintf('`%s` = ?', $column);
        }

        if (count($wheres) === 0) {
            $wheres[] = '1';
        }

        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, implode(' AND ', $wheres));
        $stmt = $this->prepare($sql);

        $this->bindParams($stmt, $identifier);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * @param string $table
     * @param array<mixed> $identifier
     * @param array<mixed> $row
     */
    public function deleteInsert(string $table, array $row, array $identifier)
    {
        $this->delete($table, $identifier);
        $this->insert($table, $row);
    }

    /**
     * @param $table
     * @param array $rows
     * @throws \Throwable
     */
    public function multiInsert($table, array $rows): void
    {
        $this->beginTransaction();
        try {
            foreach ($rows as $row) {
                $this->insert($table, $row);
            }
            $this->commit();
        } catch (\Throwable $ex) {
            $this->rollBack();
            throw $ex;
        }
    }

    /**
     * @param \PDOStatement $statement
     * @param array<mixed> $row
     * @return void
     */
    protected function bindParams(\PDOStatement $statement, array $row): void
    {
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
                        $value = $value->format('Y-m-d H:i:s');
                    }
                    break;
            }

            $statement->bindValue($index + 1, $value, $paramType);
        }
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

    /**
     * @param string $dbHost
     * @param string $dbName
     * @param string $dbUser
     * @param string $dbPassword
     * @param array $dbOptions
     * @return \PDO
     */
    public static function createPDO(string $dbHost, string $dbName, string $dbUser, string $dbPassword, array $dbOptions = []): \PDO
    {
        //$dbOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'UTF8\'';
        $dsn = sprintf('mysql:dbname=%s;host=%s', $dbName, $dbHost);

        $pdo = new \PDO($dsn, $dbUser, $dbPassword, $dbOptions);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}
