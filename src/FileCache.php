<?php
/**
 * Created by IntelliJ IDEA.
 * User: swang
 * Date: 2018-04-01
 * Time: 8:27 AM
 */

namespace RW;


use Psr\SimpleCache\CacheInterface;

class FileCache implements CacheInterface
{
    use SimpleCache;

    private $cacheDir = "/tmp/default-simple-cache-dir";

    /**
     * FileCache constructor.
     * @param string $cacheDir
     * @codeCoverageIgnore
     */
    function __construct($cacheDir = "/tmp/default-simple-cache-dir")
    {
        $this->cacheDir = $cacheDir;
        mkdir($this->cacheDir,0777);
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        if (!$this->isValidKey($key)) {
            throw new SimpleCacheException("Invalid key. Only [a-zA-Z0-9_-] allowed.");
        }
        @include $this->cacheDir . "/$key";

        if(!isset($item)) {
            return $default;
        }
        $now = time();
        $item = json_decode($item, true);

        if (isset($item['expiry']) && ($item['expiry'] === 0 || $item['expiry'] >= $now)) {
            return unserialize($item['value']);
        }
        if (isset($item['expiry']) && ($item['expiry'] < $now)) {
            @unlink($this->cacheDir . "/$key");
        }

        return $default;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        if (!$this->isValidKey($key)) {
            throw new SimpleCacheException("Invalid key. Only [a-zA-Z0-9_-] allowed.");
        }

        if (!$this->isValidTTL($ttl)) {
            throw new SimpleCacheException("TTL must only be integer greater than 0 or null.");
        }

        if ($value === null) {
            return false;
        }

        if ($ttl <= 0) {
            $expiry = 0;
        } else {
            $expiry = time() + $ttl;
        }

        $item = json_encode(["value" => serialize($value), "expiry" => $expiry]);

        // Write to temp file first to ensure atomicity
        $tmp = $this->cacheDir . "/$key." . uniqid('', true) . '.tmp';
        file_put_contents($tmp, '<?php $item = \'' . $item . "';", LOCK_EX);
        rename($tmp, $this->cacheDir . "/$key");

        return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        if (!$this->isValidKey($key)) {
            throw new SimpleCacheException("Invalid key. Only [a-zA-Z0-9_-] allowed.");
        }

        if (!file_exists($this->cacheDir . "/$key")) {
            return false;
        }

        return unlink($this->cacheDir . "/$key");
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        array_map('unlink', glob($this->cacheDir . "/*"));
        return rmdir($this->cacheDir);
    }
}