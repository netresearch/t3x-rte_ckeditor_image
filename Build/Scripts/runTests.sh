#!/usr/bin/env bash

#
# TYPO3 extension tea test runner based on docker.
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
    # shellcheck disable=SC2181 # Disabled because we don‘t want to move the long line between the brackets
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

loadHelp() {
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
TEST_SUITE="functional"
CORE_VERSION="12.4"
DBMS="sqlite"
DBMS_VERSION=""
PHP_VERSION="8.1"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
EXTRA_TEST_OPTIONS=""
PHPUNIT_RANDOM=""
# CGLCHECK_DRY_RUN is a more generic dry-run switch not limited to CGL
CGLCHECK_DRY_RUN=0
DATABASE_DRIVER=""
CONTAINER_BIN=""
COMPOSER_ROOT_VERSION="12.0.x-dev"
NODE_VERSION=22
HELP_TEXT_NPM_CI="Now running \'npm ci --silent\'."
HELP_TEXT_NPM_FAILURE="npm clean-install has failed. Please run \'${0} -s npm ci\' to explore."
CONTAINER_INTERACTIVE="-it --init"
HOST_UID=$(id -u)
HOST_PID=$(id -g)
USERSET=""
SUFFIX="$RANDOM"
NETWORK="netresearch-rteckeditor-${SUFFIX}"
CI_PARAMS="${CI_PARAMS:-}"
CONTAINER_HOST="host.docker.internal"
# shellcheck disable=SC2034 # This variable will be needed when we try to clean up the root folder
PHPSTAN_CONFIG_FILE="phpstan.neon"
IS_CORE_CI=0

# Option parsing updates above default vars
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts "a:b:s:d:i:p:e:t:xy:o:nhu" OPT; do
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
            if ! [[ ${PHP_VERSION} =~ ^(7.4|8.0|8.1|8.2|8.3|8.4)$ ]]; then
                INVALID_OPTIONS+=("-p ${OPTARG}")
            fi
            ;;
        e)
            EXTRA_TEST_OPTIONS=${OPTARG}
            ;;
        t)
            CORE_VERSION=${OPTARG}
            if ! [[ ${CORE_VERSION} =~ ^(11.5|12.4)$ ]]; then
                INVALID_OPTIONS+=("-t ${OPTARG}")
            fi
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        o)
            PHPUNIT_RANDOM="--random-order-seed=${OPTARG}"
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
    IS_CORE_CI=1
    CONTAINER_INTERACTIVE=""
fi

# determine default container binary to use: 1. podman 2. docker
if [[ -z "${CONTAINER_BIN}" ]]; then
    if type "podman" >/dev/null 2>&1; then
        CONTAINER_BIN="podman"
    elif type "docker" >/dev/null 2>&1; then
        CONTAINER_BIN="docker"
    fi
fi

if [ "$(uname)" != "Darwin" ] && [ "${CONTAINER_BIN}" == "docker" ]; then
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
IMAGE_NODE="docker.io/node:${NODE_VERSION}-alpine"
IMAGE_ALPINE="docker.io/alpine:3.8"
IMAGE_SHELLCHECK="docker.io/koalaman/shellcheck:v0.10.0"
IMAGE_DOCS="ghcr.io/typo3-documentation/render-guides:latest"
IMAGE_MARIADB="docker.io/mariadb:${DBMS_VERSION}"
IMAGE_MYSQL="docker.io/mysql:${DBMS_VERSION}"
IMAGE_POSTGRES="docker.io/postgres:${DBMS_VERSION}-alpine"

