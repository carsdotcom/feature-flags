<?php

namespace Carsdotcom\FeatureFlags\Tests\Service\Statsig;

use Carsdotcom\FeatureFlags\Service\Redis\RedisFeatureFlagCache;
use Carsdotcom\FeatureFlags\Service\Statsig\StatsigFeatureFlag;
use Carsdotcom\FeatureFlags\Service\Statsig\StatsigFeatureFlagUser;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Spy stub for RedisFeatureFlagCache.
 *
 * PHPUnit 4.8's mock generator omits PHP 7 return-type declarations, which
 * causes a fatal error when the generated mock extends a class whose interface
 * declares `exists(): bool`. A hand-written double sidesteps this limitation.
 */
class StatsigTestCache extends RedisFeatureFlagCache
{
    /** @var array Map of key => value returned by get() */
    public $responses = [];

    /** @var array Log of every set() invocation */
    public $setCalls = [];

    /** @var bool When true, get() throws to simulate a cache failure */
    public $shouldThrow = false;

    public function __construct() {}

    public function get(string $key)
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException('Forced cache exception');
        }
        return array_key_exists($key, $this->responses) ? $this->responses[$key] : null;
    }

    public function set(string $key, $value, int $ttl = null)
    {
        $this->setCalls[] = ['key' => $key, 'value' => $value, 'ttl' => $ttl];
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->responses);
    }
}

/**
 * Spy stub for GuzzleHttp\Client.
 */
class StatsigTestHttpClient extends \GuzzleHttp\Client
{
    /** @var Response[] Queue of responses consumed by get() and post() in order */
    public $responses = [];

    /** @var array Log of every get() invocation */
    public $getCalls = [];

    /** @var array Log of every post() invocation */
    public $postCalls = [];

    /** @var bool When true, get() throws to simulate a network failure */
    public $shouldThrow = false;

    public function __construct() {}

    public function get($uri, array $options = [])
    {
        $this->getCalls[] = ['uri' => $uri, 'options' => $options];
        if ($this->shouldThrow) {
            throw new \RuntimeException('Forced HTTP exception');
        }
        return array_shift($this->responses) ?: new Response(200, [], '{}');
    }

    public function post($uri, array $options = [])
    {
        $this->postCalls[] = ['uri' => $uri, 'options' => $options];
        return array_shift($this->responses) ?: new Response(200, [], '{}');
    }
}

class StatsigFeatureFlagTest extends TestCase
{
    /** @var StatsigFeatureFlag */
    private $statsig;

    /** @var StatsigTestCache */
    private $cacheStub;

    /** @var StatsigTestHttpClient */
    private $httpStub;

    public function setUp()
    {
        $this->resetSingleton();

        $this->statsig = StatsigFeatureFlag::getInstance();
        $this->statsig->initializeSettings([
            'apiKey'      => 'test-api-key',
            'environment' => 'staging',
            'redisHost'   => 'localhost',
            'redisPort'   => 6379,
        ]);

        $this->cacheStub = new StatsigTestCache();
        $this->httpStub  = new StatsigTestHttpClient();

        $this->statsig->setRedisCache($this->cacheStub);
        $this->statsig->setHttpClient($this->httpStub);
        $this->statsig->setUser(new StatsigFeatureFlagUser('user123'));
    }

