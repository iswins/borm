<?php
/**
 * Created by v.taneev.
 * Date: 03.06.18
 * Time: 14:21
 */


namespace Iswin\Borm\Annotations\HlEntity;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
class Field extends Annotation
{
    public $title;
    public $type = 'string';
    public $multi = false;
    public $settings = [];
    public $required = false;
    public $sort = 100;
}