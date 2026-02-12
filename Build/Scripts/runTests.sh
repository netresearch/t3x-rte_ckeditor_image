#!/usr/bin/env bash

#
# TYPO3 core test runner based on docker.
#

trap 'cleanUp;exit 2' SIGINT

waitFor() {
    local HOST=${1}
    local PORT=${2}
    local TESTCOMMAND="
        COUNT=0;
        while ! nc -z ${HOST} ${PORT}; do
            if [ \"\${COUNT}\" -gt 20 ]; then
              echo \"Can not connect to ${HOST} port ${PORT}. Aborting.\";
              exit 1;
            fi;
            sleep 1;
            COUNT=\$((COUNT + 1));
        done;
    "
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name wait-for-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_ALPINE} /bin/sh -c "${TESTCOMMAND}"
    if [[ $? -gt 0 ]]; then
        kill -SIGINT -$$
    fi
}

cleanUp() {
    ATTACHED_CONTAINERS=$(${CONTAINER_BIN} ps --filter network=${NETWORK} --format='{{.Names}}')
    for ATTACHED_CONTAINER in ${ATTACHED_CONTAINERS}; do
        ${CONTAINER_BIN} kill ${ATTACHED_CONTAINER} >/dev/null
    done
    ${CONTAINER_BIN} network rm ${NETWORK} >/dev/null
}

