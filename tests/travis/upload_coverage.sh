#!/usr/bin/env bash
set -e
if [ -n "$COVERAGE" ]; then
	cd cloudassets
	wget https://scrutinizer-ci.com/ocular.phar
	php ocular.phar code-coverage:upload -v --format=php-clover coverage.xml
fi
