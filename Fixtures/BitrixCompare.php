<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 18:38
 */


namespace Iswin\Borm\Fixtures;

use Iswin\Borm\Fixtures\BitrixCompare\SubQuery;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\LanguageTable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverInterface;
use Doctrine\DBAL\Driver\PDOException;

class BitrixCompare
{
    protected $primaryField = 'ID';

    protected $tableName;
    protected $existsWhere;
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * таблица языков
     * @var 
     */
    protected $langTableName = false;
    /**
     * ассоциативный массив языковых значений
     *
     * @var array
     */
    protected $langFields = [];
    /**
     * Ключ в котором хранится код языка в таблице языков
     *
     * @var bool
     */
    protected $langKey = false;
    /**
     * Примари поле таблицы языков - через него происходит связь с таблицей объектов
     *
     * @var string
     */
    protected $langPrimaryField = 'ID';

    protected $newValues = [];

    protected function __construct (Connection $connection, $tableName, $existsWhere)
    {
        $this->tableName = $tableName;
        $this->existsWhere = $existsWhere;
        $this->connection = $connection;
    }

    public static function getInstance(Connection $connection, $tableName, $existsWhere)
    {
        return new BitrixCompare($connection, $tableName, $existsWhere);
    }

    public function setLangFields($fields)
    {
        $this->langFields = $fields;
        return $this;
    }

    public function getLangFields()
    {
        return $this->langFields;
    }

    public function setLangKey($key)
    {
        $this->langKey = $key;
        return $this;
    }

    public function getLangKey()
    {
        return $this->langKey;
    }

    public function setLangPrimaryField($primaryField)
    {
        $this->langPrimaryField = $primaryField;
        return $this;
    }

    public function getLangPrimaryField()
    {
        return $this->langPrimaryField;
    }

    /**
     * @return mixed
     */
    public function getTableName ()
    {
        return $this->tableName;
    }

    /**
     * @param $tableName
     * @return $this
     */
    public function setTableName ($tableName)
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExistsWhere ()
    {
        return $this->existsWhere;
    }

    /**
     * @param $existsWhere
     * @return $this
     */
    public function setExistsWhere ($existsWhere)
    {
        $this->existsWhere = $existsWhere;
        return $this;
    }

    /**
     * @return string
     */
    public function isLangTableName ()
    {
        return $this->langTableName;
    }

    /**
     * @param $langTableName
     * @return $this
     */
    public function setLangTableName ($langTableName)
    {
        $this->langTableName = $langTableName;
        return $this;
    }

    /**
     * @return array|bool
     */
    public function isWhereLangTable ()
    {
        return $this->whereLangTable;
    }

    /**
     * @param $whereLangTable
     * @return $this
     */
    public function setWhereLangTable ($whereLangTable)
    {
        $this->whereLangTable = $whereLangTable;
        return $this;
    }

    /**
     * @return array
     */
    public function getNewValues (): array
    {
        return $this->newValues;
    }

    /**
     * @param array $newValues
     * @return $this
     */
    public function setNewValues (array $newValues)
    {
        $this->newValues = $newValues;
        return $this;
    }



    protected $compares = false;
    protected $dropCompares = false;

    protected function getDropCompares()
    {
        if ($this->dropCompares != false) {
            return $this->dropCompares;
        }

        $up = [];
        $down = [];

        $exists = $this->makeSelect(array_keys($this->newValues), $this->tableName, $this->existsWhere)->execute()->fetch();

        $translates = [];
        $fieldTranslates = [];

        if ($this->isHaveLanguageTable()) {
            foreach ($this->getLangFields() as $fieldCode => $text) {
                $fieldTranslates[$fieldCode] = $this->getTranslatedParam($text);
            }
        }


        if (!$exists) {
            return $this->dropCompares = [
                'up' => [],
                'down' => []
            ];
        }

        /*
         *             $up[] = $this->makeInsertSql($this->tableName, $values);
            $down[] = $this->makeDeleteSql($this->tableName, $this->existsWhere);
         */

        $up[] = $this->makeDeleteSql($this->tableName, $this->existsWhere);
        $down[] = $this->makeInsertSql($this->tableName, $exists);



        if ($this->isHaveLanguageTable()) {
            $langSelect = array_keys($fieldTranslates);
            $langSelect[] = $this->langKey;
            $langWhere = [
                $this->langPrimaryField => $this->getPrimarySubQuery()
            ];
            $rows = $this->makeSelect($langSelect, $this->langTableName, $langWhere)->execute()->fetchAll();
            foreach ($rows as $row) {
                $translates[$row[$this->langKey]] = $row;
            }

            $langs = $this->getAllLanguageCodes();

            foreach ($langs as $langCode) {
                $oldRow = isset($translates[$langCode]) ? $translates[$langCode] : [];

                $newValues = [
                    $this->langKey => $langCode
                ];

                $oldValues = [
                    $this->langKey => $langCode
                ];

                foreach ($fieldTranslates as $fieldKey => $langSet) {
                    $newValue = $langSet[$langCode];
                    if ($newValue instanceof SubQuery) {
                        continue;
                    }
                    $oldValue = isset($oldRow[$fieldKey]) ? $oldRow[$fieldKey] : null;

                    $newValues[$fieldKey] = $newValue;
                    $oldValues[$fieldKey] = $oldValue;
                }

                $newValues[$this->langPrimaryField] = $this->getPrimarySubQuery();

                $dropWhere = [
                    $this->langPrimaryField => $newValues[$this->langPrimaryField],
                    $this->langKey => $langCode
                ];
                $up[] = $this->makeDropLanguage($dropWhere);
                $down[] = $this->makeInsertLanguage($newValues);

            }
        }

        $up = array_reverse($up);

        return $this->compares = [
            'up' => $up,
            'down' => $down
        ];
    }

