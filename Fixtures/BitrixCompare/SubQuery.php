<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 18:46
 */


namespace Iswin\Borm\Fixtures\BitrixCompare;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverInterface;

class SubQuery
{
    protected $fieldId;
    protected $tableName;
    /**
     * @var Connection
     */
    protected $connection;
    protected $wheres = [];

    public function __construct (Connection $connection, $fieldId, $tableName, $wheres = [])
    {
        $this->fieldId = $fieldId;
        $this->tableName = $tableName;
        $this->wheres = $wheres;
        $this->connection = $connection;
    }

    protected function makeWhereSql()
    {
        $connection = $this->connection;
        /** @var DriverInterface $pdo */
        $pdo = $connection->getWrappedConnection();
        $ret = [];
        foreach ($this->wheres as $key => $where) {
            if ($where instanceof SubQuery) {
                $ret[] = "`{$this->tableName}`.`{$key}`=({$where})";
            } else {
                $ret[] = "`{$this->tableName}`.`{$key}`=" . $pdo->quote($where);
            }
        }

        return implode(" AND ", $ret);
    }

    public function toString($selectFields = [])
    {

        if ($selectFields) {
            $fields = [];
            foreach ($selectFields as $field) {
                $fields[] = $field;
            }
        } else {
            $fields = [$this->fieldId];
        }

        return "SELECT ". implode(', ', $fields) ." FROM {$this->tableName} WHERE " . $this->makeWhereSql();
    }

    public function __toString ()
    {
        return $this->toString();
    }

    public function getFieldId()
    {
        return $this->fieldId;
    }



}