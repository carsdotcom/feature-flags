<?php

namespace Carsdotcom\FeatureFlags\Tests\Service\Statsig;

use Carsdotcom\FeatureFlags\Service\Statsig\StatsigFeatureFlagUser;
use PHPUnit\Framework\TestCase;

class StatsigFeatureFlagUserTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_set_the_users_identifier()
    {
        $this->assertEquals('1234', (new StatsigFeatureFlagUser('1234'))->getId());
    }
}
