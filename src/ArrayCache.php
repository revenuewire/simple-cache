<?php
namespace RW;

use Psr\SimpleCache\CacheInterface;

/**
 * Class MemCache
 * @package RW
 */
class ArrayCache implements CacheInterface
{
    use SimpleCache;

    private $data = [];


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
        
        $now = time();

        if (isset($this->data[$key]) && isset($this->data[$key]['expiry']) && ($this->data[$key]['expiry'] === 0 || $this->data[$key]['expiry'] >= $now)) {
            return $this->data[$key]['value'];
        }

        if (isset($this->data[$key]) && isset($this->data[$key]['expiry']) && ($this->data[$key]['expiry'] < $now)) {
            unset($this->data[$key]);
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

        $this->data[$key]['value'] = $value;
        if ($ttl <= 0) {
            $this->data[$key]['expiry'] = 0;
        } else {
            $this->data[$key]['expiry'] = time() + $ttl;
        }

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
        unset($this->data[$key]);
        return true;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        $this->data = [];
        return true;
    }
}