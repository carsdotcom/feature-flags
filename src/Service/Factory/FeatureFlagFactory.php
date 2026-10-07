<?php

namespace Carsdotcom\FeatureFlags\Service\Factory;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Contracts\GateStateReader;
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
        return self::createStatsig($config, $userIdentifier);
    }

    /**
     * Same service as create(), typed for callers that need GateStateReader::gateState().
     *
     * @param array $config
     * @param string $userIdentifier
     * @return GateStateReader
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public static function createGateStateReader(array $config, string $userIdentifier): GateStateReader
    {
        return self::createStatsig($config, $userIdentifier);
    }

    /**
     * @param string $userIdentifier
     * @return FeatureFlagUser
     */
    public static function createUser(string $userIdentifier): FeatureFlagUser
    {
        return new StatsigFeatureFlagUser($userIdentifier);
    }

    /**
     * @param array $config
     * @param string $userIdentifier
     * @return StatsigFeatureFlag
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    private static function createStatsig(array $config, string $userIdentifier): StatsigFeatureFlag
    {
        $featureFlagService = StatsigFeatureFlag::getInstance();
        $featureFlagService
            ->setUser(self::createUser($userIdentifier))
            ->initializeSettings($config);

        return $featureFlagService;
    }
}