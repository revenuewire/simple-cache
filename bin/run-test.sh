#!/usr/bin/env bash
export PHP_IDE_CONFIG="serverName=cli"
php ./bin/init-db.php
./vendor/bin/phpunit