<?php
/**
 * Created by v.taneev.
 * Date: 10.06.18
 * Time: 15:50
 */


namespace Iswin\Borm;

use Iswin\Borm\DependencyInjection\BormExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BormBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new BormExtension();
    }
}