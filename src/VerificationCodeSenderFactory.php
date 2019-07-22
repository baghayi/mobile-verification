<?php

declare(strict_types=1);

namespace Baghayi\MobileVerification;

use Psr\Container\ContainerInterface;

class VerificationCodeSenderFactory
{
    public function __invoke(ContainerInterface $container): VerificationCodeSender
    {
        return new VerificationCodeSender(
            $container->get(SMSSender::class),
            $container->get(Storage::class)
        );
    }
}
