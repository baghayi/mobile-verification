<?php

declare(strict_types=1);

namespace Baghayi\MobileVerification;

use Baghayi\Value\Mobile;

interface SMSSender
{
    public function send(Mobile $mobile, string $message);
}
