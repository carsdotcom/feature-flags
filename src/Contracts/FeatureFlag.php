<?php

namespace Carsdotcom\FeatureFlags\Contracts;

use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException;

interface FeatureFlag
{
    /**
     * Will set the user the requested feature flags are evaluated for.
     *
     * @param FeatureFlagUser $user
     * @return self
     * @throws InvalidFeatureFlagUserException
     */
    public function setUser(FeatureFlagUser $user);

    /**
     * @return FeatureFlagUser
     * @throws InvalidFeatureFlagUserException
     */
    public function getUser();

    /**
     * Return a list of all the available feature flag names
     *
     * @return array
     */
    public function all();

    /**
     * Will return true/false if the feature flag is enabled. if the feature flag doesn't exist this function
     * should throw InvalidFeatureFlagException
     *
     * @param String $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagException
     * @throws InvalidFeatureFlagUserException
     */
    public function enabled($featureFlagIdentifier);

    /**
     * Will return true/false if the feature flag exists
     *
     * @param String $featureFlagIdentifier
     * @return bool
     */
    public function exists($featureFlagIdentifier);
}