<?php

namespace Carsdotcom\FeatureFlags\Tests\Service\Redis;

use Carsdotcom\FeatureFlags\Service\Redis\RedisFeatureFlagCache;
use PHPUnit\Framework\TestCase;

class RedisFeatureFlagCacheTest extends TestCase
{
    private $cache;
    private $mockClient;

    public function setUp()
    {
        $this->mockClient = $this->getMockBuilder('Predis\Client')
            ->disableOriginalConstructor()
            ->setMethods(['get', 'set', 'setex', 'exists'])
            ->getMock();

        $this->cache = new RedisFeatureFlagCache($this->mockClient);
    }

    /**
     * @test
     */
    public function get_returns_null_when_redis_returns_null()
    {
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with('mykey')
            ->willReturn(null);

        $this->assertNull($this->cache->get('mykey'));
    }

    /**
     * @test
     */
    public function get_returns_decoded_string_value()
    {
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with('mykey')
            ->willReturn(json_encode('hello'));

        $this->assertEquals('hello', $this->cache->get('mykey'));
    }

    /**
     * @test
     */
    public function get_returns_decoded_array_value()
    {
        $expected = ['foo' => 'bar', 'baz' => 1];

        $this->mockClient->expects($this->once())
            ->method('get')
            ->with('mykey')
            ->willReturn(json_encode($expected));

        $this->assertEquals($expected, $this->cache->get('mykey'));
    }

    /**
     * @test
     */
    public function get_returns_decoded_integer_value()
    {
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with('mykey')
            ->willReturn(json_encode(42));

        $this->assertEquals(42, $this->cache->get('mykey'));
    }

    /**
     * @test
     */
    public function get_returns_raw_value_when_stored_value_is_not_json()
    {
        $raw = 'not-valid-json';

        $this->mockClient->expects($this->once())
            ->method('get')
            ->with('mykey')
            ->willReturn($raw);

        $this->assertEquals($raw, $this->cache->get('mykey'));
    }

    /**
     * @test
     */
    public function set_json_encodes_value_and_calls_set_when_no_ttl()
    {
        $this->mockClient->expects($this->once())
            ->method('set')
            ->with('mykey', json_encode('myvalue'));

        $this->cache->set('mykey', 'myvalue');
    }

    /**
     * @test
     */
    public function set_json_encodes_array_value_and_calls_set_when_no_ttl()
    {
        $value = ['foo' => 'bar'];

        $this->mockClient->expects($this->once())
            ->method('set')
            ->with('mykey', json_encode($value));

        $this->cache->set('mykey', $value);
    }

    /**
     * @test
     */
    public function set_calls_setex_with_ttl_when_ttl_is_provided()
    {
        $this->mockClient->expects($this->once())
            ->method('setex')
            ->with('mykey', 300, json_encode('myvalue'));

        $this->mockClient->expects($this->never())
            ->method('set');

        $this->cache->set('mykey', 'myvalue', 300);
    }

    /**
     * @test
     */
    public function exists_returns_true_when_key_exists()
    {
        $this->mockClient->expects($this->once())
            ->method('exists')
            ->with('mykey')
            ->willReturn(1);

        $this->assertTrue($this->cache->exists('mykey'));
    }

    /**
     * @test
     */
    public function exists_returns_false_when_key_does_not_exist()
    {
        $this->mockClient->expects($this->once())
            ->method('exists')
            ->with('mykey')
            ->willReturn(0);

        $this->assertFalse($this->cache->exists('mykey'));
    }
}
