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

        if (!$this->isValidTTL($ttl)) {
            throw new SimpleCacheException("TTL must only be integer greater than 0 or null.");
        }

        if (!$this->isValidValue($value)) {
            throw new SimpleCacheException("Value cannot be empty.");
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
        if (!$this->isValidTTL($ttl)) {
            throw new SimpleCacheException("TTL must only be integer greater than 0 or null.");
        }

        $batchData = [];
        if ($ttl > 0) {
            $expiry = time() + $ttl;
        }
        foreach ($values as $k => $v) {
            if (!$this->isValidKey($k)) {
                throw new SimpleCacheException("Invalid key. Only [a-zA-Z0-9_-] allowed.");
            }

            if (!$this->isValidValue($v)) {
                throw new SimpleCacheException("Value cannot be empty.");
            }

            $item = [
                'id' => $k,
                'value' => $v,
            ];
            if (isset($expiry)) {
                $item['expiry'] = $expiry;
            }

            $batchData[] = ['PutRequest' => [
                "Item" => $this->marshaler->marshalItem($item)
            ]];
        }

        if (count($batchData) > 0) {
            $batchChunks = array_chunk($batchData, $this->getWriteBatchLimit());
            foreach ($batchChunks as $chunk) {
                $this->client->batchWriteItem([
                    'RequestItems' => [
                        $this->table => $chunk
                    ]
                ]);
            }
        }

        return true;
    }

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

        $batchChunks = array_chunk(array_map(function ($k){
            return ["id" => $this->marshaler->marshalValue($k)];
        }, $keys), $this->getReadBatchLimit());

        $returnResults = array_fill_keys($keys, $default);
        foreach ($batchChunks as $chunk) {
            $results = $this->client->batchGetItem([
                'RequestItems' => [
                    $this->table => [
                        "Keys" => $chunk,
                    ]
                ]
            ]);

            foreach ($results['Responses'][$this->table] as $response) {
                $item = $this->marshaler->unmarshalItem($response);
                $returnResults[$item['id']] = $item['value'];
            }
        }

        return $returnResults;
    }
}