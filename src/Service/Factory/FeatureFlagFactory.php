<?php

namespace Carsdotcom\FeatureFlags\Service\Factory;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException;
use Carsdotcom\FeatureFlags\Service\Statsig\StatsigFeatureFlag;
use Carsdotcom\FeatureFlags\Service\Statsig\StatsigFeatureFlagUser;

class FeatureFlagFactory
{
    /**
     * @param array $config
     * @param string $userIdentifier
     * @return FeatureFlag
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public static function create(array $config, string $userIdentifier): FeatureFlag
    {
        $featureFlagService = StatsigFeatureFlag::getInstance();
        $featureFlagService
            ->setUser(self::createUser($userIdentifier))
            ->initializeSettings($config);

        return $featureFlagService;
    }

    /**
     * @param string $userIdentifier
     * @return FeatureFlagUser
     */
    public static function createUser(string $userIdentifier): FeatureFlagUser
    {
        return new StatsigFeatureFlagUser($userIdentifier);
    }
}