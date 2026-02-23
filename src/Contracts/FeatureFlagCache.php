<?php

namespace Carsdotcom\FeatureFlags\Contracts;

interface FeatureFlagCache
{
    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key);

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return mixed
     */
    public function set(string $key, $value, int $ttl = null);

    /**
     * @param string $key
     * @return bool
     */
    public function exists(string $key): bool;
}
