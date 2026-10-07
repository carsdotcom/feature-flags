<?php

namespace Carsdotcom\FeatureFlags\Service\Statsig;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Contracts\GateState;
use Carsdotcom\FeatureFlags\Contracts\GateStateReader;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException;
use Carsdotcom\FeatureFlags\Service\Redis\RedisFeatureFlagCache;
use GuzzleHttp\Client;
use Predis\Client as PredisClient;
use Throwable;

class StatsigFeatureFlag implements FeatureFlag, GateStateReader
{
    /**
     * @var int 5 minutes
     */
    const DEFAULT_TTL = 300;

    /**
     * @var int 1 minute, so a failed gate check is retried soon instead of answering for the full DEFAULT_TTL.
     */
    const UNAVAILABLE_TTL = 60;

    const DEFAULT_GATE_REQUEST_TIMEOUT = 5;

    /**
     * @var string
     */
    const ALL_CONFIG_SPECS_KEY = 'all_config_specs';

    /**
     * @var string
     */
    const ALL_FEATURE_NAMES_KEY = 'all_feature_names';

    /**
     * gateState() results get their own cache keys: enabled() keys hold only booleans, which is all that
     * consumers on older versions of this library can read from the shared cache.
     *
     * @var string
     */
    const GATE_STATE_KEY_PREFIX = 'gate_state';

    /**
     * @var array
     */
    const REQUIRED_SETTINGS = [
        'apiKey',
        'environment',
        'cache',
    ];

    /**
     * @var array
     */
    const REQUIRED_CACHE_SETTINGS = [
        'scheme',
        'host',
        'port',
        'password',
        'prefix',
    ];

    /**
     * @var FeatureFlagUser
     */
    private $user;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var RedisFeatureFlagCache
     */
    private $redisCache;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var StatsigFeatureFlag
     */
    protected static $instance;

    private function __construct()
    {
        self::$instance = $this;
    }

    /**
     * @return StatsigFeatureFlag
     */
    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array $settings
     * @return void
     * @throws InvalidFeatureFlagSettingsException
     */
    public function initializeSettings(array $settings = [])
    {
        if (!is_null($this->redisCache)) {
            return;
        }

        $this->validateSettings($settings);

        $this->settings = $settings;

        $redisConfig = [
            'scheme' => $settings['cache']['scheme'],
            'host' => $settings['cache']['host'],
            'port' => $settings['cache']['port'],
            'password' => $settings['cache']['password'],
        ];

        if ($settings['cache']['scheme'] === 'tls') {
            $redisConfig['ssl'] = [
                'crypto_type' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            ];
        }

        $redisClient = new PredisClient($redisConfig, [
            'prefix' => $settings['cache']['prefix'],
        ]);

        $this->setRedisCache(new RedisFeatureFlagCache($redisClient));

        $this->setHttpClient(new Client([
            'base_uri' => 'https://api.statsig.com/v1/',
            'headers' => [
                'statsig-api-key' => $settings['apiKey'],
            ]
        ]));
    }

    /**
     * @param array $settings
     * @return void
     * @throws InvalidFeatureFlagSettingsException
     */
    public function validateSettings(array $settings = [])
    {
        foreach (self::REQUIRED_SETTINGS as $setting) {
            if (!array_key_exists($setting, $settings)) {
                throw new InvalidFeatureFlagSettingsException("Missing required setting: $setting");
            }
        }
        
        foreach (self::REQUIRED_CACHE_SETTINGS as $setting) {
            if (!array_key_exists($setting, $settings['cache'])) {
                throw new InvalidFeatureFlagSettingsException("Missing required cache setting: $setting");
            }
        }

        if (
            array_key_exists('gateTimeout', $settings)
            && !$this->isValidGateTimeout($settings['gateTimeout'])
        ) {
            throw new InvalidFeatureFlagSettingsException('gateTimeout must be a positive number of seconds');
        }
    }

