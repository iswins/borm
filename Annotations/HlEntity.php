<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 14:11
 */


namespace Iswin\Borm\Annotations;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class HlEntity extends Annotation
{
    public $title;
}