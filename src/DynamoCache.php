<?php
namespace RW;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Psr\SimpleCache\CacheInterface;

class DynamoCache implements CacheInterface
{
    use SimpleCache;

    private $table;

    /** @var Marshaler */
    private $marshaler;

    /**
     * @var DynamoDbClient
     */
    private $client;

    private $readBatchLimit = 100;
    private $writeBatchLimit = 25;

    /**
     * Code reference of db schema. We suggest use cloudformation to define your schema.
     * @var array
     */
    public static $schema = [
        "AttributeDefinitions" => [
            [
                'AttributeName' => 'id',
                'AttributeType' => 'S',
            ]
        ],
        'KeySchema' => [
            [
                'AttributeName' => 'id',
                'KeyType' => 'HASH',
            ]
        ],
        'ProvisionedThroughput' => [
            'ReadCapacityUnits' => 5,
            'WriteCapacityUnits' => 5,
        ]
    ];


    /**
     * DynamoCache constructor.
     * @param $table
     * @param array $config
     * @codeCoverageIgnore
     */
    function __construct($table, $config = [])
    {
        $this->table = $table;
        $config['version'] = "2012-08-10";
        $this->client = new DynamoDbClient($config);
        $this->marshaler = new Marshaler();
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

        $now = time();
        $params = [
            'TableName' => $this->table,
            'Key' => ["id" => $this->marshaler->marshalValue($key) ]
        ];

        $result = $this->client->getItem($params);
        if (empty($result) || $result->get("Item") === null) {
            return $default;
        }
        $item = $this->marshaler->unmarshalItem($result->get("Item"));
        if (!isset($item['expiry']) || $item['expiry'] >= $now) {
            return $item['value'];
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

        if ($this->isValidTTL($ttl)) {
            throw new SimpleCacheException("TTL must only be integer greater than 0 or null.");
        }

        $data = [
            "id" => $key,
            "value" => $value,
        ];

        if ($ttl > 0) {
            $data['expiry'] = time() + $ttl;
        }

        $item = array(
            'TableName' => $this->table,
            'Item' => $this->marshaler->marshalItem($data),
        );
        $this->client->putItem($item);

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

        $params = [
            'TableName' => $this->table,
            'Key' => [ "id" => $this->marshaler->marshalValue($key) ],
        ];

        $this->client->deleteItem($params);

        return true;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function clear()
    {
        $lastEvaluatedKey = null;
        do {
            $params = [
                'TableName' => $this->table,
                'Limit' => $this->getReadBatchLimit(),
            ];
            if ($lastEvaluatedKey !== null) {
                $params['ExclusiveStartKey'] = $lastEvaluatedKey;
            }

            $result = $this->client->scan($params);
            foreach ($result->get('Items') as $i) {
                $item = $this->marshaler->unmarshalItem($i);
                $this->delete($item['id']);
            }

            if (isset($result['LastEvaluatedKey'])) {
                $lastEvaluatedKey = $result['LastEvaluatedKey'];
            } else {
                $lastEvaluatedKey = null;
            }
        } while ($lastEvaluatedKey !== null);

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        $item = $this->get($key, null);
        return ($item !== null);
    }

    /**
     * @return int
     */
    public function getReadBatchLimit()
    {
        return $this->readBatchLimit;
    }

    /**
     * @param int $readBatchLimit
     * @codeCoverageIgnore
     */
    public function setReadBatchLimit($readBatchLimit)
    {
        $this->readBatchLimit = $readBatchLimit;
    }

    /**
     * @return int
     */
    public function getWriteBatchLimit()
    {
        return $this->writeBatchLimit;
    }

    /**
     * @param int $writeBatchLimit
     * @codeCoverageIgnore
     */
    public function setWriteBatchLimit($writeBatchLimit)
    {
        $this->writeBatchLimit = $writeBatchLimit;
    }
}