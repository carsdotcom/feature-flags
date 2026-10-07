<?php

namespace Carsdotcom\FeatureFlags\Service\Null;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Contracts\GateState;
use Carsdotcom\FeatureFlags\Contracts\GateStateReader;

class NullFeatureFlag implements FeatureFlag, GateStateReader
{
    /**
     * @var FeatureFlagUser
     */
    protected $user;

    /**
     * @inheritDoc
     */
    public function setUser(FeatureFlagUser $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function enabled(string $featureFlagIdentifier): bool
    {
        return false;
    }

    /**
     * Without a provider no gate can be evaluated, so callers that act on GateState::OFF never act on this one.
     *
     * @inheritDoc
     */
    public function gateState(string $featureFlagIdentifier): string
    {
        return GateState::UNAVAILABLE;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $featureFlagIdentifier): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function config(string $featureFlagIdentifier): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getDynamicConfig(string $identifier): array
    {
        return [];
    }
}