handleDbmsOptions() {
    # -a, -d, -i depend on each other. Validate input combinations and set defaults.
    case ${DBMS} in
        mariadb)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10.2"
            if ! [[ ${DBMS_VERSION} =~ ^(10.2|10.3|10.4|10.5|10.6|10.7|10.8|10.9|10.10|10.11|11.0|11.1)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        mysql)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="5.5"
            if ! [[ ${DBMS_VERSION} =~ ^(5.5|5.6|5.7|8.0)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        postgres)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10"
            if ! [[ ${DBMS_VERSION} =~ ^(10|11|12|13|14|15|16)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        sqlite)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            if [ -n "${DBMS_VERSION}" ]; then
                echo "Invalid combination -d ${DBMS} -i ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        *)
            echo "Invalid option -d ${DBMS}" >&2
            echo >&2
            echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
            exit 1
            ;;
    esac
}

cleanCacheFiles() {
    echo -n "Clean caches ... "
    rm -rf \
        .Build/.cache \
        .php-cs-fixer.cache
    echo "done"
}

cleanTestFiles() {
    # test related
    echo -n "Clean test related files ... "
    rm -rf \
        .Build/public/typo3temp/var/tests/
    echo "done"
}

cleanRenderedDocumentationFiles() {
    echo -n "Clean rendered documentation files ... "
    rm -rf \
        Documentation-GENERATED-temp
    echo "done"
}

cleanComposer() {
  rm -rf \
    .Build/vendor \
    .Build/bin \
    composer.lock
}

stashComposerFiles() {
    cp composer.json composer.json.orig
    if [ -f "composer.json.testing" ]; then
        cp composer.json composer.json.orig
    fi
}

restoreComposerFiles() {
    cp composer.json composer.json.testing
    mv composer.json.orig composer.json
}

loadHelp() {
    # Load help text into $HELP
    read -r -d '' HELP <<EOF
TYPO3 core test runner. Execute unit, functional and other test suites in
a container based test environment. Handles execution of single test files,
sending xdebug information to a local IDE and more.

Usage: $0 [options] [file]

Options:
    -s <...>
        Specifies which test suite to run
            - cgl: cgl test and fix all php files
            - clean: clean up build and testing related files
            - cleanRenderedDocumentation: clean up rendered documentation files and folders (Documentation-GENERATED-temp)
            - composer: Execute "composer" command, using -e for command arguments pass-through, ex. -e "ci:php:stan"
            - composerInstall: "composer update", handy if host has no PHP
            - composerInstallLowest: "composer update", handy if host has no PHP
            - composerInstallHighest: "composer update", handy if host has no PHP
            - coveralls: Generate coverage
            - docsGenerate: Renders the extension ReST documentation.
            - e2e: Playwright E2E tests (TYPO3 Core pattern, no DDEV)
            - functional: functional tests
            - fuzz: Run fuzz tests with php-fuzzer
            - lint: PHP linting
            - mutation: Run mutation tests with Infection
            - unit: PHP unit tests

    -a <mysqli|pdo_mysql>
        Only with -s functional|functionalDeprecated
        Specifies to use another driver, following combinations are available:
            - mysql
                - mysqli (default)
                - pdo_mysql
            - mariadb
                - mysqli (default)
                - pdo_mysql

    -b <docker|podman>
        Container environment:
            - docker (default)
            - podman

    -d <sqlite|mariadb|mysql|postgres>
        Only with -s functional|functionalDeprecated
        Specifies on which DBMS tests are performed
            - sqlite: (default): use sqlite
            - mariadb: use mariadb
            - mysql: use MySQL
            - postgres: use postgres

    -i version
        Specify a specific database version
        With "-d mariadb":
            - 10.2   short-term, maintained until 2023-05-25 (default)
            - 10.3   short-term, maintained until 2023-05-25
            - 10.4   short-term, maintained until 2024-06-18
            - 10.5   short-term, maintained until 2025-06-24
            - 10.6   long-term, maintained until 2026-06
            - 10.7   short-term, no longer maintained
            - 10.8   short-term, maintained until 2023-05
            - 10.9   short-term, maintained until 2023-08
            - 10.10  short-term, maintained until 2023-11
            - 10.11  long-term, maintained until 2028-02
            - 11.0   development series
            - 11.1   short-term development series
        With "-d mysql":
            - 5.5   unmaintained since 2018-12 (default)
            - 5.6   unmaintained since 2021-02
            - 5.7   maintained until 2023-10
            - 8.0   maintained until 2026-04
        With "-d postgres":
            - 10    unmaintained since 2022-11-10 (default)
            - 11    unmaintained since 2023-11-09
            - 12    maintained until 2024-11-14
            - 13    maintained until 2025-11-13
            - 14    maintained until 2026-11-12
            - 15    maintained until 2027-11-11
            - 16    maintained until 2028-11-09

    -t <11|12|13>
        Only with -s composerInstall|composerInstallMin|composerInstallMax
        Specifies the TYPO3 CORE Version to be used
            - 11.5: use TYPO3 v11 (default)
            - 12.4: use TYPO3 v12
            - 13.4: use TYPO3 v13

    -p <8.2|8.3|8.4|8.5>
        Specifies the PHP minor version to be used
            - 8.2: use PHP 8.2 (default)
            - 8.3: use PHP 8.3
            - 8.4: use PHP 8.4
            - 8.5: use PHP 8.5

    -e "<phpunit options>"
        Only with -s docsGenerate|functional|unit
        Additional options to send to phpunit (unit & functional tests). For phpunit,
        options starting with "--" must be added after options starting with "-".
        Example -e "--filter classCanBeRegistered" to enable verbose output AND filter tests
        named "classCanBeRegistered"

        DEPRECATED - pass arguments after the `--` separator directly. For example, instead of
            Build/Scripts/runTests.sh -s unit -e "--filter classCanBeRegistered"
        use
            Build/Scripts/runTests.sh -s unit -- --filter classCanBeRegistered

    -x
        Only with -s functional|functionalDeprecated|unit|unitDeprecated|unitRandom
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -n
        Only with -s cgl|composerNormalize
        Activate dry-run in CGL check that does not actively change files and only prints broken ones.

    -u
        Update existing typo3/core-testing-*:latest container images and remove dangling local volumes.
        New images are published once in a while and only the latest ones are supported by core testing.
        Use this if weird test errors occur. Also removes obsolete image versions of typo3/core-testing-*.

    -h
        Show this help.

Examples:
    # Run all core unit tests using PHP 7.4
    ./Build/Scripts/runTests.sh -s unit

    # Run all core units tests and enable xdebug (have a PhpStorm listening on port 9003!)
    ./Build/Scripts/runTests.sh -x -s unit

    # Run unit tests in phpunit verbose mode with xdebug on PHP 8.1 and filter for test canRetrieveValueWithGP
    ./Build/Scripts/runTests.sh -x -p 8.1 -- --filter 'classCanBeRegistered'

    # Run functional tests in phpunit with a filtered test method name in a specified file
    # example will currently execute two tests, both of which start with the search term
    ./Build/Scripts/runTests.sh -s functional -- --filter 'findRecordByImportSource' Tests/Functional/Repository/CategoryRepositoryTest.php

    # Run functional tests on postgres with xdebug, php 8.1 and execute a restricted set of tests
    ./Build/Scripts/runTests.sh -x -p 8.1 -s functional -d postgres -- Tests/Functional/Repository/CategoryRepositoryTest.php

    # Run functional tests on postgres 11
    ./Build/Scripts/runTests.sh -s functional -d postgres -i 11

    # Run E2E Playwright tests with PHP 8.3
    ./Build/Scripts/runTests.sh -s e2e -p 8.3

    # Run specific E2E test file
    ./Build/Scripts/runTests.sh -s e2e -- tests/click-to-enlarge.spec.ts
EOF
}

# Test if at least one of the supported container binaries exists, else exit out with error
if ! type "docker" >/dev/null 2>&1 && ! type "podman" >/dev/null 2>&1; then
    echo "This script relies on docker or podman. Please install at least one of them" >&2
    exit 1
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called, then go up two dirs.
THIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)"
cd "$THIS_SCRIPT_DIR" || exit 1
cd ../../ || exit 1
ROOT_DIR="${PWD}"

# Option defaults
TEST_SUITE=""
TYPO3_VERSION="11"
DBMS="sqlite"
DBMS_VERSION=""
PHP_VERSION="8.2"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
EXTRA_TEST_OPTIONS=""
CGLCHECK_DRY_RUN=0
DATABASE_DRIVER=""
CONTAINER_BIN=""
COMPOSER_ROOT_VERSION="11.4.3-dev"
# Default to non-interactive; detect TTY below
CONTAINER_INTERACTIVE="--init"
HOST_UID=$(id -u)
HOST_PID=$(id -g)
USERSET=""
SUFFIX=$(echo $RANDOM)
NETWORK="friendsoftypo3-tea-${SUFFIX}"
CI_PARAMS="${CI_PARAMS:-}"
CONTAINER_HOST="host.docker.internal"
PHPSTAN_CONFIG_FILE="phpstan.neon"
IS_CI=0

# Option parsing updates above default vars
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts "a:b:s:d:i:p:e:t:xy:nhu" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        a)
            DATABASE_DRIVER=${OPTARG}
            ;;
        b)
            if ! [[ ${OPTARG} =~ ^(docker|podman)$ ]]; then
                INVALID_OPTIONS+=("-b ${OPTARG}")
            fi
            CONTAINER_BIN=${OPTARG}
            ;;
        d)
            DBMS=${OPTARG}
            ;;
        i)
            DBMS_VERSION=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            if ! [[ ${PHP_VERSION} =~ ^(8.2|8.3|8.4|8.5)$ ]]; then
                INVALID_OPTIONS+=("-p ${OPTARG}")
            fi
            ;;
        e)
            EXTRA_TEST_OPTIONS=${OPTARG}
            ;;
        t)
            TYPO3_VERSION=${OPTARG}
            if ! [[ ${TYPO3_VERSION} =~ ^(11|12|13)$ ]]; then
                INVALID_OPTIONS+=("-t ${OPTARG}")
            fi
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        n)
            CGLCHECK_DRY_RUN=1
            ;;
        h)
            loadHelp
            echo "${HELP}"
            exit 0
            ;;
        u)
            TEST_SUITE=update
            ;;
        \?)
            INVALID_OPTIONS+=("-${OPTARG}")
            ;;
        :)
            INVALID_OPTIONS+=("-${OPTARG}")
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo ${I} >&2
    done
    echo >&2
    echo "call \".Build/Scripts/runTests.sh -h\" to display help and valid options"
    exit 1
fi

handleDbmsOptions

# ENV var "CI" is set by gitlab-ci. Use it to force some CI details.
if [ "${CI}" == "true" ]; then
    IS_CI=1
    CONTAINER_INTERACTIVE=""
# Detect TTY availability for interactive mode (allows running from scripts/pipes)
# Note: Use -i (interactive) but NOT -t (tty) since -t fails in non-TTY contexts
# even when [ -t 0 ] returns true (e.g., when running from CI tools)
elif [ -t 0 ] && [ -t 1 ]; then
    CONTAINER_INTERACTIVE="-i --init"
fi

