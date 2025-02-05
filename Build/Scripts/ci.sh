#!/bin/bash

# Convenience script to run CI tests locally
# default: PHP 8.1 and composer latest (uses TYPO3 v11)

# abort on error
set -e
set -x

# --------
# defaults
# --------
PHP_VERSION="8.2"
CORE_VERSION="13"

# -------------------
# automatic variables
# -------------------
thisdir=$(dirname $0)
cd $thisdir
thisdir=$(pwd)
progname=$(basename $0)

echo "Running with PHP version ${PHP_VERSION} and TYPO3 Core ${CORE_VERSION}"

echo "composer update"
${thisdir}/runTests.sh -p ${PHP_VERSION} -t ${CORE_VERSION} -s composerUpdate

#echo "cgl"
#Build/Scripts/runTests.sh -p ${PHP_VERSION} -t ${CORE_VERSION} -s cgl -n

#echo "lint"
#Build/Scripts/runTests.sh -p ${PHP_VERSION} -t ${CORE_VERSION} -s lint

#echo "phpstan"
#Build/Scripts/runTests.sh -p ${PHP_VERSION} -t ${CORE_VERSION} -s phpstan

#echo "Unit tests"
#Build/Scripts/runTests.sh -p ${PHP_VERSION} -t ${CORE_VERSION} -s unit

echo "functional tests"
${thisdir}/runTests.sh -p ${PHP_VERSION} -t ${CORE_VERSION} -d mariadb -s functional

# -------
# cleanup
# -------

$thisdir/cleanup.sh

echo "done: ok"