    private function resetSingleton()
    {
        $reflection = new \ReflectionClass(StatsigFeatureFlag::class);
        $property   = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    // -------------------------------------------------------------------------
    // getInstance / singleton
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function getInstance_always_returns_same_instance()
    {
        $a = StatsigFeatureFlag::getInstance();
        $b = StatsigFeatureFlag::getInstance();
        $this->assertSame($a, $b);
    }

    // -------------------------------------------------------------------------
    // validateSettings
    // -------------------------------------------------------------------------

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException
     */
    public function validateSettings_throws_when_apiKey_is_missing()
    {
        $this->statsig->validateSettings([
            'environment' => 'staging',
            'redisHost'   => 'localhost',
            'redisPort'   => 6379,
        ]);
    }

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException
     */
    public function validateSettings_throws_when_environment_is_missing()
    {
        $this->statsig->validateSettings([
            'apiKey'    => 'test-key',
            'redisHost' => 'localhost',
            'redisPort' => 6379,
        ]);
    }

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException
     */
    public function validateSettings_throws_when_redisHost_is_missing()
    {
        $this->statsig->validateSettings([
            'apiKey'      => 'test-key',
            'environment' => 'staging',
            'redisPort'   => 6379,
        ]);
    }

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException
     */
    public function validateSettings_throws_when_redisPort_is_missing()
    {
        $this->statsig->validateSettings([
            'apiKey'      => 'test-key',
            'environment' => 'staging',
            'redisHost'   => 'localhost',
        ]);
    }

    /**
     * @test
     */
    public function validateSettings_does_not_throw_with_all_required_settings()
    {
        $this->statsig->validateSettings([
            'apiKey'      => 'test-key',
            'environment' => 'staging',
            'redisHost'   => 'localhost',
            'redisPort'   => 6379,
        ]);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // initializeSettings idempotency
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function initializeSettings_is_idempotent_and_ignores_subsequent_calls()
    {
        // Already initialized in setUp. A second call with empty (invalid) settings
        // must not throw because it returns early once $redisCache is set.
        $this->statsig->initializeSettings([]);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // setUser / getUser
    // -------------------------------------------------------------------------

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException
     */
    public function setUser_throws_when_user_has_null_id()
    {
        $this->statsig->setUser(new StatsigFeatureFlagUser(null));
    }

    /**
     * @test
     */
    public function setUser_returns_self_for_fluent_chaining()
    {
        $result = $this->statsig->setUser(new StatsigFeatureFlagUser('abc'));
        $this->assertSame($this->statsig, $result);
    }

    /**
     * @test
     */
    public function getUser_returns_the_user_that_was_set()
    {
        $user = new StatsigFeatureFlagUser('abc123');
        $this->statsig->setUser($user);
        $this->assertSame($user, $this->statsig->getUser());
    }

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException
     */
    public function getUser_throws_when_no_user_has_been_set()
    {
        $this->resetSingleton();
        StatsigFeatureFlag::getInstance()->getUser();
    }

    // -------------------------------------------------------------------------
    // validateInitialization
    // -------------------------------------------------------------------------

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException
     */
    public function validateInitialization_throws_when_not_initialized()
    {
        $this->resetSingleton();
        StatsigFeatureFlag::getInstance()->validateInitialization();
    }

    /**
     * @test
     * @expectedException \Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException
     */
    public function validateInitialization_throws_when_no_user_is_set()
    {
        $this->resetSingleton();
        $statsig = StatsigFeatureFlag::getInstance();
        $statsig->initializeSettings([
            'apiKey'      => 'test-key',
            'environment' => 'staging',
            'redisHost'   => 'localhost',
            'redisPort'   => 6379,
        ]);
        $statsig->validateInitialization();
    }

    // -------------------------------------------------------------------------
    // getCacheKey
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function getCacheKey_joins_gate_name_and_user_id_with_double_colon()
    {
        $this->assertEquals('my-gate::user123', $this->statsig->getCacheKey('my-gate', 'user123'));
    }

    // -------------------------------------------------------------------------
    // getAllStatsigConfigs
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function getAllStatsigConfigs_returns_cached_configs_when_available()
    {
        $configs = ['feature_gates' => [['name' => 'gate-one']]];
        $this->cacheStub->responses[StatsigFeatureFlag::ALL_CONFIG_SPECS_KEY] = $configs;

        $this->assertEquals($configs, $this->statsig->getAllStatsigConfigs());
        $this->assertEmpty($this->httpStub->getCalls);
    }

    /**
     * @test
     */
    public function getAllStatsigConfigs_fetches_from_api_and_caches_on_cache_miss()
    {
        $configs = ['feature_gates' => [['name' => 'gate-one']]];
        $this->httpStub->responses[] = new Response(200, [], json_encode($configs));

        $result = $this->statsig->getAllStatsigConfigs();

        $this->assertEquals($configs, $result);
        $this->assertCount(1, $this->httpStub->getCalls);
        $this->assertEquals('download_config_specs', $this->httpStub->getCalls[0]['uri']);
        $this->assertCount(1, $this->cacheStub->setCalls);
        $this->assertEquals(StatsigFeatureFlag::ALL_CONFIG_SPECS_KEY, $this->cacheStub->setCalls[0]['key']);
        $this->assertEquals($configs, $this->cacheStub->setCalls[0]['value']);
        $this->assertEquals(StatsigFeatureFlag::DEFAULT_TTL, $this->cacheStub->setCalls[0]['ttl']);
    }

    // -------------------------------------------------------------------------
    // all
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function all_returns_cached_names_without_hitting_the_api()
    {
        $cachedNames = ['flag-one', 'flag-two'];
        $this->cacheStub->responses[StatsigFeatureFlag::ALL_FEATURE_NAMES_KEY] = $cachedNames;

        $this->assertEquals($cachedNames, $this->statsig->all());
        $this->assertEmpty($this->httpStub->getCalls);
    }

    /**
     * @test
     */
    public function all_fetches_from_api_extracts_names_and_caches_on_cache_miss()
    {
        $configsPayload = ['feature_gates' => [['name' => 'gate-one'], ['name' => 'gate-two']]];
        $this->httpStub->responses[] = new Response(200, [], json_encode($configsPayload));

        $result = $this->statsig->all();

        $this->assertEquals(['gate-one', 'gate-two'], $result);
        $this->assertCount(1, $this->httpStub->getCalls);
        // Two set() calls: ALL_CONFIG_SPECS_KEY then ALL_FEATURE_NAMES_KEY
        $this->assertCount(2, $this->cacheStub->setCalls);
        $setKeys = array_column($this->cacheStub->setCalls, 'key');
        $this->assertContains(StatsigFeatureFlag::ALL_CONFIG_SPECS_KEY, $setKeys);
        $this->assertContains(StatsigFeatureFlag::ALL_FEATURE_NAMES_KEY, $setKeys);
    }

    /**
     * @test
     */
    public function all_returns_empty_array_when_an_exception_occurs()
    {
        $this->httpStub->shouldThrow = true;

        $this->assertEquals([], $this->statsig->all());
    }

    // -------------------------------------------------------------------------
    // isFeatureGateEnabled
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function isFeatureGateEnabled_sends_correct_payload_and_returns_true()
    {
        $this->httpStub->responses[] = new Response(200, [], json_encode(['value' => true]));

        $result = $this->statsig->isFeatureGateEnabled('my-gate');

        $this->assertTrue($result);
        $this->assertCount(1, $this->httpStub->postCalls);
        $call = $this->httpStub->postCalls[0];
        $this->assertEquals('check_gate', $call['uri']);
        $this->assertEquals('my-gate', $call['options']['json']['gateName']);
        $this->assertEquals('user123', $call['options']['json']['user']['userID']);
        $this->assertEquals('staging', $call['options']['json']['user']['statsigEnvironment']);
    }

    /**
     * @test
     */
    public function isFeatureGateEnabled_returns_false_when_api_returns_value_false()
    {
        $this->httpStub->responses[] = new Response(200, [], json_encode(['value' => false]));

        $this->assertFalse($this->statsig->isFeatureGateEnabled('my-gate'));
    }

    /**
     * @test
     */
    public function isFeatureGateEnabled_returns_false_when_api_response_has_no_value_key()
    {
        $this->httpStub->responses[] = new Response(200, [], json_encode([]));

        $this->assertFalse($this->statsig->isFeatureGateEnabled('my-gate'));
    }

    // -------------------------------------------------------------------------
    // enabled
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function enabled_returns_cached_value_without_hitting_the_api()
    {
        $cacheKey = $this->statsig->getCacheKey('my-flag', 'user123');
        $this->cacheStub->responses[$cacheKey] = true;

        $this->assertTrue($this->statsig->enabled('my-flag'));
        $this->assertEmpty($this->httpStub->postCalls);
    }

    /**
     * @test
     */
    public function enabled_calls_api_on_cache_miss_and_caches_the_result()
    {
        $cacheKey = $this->statsig->getCacheKey('my-flag', 'user123');
        $this->httpStub->responses[] = new Response(200, [], json_encode(['value' => true]));

        $this->assertTrue($this->statsig->enabled('my-flag'));
        $this->assertCount(1, $this->httpStub->postCalls);
        $this->assertCount(1, $this->cacheStub->setCalls);
        $this->assertEquals($cacheKey, $this->cacheStub->setCalls[0]['key']);
        $this->assertEquals(true, $this->cacheStub->setCalls[0]['value']);
        $this->assertEquals(StatsigFeatureFlag::DEFAULT_TTL, $this->cacheStub->setCalls[0]['ttl']);
    }

    // -------------------------------------------------------------------------
    // exists
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function exists_returns_true_when_flag_is_in_the_all_list()
    {
        $this->cacheStub->responses[StatsigFeatureFlag::ALL_FEATURE_NAMES_KEY] = ['flag-one', 'flag-two', 'flag-three'];

        $this->assertTrue($this->statsig->exists('flag-two'));
    }

    /**
     * @test
     */
    public function exists_returns_false_when_flag_is_not_in_the_all_list()
    {
        $this->cacheStub->responses[StatsigFeatureFlag::ALL_FEATURE_NAMES_KEY] = ['flag-one', 'flag-two'];

        $this->assertFalse($this->statsig->exists('flag-three'));
    }

    // -------------------------------------------------------------------------
    // config
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function config_returns_the_gate_config_for_an_existing_flag()
    {
        $gateConfig     = ['name' => 'my-flag', 'enabled' => true, 'rules' => []];
        $configsPayload = ['feature_gates' => [$gateConfig, ['name' => 'other-flag']]];
        $this->cacheStub->responses[StatsigFeatureFlag::ALL_CONFIG_SPECS_KEY] = $configsPayload;

        $this->assertEquals($gateConfig, $this->statsig->config('my-flag'));
    }

    /**
     * @test
     */
    public function config_returns_empty_array_for_a_non_existent_flag()
    {
        $configsPayload = ['feature_gates' => [['name' => 'existing-flag']]];
        $this->cacheStub->responses[StatsigFeatureFlag::ALL_CONFIG_SPECS_KEY] = $configsPayload;

        $this->assertEquals([], $this->statsig->config('non-existent-flag'));
    }
}