# determine default container binary to use: 1. podman 2. docker
if [[ -z "${CONTAINER_BIN}" ]]; then
    if type "podman" >/dev/null 2>&1; then
        CONTAINER_BIN="podman"
    elif type "docker" >/dev/null 2>&1; then
        CONTAINER_BIN="docker"
    fi
fi

if [ $(uname) != "Darwin" ] && [ "${CONTAINER_BIN}" == "docker" ]; then
    # Run docker jobs as current user to prevent permission issues. Not needed with podman.
    USERSET="--user $HOST_UID"
fi

if ! type ${CONTAINER_BIN} >/dev/null 2>&1; then
    echo "Selected container environment \"${CONTAINER_BIN}\" not found. Please install \"${CONTAINER_BIN}\" or use -b option to select one." >&2
    exit 1
fi

# Create .cache dir: composer need this.
mkdir -p .cache
mkdir -p .Build/public/typo3temp/var/tests

IMAGE_PHP="ghcr.io/typo3/core-testing-$(echo "php${PHP_VERSION}" | sed -e 's/\.//'):latest"
IMAGE_ALPINE="docker.io/alpine:3.8"
IMAGE_DOCS="ghcr.io/typo3-documentation/render-guides:latest"
IMAGE_MARIADB="docker.io/mariadb:${DBMS_VERSION}"
IMAGE_MYSQL="docker.io/mysql:${DBMS_VERSION}"
IMAGE_POSTGRES="docker.io/postgres:${DBMS_VERSION}-alpine"
# E2E testing images (TYPO3 Core pattern)
IMAGE_APACHE="ghcr.io/typo3/core-testing-apache24:1.7"
IMAGE_PLAYWRIGHT="mcr.microsoft.com/playwright:v1.58.0-noble"

# Set $1 to first mass argument, this is the optional test file or test directory to execute
shift $((OPTIND - 1))

${CONTAINER_BIN} network create ${NETWORK} >/dev/null

if [ "${CONTAINER_BIN}" == "docker" ]; then
    CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} --rm --network ${NETWORK} --add-host "${CONTAINER_HOST}:host-gateway" ${USERSET} -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
else
    # podman
    CONTAINER_HOST="host.containers.internal"
    CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm --network ${NETWORK} -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
fi

if [ ${PHP_XDEBUG_ON} -eq 0 ]; then
    XDEBUG_MODE="-e XDEBUG_MODE=off"
    XDEBUG_CONFIG=" "
else
    XDEBUG_MODE="-e XDEBUG_MODE=debug -e XDEBUG_TRIGGER=foo"
    XDEBUG_CONFIG="client_port=${PHP_XDEBUG_PORT} client_host=host.docker.internal"
fi

# Suite execution
case ${TEST_SUITE} in
    cgl)
        DRY_RUN_OPTIONS=''
        if [ "${CGLCHECK_DRY_RUN}" -eq 1 ]; then
            DRY_RUN_OPTIONS='--dry-run --diff'
        fi
        COMMAND="php -dxdebug.mode=off .Build/bin/php-cs-fixer fix -v ${DRY_RUN_OPTIONS} --config=Build/php-cs-fixer/php-cs-fixer.php --using-cache=no"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    clean)
        rm -rf \
          var/ \
          .cache \
          composer.lock \
          .Build/ \
          Tests/Acceptance/Support/_generated/ \
          composer.json.testing \
          Documentation-GENERATED-temp
        ;;
    composer)
        COMMAND=(composer "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    composerInstall)
        cleanComposer
        stashComposerFiles
        COMMAND=(composer install "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-install-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        restoreComposerFiles
        ;;
    composerInstallHighest)
        cleanComposer
        stashComposerFiles
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-install-highest-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/bash -c "
            if [ ${TYPO3_VERSION} -eq 11 ]; then
              composer require --no-ansi --no-interaction --no-progress --no-install \
                typo3/cms-core:^11.5.38 \\
                typo3/cms-backend:^11.5.38 \\
                typo3/cms-frontend:^11.5.38 \\
                typo3/cms-extbase:^11.5.38 \\
                typo3/cms-rte-ckeditor:^11.5.38 \\
                || exit 1
            fi
            if [ ${TYPO3_VERSION} -eq 12 ]; then
              composer require --no-ansi --no-interaction --no-progress --no-install \
                typo3/cms-core:^12.4.17 \\
                typo3/cms-backend:^12.4.17 \\
                typo3/cms-frontend:^12.4.17 \\
                typo3/cms-extbase:^12.4.17 \\
                typo3/cms-rte-ckeditor:^12.4.17 \\
                 || exit 1
            fi
            if [ ${TYPO3_VERSION} -eq 13 ]; then
              composer require --no-ansi --no-interaction --no-progress --no-install \
                typo3/cms-core:^13.4 \\
                typo3/cms-backend:^13.4 \\
                typo3/cms-frontend:^13.4 \\
                typo3/cms-extbase:^13.4 \\
                typo3/cms-rte-ckeditor:^13.4 \\
                 || exit 1
            fi
            composer update --no-progress --no-interaction  || exit 1
            composer show || exit 1
        "
        SUITE_EXIT_CODE=$?
        restoreComposerFiles
        ;;
    composerInstallLowest)
        cleanComposer
        stashComposerFiles
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-install-lowest-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/bash -c "
            if [ ${TYPO3_VERSION} -eq 11 ]; then
              composer require --no-ansi --no-interaction --no-progress --no-install \
                typo3/cms-core:^11.5.38 || exit 1
            fi
            if [ ${TYPO3_VERSION} -eq 12 ]; then
              composer require --no-ansi --no-interaction --no-progress --no-install \
                typo3/cms-core:^12.4.17 || exit 1
            fi
            if [ ${TYPO3_VERSION} -eq 13 ]; then
              composer require --no-ansi --no-interaction --no-progress --no-install \
                typo3/cms-core:^13.4 || exit 1
            fi
            composer update --no-ansi --no-interaction --no-progress --with-dependencies --prefer-lowest || exit 1
            composer show || exit 1
        "
        SUITE_EXIT_CODE=$?
        restoreComposerFiles
        ;;
    docsGenerate)
        mkdir -p Documentation-GENERATED-temp
        chown -R ${HOST_UID}:${HOST_PID} Documentation-GENERATED-temp
        COMMAND=(--config=Documentation --fail-on-log ${EXTRA_TEST_OPTIONS} "$@")
        ${CONTAINER_BIN} run ${CONTAINER_INTERACTIVE} --rm --pull always ${USERSET} -v "${ROOT_DIR}":/project ${IMAGE_DOCS} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    e2e)
        # E2E tests using TYPO3 Core pattern: PHP + MariaDB + Playwright
        # No DDEV dependency - lightweight containers only
        # Uses MariaDB because SQLite doesn't work with TYPO3's database:updateschema
        E2E_ROOT="${ROOT_DIR}/.Build/e2e-typo3"
        E2E_WEB_PORT=8080
        E2E_SCRIPTS="${ROOT_DIR}/.Build/e2e-scripts"

        echo "Setting up E2E test environment..."

        # Clean and create E2E TYPO3 instance
        rm -rf "${E2E_ROOT}"
        rm -rf "${E2E_SCRIPTS}"
        mkdir -p "${E2E_ROOT}"
        mkdir -p "${E2E_SCRIPTS}"
        mkdir -p "${ROOT_DIR}/Build/test-results"

        # Create helper scripts on host (to avoid heredoc-in-double-quotes bash parsing issues)
        # These will be mounted into the container

        # additional.php - TYPO3 system configuration with verbose error output
        cat > "${E2E_SCRIPTS}/additional.php" << 'ADDITIONAL_EOF'
