<?php

namespace Carsdotcom\FeatureFlags\Service\Redis;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlagCache;
use Predis\Client;

class RedisFeatureFlagCache implements FeatureFlagCache
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $redisClient)
    {
        $this->client = $redisClient;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        $value = $this->client->get($key);
        if ($value === null) {
            return null;
        }

        // The set() method json_encodes all values
        $decoded = json_decode($value, true);

        // Handle non-encoded values too, to be safe.
        return $decoded !== null ? $decoded : $value;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return mixed
     */
    public function set(string $key, $value, int $ttl = null)
    {
        $value = json_encode($value);

        if ($ttl !== null) {
            return $this->client->setex($key, $ttl, $value);
        }

        return $this->client->set($key, $value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function exists(string $key): bool
    {
        return (bool) $this->client->exists($key);
    }
}