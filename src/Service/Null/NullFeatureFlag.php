<?php

namespace Carsdotcom\FeatureFlags\Service\Null;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Contracts\FlagState;

class NullFeatureFlag implements FeatureFlag
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
     * Without a provider no flag can be evaluated, so callers that act on FlagState::OFF never act on this one.
     *
     * @inheritDoc
     */
    public function getFlagState(string $featureFlagIdentifier): string
    {
        return FlagState::UNAVAILABLE;
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