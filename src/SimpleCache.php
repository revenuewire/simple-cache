<?php
namespace RW;

trait SimpleCache
{
    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) || empty($keys)) {
            throw new SimpleCacheException("Keys must be array and cannot be empty.");
        }

        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $k =>$v) {
            $this->set($k, $v, $ttl);
        }

        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        foreach ($keys as $k) {
            $this->delete($k);
        }
        return true;
    }


    /**
     * Check if a given key is valid
     *
     * @param $key
     * @return bool
     */
    private function isValidKey($key)
    {
        return (!empty($key) && ctype_alnum(str_replace(['-', '_'], '', $key)));
    }

    /**
     * Check if a given ttl is valid
     * @param $ttl
     * @return bool
     */
    private function isValidTTL($ttl)
    {
        return ($ttl !== null && (!is_int($ttl) || $ttl <= 0));
    }
}