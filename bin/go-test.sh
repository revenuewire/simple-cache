#!/usr/bin/env bash

docker-compose down
docker-compose run --rm test
docker-compose down
docker container prune -f