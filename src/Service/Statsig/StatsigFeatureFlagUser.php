<?php

namespace Carsdotcom\FeatureFlags\Service\Statsig;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;

class StatsigFeatureFlagUser implements FeatureFlagUser
{
    /**
     * @var string
     */
    protected $userIdentifier;

    /**
     * @inheritDoc
     */
    public function __construct($userIdentifier)
    {
        $this->userIdentifier = $userIdentifier;
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->userIdentifier;
    }
}