[![Build Status](https://travis-ci.org/revenuewire/simple-cache.svg?branch=master)](https://travis-ci.org/revenuewire/simple-cache)
[![Coverage Status](https://coveralls.io/repos/github/revenuewire/simple-cache/badge.svg?branch=master)](https://coveralls.io/github/revenuewire/simple-cache?branch=master)
[![Latest Stable Version](https://poser.pugx.org/revenuewire/simple-cache/v/stable)](https://packagist.org/packages/revenuewire/simple-cache)


# Install
```
composer require revenuewire/simple-cache
```

# About
A PSR-16 "Simple Cache" implementation for adding cache to your applications. 

# Adapters
The following cache adapters are available:
* Array
* APCu
* DyanamoDB
* File

# Examples
```php
<?php
    /**
    * File Cache
    */
    $cache = new \RW\FileCache("/tmp/unit-test-" . uniqid());
    $k = "hello";
    $v = "world";
    $cache->set($k, $v); //return true
    $cache->get($k); //world
    $cache->has($k); //true
    $cache->delete($k); //true
    $cache->get($k); //null
    $cache->has($k); //false
    
    /**
    * DynamoDB Cache
    */
    $region = "us-west-1";
    $tableName = "sandbox-demo-cache";
    $cache = new \RW\DynamoCache($tableName, [
        "region" => $region,
    ]);
```

# Run unittest
```bash
sh ./bin/go-test.sh
```

# DynamoDB Schema
```yaml
AWSTemplateFormatVersion: '2010-09-09'
Description: (2-resources) DynamoDB Cache v1.0.0
Parameters:
  EnvironmentType:
    Description: The environment type
    Type: String
    Default: sandbox
    AllowedValues:
      - production
      - sandbox
    ConstraintDescription: must be a production or sandbox

  APP:
    Description: APP Name
    Type: String
    AllowedPattern: "^[a-z0-9-]+$"
    Default: "demo"
    MinLength: 4
    MaxLength: 30
    
Mappings:
  BuildEnvironment:
    sandbox:
      "env": "sandbox"
      "capacityUnits": 5
    production:
      "env": "production"
      "capacityUnits": 5

Resources:
  DynamoDBTable:
    Type: AWS::DynamoDB::Table
    Properties:
      TableName: !Sub "${EnvironmentType}-${APP}-cache"
      AttributeDefinitions:
        - AttributeName: id
          AttributeType: S
      KeySchema:
        - AttributeName: id
          KeyType: HASH
      ProvisionedThroughput:
        ReadCapacityUnits: { "Fn::FindInMap" : [ "BuildEnvironment", { "Ref" : "EnvironmentType" }, "capacityUnits"]}
        WriteCapacityUnits: { "Fn::FindInMap" : [ "BuildEnvironment", { "Ref" : "EnvironmentType" }, "capacityUnits"]}
      TimeToLiveSpecification:
        AttributeName: expiry
        Enabled: true
```