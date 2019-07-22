<?php

declare(strict_types=1);

namespace MobileVerification_Test;

use Baghayi\MobileVerification\Storage;
use Baghayi\Value\Mobile;
use Kavenegar\KavenegarApi;
use Baghayi\MobileVerification\SMSSender;
use Baghayi\MobileVerification\VerificationCodePlaceHolderMissing;
use Baghayi\MobileVerification\VerificationCodeSender;
use Baghayi\MobileVerification\VerificationCodeSenderFactory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @group MobileVerification
 */
class VerificationCodeSMSTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $mobile;

    public function setUp(): void
    {
        $this->mobile = new Mobile('9140000000');
    }

    /**
    * @test
    */
    public function there_is_the_service()
    {
        $this->assertIsObject(new VerificationCodeSender($this->getSMSSender(), $this->getStorageSpy()));
    }

    /**
    * @test
    */
    public function sends_5_digit_as_a_verification_code_to_a_given_mobile()
    {
        $smsService = Mockery::mock(SMSSender::class);
        $smsService->shouldReceive('send')->withArgs(function($receiverMobile, $smsText){
            return $this->deliversToExpectedPhoneNumber($receiverMobile) &&
                $this->isValidFiveDigitVerificationCodeAsSMSText($smsText);
        })->once();
        $service = new VerificationCodeSender($smsService, $this->getStorageSpy());
        $service->sendACodeToMobile($this->mobile);
    }

    private function deliversToExpectedPhoneNumber($receiverMobile): bool
    {
        return (string) $receiverMobile === (string) $this->mobile;
    }

    private function isValidFiveDigitVerificationCodeAsSMSText(string $smsText)
    {
        return (bool) preg_match('/[0-9]{5}/', $smsText, $matches);
    }

    /**
    * @test
    */
    public function it_generates_a_new_random_5_digit_each_and_every_time()
    {
        $verificationCodes = [];

        $smsService = Mockery::mock(SMSSender::class);
        $smsService->shouldReceive('send')->withArgs(function($receiverMobile, $smsText) use(&$verificationCodes) {
            return $this->uniqueVerificationCodeIsUsed($smsText, $verificationCodes);
        });
        $service = new VerificationCodeSender($smsService, $this->getStorageSpy());
        $service->sendACodeToMobile($this->mobile);
        $service->sendACodeToMobile($this->mobile);
        $service->sendACodeToMobile($this->mobile);
        $service->sendACodeToMobile($this->mobile);
    }

    private function uniqueVerificationCodeIsUsed($smsText, array &$verificationCodes): bool
    {
        $code = $this->extractVerificationCode($smsText);
        $isUnique = !in_array($code, $verificationCodes);
        array_push($verificationCodes, $code);
        return $isUnique;
    }

    /**
    * @test
    */
    public function checking_verification_code_validity()
    {
        $smsService = Mockery::mock(SMSSender::class);
        $verificationCode = null;
        $this->listenForVerificationCodeToExtractFromSMSMessage($smsService, $verificationCode);
        $service = new VerificationCodeSender($smsService, $this->getStorageMock());
        $service->sendACodeToMobile($this->mobile);
        $this->assertTrue($service->isVerificationCodeValid($this->mobile, $verificationCode));
    }

    private function extractVerificationCode($smsText): int
    {
        preg_match('/[0-9]{5}/', $smsText, $matches);
        return (int) $matches[0];
    }

    private function listenForVerificationCodeToExtractFromSMSMessage($smsService, &$verificationCode)
    {
        $smsService->shouldReceive('send')->withArgs(function($receiverMobile, $smsText)
            use(&$verificationCode) {
            $verificationCode = $this->extractVerificationCode($smsText);
                return true;
        });
    }

    /**
    * @test
    */
    public function invalid_verification_code_length()
    {
        $service = new VerificationCodeSender($this->getSMSSender(), $this->getStorageSpy());
        $this->assertFalse($service->isVerificationCodeValid($this->mobile, 123456));
    }

    /**
    * @test
    */
    public function invalid_verification_code_is_should_be_considered_invalid()
    {
        $service = new VerificationCodeSender($this->getSMSSender(), $this->getStorageMock());
        $this->assertFalse($service->isVerificationCodeValid($this->mobile, 12345));
    }

    private function getStorageMock()
    {
        $savedCombinations = [];
        $storage = Mockery::mock(Storage::class)->shouldIgnoreMissing();
        $storage->shouldReceive('saveCombinations')->withArgs(function(Mobile $mobile, int $verificationCode) 
            use (&$savedCombinations) {
                $savedCombinations[] = sprintf("%s_%s", (string) $mobile, $verificationCode);
        });
        $storage->shouldReceive('isValidCombinations')->withArgs(function(Mobile $mobile, int $verificationCode) 
            use(&$savedCombinations) {
                $combinations = sprintf("%s_%s", (string) $mobile, $verificationCode);
                return in_array($combinations, $savedCombinations);
        })->andReturn(true);
        return $storage;
    }

    private function getStorageSpy(): Storage
    {
        return Mockery::spy(Storage::class);
    }

    /**
    * @test
    */
    public function VerificationCodeSMS_service_Factory()
    {
        $this->assertIsObject(new VerificationCodeSenderFactory());
    }

    /**
    * @test
    */
    public function factory_makes_VerificationCodeSMS_instance()
    {
        $factory = new VerificationCodeSenderFactory();
        $service = $factory($this->getContainer());
        $this->assertInstanceOf(VerificationCodeSender::class, $service);
    }

    private function getContainer(): ContainerInterface
    {
        $mock = Mockery::mock(ContainerInterface::class);
        $mock->allows()->get(SMSSender::class)->andReturn($this->getSMSSender());
        $mock->allows()->get(Storage::class)->andReturn($this->getStorageSpy());
        return $mock;
    }

    private function getKavenegarApiMock()
    {
        return Mockery::spy(KavenegarApi::class);
    }

    private function getSMSSender(): SMSSender
    {
        $mock = Mockery::mock(SMSSender::class);
        return $mock;
    }

    /**
    * @test
    */
    public function can_change_sms_message_template()
    {
        $message = 'This is an interesting SMS code message';
        $messageTemplate = $message . PHP_EOL . '%d';
        $smsService = Mockery::mock(SMSSender::class);
        $smsService->shouldReceive('send')->withArgs(function($mobile, $actualMessage) use($message){
            return $this->isUsingNewTemplate($actualMessage, $message);
        })->once();
        $service = new VerificationCodeSender($smsService, $this->getStorageSpy());
        $service->changeMessageTemplate($messageTemplate);
        $service->sendACodeToMobile($this->mobile);
    }

    private function isUsingNewTemplate($actualMessage, string $partialyExpectedMessage)
    {
        return false !== strpos($actualMessage, $partialyExpectedMessage);
    }

    /**
    * @test
    */
    public function make_sure_by_changing_message_template_you_are_not_shooting_yourself_at_foot()
    {
        $this->expectException(VerificationCodePlaceHolderMissing::class);
        $messageTemaplateWithNoPlaceHolder = 'hello darling';
        $smsService = Mockery::mock(SMSSender::class);
        $service = new VerificationCodeSender($smsService, $this->getStorageSpy());
        $service->changeMessageTemplate($messageTemaplateWithNoPlaceHolder);
    }

    /**
    * @test
    */
    public function make_default_sms_message_generic()
    {
        $smsService = Mockery::mock(SMSSender::class);
        $smsService->shouldReceive('send')->withArgs(function($mobile, $message){
            return $this->isGenericMessage($message);
        })->once();
        $service = new VerificationCodeSender($smsService, $this->getStorageSpy());
        $service->sendACodeToMobile($this->mobile);
    }

    private function isGenericMessage($message): bool
    {
        return false !== strpos($message, 'کد تایید برای ورود:');
    }
}
