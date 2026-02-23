<?php

namespace Carsdotcom\FeatureFlags\Tests\Service\SplitIO;

use Carsdotcom\FeatureFlags\Service\SplitIO\SplitFeatureFlagUser;
use PHPUnit\Framework\TestCase;

class SplitFeatureFlagUserTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_set_the_users_identifier()
    {
        $this->assertEquals('1234', (new SplitFeatureFlagUser('1234'))->getId());
    }
}
