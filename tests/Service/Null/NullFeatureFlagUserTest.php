<?php

namespace Carsdotcom\FeatureFlags\Tests;

use Carsdotcom\FeatureFlags\Service\Null\NullFeatureFlagUser;
use PHPUnit\Framework\TestCase;

class NullFeatureFlagUserTest extends TestCase
{

    /**
     * @test
     */
    function it_can_set_the_users_identifier()
    {
        $this->assertEquals('1234', (new NullFeatureFlagUser('1234'))->getId());

        $this->assertEquals(null, (new NullFeatureFlagUser(null))->getId());

        $this->assertEquals("foobar", (new NullFeatureFlagUser("foobar"))->getId());

        $this->assertEquals(1234, (new NullFeatureFlagUser(1234))->getId());
    }
}
