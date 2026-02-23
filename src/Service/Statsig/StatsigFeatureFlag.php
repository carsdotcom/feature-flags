<?php

namespace Carsdotcom\FeatureFlags\Service\Statsig;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException;
use Carsdotcom\FeatureFlags\Service\Redis\RedisFeatureFlagCache;
use GuzzleHttp\Client;
use Throwable;
use function SplitIO\Component\Utils\environment;

class StatsigFeatureFlag implements FeatureFlag
{
    /**
     * @var int 5 minutes
     */
    const DEFAULT_TTL = 300;

    /**
     * @var string
     */
    const ALL_CONFIG_SPECS_KEY = 'all_config_specs';

    /**
     * @var string
     */
    const ALL_FEATURE_NAMES_KEY = 'all_feature_names';

    /**
     * @var array
     */
    const REQUIRED_SETTINGS = [
        'apiKey',
        'environment',
        'redisHost',
        'redisPort'
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

        $redisClient = new \Predis\Client([
            'scheme' => 'tcp',
            'host' => $settings['redisHost'],
            'port' => $settings['redisPort']
        ], [
            'prefix' => "statsig::${settings['environment']}::"
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
        $this->validateInitialization();

        $cacheKey = $this->getCacheKey($featureFlagIdentifier, $this->getUser()->getId());
        $cachedValue = $this->redisCache->get($cacheKey);
        if ($cachedValue !== null) {
            return $cachedValue;
        }

        $isEnabled = $this->isFeatureGateEnabled($featureFlagIdentifier);

        $this->redisCache->set($cacheKey, $isEnabled, self::DEFAULT_TTL);

        return $isEnabled;
    }

    /**
     * @param string $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function isFeatureGateEnabled(string $featureFlagIdentifier): bool
    {
        $this->validateInitialization();

        try {
            $response = $this->httpClient->post('check_gate', [
                'json' => [
                    'user' => [
                        'userID' => $this->getUser()->getId(),
                        'statsigEnvironment' => $this->settings['environment'],
                    ],
                    'gateName' => $featureFlagIdentifier,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Statsig returns false for non-existent gates in the same format:
            // {"name":"my-fake-gate","value":false,"rule_id":null,"group_name":null}
            return $data['value'] ?? false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param string $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function exists(string $featureFlagIdentifier): bool
    {
        return in_array($featureFlagIdentifier, $this->all(), true);
    }

    /**
     * @param string $featureFlagIdentifier
     * @return array
     * @throws InvalidFeatureFlagSettingsException
     * @throws InvalidFeatureFlagUserException
     */
    public function config(string $featureFlagIdentifier): array
    {
        $allConfigs = $this->getAllStatsigConfigs();

        $index = array_search(
            $featureFlagIdentifier,
            array_column($allConfigs['feature_gates'], 'name'),
            true
        );

        return $index !== false ? $allConfigs['feature_gates'][$index] : [];
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