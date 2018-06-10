<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 16:04
 */


namespace Iswin\Borm\OrmProvider;

use Iswin\Borm\Annotations\HlEntity;
use Iswin\Borm\Fixtures\HlBlock;
use Bitrix\Main\Loader;
use Bitrix\Perfmon\Sql\Column;
use Doctrine\DBAL\Migrations\Provider\SchemaProviderInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\Annotations\AnnotationReader;

class BitrixSchemaProvider implements SchemaProviderInterface
{
    /**
     * @var     EntityManagerInterface
     */
    private $entityManager;

    public function __construct($em)
    {
        if ( ! $this->isEntityManager($em)) {
            throw new \InvalidArgumentException(sprintf(
                '$em is not a valid Doctrine ORM Entity Manager, got "%s"',
                is_object($em) ? get_class($em) : gettype($em)
            ));
        }

        $this->entityManager = $em;
    }


    /**
     * @return HlBlock[]
     */
    public function getBitrixFixtures()
    {
        $ret = [];
        $annotationReader = new AnnotationReader();
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        Loader::includeModule('highloadblock');

        $existsBlocks = HlBlock::loadFromDb($this->entityManager->getConnection());

        /** @var \Doctrine\ORM\Mapping\ClassMetadata $row */
        foreach ($metadata as $row) {
            $className = $row->getReflectionClass();
            $classAnnotations = $annotationReader->getClassAnnotations($className);

            $tableName = $row->getTableName();
            $entityName = $className->getShortName();
            $entityTitle = $entityName;

            foreach ($classAnnotations as $annotation) {
                if ($annotation instanceof HlEntity) {
                    $entityTitle = $annotation->title;
                }
            }

            $hlBlock = HlBlock::getInstance($tableName, $entityTitle, $entityName);
            $hlBlock->unMarkDrop();
            $ret[] = $hlBlock;
            /** @var \ReflectionProperty $prop */
            foreach ($row->getReflectionProperties() as $prop) {
                $propertyAnnotations = $annotationReader->getPropertyAnnotations($prop);

                $propertyCode = $row->getColumnName($prop->getName());
                if (!preg_match("#^UF_.*#", $propertyCode)) {
                    $assocMap = $row->getAssociationMappings();
                    if ($assocMap) {
                        $propertyCode = current($assocMap)['targetToSourceKeyColumns']['id'];
                        if (!$propertyCode) {
                            $propertyCode = current($assocMap)['targetToSourceKeyColumns']['ID'];
                        }
                    }
                }

                $propertyAnnotation = false;

                foreach ($propertyAnnotations as $annotation) {

                    if ($annotation instanceof HlEntity\Field) {
                        $propertyAnnotation = $annotation;
                    }
                }

                if ($propertyAnnotation) {
                    $property = HlBlock\Property::getInstance('HLBLOCK_#HL_ID#', $propertyCode, $propertyAnnotation->type);
                    $property->unMarkDrop();
                    if ($propertyAnnotation->multi) {
                        $property->setMulti();
                    }

                    $property->setSettings($propertyAnnotation->settings);
                    if ($propertyAnnotation->required) {
                        $property->setRequired();
                    }

                    $property->setSort($propertyAnnotation->sort);
                    $property->setName($propertyAnnotation->title ? : $propertyCode);

                    $hlBlock->addProperty($property);
                }
            }
        }

        /** @var HlBlock $existsBlock */
        foreach ($existsBlocks as $existsBlock) {
            if (!$existsBlock->isDrop()) {
                continue;
            }
            $ret[] = $existsBlock;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function createSchema()
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        if (empty($metadata)) {
            throw new \UnexpectedValueException('No mapping information to process');
        }

        $tool = new SchemaTool($this->entityManager);

        return $tool->getSchemaFromMetadata($metadata);
    }


    /**
     * Doctrine's EntityManagerInterface was introduced in version 2.4, since this
     * library allows those older version we need to be able to check for those
     * old ORM versions. Hence the helper method.
     *
     * No need to check to see if EntityManagerInterface exists first here, PHP
     * doesn't care.
     *
     * @param   mixed $manager Hopefully an entity manager, but it may be anything
     * @return  boolean
     */
    private function isEntityManager($manager)
    {
        return $manager instanceof EntityManagerInterface || $manager instanceof EntityManager;
    }
}