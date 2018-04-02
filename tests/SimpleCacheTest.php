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
        $this->assertSame($expected, self::$cache->set($k, $v));
        $this->assertSame($v, self::$cache->get($k));
        $this->assertSame(true, self::$cache->has($k));
        $this->assertSame(true, self::$cache->delete($k));
        $this->assertSame(null, self::$cache->get($k));
        $this->assertSame(false, self::$cache->has($k));
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey($k, $v)
    {
        self::$cache->set($k, $v);
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey2($k, $v)
    {
        self::$cache->has($k);
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey3($k, $v)
    {
        self::$cache->get($k);
    }

    public function getWithDefaultValue()
    {
        $this->assertSame("Default Value", self::$cache->get("none-existing-key", "Default Value"));
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage Only [a-zA-Z0-9_-] allowed.
     * @dataProvider cacheWrongKeyProvider
     */
    public function testSetWithWrongKey4($k, $v)
    {
        self::$cache->delete($k);
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @expectedException \RW\SimpleCacheException
     * @expectedExceptionMessage TTL must only be integer greater than 0 or null.
     * @dataProvider cacheWrongTTLProvider
     */
    public function testSetWithWrongTTL($k, $v, $ttl)
    {
        self::$cache->set($k, $v, $ttl);
    }

    public function testWithTTL()
    {
        $k = "helloTTL";
        $v = "hello TTL";
        $ttl = 5;

        $this->assertSame(true, self::$cache->set($k, $v, $ttl));
        $this->assertSame($v, self::$cache->get($k));
        $this->assertSame(true, self::$cache->has($k));

        sleep($ttl+1);
        $this->assertSame(false, self::$cache->has($k));
        $this->assertSame(null, self::$cache->get($k));
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
        $this->assertSame(true, self::$cache->setMultiple($data, 100));
        $this->assertEquals($data, self::$cache->getMultiple(array_keys($data)));

        $this->assertSame(true, self::$cache->deleteMultiple(['a', 'd', 'f']));
        $this->assertEquals([
            "a" => null,
            "b" => "B",
            "c" => "C",
            "d" => null,
            "e" => "E",
            "f" => null,
            "g" => "G",
        ], self::$cache->getMultiple(array_keys($data)));

        $this->assertSame([
            "a" => "Default",
            "b" => "B",
            "c" => "C",
            "d" => "Default",
            "e" => "E",
            "f" => "Default",
            "g" => "G",
        ], self::$cache->getMultiple(array_keys($data), "Default"));

        $this->assertSame("B", self::$cache->get('b'));
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
        $this->assertSame([], self::$cache->getMultiple($keys));
    }

    /**
     * @expectedExceptionMessage TTL must only be integer greater than 0 or null.
     * @expectedException \RW\SimpleCacheException
     */
    public function testMultiErrorWithWrongTTL()
    {
        $this->assertSame([], self::$cache->setMultiple([
            "abc" => "abc",
            "bcd" => "bcd"
        ], "hg"));
    }

    /**
     * @expectedExceptionMessage Invalid key. Only [a-zA-Z0-9_-] allowed.
     * @expectedException \RW\SimpleCacheException
     */
    public function testMultiErrorWithWrongKey()
    {
        $this->assertSame([], self::$cache->setMultiple([
            "abc" => "abc",
            "bcd*" => "bcd"
        ]));
    }

    /**
     * Last test
     */
    public function testAtLast()
    {
        $this->assertSame("B", self::$cache->get('b'));
        $this->assertSame(true, self::$cache->clear());
        $this->assertSame(null, self::$cache->get('b', null));
    }
}

class ArrayCacheTest extends \PHPUnit\Framework\TestCase
{
    use SimpleCacheTest;
    public static $cache = null;

    public static function setUpBeforeClass()
    {
        self::$cache = new \RW\ArrayCache();
    }
}

class APCUTest extends \PHPUnit\Framework\TestCase
{
    use SimpleCacheTest;
    public static $cache = null;

    public static function setUpBeforeClass()
    {
        self::$cache = new \RW\APCUCache();
    }
}

/**
 * @group file-cache
 */
class FileCacheTest extends \PHPUnit\Framework\TestCase
{
    use SimpleCacheTest;
    public static $cache = null;

    public static function setUpBeforeClass()
    {
        self::$cache = new \RW\FileCache("/tmp/unit-test-" . uniqid());
    }
}

/**
 * Class DynamoCacheTest
 */
class DynamoCacheTest extends \PHPUnit\Framework\TestCase
{
    use SimpleCacheTest;
    /** @var $cache \RW\DynamoCache */
    public static $cache = null;

    public static function setUpBeforeClass()
    {
        self::$cache = new \RW\DynamoCache("dynamo-cache", [
            "region" => "us-west-1",
            "endpoint" => 'http://dynamodb:8000'
        ]);
        //this to ensure we get to lastEvalutedKey section
        self::$cache->setReadBatchLimit(2);
    }
}