# Set $1 to first mass argument, this is the optional test file or test directory to execute
shift $((OPTIND - 1))
TEST_FILE=${1}

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
        if [ "${CGLCHECK_DRY_RUN}" -eq 1 ]; then
            COMMAND="composer ci:php:cs-fixer"
        else
            COMMAND="composer fix:php:cs"
        fi
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    clean)
        cleanCacheFiles
        cleanRenderedDocumentationFiles
        cleanTestFiles
        ;;
    cleanCache)
        cleanCacheFiles
        ;;
    cleanRenderedDocumentation)
        cleanRenderedDocumentationFiles
        ;;
    cleanTests)
        cleanTestFiles
        ;;
    composer)
        COMMAND_PARTS=(composer "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} "${COMMAND_PARTS[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    composerUpdateMax)
        COMMAND="composer config --unset platform.php; composer require --no-ansi --no-interaction --no-progress --no-install typo3/cms-core:"^${CORE_VERSION}"; composer update --no-progress --no-interaction; composer dumpautoload; composer show"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-install-max-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    composerUpdateMin)
        COMMAND="composer config platform.php ${PHP_VERSION}.0; composer require --no-ansi --no-interaction --no-progress --no-install typo3/cms-core:"^${CORE_VERSION}"; composer update --prefer-lowest --no-progress --no-interaction; composer dumpautoload; composer show"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-install-min-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    composerNormalize)
        COMMAND="composer ci:composer:normalize"
        if [ "${CGLCHECK_DRY_RUN}" -eq 1 ]; then
            COMMAND="composer ci:composer:normalize"
        else
            COMMAND="composer fix:composer:normalize"
        fi
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-normalize-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_DOCS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    composerUnused)
        COMMAND="composer ci:composer:unused"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-unused-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    docsGenerate)
        mkdir -p Documentation-GENERATED-temp
        chown -R ${HOST_UID}:${HOST_PID} Documentation-GENERATED-temp
        ${CONTAINER_BIN} run ${CONTAINER_INTERACTIVE} --rm --pull always ${USERSET} -v "${ROOT_DIR}":/project ${IMAGE_DOCS} --config=Documentation --fail-on-log
        SUITE_EXIT_CODE=$?
        ;;
    shellcheck)
        ${CONTAINER_BIN} run ${CONTAINER_INTERACTIVE} --rm --pull always ${USERSET} -v "${ROOT_DIR}":/project:ro -e SHELLCHECK_OPTS="-e SC2086" ${IMAGE_SHELLCHECK} /project/Build/Scripts/runTests.sh
        SUITE_EXIT_CODE=$?
        ;;
    functional)
        [ -z "${TEST_FILE}" ] && TEST_FILE="Tests/Functional"
        COMMAND=".Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml --exclude-group not-${DBMS} ${EXTRA_TEST_OPTIONS} ${TEST_FILE}"
        case ${DBMS} in
            mariadb)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mariadb-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MARIADB} >/dev/null
                waitFor mariadb-func-${SUFFIX} 3306
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mariadb-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} ${COMMAND}
                SUITE_EXIT_CODE=$?
                ;;
            mysql)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mysql-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MYSQL} >/dev/null
                waitFor mysql-func-${SUFFIX} 3306
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mysql-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} ${COMMAND}
                SUITE_EXIT_CODE=$?
                ;;
            postgres)
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name postgres-func-${SUFFIX} --network ${NETWORK} -d -e POSTGRES_PASSWORD=funcp -e POSTGRES_USER=funcu --tmpfs /var/lib/postgresql/data:rw,noexec,nosuid ${IMAGE_POSTGRES} >/dev/null
                waitFor postgres-func-${SUFFIX} 5432
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_pgsql -e typo3DatabaseName=bamboo -e typo3DatabaseUsername=funcu -e typo3DatabaseHost=postgres-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} ${COMMAND}
                SUITE_EXIT_CODE=$?
                ;;
            sqlite)
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_sqlite"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} ${COMMAND}
                SUITE_EXIT_CODE=$?
                ;;
        esac
        ;;
    lintTypoScript)
        COMMAND="composer ci:typoscript:lint"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintPhp)
        COMMAND="composer ci:php:lint"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintJs)
        if [ "${CGLCHECK_DRY_RUN}" -eq 1 ]; then
            COMMAND="echo ${HELP_TEXT_NPM_CI}; npm ci --silent || { echo ${HELP_TEXT_NPM_FAILURE}; exit 1; } && npm run ci:lint:js"
        else
            COMMAND="echo ${HELP_TEXT_NPM_CI}; npm ci --silent || { echo ${HELP_TEXT_NPM_FAILURE}; exit 1; } && npm run fix:lint:js"
        fi
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name npm-command-${SUFFIX} ${IMAGE_NODE} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintCss)
        if [ "${CGLCHECK_DRY_RUN}" -eq 1 ]; then
            COMMAND="echo ${HELP_TEXT_NPM_CI}; npm ci --silent || { echo ${HELP_TEXT_NPM_FAILURE}; exit 1; } && npm run ci:lint:css"
        else
            COMMAND="echo ${HELP_TEXT_NPM_CI}; npm ci --silent || { echo ${HELP_TEXT_NPM_FAILURE}; exit 1; } && npm run fix:lint:css"
        fi
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name npm-command-${SUFFIX} ${IMAGE_NODE} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintJson)
        COMMAND="composer ci:json:lint"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintYaml)
        COMMAND="composer ci:yaml:lint"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    npm)
        COMMAND_PARTS=(npm "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name npm-command-${SUFFIX} ${IMAGE_NODE} "${COMMAND_PARTS[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    phpstan)
        COMMAND="composer ci:php:stan"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    phpstanGenerateBaseline)
        COMMAND="composer phpstan:baseline"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-command-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    unit)
        [ -z "${TEST_FILE}" ] && TEST_FILE="Tests/Unit"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} .Build/bin/phpunit -c Build/phpunit/UnitTests.xml ${EXTRA_TEST_OPTIONS} ${TEST_FILE}
        SUITE_EXIT_CODE=$?
        ;;
    unitRandom)
        [ -z "${TEST_FILE}" ] && TEST_FILE="Tests/Unit"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-random-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} .Build/bin/phpunit -c Build/phpunit/UnitTests.xml --order-by=random ${EXTRA_TEST_OPTIONS} ${PHPUNIT_RANDOM} ${TEST_FILE}
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
        if [ ${CONTAINER_BIN} = "docker" ]; then
            ${CONTAINER_BIN} network rm ${NETWORK} >/dev/null
        else
            ${CONTAINER_BIN} network rm -f ${NETWORK} >/dev/null
        fi
        exit 1
        ;;
esac

cleanUp

# Print summary
echo "" >&2
echo "###########################################################################" >&2
echo "Result of ${TEST_SUITE}" >&2
if [[ ${IS_CORE_CI} -eq 1 ]]; then
    echo "Environment: CI" >&2
else
    echo "Environment: local" >&2
fi
if [[ ${TEST_SUITE} =~ ^(npm|lintCss|lintJs)$ ]]; then
    echo "NODE: ${NODE_VERSION}" >&2
else
    echo "PHP: ${PHP_VERSION}" >&2
    echo "TYPO3: ${CORE_VERSION}" >&2
fi
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
if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
    echo "SUCCESS" >&2
else
    echo "FAILURE" >&2
fi
echo "###########################################################################" >&2
echo "" >&2

# Exit with code of test suite - This script return non-zero if the executed test failed.
exit $SUITE_EXIT_CODE