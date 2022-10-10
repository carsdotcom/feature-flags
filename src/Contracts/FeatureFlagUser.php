<?php

namespace Carsdotcom\FeatureFlags\Contracts;

interface FeatureFlagUser
{
    /**
     * @param string $userIdentifier
     */
    public function __construct($userIdentifier);

    /**
     * @return string
     */
    public function getId();
}