<?php
require_once __DIR__ . './../vendor/autoload.php';

$clinet = new \Aws\DynamoDb\DynamoDbClient(
    ["region" => "us-west-1", "endpoint" => 'http://dynamodb:8000', "version" => "2012-08-10"]
);

$schema = \RW\DynamoCache::$schema;
$schema['TableName'] = "dynamo-cache";
try {
    $clinet->createTable($schema);
    echo "done\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}

?>