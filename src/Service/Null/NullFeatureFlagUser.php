<?php

namespace Carsdotcom\FeatureFlags\Service\Null;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;

class NullFeatureFlagUser implements FeatureFlagUser
{
    /**
     * @var string
     */
    protected $userIdentifier;

    /**
     * @param string $userIdentifier
     */
    public function __construct($userIdentifier)
    {
        $this->userIdentifier = $userIdentifier;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->userIdentifier;
    }
}