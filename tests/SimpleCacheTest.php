<?php
/**
 * Created by IntelliJ IDEA.
 * User: swang
 * Date: 2018-03-09
 * Time: 8:22 PM
 */

trait SimpleCacheTest
{
    public function cacheSetProvider()
    {
        return [
            ["key", "value1", true],
            ["key1", "value2", true],
            ["key_1", "value3", true],
            ["key-1", "value4", true],
            ["123", "value5", true],
            ["key1", "value6", true],
        ];
    }

    public function cacheWrongKeyProvider()
    {
        return [
            ["key ", "value1"],
            ["key1&", "value2"],
            ["是", "value3"],
            ["  ", "value4"],
            ["p#t", "value5"],
            ["", "value6"],
        ];
    }

    public function cacheWrongTTLProvider()
    {
        return [
            ["key", "value1", -1],
            ["key1", "value2", "ab"],
            ["key2", "value2", ""],
        ];
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider cacheSetProvider
     */
    public function testSet($k, $v, $expected)
    {
        $this->assertSame($expected, $this->cache->set($k, $v));
        $this->assertSame($v, $this->cache->get($k));
        $this->assertSame(true, $this->cache->has($k));
        $this->assertSame(true, $this->cache->delete($k));
        $this->assertSame(null, $this->cache->get($k));
        $this->assertSame(false, $this->cache->has($k));
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey($k, $v)
    {
        $this->cache->set($k, $v);
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey2($k, $v)
    {
        $this->cache->has($k);
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey3($k, $v)
    {
        $this->cache->get($k);
    }

    public function getWithDefaultValue()
    {
        $this->assertSame("Default Value", $this->cache->get("none-existing-key", "Default Value"));
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey4($k, $v)
    {
        $this->cache->delete($k);
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage TTL must only be integer greater than 0 or null.
     * @dataProvider cacheWrongTTLProvider
     */
    public function testSetWithWrongTTL($k, $v, $ttl)
    {
        $this->cache->set($k, $v, $ttl);
    }

    public function testWithTTL()
    {
        $k = "helloTTL";
        $v = "hello TTL";
        $ttl = 5;

        $this->assertSame(true, $this->cache->set($k, $v, $ttl));
        $this->assertSame($v, $this->cache->get($k));
        $this->assertSame(true, $this->cache->has($k));

        sleep($ttl+1);
        $this->assertSame(false, $this->cache->has($k));
        $this->assertSame(null, $this->cache->get($k));
    }

    public function testMulti()
    {
        $data = [
            "a" => "A",
            "b" => "B",
            "c" => "C",
            "d" => "D",
            "e" => "E",
            "f" => "F",
            "g" => "G",
        ];
        $this->assertSame(true, $this->cache->setMultiple($data));
        $this->assertSame($data, $this->cache->getMultiple(array_keys($data)));

        $this->assertSame(true, $this->cache->deleteMultiple(['a', 'd', 'f']));
        $this->assertSame([
            "a" => null,
            "b" => "B",
            "c" => "C",
            "d" => null,
            "e" => "E",
            "f" => null,
            "g" => "G",
        ], $this->cache->getMultiple(array_keys($data)));

        $this->assertSame([
            "a" => "Default",
            "b" => "B",
            "c" => "C",
            "d" => "Default",
            "e" => "E",
            "f" => "Default",
            "g" => "G",
        ], $this->cache->getMultiple(array_keys($data), "Default"));
    }

    public function multiErrorProvider()
    {
        return [
            [[]],
            ["aa"],
        ];
    }

    /**
     * @param $keys
     * @dataProvider multiErrorProvider
     * @expectedExceptionMessage Keys must be array and cannot be empty.
     * @expectedException \RW\SimpleCacheException
     */
    public function testMultiError($keys)
    {
        $this->assertSame([], $this->cache->getMultiple($keys));
    }

    public function tearDown()
    {
        $this->assertSame(true, $this->cache->clear());
    }
}

class MemCacheTest extends \PHPUnit\Framework\TestCase
{
    use SimpleCacheTest;
    public $cache = null;

    public function setUp()
    {
        $this->cache = new \RW\MemCache();
    }
}

class APCUTest extends \PHPUnit\Framework\TestCase
{
    use SimpleCacheTest;
    public $cache = null;

    public function setUp()
    {
        $this->cache = new \RW\APCUCache();
        $this->assertSame(true, function_exists('apcu_store'));
    }
}