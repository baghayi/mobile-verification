<?php

declare(strict_types=1);

namespace Baghayi\MobileVerification;

use Baghayi\Value\Mobile;
use Predis\Client;

class Storage
{
    const DEFAULT_TIME_TO_LIVE_IN_SECONDS = 60 * 2;
    const ITEM_KEY_PREFIX = 'mobile_verification_';
    private $redis;
    private $timeToLive;

    public function __construct(Client $redis, int $ttlInSeconds = self::DEFAULT_TIME_TO_LIVE_IN_SECONDS)
    {
        $this->redis = $redis;
        $this->timeToLive = $ttlInSeconds;
    }

    public function isValidCombinations(Mobile $mobile, int $verificationCode): bool
    {
        return (bool) $this->redis->get($this->generateKey($mobile, $verificationCode));
    }

    public function saveCombinations(Mobile $mobile, int $verificationCode)
    {
        $key = $this->generateKey($mobile, $verificationCode);
        $this->redis->set($key, 1);
        $this->redis->expire($key, $this->timeToLive);
    }

    private function generateKey(Mobile $mobile, int $verificationCode): string
    {
        $combinationsHash = sha1((string) $mobile . $verificationCode);
        return sprintf("%s_%s", self::ITEM_KEY_PREFIX, $combinationsHash);
    }
}
