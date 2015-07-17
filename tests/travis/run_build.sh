#!/usr/bin/env bash
set -e
if [ -n "$COVERAGE" ]; then
	phpunit -c cloudassets/phpunit.xml.dist --coverage-clover cloudassets/coverage.xml;
else
	phpunit -c cloudassets/phpunit.xml.dist
fi
