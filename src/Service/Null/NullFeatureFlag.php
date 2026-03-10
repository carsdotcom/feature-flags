<?php

namespace Carsdotcom\FeatureFlags\Service\Null;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;

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
}