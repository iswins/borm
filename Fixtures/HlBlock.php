<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 16:15
 */


namespace Iswin\Borm\Fixtures;


use Iswin\Borm\Fixtures\HlBlock\Property;
use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\LanguageTable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type as DBType;
use Doctrine\DBAL\Driver\Connection as DriverInterface;

class HlBlock
{
    protected $table;
    protected $name;
    protected $code;

    protected $props;

    protected $isDrop = true;

    protected function __construct ($table)
    {
        $this->table = $table;
    }

    /**
     * @var HlBlock[]
     */
    protected static $instances = [];

    public static function getInstance($table, $name, $code)
    {
        if (!isset(self::$instances[$table])) {
            self::$instances[$table] = new HlBlock($table);
        }

        return self::$instances[$table]
            ->setName($name)
            ->setCode($code)
            ->unMarkDrop();
    }

    /**
     * @return $this
     */
    public function unMarkDrop()
    {
        $this->isDrop = false;
        return $this;
    }

    public function isDrop()
    {
        return $this->isDrop;
    }

    /**
     * @return HlBlock[]
     */
    public static function loadFromDb(Connection $connection)
    {
        $ret = [];

        $rows = HighloadBlockTable::query()
            ->addSelect('*')
            ->exec();

        while ($row = $rows->fetch()) {

            $langs = HighloadBlockLangTable::query()
                ->addSelect('NAME')
                ->addSelect('LID')
                ->addFilter('ID', $row['ID'])
                ->exec();

            $names = [];

            while ($lang = $langs->fetch()) {
                $names[$lang['LID']] = $lang['NAME'];
            }

            $block = new HlBlock(
                $row['TABLE_NAME']
            );

            $block->setName($names)
                ->setCode($row['NAME']);

            $properties = Property::loadFromDb($connection, $row['ID']);
            foreach ($properties as $property) {
                $block->addProperty($property);
            }

            self::$instances[$row['TABLE_NAME']] = $block;

            $ret[] = $block;
        }

        return $ret;
    }

    /**
     * @return mixed
     */
    public function getName ()
    {
        return $this->name;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setName ($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCode ()
    {
        return $this->code;
    }

    /**
     * @param $code
     * @return $this
     */
    public function setCode ($code)
    {
        $this->code = $code;
        return $this;
    }

    public function addProperty(Property $property)
    {
        $this->props[$property->getCode()] = $property;
    }

    protected $compares = false;
    protected $dropCompares = false;


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
     * @param Connection $connection
     * @return BitrixCompare
     */
    protected function getBitrixCompare(Connection $connection)
    {
        Loader::includeModule('highloadblock');


        $bitrixCompare = BitrixCompare::getInstance($connection, HighloadBlockTable::getTableName(), ['TABLE_NAME' => $this->table])
            ->setNewValues(['NAME' => $this->code])
            ->setLangFields(['NAME' => $this->name])
            ->setLangKey('LID')
            ->setLangPrimaryField('ID')
            ->setLangTableName(HighloadBlockLangTable::getTableName());

        return $bitrixCompare;
    }

    protected function getCompare(Connection $connection)
    {
        if ($this->compares != false) {
            return $this->compares;
        }

        $bitrixCompare = $this->getBitrixCompare($connection);

        $up = $bitrixCompare->getUpSql();
        $down = $bitrixCompare->getDownSql();

        /** @var Property $prop */
        foreach ($this->props as $prop) {
            $ups = $prop->getUpMigrations($connection, $this->table);
            foreach ($ups as $upItem) {
                $up[] = $upItem;
            }

            $downs = $prop->getDownMigrations($connection, $this->table);
            foreach ($downs as $downItem) {
                $down[] = $downItem;
            }
        }

        $down = array_reverse($down);
        $this->compares = [
            'up' => $up,
            'down' => $down
        ];

        return $this->compares;
    }


    protected function getDropCompare(Connection $connection)
    {
        if ($this->dropCompares != false) {
            return $this->dropCompares;
        }

        $bitrixCompare = $this->getBitrixCompare($connection);

        $up = $bitrixCompare->getDropUpSql();
        $down = $bitrixCompare->getDropDownSql();

        /** @var Property $prop */
        foreach ($this->props as $prop) {
            $ups = $prop->getUpMigrations($connection, $this->table);
            foreach ($ups as $upItem) {
                $up[] = $upItem;
            }

            $downs = $prop->getDownMigrations($connection, $this->table);
            foreach ($downs as $downItem) {
                $down[] = $downItem;
            }
        }

        $down = array_reverse($down);
        $this->dropCompares = [
            'up' => $up,
            'down' => $down
        ];

        return $this->dropCompares;
    }


    public function getProps()
    {
        return $this->props;
    }


    public function getUpMigrations(Connection $connection)
    {
        if ($this->isDrop()) {
            return $this->getDropCompare($connection)['up'];
        }

        return $this->getCompare($connection)['up'];
    }

    public function getDownMigrations(Connection $connection)
    {
        if ($this->isDrop()) {
            return $this->getDropCompare($connection)['down'];
        }

        return $this->getCompare($connection)['down'];
    }
}