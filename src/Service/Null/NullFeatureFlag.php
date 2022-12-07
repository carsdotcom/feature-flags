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
    public function all()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function enabled($featureFlagIdentifier)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function exists($featureFlagIdentifier)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function config($featureFlagIdentifier)
    {
        return [];
    }
}