    protected function getCompares()
    {
        if ($this->compares != false) {
            return $this->compares;
        }

        $up = [];
        $down = [];

        $exists = $this->makeSelect(array_keys($this->newValues), $this->tableName, $this->existsWhere)->execute()->fetch();

        $translates = [];
        $fieldTranslates = [];

        if ($this->isHaveLanguageTable()) {
            foreach ($this->getLangFields() as $fieldCode => $text) {
                $fieldTranslates[$fieldCode] = $this->getTranslatedParam($text);
            }
        }


        if (!$exists) {
            $values = array_merge($this->newValues, $this->existsWhere);
            $up[] = $this->makeInsertSql($this->tableName, $values);
            $down[] = $this->makeDeleteSql($this->tableName, $this->existsWhere);
        } else {
            $needUpdate = false;

            foreach ($this->newValues as $code => $val) {
                if (!($val instanceof SubQuery) && $exists[$code] != $val) {
                    $needUpdate = true;
                    break;
                }
            }

            if ($needUpdate) {
                $up[] = $this->makeUpdateSql($this->tableName, $this->existsWhere, $this->newValues);
                $down[] = $this->makeUpdateSql($this->tableName, $this->existsWhere, $exists);
            }

            if ($this->isHaveLanguageTable()) {
                $langSelect = array_keys($fieldTranslates);
                $langSelect[] = $this->langKey;
                $langWhere = [
                    $this->langPrimaryField => $this->getPrimarySubQuery()
                ];
                $rows = $this->makeSelect($langSelect, $this->langTableName, $langWhere)->execute()->fetchAll();
                foreach ($rows as $row) {
                    $translates[$row[$this->langKey]] = $row;
                }
            }
        }


        if ($this->isHaveLanguageTable()) {

            $langs = $this->getAllLanguageCodes();

            foreach ($langs as $langCode) {
                $oldRow = isset($translates[$langCode]) ? $translates[$langCode] : [];
                $needCreate = !isset($translates[$langCode]);
                $needUpdate = false;

                $newValues = [
                    $this->langKey => $langCode
                ];

                $oldValues = [
                    $this->langKey => $langCode
                ];

                foreach ($fieldTranslates as $fieldKey => $langSet) {
                    $newValue = $langSet[$langCode];
                    if ($newValue instanceof SubQuery) {
                        continue;
                    }
                    $oldValue = isset($oldRow[$fieldKey]) ? $oldRow[$fieldKey] : null;
                    if ($oldValue != $newValue) {
                        $needUpdate = true;
                    }

                    $newValues[$fieldKey] = $newValue;
                    $oldValues[$fieldKey] = $oldValue;
                }

                $newValues[$this->langPrimaryField] = $this->getPrimarySubQuery();

                if ($needCreate) {
                    $up[] = $this->makeInsertLanguage($newValues);
                    $dropWhere = [
                        $this->langPrimaryField => $newValues[$this->langPrimaryField],
                        $this->langKey => $langCode
                    ];
                    $down[] = $this->makeDropLanguage($dropWhere);
                } elseif($needUpdate) {
                    $up[] = $this->makeUpdateLanguage($newValues);
                    $down[] = $this->makeUpdateLanguage($oldValues);
                }

            }
        }

       return $this->compares = [
            'up' => $up,
            'down' => $down
        ];
    }

    /**
     * @return SubQuery
     */
    protected function getPrimarySubQuery()
    {
        $langSubQuery = new SubQuery($this->connection, $this->primaryField, $this->tableName, $this->existsWhere);
        return $langSubQuery;
    }



    protected function makeInsertLanguage($values)
    {
        $sql = $this->makeInsertSql($this->langTableName, $values);
        return $sql;
    }

    protected function makeUpdateLanguage($values)
    {
        $langCode = $values[$this->langKey];


        $wheres = [
            $this->langPrimaryField => $this->getPrimarySubQuery(),
            $this->langKey => $langCode
        ];

        $sql = $this->makeUpdateSql($this->langTableName, $wheres, $values);
        return $sql;
    }

    protected function makeDropLanguage($values)
    {
        return $this->makeDeleteSql($this->langTableName, $values);
    }

    protected function isHaveLanguageTable()
    {
        return $this->langTableName && $this->getLangFields() &&  $this->langKey;
    }

