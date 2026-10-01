<?php

declare(strict_types=1);

namespace App;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements WarmableInterface
{
    use CoroutinesSupportingKernel;
    use MicroKernelTrait {
        CoroutinesSupportingKernel::initializeContainer insteadof MicroKernelTrait;
        CoroutinesSupportingKernel::getContainerBaseClass insteadof MicroKernelTrait;
    }
}
