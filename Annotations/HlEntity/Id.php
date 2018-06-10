<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 14:20
 */


namespace Iswin\Borm\Annotations\HlEntity;


use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class Id extends Annotation
{
    public $isId = true;
}