    protected function getAllLanguageCodes()
    {
        $rows = LanguageTable::query()
            ->addSelect('LANGUAGE_ID')
            ->addOrder('SORT', 'ASC')
            ->addFilter('=ACTIVE', 'Y')
            ->exec();

        $ret = [];
        while ($row = $rows->fetch()) {
            $ret[] = $row['LANGUAGE_ID'];
        }

        return $ret;
    }

    protected function getTranslatedParam($value)
    {
        $codes = $this->getAllLanguageCodes();
        if (!is_array($value)) {
            $firstValue = $value;
            $value = [];
            $value[key($codes)] = $firstValue;
        }

        $firstValue = current($value);
        $ret = [];
        foreach ($codes as $code) {
            if (!isset($value[$code])) {
                $value[$code] = $firstValue;
            }

            $ret[$code] = $value[$code];
        }

        return $ret;
    }

    /**
     * @param $fields
     * @param $tableName
     * @param $wheres
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    protected function makeSelect($fields, $tableName, $wheres)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $query = $this->connection->createQueryBuilder();

        $query->from($tableName);

        foreach ($fields as $field) {
            $query->addSelect($field);
        }

        foreach ($wheres as $key => $val) {
            if ($val instanceof SubQuery) {
                $query->andWhere("{$key} = ({$val})");
            } else {
                $query->andWhere("{$key} = :{$key}");
                $query->setParameter($key, $val);
            }
        }

        return $query;
    }

    protected function makeWhereSql($wheres)
    {
        $connection = $this->connection;
        /** @var DriverInterface $pdo */
        $pdo = $connection->getWrappedConnection();
        $ret = [];
        foreach ($wheres as $key => $where) {
            if ($where instanceof SubQuery) {
                $ret[] = "{$key}({$where})";
            } else {
                $ret[] = "{$key}" . $pdo->quote($where);
            }
        }

        return implode(" AND ", $ret);
    }

    protected function makeUpdateSql($tableName, $wheres, $values)
    {
        /** @var DriverInterface $pdo */
        $pdo = $this->connection->getWrappedConnection();


        $sqlValues = [];
        $sqlWheres = [];

        foreach ($values as $key => $val) {
            if ($val instanceof SubQuery) {
                $sqlValues[] = "`{$key}` = ($val)";
            } else {
                $sqlValues[] = "`{$key}` = " . $pdo->quote($val);
            }
        }

        foreach ($wheres as $key => $val) {
            if ($val instanceof SubQuery) {
                $sqlWheres[] = "`{$key}` = ($val)";
            } else {
                $sqlWheres[] = "`{$key}` = " . $pdo->quote($val);
            }
        }

        $sql = "UPDATE {$tableName} SET " . implode(", ", $sqlValues) . " WHERE " . implode(" AND ", $sqlWheres);

        return $sql;
    }

    protected function makeDeleteSql($tableName, $wheres)
    {
        $data = $this->makeFields($wheres);

        $sql = "DELETE FROM {$tableName} WHERE ";
        $lines = [];

        foreach ($data['keys'] as $index => $key) {
            $value = $data['values'][$index];
            if ($value instanceof SubQuery) {
                $value = "({$value})";
            }
            $lines[] = "{$key} = {$value}";
        }


        return $sql . implode(" AND ", $lines);
    }

    protected function makeInsertSql($tableName, $fields)
    {
        /** @var DriverInterface $pdo */
        $pdo = $this->connection->getWrappedConnection();

        $data = $this->makeFields($fields);
        $asSubQuery = $data['have_sub_query'];

        $sql = "INSERT INTO {$tableName} (" . implode(' , ', $data['keys']) . ") ";
        if ($asSubQuery) {
            $selectedFields = [];
            $subQuery = false;
            foreach ($data['values'] as $index => $val) {
                if ($val instanceof SubQuery) {
                    $subQuery = $val;
                    $selectedFields[] = $val->getFieldId();
                } else {
                    $selectedFields[] = $val;
                }
            }

            if ($subQuery) {
                $sql .= "(" . $subQuery->toString($selectedFields) . ")";
            } else {
                $sql .= " VALUES (" . implode(' , ', $data['values']) . ")";
            }

        } else {
            $sql .= " VALUES (" . implode(' , ', $data['values']) . ")";
        }

        return $sql;
    }

    protected function makeFields($fields)
    {
        $asSubQuery = false;
        $keys = [];
        $values = [];

        /** @var DriverInterface $pdo */
        $pdo = $this->connection->getWrappedConnection();

        foreach ($fields as $key => $val) {

            if ($val instanceof SubQuery) {
                $asSubQuery = true;
                $values[] = $val;
                $keys[] = $key;
            } else {
                $keys[] = "`{$key}`";
                $values[] = $pdo->quote($val);
            }
        }

        return [
            'keys' => $keys,
            'values' => $values,
            'have_sub_query' => $asSubQuery
        ];
    }

    public function getUpSql()
    {
        return $this->getCompares()['up'];
    }

    public function getDownSql()
    {
        return $this->getCompares()['down'];
    }

    public function getDropUpSql()
    {
        return $this->getDropCompares()['up'];
    }

    public function getDropDownSql()
    {
        return $this->getDropCompares()['down'];
    }


}