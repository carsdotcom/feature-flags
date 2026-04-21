<?php

namespace Carsdotcom\FeatureFlags\Contracts;

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
    public function all(): array;

    /**
     * Will return true/false if the feature flag is enabled. if the feature flag doesn't exist this function
     * should return false
     *
     * @param string $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagUserException
     */
    public function enabled(string $featureFlagIdentifier): bool;

    /**
     * Will return true/false if the feature flag exists
     *
     * @param string $featureFlagIdentifier
     * @return bool
     */
    public function exists(string $featureFlagIdentifier): bool;

    /**
     * @deprecated Use getDynamicConfig instead.
     *
     * Will return the decoded json in an array format. If feature flag doesn't exist will return an empty array
     *
     * @param string $featureFlagIdentifier
     * @return array
     * @throws InvalidFeatureFlagUserException
     */
    public function config(string $featureFlagIdentifier): array;

    /**
     * @param string $identifier
     * @return array
     * @throws InvalidFeatureFlagUserException
     */
    public function getDynamicConfig(string $identifier): array;
}