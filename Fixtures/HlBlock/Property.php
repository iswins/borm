<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 16:18
 */


namespace Iswin\Borm\Fixtures\HlBlock;

use Iswin\Borm\Fixtures\BitrixCompare;
use Iswin\Borm\Fixtures\BitrixCompare\SubQuery;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\UserFieldTable;
use Doctrine\DBAL\Connection;

class Property
{
    protected $data = [
        'SORT' => '100',
        'MULTIPLE' => 'N',
        'MANDATORY' => 'N',
        'SHOW_FILTER' => 'N',
        'SHOW_IN_LIST' => 'Y',
        'EDIT_IN_LIST' => 'Y',
        'IS_SEARCHABLE' => 'N',
        'SETTINGS' => []
    ];

    protected $name = false;

    protected $isDrop = true;

    protected function __construct ($entityId, $code, $type)
    {
        $this->name = $code;
        $this->setEntityId($entityId)
            ->setCode($code)
            ->setType($type);
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

    public static function getInstance($entityId, $code, $type)
    {
        $prop = new Property($entityId, $code, $type);
        $prop->unMarkDrop();
        return $prop;
    }

    public function setSort($value)
    {
        $this->data['SORT'] = $value;
        return $this;
    }

    public function setMulti()
    {
        $this->data['MULTIPLE'] = 'Y';
        return $this;
    }

    public function setRequired()
    {
        $this->data['MANDATORY'] = 'Y';
        return $this;
    }

    public function setShowInFilter($value)
    {
        $this->data['SHOW_FILTER'] = $value;
        return $this;
    }

    public function setShowInList($value)
    {
        $this->data['SHOW_IN_LIST'] = $value;
        return $this;
    }

    public function setEditInList($value)
    {
        $this->data['EDIT_IN_LIST'] = $value;
        return $this;
    }

    public function setIsSearch($value)
    {
        $this->data['IS_SEARCHABLE'] = $value;
        return $this;
    }

    public function setSettings($value)
    {
        $this->data['SETTINGS'] = $value;
        return $this;
    }

    public function setName($value)
    {
        $this->name = $value;
        return $this;
    }

    public function setEntityId($value)
    {
        $this->data['ENTITY_ID'] = $value;
        return $this;
    }

    public function setCode($value)
    {
        $this->data['FIELD_NAME'] = $value;
        $this->data['XML_ID'] = $value;
        return $this;
    }

    public function setType($value)
    {
        $this->data['USER_TYPE_ID'] = $value;
    }

    public function getCode()
    {
        return $this->data['FIELD_NAME'];
    }

    public function getType()
    {
        return $this->data['USER_TYPE_ID'];
    }

    public function getEntityId()
    {
        return $this->data['ENTITY_ID'];
    }

    public function getData()
    {
        return $this->data;
    }

    protected $compare = false;
    protected $dropCompare = false;

    /**
     * @param Connection $connection
     * @param $entityId
     * @return Property[]
     */
    public static function loadFromDb(Connection $connection, $entityId)
    {
        $entityKey = "HLBLOCK_{$entityId}";

        $query = $connection->createQueryBuilder();

        $query
            ->from('b_user_field', 'props')
            ->addSelect('props.*')
            ->addSelect('lng.LANGUAGE_ID')
            ->addSelect('lng.EDIT_FORM_LABEL')
            ->addSelect('lng.LIST_COLUMN_LABEL')
            ->addSelect('lng.LIST_FILTER_LABEL')
            ->join(
                'props',
                'b_user_field_lang', 'lng',
                'props.ID = lng.USER_FIELD_ID'
            )
            ->where("props.ENTITY_ID = :entity_id")
            ->setParameter('entity_id', $entityKey);

        $rows = $query->execute()->fetchAll();
        $list = [];


        foreach ($rows as $row) {
            $id = $row['ID'];
            $languageId = $row['LANGUAGE_ID'];
            unset($row['LANGUAGE_ID']);

            if (!isset($list[$id])) {
               $data = $row;
               $data['EDIT_FORM_LABEL'] = [];
               $data['LIST_COLUMN_LABEL'] = [];
               $data['LIST_FILTER_LABEL'] = [];
               $list[$id] = $data;
            }

            $list[$id]['EDIT_FORM_LABEL'][$languageId] = $row['EDIT_FORM_LABEL'];
            $list[$id]['LIST_COLUMN_LABEL'][$languageId] = $row['LIST_COLUMN_LABEL'];
            $list[$id]['LIST_FILTER_LABEL'][$languageId] = $row['LIST_FILTER_LABEL'];

        }



        $ret = [];

        foreach ($list as $row) {
            $prop = new Property($entityKey, $row['FIELD_NAME'], $row['USER_TYPE_ID']);
            $prop
                ->setSort($row['SORT'])
                ->setShowInFilter($row['SHOW_FILTER'])
                ->setShowInList($row['SHOW_IN_LIST'])
                ->setEditInList($row['EDIT_IN_LIST'])
                ->setIsSearch($row['IS_SEARCHABLE'])
                ->setName($row['EDIT_FORM_LABEL'])
                ->setSettings(@unserialize($row['SETTINGS']));

            $ret[] = $prop;
        }

        return $ret;
    }

    /**
     * @param Connection $connection
     * @param $entityTableName
     * @return BitrixCompare
     */
    protected function getBitrixCompare(Connection $connection, $entityTableName)
    {
        $entitiesTableName = HighloadBlockTable::getTableName();

        $data = $this->getData();
        $data['ENTITY_ID'] = new SubQuery(
            $connection,
            "CONCAT('HLBLOCK_', {$entitiesTableName}.ID) as ENTITY_ID",
            $entitiesTableName,
            [
                'TABLE_NAME' => $entityTableName
            ]
        );

        $data['SETTINGS'] = ['test' => 22];
        $data['SETTINGS'] = serialize($data['SETTINGS']);

        $name = $this->name ? : $this->getCode();
        /** @todo поддерживает только одно название для всех колонок */
        $editName = $listName = $listFilterName = $name;

        $bitrixCompare = BitrixCompare::getInstance(
            $connection,
            "b_user_field",
            [
                'FIELD_NAME' => $data['FIELD_NAME'],
                'ENTITY_ID' => $data['ENTITY_ID']
            ]
        )
            ->setNewValues($data)
            ->setLangFields(
                [
                    'EDIT_FORM_LABEL' => $editName,
                    'LIST_COLUMN_LABEL' => $listName,
                    'LIST_FILTER_LABEL' => $listFilterName
                ]
            )
            ->setLangKey('LANGUAGE_ID')
            ->setLangPrimaryField('USER_FIELD_ID')
            ->setLangTableName('b_user_field_lang');

        return $bitrixCompare;
    }


    protected function getCompare(Connection $connection, $entityTableName)
    {

        if ($this->compare !== false) {
            return $this->compare;
        }

        $bitrixCompare = $this->getBitrixCompare($connection, $entityTableName);
        $up = $bitrixCompare->getUpSql();
        $down = $bitrixCompare->getDownSql();

        $down = array_reverse($down);

        $this->compare = [
            'up' => $up,
            'down' => $down
        ];

        return $this->compare;
    }

    protected function getDropCompare(Connection $connection, $entityTableName)
    {
        if ($this->dropCompare !== false) {
            return $this->dropCompare;
        }

        $bitrixCompare = $this->getBitrixCompare($connection, $entityTableName);
        $up = $bitrixCompare->getDropUpSql();
        $down = $bitrixCompare->getDropDownSql();

        $down = array_reverse($down);

        $this->dropCompare = [
            'up' => $up,
            'down' => $down
        ];

        return $this->dropCompare;
    }

    public function getUpMigrations(Connection $connection, $entityTableName)
    {
        if ($this->isDrop()) {
            return $this->getDropCompare($connection, $entityTableName)['up'];
        }

        return $this->getCompare($connection, $entityTableName)['up'];
    }

    public function getDownMigrations(Connection $connection, $entityTableName)
    {
        if ($this->isDrop()) {
            return $this->getDropCompare($connection, $entityTableName)['down'];
        }

        return $this->getCompare($connection, $entityTableName)['down'];
    }

    public function getName()
    {
        return $this->name;
    }
}