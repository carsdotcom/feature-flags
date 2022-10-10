<?php

namespace Carsdotcom\FeatureFlags\Service\SplitIO;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;

class SplitFeatureFlagUser implements FeatureFlagUser
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