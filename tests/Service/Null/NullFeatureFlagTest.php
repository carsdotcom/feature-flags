<?php

namespace Carsdotcom\FeatureFlags\Tests;

use Carsdotcom\FeatureFlags\Service\Null\NullFeatureFlag;
use Carsdotcom\FeatureFlags\Service\Null\NullFeatureFlagUser;
use PHPUnit\Framework\TestCase;

class NullFeatureFlagTest extends TestCase
{

    function setUp()
    {
        $this->featureFlags = new NullFeatureFlag();
        $this->featureFlags->setUser(new NullFeatureFlagUser('1234'));
    }

    /**
     * @test
     */
    function it_will_accept_any_user_id()
    {
        $this->featureFlags->setUser(new NullFeatureFlagUser(null));
        $this->assertEquals(null, $this->featureFlags->getUser()->getId(), 'Null value not accept by NullFeatureFlagUser');

        $this->featureFlags->setUser(new NullFeatureFlagUser('1234'));
        $this->assertEquals('1234', $this->featureFlags->getUser()->getId(), 'char string not accept by NullFeatureFlagUser');

        $this->featureFlags->setUser(new NullFeatureFlagUser(1234));
        $this->assertEquals(1234, $this->featureFlags->getUser()->getId(), 'int value not accept by NullFeatureFlagUser');

        $this->featureFlags->setUser(new NullFeatureFlagUser("foobar"));
        $this->assertEquals("foobar", $this->featureFlags->getUser()->getId(), 'string value not accept by NullFeatureFlagUser');
    }

    /**
     * @test
     */
    function it_will_return_an_empty_array_when_all_is_called()
    {
        $this->assertEquals([], $this->featureFlags->all());
    }

    /**
     * @test
     */
    function it_will_always_return_false_when_enabled_called()
    {
        $this->assertFalse($this->featureFlags->enabled('foobar'));
    }

    /**
     * @test
     */
    function it_will_always_return_false_when_exist_called()
    {
        $this->assertFalse($this->featureFlags->exists('foobar'));
    }
}