<?php
return [
    'BE' => ['debug' => true],
    'FE' => [
        'debug' => true,
        'debugExceptionHandler' => \TYPO3\CMS\Core\Error\DebugExceptionHandler::class,
    ],
    'SYS' => [
        'devIPmask' => '*',
        'displayErrors' => 1,
        'exceptionalErrors' => E_WARNING | E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE,
        'trustedHostsPattern' => '.*',
        'debugExceptionHandler' => \TYPO3\CMS\Core\Error\DebugExceptionHandler::class,
        'productionExceptionHandler' => \TYPO3\CMS\Core\Error\DebugExceptionHandler::class,
    ],
];
ADDITIONAL_EOF

        # db-setup.php - Insert required database records (tables are created by database:updateschema)
        cat > "${E2E_SCRIPTS}/db-setup.php" << 'DBSETUP_EOF'
<?php
// Connect to MariaDB
$pdo = new PDO(
    'mysql:host=mariadb-e2e;port=3306;dbname=e2e_test',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$now = time();

// Debug: List existing tables to verify schema was created by TYPO3's database:updateschema
echo "Checking existing tables...\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Found " . count($tables) . " tables\n";
if (count($tables) < 10) {
    echo "WARNING: Expected many tables from database:updateschema, but found only " . count($tables) . "\n";
}

// Ensure default file storage has correct configuration
// TYPO3 setup may create uid=1 with empty configuration — we must fix it
// CRITICAL: is_public = 1 is required for click-to-enlarge to work (imageLinkWrap)
$storageConfig = '{"basePath":"fileadmin/","pathType":"relative"}';
$pdo->prepare("INSERT INTO sys_file_storage (uid, name, driver, configuration, is_default, is_public, tstamp, crdate) VALUES (1, 'fileadmin', 'Local', ?, 1, 1, ?, ?) ON DUPLICATE KEY UPDATE configuration = VALUES(configuration), is_public = 1")
    ->execute([$storageConfig, $now, $now]);
echo "Default file storage ensured (with basePath configuration)\n";

// Insert root page
$pdo->exec("INSERT IGNORE INTO pages (uid, pid, title, slug, doktype, is_siteroot, hidden, deleted, tstamp, crdate) VALUES (1, 0, 'Home', '/', 1, 1, 0, 0, $now, $now)");
echo "Pages record inserted\n";

// TypoScript CONSTANTS - defines default values used by setup
// fluid_styled_content needs these constants for proper operation
$tsConstants = <<<'TYPOSCRIPT'
@import 'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript'

# Image lazy loading setting
styles.content.image.lazyLoading = lazy
TYPOSCRIPT;

// TypoScript SETUP configuration for PAGE rendering
// IMPORTANT: Load fluid_styled_content FIRST to define lib.parseFunc_RTE base
// Then load our extension to add the tags.img.preUserFunc for click-to-enlarge
$tsConfig = <<<'TYPOSCRIPT'
@import 'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript'
@import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'

# Ensure lib.contentElement.settings.media.popup is set for click-to-enlarge
# This path MUST exist for ImageRenderingController to find popup config
lib.contentElement.settings.media.popup {
    bodyTag = <body style="margin:0; background:#fff;">
    wrap = <a href="javascript:close();"> | </a>
    width = 800m
    height = 600m
    crop.data = file:current:crop
    JSwindow = 1
    JSwindow.newWindow = 1
    directImageLink = 0
}

page = PAGE
page.typeNum = 0
page.10 < styles.content.get

# Include CSS for image alignment styles (image-left, image-center, image-right)
page.includeCSS.rte_ckeditor_image_alignment = EXT:rte_ckeditor_image/Resources/Public/Css/image-alignment.css
TYPOSCRIPT;

// Insert or update sys_template with BOTH constants and config
// Use ON DUPLICATE KEY UPDATE to ensure our TypoScript is applied even if TYPO3 setup pre-created it
$stmt = $pdo->prepare("INSERT INTO sys_template (uid, pid, root, title, clear, constants, config, hidden, deleted, tstamp, crdate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE constants = VALUES(constants), config = VALUES(config), root = 1, clear = 1");
$stmt->execute([1, 1, 1, 'Root', 1, $tsConstants, $tsConfig, 0, 0, $now, $now]);
echo "sys_template record ensured with constants and config\n";
DBSETUP_EOF

        # site-config.yaml - Site configuration
        cat > "${E2E_SCRIPTS}/site-config.yaml" << 'SITECONFIG_EOF'
rootPageId: 1
base: /
languages:
  - title: English
    enabled: true
    languageId: 0
    base: /
    locale: en_US.UTF-8
    navigationTitle: English
    flag: us
dependencies:
  - typo3/fluid-styled-content
  - netresearch/rte-ckeditor-image
SITECONFIG_EOF

        # create-test-content.php - Create test image and content records
        cat > "${E2E_SCRIPTS}/create-test-content.php" << 'CONTENT_EOF'
<?php
// Create test image
$im = imagecreatetruecolor(800, 600);
$blue = imagecolorallocate($im, 0, 100, 200);
$white = imagecolorallocate($im, 255, 255, 255);
imagefill($im, 0, 0, $blue);
imagestring($im, 5, 300, 280, 'E2E Test Image', $white);
imagejpeg($im, 'public/fileadmin/user_upload/example.jpg', 90);
imagedestroy($im);
echo "Test image created\n";

// Connect to MariaDB
$pdo = new PDO(
    'mysql:host=mariadb-e2e;port=3306;dbname=e2e_test',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$now = time();

// Create or update sys_file entry
$identifierHash = sha1('/user_upload/example.jpg');
$folderHash = sha1('/user_upload/');
$pdo->exec("INSERT INTO sys_file (uid, storage, identifier, identifier_hash, folder_hash, name, extension, mime_type, size, tstamp, creation_date)
            VALUES (1, 1, '/user_upload/example.jpg', '$identifierHash', '$folderHash', 'example.jpg', 'jpg', 'image/jpeg', 48000, $now, $now)
            ON DUPLICATE KEY UPDATE storage = 1, identifier = '/user_upload/example.jpg', identifier_hash = '$identifierHash', folder_hash = '$folderHash'");
echo "sys_file record created\n";

// Insert test content with RTE image (no caption)
$bodytext = '<p>This is a test page with an RTE image:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Example" width="800" height="600" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p><p>Click the image to see click-to-enlarge.</p>';
$stmt = $pdo->prepare("INSERT INTO tt_content (pid, CType, header, bodytext, hidden, deleted, tstamp, crdate, colPos, sorting) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([1, 'text', 'RTE CKEditor Image Demo', $bodytext, 0, 0, $now, $now, 0, 256]);
echo "tt_content record created\n";

// Insert test content with RTE image WITH CAPTION (to test for <p>&nbsp;</p> artifacts)
// This triggers the WithCaption.html template which has internal whitespace between img and figcaption
$bodytextCaption = '<p>Image with caption test:</p><p><img src="fileadmin/user_upload/example.jpg" alt="Caption Test" width="400" height="300" data-htmlarea-file-uid="1" data-caption="Test Caption Text" /></p>';
$stmt->execute([1, 'text', 'Caption Test', $bodytextCaption, 0, 0, $now, $now, 0, 512]);
echo "tt_content record with caption created\n";

// Insert test content with LINKED IMAGE (issue #565 - duplicate links)
// This tests that linked images render with a single <a> tag, not duplicated
$bodytextLinked = '<p>Test linked image (should have single link wrapper):</p><a href="https://example.com" target="_blank" title="Example Link" class="test-linked-image"><img src="fileadmin/user_upload/example.jpg" alt="Linked Image" width="400" height="300" data-htmlarea-file-uid="1" /></a><p>The image above should link to example.com with a single &lt;a&gt; tag.</p>';
$stmt->execute([1, 'text', 'Linked Image Test (#565)', $bodytextLinked, 0, 0, $now, $now, 0, 768]);
echo "tt_content record with linked image created\n";

// Insert test content with LINKED IMAGE with CAPTION using data-caption attribute
// This tests that linked images with captions render correctly via the renderImages handler
// Note: We DON'T use raw <figure> because parseFunc_RTE.tags.figure.preUserFunc handles
// figure-wrapped images, but linked images inside figures have complex processing
// The simpler case tests link + data-caption combination
$bodytextFigureLinked = '<p>Test linked image with caption:</p><p><a href="https://netresearch.de" target="_blank" class="test-figure-linked"><img src="fileadmin/user_upload/example.jpg" alt="Captioned Linked Image" width="400" height="300" data-htmlarea-file-uid="1" /></a></p>';
$stmt->execute([1, 'text', 'Figure Linked Image Test', $bodytextFigureLinked, 0, 0, $now, $now, 0, 1024]);
echo "tt_content record with linked image created\n";

// Insert test content with standalone linked image (no figure, no caption) for regression test
$bodytextSimpleLinked = '<p>Simple linked image without caption:</p><p><a href="https://typo3.org" class="test-simple-link"><img src="fileadmin/user_upload/example.jpg" alt="Simple Link" width="300" height="225" data-htmlarea-file-uid="1" /></a></p>';
$stmt->execute([1, 'text', 'Simple Linked Image', $bodytextSimpleLinked, 0, 0, $now, $now, 0, 1280]);
echo "tt_content record with simple linked image created\n";

// UID 6: Styled/Alignment Images (needed by image-styles.spec.ts)
$bodytextStyles = '<p>Images with alignment classes:</p>'
    . '<p><img class="image-left" src="fileadmin/user_upload/example.jpg" alt="Left Aligned" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-right" src="fileadmin/user_upload/example.jpg" alt="Right Aligned" width="300" height="225" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-center" src="fileadmin/user_upload/example.jpg" alt="Center Aligned" width="400" height="300" data-htmlarea-file-uid="1" /></p>'
    . '<p><img class="image-block" src="fileadmin/user_upload/example.jpg" alt="Block Image" width="400" height="300" data-htmlarea-file-uid="1" /></p>'
    . '<figure class="image-center"><img src="fileadmin/user_upload/example.jpg" alt="Centered Figure" width="400" height="300" data-htmlarea-file-uid="1" /><figcaption>Centered figure with caption</figcaption></figure>';
$stmt->execute([1, 'text', 'Styled/Alignment Images', $bodytextStyles, 0, 0, $now, $now, 0, 1536]);
echo "tt_content record with styled/alignment images created\n";

// UID 7: Inline Images (needed by inline-images.spec.ts and inline-image-editing.spec.ts)
$bodytextInline = '<p>Text before <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Inline Example" width="100" height="75" data-htmlarea-file-uid="1" /> text after.</p>'
    . '<p>A linked inline image: <a href="https://example.com"><img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Linked Inline" width="80" height="60" data-htmlarea-file-uid="1" /></a> in text.</p>'
    . '<p>Multiple inline images: <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="First Inline" width="50" height="38" data-htmlarea-file-uid="1" /> and <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="Second Inline" width="50" height="38" data-htmlarea-file-uid="1" /> in one paragraph.</p>';
$stmt->execute([1, 'text', 'Inline Images', $bodytextInline, 0, 0, $now, $now, 0, 1792]);
echo "tt_content record with inline images created\n";

// UID 8: Inline Image Complex Patterns (needed by inline-image-patterns.spec.ts)
$bodytextInlinePatterns = '<p>Link with inline image at start:</p>'
    . '<p><a href="https://docs.example.com"><img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="docs" width="16" height="16" data-htmlarea-file-uid="1" /> Documentation</a></p>'
    . '<p>Link with inline image at end:</p>'
    . '<p><a href="https://download.example.com">Get the latest version <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="download" width="20" height="20" data-htmlarea-file-uid="1" /></a></p>'
    . '<p>Link with text before and after image:</p>'
    . '<p><a href="https://example.com">Check our <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="icon" width="16" height="16" data-htmlarea-file-uid="1" /> documentation</a></p>'
    . '<table><tr><td>Feature <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="feature" width="24" height="24" data-htmlarea-file-uid="1" /></td><td>Works great</td></tr></table>'
    . '<ul><li>Support for <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="feature" width="20" height="20" data-htmlarea-file-uid="1" /> inline images</li></ul>'
    . '<h3>Features <img class="image-inline" src="fileadmin/user_upload/example.jpg" alt="feature" width="24" height="24" data-htmlarea-file-uid="1" /></h3>';
$stmt->execute([1, 'text', 'Inline Image Complex Patterns', $bodytextInlinePatterns, 0, 0, $now, $now, 0, 2048]);
echo "tt_content record with inline image complex patterns created\n";

// UID 9: Multiple Popup/Zoom Images (needed by click-to-enlarge.spec.ts "multiple images all have popup functionality")
$bodytextMultiZoom = '<p>Multiple images with click-to-enlarge:</p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="popup1" width="300" height="225" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="popup2" width="300" height="225" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p>'
    . '<p><img src="fileadmin/user_upload/example.jpg" alt="popup3" width="300" height="225" data-htmlarea-zoom="true" data-htmlarea-file-uid="1" /></p>';
$stmt->execute([1, 'text', 'Multiple Popup Images', $bodytextMultiZoom, 0, 0, $now, $now, 0, 2304]);
echo "tt_content record with multiple popup images created\n";

// UID 10: Mixed Content with Text Links (needed by linked-image-backend.spec.ts "regular text links still show link balloon")
$bodytextMixed = '<p>Visit our <a href="https://example.com">website</a> for more info.</p><p><img src="fileadmin/user_upload/example.jpg" alt="Mixed Content" width="400" height="300" data-htmlarea-file-uid="1" /></p>';
$stmt->execute([1, 'text', 'Mixed Content with Text Links', $bodytextMixed, 0, 0, $now, $now, 0, 2560]);
echo "tt_content record with mixed content created\n";
CONTENT_EOF

        # Start MariaDB container for E2E tests
        # TYPO3's database:updateschema works properly with MariaDB (not SQLite)
        # Use network alias so PHP scripts can use a fixed hostname
        echo "Starting MariaDB container..."
        # Admin password used for TYPO3 setup and Playwright backend tests
        E2E_ADMIN_PASSWORD="${TYPO3_BACKEND_PASSWORD:-Joh316!!}"
        # Set default MariaDB version for E2E (DBMS_VERSION is only set for functional tests)
        E2E_MARIADB_IMAGE="docker.io/mariadb:10.11"
        ${CONTAINER_BIN} run -d --rm ${CI_PARAMS} \
            --name mariadb-e2e-${SUFFIX} \
            --network ${NETWORK} \
            --network-alias mariadb-e2e \
            -e MYSQL_ROOT_PASSWORD=root \
            -e MYSQL_DATABASE=e2e_test \
            ${E2E_MARIADB_IMAGE} \
            --character-set-server=utf8mb4 \
            --collation-server=utf8mb4_unicode_ci

        # Wait for MariaDB to be ready (use network alias since waitFor runs in a container)
        waitFor mariadb-e2e 3306

        # Install TYPO3 v13 with the extension FIRST (before starting services)
        echo "Installing TYPO3 v13 for E2E tests..."
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name e2e-setup-${SUFFIX} \
            -v ${E2E_ROOT}:/var/www/html \
            -v ${E2E_SCRIPTS}:/e2e-scripts:ro \
            -v ${ROOT_DIR}:/extension:ro \
            -w /var/www/html \
            -e COMPOSER_CACHE_DIR=/.cache/composer \
            ${IMAGE_PHP} /bin/bash -c "
                # Create TYPO3 project (--no-scripts to prevent DB access before setup)
                composer create-project typo3/cms-base-distribution:^13.4 . --no-interaction --no-progress --no-scripts

                # Install ALL packages with --no-scripts FIRST, so database:updateschema knows about all tables
                # Mount extension at /extension and use that path for composer
                composer config repositories.local path /extension
                composer require netresearch/rte-ckeditor-image:@dev --no-interaction --no-progress --no-scripts
                composer require typo3/cms-fluid-styled-content typo3/cms-reports --no-interaction --no-progress --no-scripts

                # Install typo3-console for database:updateschema command (not in TYPO3 Core)
                composer require helhum/typo3-console --no-interaction --no-progress --no-scripts

                # NOW run composer install to execute ALL Composer scripts
                # This registers TYPO3 commands, sets up autoloading, and configures extensions
                # Must be done AFTER all packages are added but BEFORE TYPO3 setup
                echo 'Running Composer scripts to register TYPO3 commands...'
                composer install --no-interaction --no-progress

                # Use TYPO3 setup command for proper installation with MariaDB
                # All env vars prevent interactive prompts
                # Use network alias 'mariadb-e2e' for database host
                TYPO3_SETUP_ADMIN_USERNAME=admin \
                TYPO3_SETUP_ADMIN_PASSWORD="${E2E_ADMIN_PASSWORD}" \
                TYPO3_SETUP_ADMIN_EMAIL='admin@example.com' \
                vendor/bin/typo3 setup \
                    --driver=mysqli \
                    --host=mariadb-e2e \
                    --port=3306 \
                    --dbname=e2e_test \
                    --username=root \
                    --password=root \
                    --server-type=other \
                    --no-interaction \
                    --force || exit 1

                # Copy configuration files from mounted scripts
                mkdir -p config/system
                cp /e2e-scripts/additional.php config/system/additional.php

                # CRITICAL: Inject trustedHostsPattern directly into settings.php
                # This MUST be done because TYPO3 checks trustedHostsPattern BEFORE
                # loading additional.php or environment variables
                echo \"Injecting trustedHostsPattern into settings.php...\"
                sed -i \"s/'SYS' => \\[/'SYS' => [\\n        'trustedHostsPattern' => '.*',/\" config/system/settings.php

                # Verify the change was applied
                grep -q \"trustedHostsPattern\" config/system/settings.php && echo \"trustedHostsPattern injected successfully\" || echo \"WARNING: trustedHostsPattern injection failed\"

                # Setup extensions (configures extensions, doesn't create tables)
                vendor/bin/typo3 extension:setup || exit 1

                # Create database tables - this works correctly with MariaDB
                echo 'Creating database tables...'
                vendor/bin/typo3 database:updateschema '*' --verbose 2>&1 || exit 1

                # Create site configuration (needed for frontend rendering)
                mkdir -p config/sites/main
                cp /e2e-scripts/site-config.yaml config/sites/main/config.yaml

                # Insert required database records (pages, sys_template)
                php /e2e-scripts/db-setup.php || exit 1

                # Create test content (sys_file, tt_content)
                mkdir -p public/fileadmin/user_upload
                php /e2e-scripts/create-test-content.php || exit 1

                # Set permissions BEFORE cache operations
                chmod -R 777 var/ public/typo3temp/ public/fileadmin/

                echo '[DEBUG] Setup container finishing'
            "

        # Run cache operations in a SEPARATE container to isolate any issues
        echo "Running cache warmup in separate container..."
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name e2e-cache-${SUFFIX} \
            -v ${E2E_ROOT}:/var/www/html \
            -v ${ROOT_DIR}:/extension:ro \
            -w /var/www/html \
            ${IMAGE_PHP} /bin/bash -c "
                echo '[CACHE] Flushing caches...'
                vendor/bin/typo3 cache:flush || echo '[CACHE] cache:flush failed'
                echo '[CACHE] Warming up caches (rebuilds DI container)...'
                vendor/bin/typo3 cache:warmup || echo '[CACHE] cache:warmup failed'
                echo '[CACHE] Checking DI cache...'
                ls var/cache/code/di/ || echo '[CACHE] DI cache not found'
                echo '[SITE] Listing configured sites...'
                vendor/bin/typo3 site:list || echo '[SITE] site:list failed'
                echo '[SITE] Site configuration details...'
                vendor/bin/typo3 site:show main 2>/dev/null || echo '[SITE] No site named main found'
                echo '[CACHE] Done'
            "

        # Start PHP built-in web server (simpler than Apache + PHP-FPM)
        # This is the approach used by TYPO3 Core for functional testing
        # trustedHostsPattern is set directly in settings.php during setup
        echo "Starting PHP built-in web server..."
        ${CONTAINER_BIN} run -d --rm ${CI_PARAMS} \
            --name webserver-e2e-${SUFFIX} \
            --network ${NETWORK} \
            -v ${E2E_ROOT}:/var/www/html \
            -v ${ROOT_DIR}:/extension:ro \
            -w /var/www/html \
            ${IMAGE_PHP} php -S 0.0.0.0:80 -t public/

        # Wait for web server to be ready
        waitFor webserver-e2e-${SUFFIX} 80

        # Give services a moment to stabilize
        sleep 2

        # Debug: check what's in the document root
        echo "DEBUG: Checking document root contents..."
        ${CONTAINER_BIN} exec webserver-e2e-${SUFFIX} ls -la /var/www/html/public/ | head -20

        echo ""
        echo "DEBUG: Checking if index.php exists..."
        ${CONTAINER_BIN} exec webserver-e2e-${SUFFIX} head -5 /var/www/html/public/index.php || echo "index.php not found!"

        echo ""
        echo "DEBUG: Fetching page content to check rendering..."
        ${CONTAINER_BIN} run --rm ${CI_PARAMS} \
            --name curl-debug-${SUFFIX} \
            --network ${NETWORK} \
            ${IMAGE_PHP} curl -sS -v http://webserver-e2e-${SUFFIX}:80/ 2>&1 | head -100

        echo ""
        echo "DEBUG: Checking TYPO3 error logs..."
        ${CONTAINER_BIN} exec webserver-e2e-${SUFFIX} /bin/bash -c "if [ -f var/log/typo3_*.log ]; then tail -100 var/log/typo3_*.log; else echo 'No TYPO3 log files found'; fi" 2>/dev/null || true

        echo ""
        echo "DEBUG: Checking PHP built-in server stderr for errors..."
        ${CONTAINER_BIN} logs webserver-e2e-${SUFFIX} 2>&1 | tail -50 || true

        echo "Running Playwright E2E tests..."

        # Run Playwright tests
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name playwright-e2e-${SUFFIX} \
            -v ${ROOT_DIR}/Tests/E2E:/app \
            -v ${ROOT_DIR}/Build/test-results:/app/test-results \
            -w /app \
            -e BASE_URL=http://webserver-e2e-${SUFFIX}:80 \
            -e TYPO3_BACKEND_PASSWORD="${E2E_ADMIN_PASSWORD}" \
            -e CI=true \
            ${IMAGE_PLAYWRIGHT} /bin/bash -c "
                # Skip npm install if node_modules exists (pre-cached in CI)
                if [ ! -d node_modules ] || [ ! -f node_modules/.package-lock.json ]; then
                    npm ci --ignore-scripts 2>/dev/null || npm install --no-save
                fi
                npx playwright test ${EXTRA_TEST_OPTIONS} $@
            "
        SUITE_EXIT_CODE=$?

        # Stop containers
        ${CONTAINER_BIN} kill webserver-e2e-${SUFFIX} >/dev/null 2>&1 || true
        ${CONTAINER_BIN} kill mariadb-e2e-${SUFFIX} >/dev/null 2>&1 || true

        # Clean up E2E directories (keep for debugging if failed)
        if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
            rm -rf "${E2E_ROOT}"
            rm -rf "${E2E_SCRIPTS}"
        else
            echo "E2E test environment preserved at ${E2E_ROOT} for debugging"
        fi
        ;;
    coveralls)
        COMMAND=(php -dxdebug.mode=coverage ./.Build/bin/php-coveralls --coverage_clover=./.Build/logs/clover.xml --json_path=./.Build/logs/coveralls-upload.json -v)
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-coverals-${SUFFIX} -e XDEBUG_MODE=coverage -e XDEBUG_TRIGGER=foo -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    functional)
        COMMAND=(.Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml --exclude-group not-${DBMS} ${EXTRA_TEST_OPTIONS} "$@")
        case ${DBMS} in
            mariadb)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mariadb-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MARIADB} >/dev/null
                waitFor mariadb-func-${SUFFIX} 3306
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mariadb-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            mysql)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mysql-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MYSQL} >/dev/null
                waitFor mysql-func-${SUFFIX} 3306
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mysql-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            postgres)
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name postgres-func-${SUFFIX} --network ${NETWORK} -d -e POSTGRES_PASSWORD=funcp -e POSTGRES_USER=funcu --tmpfs /var/lib/postgresql/data:rw,noexec,nosuid ${IMAGE_POSTGRES} >/dev/null
                waitFor postgres-func-${SUFFIX} 5432
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_pgsql -e typo3DatabaseName=bamboo -e typo3DatabaseUsername=funcu -e typo3DatabaseHost=postgres-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            sqlite)
                # create sqlite tmpfs mount typo3temp/var/tests/functional-sqlite-dbs/ to avoid permission issues
                mkdir -p "${ROOT_DIR}/typo3temp/var/tests/functional-sqlite-dbs/"
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_sqlite --tmpfs ${ROOT_DIR}/typo3temp/var/tests/functional-sqlite-dbs/:rw,noexec,nosuid"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
        esac
        ;;
    fuzz)
        # Run fuzz tests using php-fuzzer
        # Default: run ImageAttributeParser fuzzer for 60 seconds
        FUZZ_TARGET="${1:-Tests/Fuzz/ImageAttributeParserTarget.php}"
        FUZZ_CORPUS="Tests/Fuzz/corpus/image-parser"
        FUZZ_MAX_RUNS="${2:-10000}"

        # Determine corpus based on target
        if [[ "${FUZZ_TARGET}" == *"SoftReference"* ]]; then
            FUZZ_CORPUS="Tests/Fuzz/corpus/softref-parser"
        fi

        echo "Fuzzing target: ${FUZZ_TARGET}"
        echo "Corpus: ${FUZZ_CORPUS}"
        echo "Max runs: ${FUZZ_MAX_RUNS}"

        COMMAND=(.Build/bin/php-fuzzer fuzz "${FUZZ_TARGET}" "${FUZZ_CORPUS}" --max-runs "${FUZZ_MAX_RUNS}")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name fuzz-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    mutation)
        # Run mutation tests using Infection
        # First run unit tests with coverage, then run mutation testing
        echo "Running unit tests with coverage for mutation testing..."
        COMMAND=(.Build/bin/phpunit -c Build/phpunit/UnitTests.xml --coverage-xml=.Build/logs/coverage-xml --log-junit=.Build/logs/coverage-xml/junit.xml)
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-coverage-${SUFFIX} -e XDEBUG_MODE=coverage -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} "${COMMAND[@]}"
        UNIT_EXIT_CODE=$?

        if [ ${UNIT_EXIT_CODE} -ne 0 ]; then
            echo "Unit tests failed, skipping mutation testing"
            SUITE_EXIT_CODE=${UNIT_EXIT_CODE}
        else
            echo "Running mutation tests..."
            COMMAND=(.Build/bin/infection --configuration=infection.json5 --threads=4 --coverage=.Build/logs/coverage-xml --skip-initial-tests "$@")
            ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name mutation-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
            SUITE_EXIT_CODE=$?
        fi
        ;;
    lint)
        COMMAND="php -v | grep '^PHP'; find . -name '*.php' ! -path '*.Build/*' -print0 | xargs -0 -n1 -P4 php -dxdebug.mode=off -l >/dev/null"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/bash -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    unit)
        COMMAND=(.Build/bin/phpunit -c Build/phpunit/UnitTests.xml --exclude-group not-${DBMS} ${EXTRA_TEST_OPTIONS} "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    update)
        # pull typo3/core-testing-*:latest versions of those ones that exist locally
        echo "> pull ghcr.io/typo3/core-testing-*:latest versions of those ones that exist locally"
        ${CONTAINER_BIN} images ghcr.io/typo3/core-testing-*:latest --format "{{.Repository}}:latest" | xargs -I {} ${CONTAINER_BIN} pull {}
        echo ""
        # remove "dangling" typo3/core-testing-* images (those tagged as <none>)
        echo "> remove \"dangling\" ghcr.io/typo3/core-testing-* images (those tagged as <none>)"
        ${CONTAINER_BIN} images --filter "reference=ghcr.io/typo3/core-testing-*" --filter "dangling=true" --format "{{.ID}}" | xargs -I {} ${CONTAINER_BIN} rmi {}
        echo ""
        ;;
    *)
        loadHelp
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
        ;;
