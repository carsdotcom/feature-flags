<?php

namespace Carsdotcom\FeatureFlags\Contracts;

use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException;

interface GateStateReader
{
    /**
     * Returns GateState::ON, GateState::OFF or GateState::UNAVAILABLE. Unlike FeatureFlag::enabled(),
     * a failed evaluation is reported as UNAVAILABLE instead of OFF.
     *
     * @param string $featureFlagIdentifier
     * @return string
     * @throws InvalidFeatureFlagUserException
     */
    public function gateState(string $featureFlagIdentifier): string;
}
