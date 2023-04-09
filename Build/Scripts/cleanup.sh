#!/bin/bash

# convenience script for cleaning up after running test suite locally

composer config --unset platform.php
composer config --unset platform

echo "--------------------------------------------------------------------------------"
echo "!!!! Make sure to revert changes to composer.json by test suite e.g. by git checkout composer.json"
git diff composer.json
echo "--------------------------------------------------------------------------------"

rm -rf .Build
rm -f composer.lock
rm -f Build/testing-docker/.env
