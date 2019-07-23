<?php

declare(strict_types=1);

namespace Baghayi\MobileVerification;

use Baghayi\MobileVerification\Storage;
use Baghayi\Value\Mobile;

class VerificationCodeSender
{
    private $sms;
    private $storage;
    private $messageTemplate = 'کد تایید برای ورود:' . PHP_EOL . '%d';

    public function __construct(SMSSender $sms, Storage $storage)
    {
        $this->sms = $sms;
        $this->storage = $storage;
    }

    public function sendACodeToMobile(Mobile $mobile)
    {
        $verificationCode = $this->generateVerificationCode();
        $this->storage->saveCombinations($mobile, $verificationCode);
        $this->sms->send(
            $mobile,
            $this->getSMSMessage($verificationCode)
        );
    }

    public function isVerificationCodeValid(Mobile $mobile, int $verificationCode): bool
    {
        if (!$this->codeHasCorrentLength($verificationCode))
            return false;
        return $this->storage->isValidCombinations($mobile, $verificationCode);
    }

    public function changeMessageTemplate(string $newTemplate)
    {
        if ($this->isVerificationCodePlaceHolderMissing($newTemplate))
            throw new VerificationCodePlaceHolderMissing();
        $this->messageTemplate = $newTemplate;
    }

    private function isVerificationCodePlaceHolderMissing(string $newTemplate)
    {
        return false === strpos($newTemplate, '%d');
    }

    private function getSMSMessage(int $verificationCode): string
    {
        return sprintf(
            $this->messageTemplate,
            $verificationCode
        );
    }

    private function generateVerificationCode(): int
    {
        return random_int(10000, 99999);
    }

    private function codeHasCorrentLength(int $verificationCode): bool
    {
        if (strlen((string) $verificationCode) === 5)
            return true;
        return false;
    }
}
