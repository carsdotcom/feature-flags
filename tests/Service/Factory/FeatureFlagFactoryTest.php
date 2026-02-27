<?php

namespace Carsdotcom\FeatureFlags\Tests\Service\Factory;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException;
use Carsdotcom\FeatureFlags\Service\Factory\FeatureFlagFactory;
use Carsdotcom\FeatureFlags\Service\Statsig\StatsigFeatureFlag;
use Carsdotcom\FeatureFlags\Service\Statsig\StatsigFeatureFlagUser;
use PHPUnit\Framework\TestCase;

class FeatureFlagFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->resetSingleton();
    }

    private function resetSingleton()
    {
        $reflection = new \ReflectionClass(StatsigFeatureFlag::class);
        $property   = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    private function validConfig(): array
    {
        return [
            'apiKey'      => 'test-api-key',
            'environment' => 'staging',
            'redisHost'   => 'localhost',
            'redisPort'   => 6379,
        ];
    }

    // -------------------------------------------------------------------------
    // create — happy path
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function create_returns_a_FeatureFlag_instance()
    {
        $result = FeatureFlagFactory::create($this->validConfig(), 'user123');

        $this->assertInstanceOf(FeatureFlag::class, $result);
    }

    /**
     * @test
     */
    public function create_returns_a_StatsigFeatureFlag_instance()
    {
        $result = FeatureFlagFactory::create($this->validConfig(), 'user123');

        $this->assertInstanceOf(StatsigFeatureFlag::class, $result);
    }

    /**
     * @test
     */
    public function create_returns_the_statsig_singleton()
    {
        $result = FeatureFlagFactory::create($this->validConfig(), 'user123');

        $this->assertSame(StatsigFeatureFlag::getInstance(), $result);
    }

    /**
     * @test
     */
    public function create_sets_the_user_with_the_provided_identifier()
    {
        $result = FeatureFlagFactory::create($this->validConfig(), 'user-abc');

        $this->assertEquals('user-abc', $result->getUser()->getId());
    }

    // -------------------------------------------------------------------------
    // create — user update across calls
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function create_updates_user_on_subsequent_calls()
    {
        FeatureFlagFactory::create($this->validConfig(), 'first-user');
        $result = FeatureFlagFactory::create($this->validConfig(), 'second-user');

        $this->assertEquals('second-user', $result->getUser()->getId());
    }

    // -------------------------------------------------------------------------
    // createUser
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function createUser_returns_a_FeatureFlagUser_instance()
    {
        $result = FeatureFlagFactory::createUser('user123');

        $this->assertInstanceOf(FeatureFlagUser::class, $result);
    }

    /**
     * @test
     */
    public function createUser_returns_a_StatsigFeatureFlagUser_instance()
    {
        $result = FeatureFlagFactory::createUser('user123');

        $this->assertInstanceOf(StatsigFeatureFlagUser::class, $result);
    }

    /**
     * @test
     */
    public function createUser_sets_the_identifier_on_the_returned_user()
    {
        $result = FeatureFlagFactory::createUser('my-user-id');

        $this->assertEquals('my-user-id', $result->getId());
    }

    // -------------------------------------------------------------------------
    // create — invalid config
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function create_throws_when_config_is_empty()
    {
        try {
            FeatureFlagFactory::create([], 'user123');
            $this->fail('Expected InvalidFeatureFlagSettingsException was not thrown');
        } catch (InvalidFeatureFlagSettingsException $e) {
            $this->assertInstanceOf(InvalidFeatureFlagSettingsException::class, $e);
        }
    }

    /**
     * @test
     */
    public function create_throws_when_apiKey_is_missing()
    {
        try {
            FeatureFlagFactory::create([
                'environment' => 'staging',
                'redisHost'   => 'localhost',
                'redisPort'   => 6379,
            ], 'user123');
            $this->fail('Expected InvalidFeatureFlagSettingsException was not thrown');
        } catch (InvalidFeatureFlagSettingsException $e) {
            $this->assertInstanceOf(InvalidFeatureFlagSettingsException::class, $e);
        }
    }

    /**
     * @test
     */
    public function create_throws_when_environment_is_missing()
    {
        try {
            FeatureFlagFactory::create([
                'apiKey'    => 'test-key',
                'redisHost' => 'localhost',
                'redisPort' => 6379,
            ], 'user123');
            $this->fail('Expected InvalidFeatureFlagSettingsException was not thrown');
        } catch (InvalidFeatureFlagSettingsException $e) {
            $this->assertInstanceOf(InvalidFeatureFlagSettingsException::class, $e);
        }
    }

    /**
     * @test
     */
    public function create_throws_when_redisHost_is_missing()
    {
        try {
            FeatureFlagFactory::create([
                'apiKey'      => 'test-key',
                'environment' => 'staging',
                'redisPort'   => 6379,
            ], 'user123');
            $this->fail('Expected InvalidFeatureFlagSettingsException was not thrown');
        } catch (InvalidFeatureFlagSettingsException $e) {
            $this->assertInstanceOf(InvalidFeatureFlagSettingsException::class, $e);
        }
    }

    /**
     * @test
     */
    public function create_throws_when_redisPort_is_missing()
    {
        try {
            FeatureFlagFactory::create([
                'apiKey'      => 'test-key',
                'environment' => 'staging',
                'redisHost'   => 'localhost',
            ], 'user123');
            $this->fail('Expected InvalidFeatureFlagSettingsException was not thrown');
        } catch (InvalidFeatureFlagSettingsException $e) {
            $this->assertInstanceOf(InvalidFeatureFlagSettingsException::class, $e);
        }
    }
}