esac

cleanUp

# Print summary
echo "" >&2
echo "###########################################################################" >&2
echo "Result of ${TEST_SUITE}" >&2
if [[ ${IS_CI} -eq 1 ]]; then
    echo "Environment: CI" >&2
else
    echo "Environment: local" >&2
fi
echo "PHP: ${PHP_VERSION}" >&2
echo "TYPO3: ${TYPO3_VERSION}" >&2
echo "CONTAINER_BIN: ${CONTAINER_BIN}"
if [[ ${TEST_SUITE} =~ ^functional$ ]]; then
    case "${DBMS}" in
        mariadb|mysql)
            echo "DBMS: ${DBMS}  version ${DBMS_VERSION}  driver ${DATABASE_DRIVER}" >&2
            ;;
        postgres)
            echo "DBMS: ${DBMS}  version ${DBMS_VERSION}  driver pdo_pgsql" >&2
            ;;
        sqlite)
            echo "DBMS: ${DBMS}  driver pdo_sqlite" >&2
            ;;
    esac
fi
if [[ -n ${EXTRA_TEST_OPTIONS} ]]; then
    echo " Note: Using -e is deprecated. Simply add the options at the end of the command."
    echo " Instead of: Build/Scripts/runTests.sh -s ${TEST_SUITE} -e '${EXTRA_TEST_OPTIONS}' $@"
    echo " use:        Build/Scripts/runTests.sh -s ${TEST_SUITE} -- ${EXTRA_TEST_OPTIONS} $@"
fi
if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
    echo "SUCCESS" >&2
else
    echo "FAILURE" >&2
fi
echo "###########################################################################" >&2
echo "" >&2

# Exit with code of test suite - This script return non-zero if the executed test failed.
exit $SUITE_EXIT_CODE