    private function isValidGateTimeout($timeout): bool
    {
        if (!is_numeric($timeout)) {
            return false;
        }

        $seconds = (float) $timeout;
        return $seconds > 0 && is_finite($seconds);
    }

    /**
     * @param RedisFeatureFlagCache $redisCache
     * @return $this
     */
    public function setRedisCache(RedisFeatureFlagCache $redisCache): self
    {
        $this->redisCache = $redisCache;

        return $this;
    }

    /**
     * @param Client $httpClient
     * @return $this
     */
    public function setHttpClient(Client $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * @return void
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function validateInitialization()
    {
        if (!isset($this->redisCache, $this->httpClient)) {
            throw new InvalidFeatureFlagSettingsException('Statsig not initialized, call initializeSettings()');
        }

        if (is_null($this->user)) {
            throw new InvalidFeatureFlagUserException('No user is set, call setUser()');
        }
    }

    /**
     * @param FeatureFlagUser $user
     * @return self
     * @throws InvalidFeatureFlagUserException
     */
    public function setUser(FeatureFlagUser $user): self
    {
        if (is_null($user->getId())) {
            throw new InvalidFeatureFlagUserException('No user ID provided.');
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return FeatureFlagUser
     * @throws InvalidFeatureFlagUserException
     */
    public function getUser()
    {
        if (is_null($this->user)) {
            throw new InvalidFeatureFlagUserException('No user is set. Call setUser() first.');
        }

        return $this->user;
    }

    /**
     * @return array
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function all(): array
    {
        $this->validateInitialization();

        try {
            $allNames = $this->redisCache->get(self::ALL_FEATURE_NAMES_KEY);
            if (!empty($allNames)) {
                return $allNames;
            }

            $allConfigs = $this->getAllStatsigConfigs();
            $allNames = array_column($allConfigs['feature_gates'], 'name');
            $this->redisCache->set(self::ALL_FEATURE_NAMES_KEY, $allNames, self::DEFAULT_TTL);

            return $allNames;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param string $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function enabled(string $featureFlagIdentifier): bool
    {
        $featureFlagIdentifier = strtolower($featureFlagIdentifier);
        $this->validateInitialization();

        $cacheKey = $this->getCacheKey($featureFlagIdentifier, $this->getUser()->getId());
        $cachedValue = $this->redisCache->get($cacheKey);
        if ($cachedValue !== null) {
            return $cachedValue;
        }

        $state = $this->evaluateGate($featureFlagIdentifier);
        $isEnabled = $state === GateState::ON;

        $this->redisCache->set($cacheKey, $isEnabled, $this->ttlFor($state));

        return $isEnabled;
    }

    /**
     * Cache failures are skipped rather than reported, so the result always comes from Statsig or the cache.
     *
     * @param string $featureFlagIdentifier
     * @return string
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function gateState(string $featureFlagIdentifier): string
    {
        $featureFlagIdentifier = strtolower($featureFlagIdentifier);
        $this->validateInitialization();

        $cacheKey = $this->getGateStateCacheKey($featureFlagIdentifier, $this->getUser()->getId());
        try {
            $cachedState = $this->redisCache->get($cacheKey);
        } catch (Throwable $e) {
            $cachedState = null;
        }
        if (in_array($cachedState, [GateState::ON, GateState::OFF, GateState::UNAVAILABLE], true)) {
            return $cachedState;
        }

        $state = $this->evaluateGate($featureFlagIdentifier);

        try {
            $this->redisCache->set($cacheKey, $state, $this->ttlFor($state));
        } catch (Throwable $e) {
            // The result is still correct; the next call asks Statsig again.
        }

        return $state;
    }

    /**
     * @param string $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function isFeatureGateEnabled(string $featureFlagIdentifier): bool
    {
        $featureFlagIdentifier = strtolower($featureFlagIdentifier);
        $this->validateInitialization();

        return $this->evaluateGate($featureFlagIdentifier) === GateState::ON;
    }

    /**
     * Asks Statsig for one gate. Any failure (timeout, network error, non-200 response, or a body without a
     * boolean "value") is GateState::UNAVAILABLE.
     *
     * @param string $featureFlagIdentifier Already lowercased.
     * @return string
     */
    private function evaluateGate(string $featureFlagIdentifier): string
    {
        try {
            $response = $this->httpClient->post('check_gate', [
                'timeout' => $this->settings['gateTimeout'] ?? self::DEFAULT_GATE_REQUEST_TIMEOUT,
                'json' => [
                    'user' => [
                        'userID' => $this->getUser()->getId(),
                        'statsigEnvironment' => [
                            'tier' => $this->settings['environment'],
                        ],
                    ],
                    'gateName' => $featureFlagIdentifier,
                ]
            ]);
            if ($response->getStatusCode() !== 200) {
                return GateState::UNAVAILABLE;
            }

            $data = json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            return GateState::UNAVAILABLE;
        }

        // Statsig answers a non-existent gate in the same format, so it reads as off:
        // {"name":"my-fake-gate","value":false,"rule_id":null,"group_name":null}
        if (!is_array($data) || !array_key_exists('value', $data) || !is_bool($data['value'])) {
            return GateState::UNAVAILABLE;
        }

        return $data['value'] ? GateState::ON : GateState::OFF;
    }

    /**
     * @param string $state
     * @return int
     */
    private function ttlFor(string $state): int
    {
        return $state === GateState::UNAVAILABLE ? self::UNAVAILABLE_TTL : self::DEFAULT_TTL;
    }

    /**
     * @param string $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function exists(string $featureFlagIdentifier): bool
    {
        $featureFlagIdentifier = strtolower($featureFlagIdentifier);
        return in_array($featureFlagIdentifier, $this->all(), true);
    }

    /**
     * @deprecated Use getDynamicConfig instead.
     * @param string $featureFlagIdentifier
     * @return array
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function config(string $featureFlagIdentifier): array
    {
        $featureFlagIdentifier = strtolower($featureFlagIdentifier);
        $allConfigs = $this->getAllStatsigConfigs();

        $index = array_search(
            $featureFlagIdentifier,
            array_column($allConfigs['feature_gates'], 'name'),
            true
        );

        return $index !== false ? $allConfigs['feature_gates'][$index] : [];
    }

    /**
     * @param string $identifier
     * @return array
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function getDynamicConfig(string $identifier): array
    {
        $identifier = strtolower($identifier);
        $this->validateInitialization();

        $cacheKey = $this->getCacheKey($identifier, $this->getUser()->getId());
        $cachedValue = $this->redisCache->get($cacheKey);
        if ($cachedValue !== null) {
            return $cachedValue;
        }

        try {
            $response = $this->httpClient->post('get_config', [
                'json' => [
                    'user' => [
                        'userID' => $this->getUser()->getId(),
                        'statsigEnvironment' => [
                            'tier' => $this->settings['environment'],
                        ],
                    ],
                    'configName' => $identifier,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $result = $data['value'] ?? [];

            $this->redisCache->set($cacheKey, $result, self::DEFAULT_TTL);

            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param string $gateName
     * @param string $userId
     * @return string
     */
    public function getCacheKey(string $gateName, string $userId): string
    {
        return implode('::', [$gateName, $userId]);
    }

    /**
     * @param string $gateName
     * @param string $userId
     * @return string
     */
    public function getGateStateCacheKey(string $gateName, string $userId): string
    {
        return implode('::', [self::GATE_STATE_KEY_PREFIX, $gateName, $userId]);
    }

    /**
     * @return array
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function getAllStatsigConfigs(): array
    {
        $this->validateInitialization();

        $allConfigs = $this->redisCache->get(self::ALL_CONFIG_SPECS_KEY);
        if (!empty($allConfigs)) {
            return $allConfigs;
        }

        $response = $this->httpClient->get('download_config_specs');
        $allConfigs = json_decode($response->getBody()->getContents(), true);
        $this->redisCache->set(self::ALL_CONFIG_SPECS_KEY, $allConfigs, self::DEFAULT_TTL);

        return $allConfigs;
    }
}
