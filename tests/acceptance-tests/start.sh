#!/usr/bin/env bash

cd ../../

# Run just one test
#docker-compose run --rm codeception run tests/acceptance-tests/acceptance/product-name-field-type-Cept.php -vvv --html

# Run all tests
docker-compose run --rm codeception run -vvv --html
