<?php

namespace Carsdotcom\FeatureFlags\Tests;

use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException;
use Carsdotcom\FeatureFlags\Service\SplitIO\SplitFeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Service\SplitIO\SplitFeatureFlagUser;
use PHPUnit\Framework\TestCase;
use SplitIO\Sdk\Factory\LocalhostSplitFactory;

class SplitFeatureFlagTest extends TestCase
{

    function setup()
    {
        $factory = new LocalhostSplitFactory(['splitFile' => __DIR__ . '/../../data/SplitIO/split.yaml']);
        $this->split = SplitFeatureFlag::getInstance()
            ->setFactory($factory)
            ->setClient($factory->client())
            ->setManager($factory->manager());
    }

    /**
     * @test
     */
    function it_throws_exception_with_bad_settings()
    {
        $this->setExpectedException(InvalidFeatureFlagSettingsException::class);

        $this->split->validateSettings(['foo' => 'bar']);
    }

    /**
     * @test
     */
    function it_is_an_instance_of_feature_flag_contract()
    {
        $this->assertInstanceOf(FeatureFlag::class, $this->split, "SplitFeatureFlag should implement FeatureFlag interface.");
    }

    /**
     * @test
     */
    function it_will_throw_invalid_user_exception_when_get_user_is_called_without_a_set_user()
    {
        $this->setExpectedException(InvalidFeatureFlagUserException::class);

        $this->split->getUser();
    }

    /**
     * @test
     */
    function it_can_set_a_valid_user()
    {
        $user = new SplitFeatureFlagUser('1234');

        $featureFlag = $this->split->setUser($user);

        $this->assertEquals($user, $featureFlag->getUser(), "User was not set properly.");
    }

    /**
     * @test
     */
    function it_will_throw_invalid_user_exception_when_invalid_user_is_set()
    {
        $this->setExpectedException(InvalidFeatureFlagUserException::class);

        $user = new SplitFeatureFlagUser(null);

        $this->split->setUser($user);
    }

    /**
     * @test
     */
    function it_will_return_all_feature_flag_names()
    {
        $this->assertEquals([
            'my_feature',
            'some_other_feature'
        ], $this->split->all());
    }

    /**
     * @test
     */
    function it_will_return_true_when_a_flag_is_enbaled()
    {
        $this->assertTrue($this->split->enabled('my_feature'));
    }

    /**
     * @test
     */
    function it_will_return_false_when_a_flag_is_disabled()
    {
        $this->assertFalse($this->split->enabled('some_other_feature'));
    }

    /**
     * @test
     */
    function it_will_return_false_if_enabled_called_with_nonexistent_flag()
    {
        $this->assertFalse($this->split->enabled('foobar'));
    }

    /**
     * @test
     */
    function it_returns_false_if_nonexistent_flag_name_is_passed_to_exists()
    {
        $this->assertFalse($this->split->exists('foobar'));
    }

    /**
     * @test
     */
    function it_returns_true_if_nonexistent_flag_name_is_passed_to_exists()
    {
        $this->assertTrue($this->split->exists('my_feature'));
    }

    /**
     * @test
     */
    function it_evaluates_flag_based_on_provided_user()
    {
        $this->assertTrue($this->split->setUser(new SplitFeatureFlagUser('1234'))->enabled('my_feature'));

        $this->assertFalse($this->split->setUser(new SplitFeatureFlagUser('some-user@dealerinspire.com'))->enabled('my_feature'));
    }
}
