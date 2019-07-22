<?php

declare(strict_types=1);

namespace Baghayi\MobileVerification;

use Predis\Client as Redis;
use Psr\Container\ContainerInterface;

class StorageFactory
{
    public function __invoke(ContainerInterface $container): Storage
    {
        return new Storage(
            $container->get(Redis::class)
        );
    }
}
