<?php

declare(strict_types=1);

namespace Test;

use Baghayi\Value\Mobile;
use Baghayi\MobileVerification\Storage;
use Baghayi\MobileVerification\StorageFactory;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Predis\Client as Redis;
use Psr\Container\ContainerInterface;

/**
 * @group MobileVerification
 */
class StorageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $mobile;
    private $verificationCode = 12345;

    public function setUp(): void
    {
        $this->mobile = new Mobile('9140000000');
        $this->redis = new Redis();
    }

    public function tearDown(): void
    {
        $this->redis->flushall();
    }

    /**
    * @test
    */
    public function storage_class()
    {
        $this->assertIsObject(new \Baghayi\MobileVerification\Storage($this->redis));
    }

    /**
    * @test
    */
    public function check_a_mobile_and_verification_code_combinations_validity()
    {
        $storage = new Storage($this->redis);
        $this->assertFalse($storage->isValidCombinations($this->mobile, $this->verificationCode));
    }

    /**
    * @test
    */
    public function stores_combinations_of_mobile_and_verification_code()
    {
        $storage = new Storage($this->redis);
        $storage->saveCombinations($this->mobile, $this->verificationCode);
        $this->assertTrue($storage->isValidCombinations($this->mobile, $this->verificationCode));
    }

    /**
    * @test
    */
    public function combination_is_no_longer_valid_after_a_specific_amount_of_time()
    {
        $ttlInSeconds = 1;
        $storage = new Storage($this->redis, $ttlInSeconds);
        $storage->saveCombinations($this->mobile, $this->verificationCode);
        sleep(2);
        $this->assertFalse($storage->isValidCombinations($this->mobile, $this->verificationCode));
    }

    /**
    * @test
    */
    public function by_default_verification_codes_will_expires_after_2_minutes()
    {
        $this->assertSame(2, Storage::DEFAULT_TIME_TO_LIVE_IN_SECONDS/60);
    }

    /**
    * @test
    */
    public function storage_factory()
    {
        $this->assertIsObject(new \Baghayi\MobileVerification\StorageFactory());
    }

    /**
    * @test
    */
    public function storage_factory_makes_us_storage_instance()
    {
        $factory = new StorageFactory;
        $storage = $factory($this->getContainer());
        $this->assertInstanceOf(Storage::class, $storage);
    }

    private function getContainer()
    {
        $mock = Mockery::mock(ContainerInterface::class);
        $mock->allows()->get(Redis::class)->andReturn($this->redis);
        return $mock;
    }
}
