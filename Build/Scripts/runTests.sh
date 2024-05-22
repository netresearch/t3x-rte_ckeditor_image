#!/usr/bin/env bash

# copied from EXT:enetcache:
#  https://github.com/lolli42/enetcache/blob/main/Build/Scripts/runTests.sh

#
# TYPO3 core test runner based on docker and docker-compose.
#

# Function to write a .env file in Build/testing-docker/local
# This is read by docker-compose and vars defined here are
# used in Build/testing-docker/local/docker-compose.yml
setUpDockerComposeDotEnv() {
    # Delete possibly existing local .env file if exists
    [ -e .env ] && rm .env
    # Set up a new .env file for docker-compose
    echo "COMPOSE_PROJECT_NAME=local" >> .env
    # To prevent access rights of files created by the testing, the docker image later
    # runs with the same user that is currently executing the script. docker-compose can't
    # use $UID directly itself since it is a shell variable and not an env variable, so
    # we have to set it explicitly here.
    echo "HOST_UID=`id -u`" >> .env
    # Your local home directory for composer and npm caching
    echo "HOST_HOME=${HOME}" >> .env
    # Your local user
    echo "ROOT_DIR"=${ROOT_DIR} >> .env
    echo "HOST_USER=${USER}" >> .env
    echo "TEST_FILE=${TEST_FILE}" >> .env
    echo "TYPO3_VERSION=${TYPO3_VERSION}" >> .env
    echo "PHP_XDEBUG_ON=${PHP_XDEBUG_ON}" >> .env
    echo "PHP_XDEBUG_PORT=${PHP_XDEBUG_PORT}" >> .env
    echo "PHP_VERSION=${PHP_VERSION}" >> .env
    echo "DOCKER_PHP_IMAGE=${DOCKER_PHP_IMAGE}" >> .env
    echo "EXTRA_TEST_OPTIONS=${EXTRA_TEST_OPTIONS}" >> .env
    echo "SCRIPT_VERBOSE=${SCRIPT_VERBOSE}" >> .env
}

# Load help text into $HELP
read -r -d '' HELP <<EOF
enetcache test runner. Execute unit test suite and some other details.
Also used by github actions for test execution.

Usage: $0 [options] [file]

No arguments: Run all unit tests with PHP 8.3

Options:
    -s <...>
        Specifies which test suite to run
            - composerUpdate: "composer update"
            - clean: clean up build and testing related files
            - lint: PHP linting
            - unit (default): PHP unit tests
            - functional: functional tests

    -d <mariadb|postgres|sqlite>
        Only with -s functional
        Specifies on which DBMS tests are performed
            - mariadb: use mariadb
            - postgres (default): use postgres
            - sqlite: use sqlite

    -p <8.3|8.2|8.1>
        Specifies the PHP minor version to be used
            - 8.3: (default) use PHP 8.3
            - 8.2: use PHP 8.2
            - 8.1: use PHP 8.1

    -t <12|13>
        Only with -s composerUpdate
        Specifies the TYPO3 core major version to be used
            - 12: (default) use TYPO3 core v12
            - 13: use TYPO3 core v13

    -e "<phpunit options>"
        Only with -s functional|unit
        Additional options to send to phpunit tests.
        For phpunit, options starting with "--" must be added after options starting with "-".
        Example -e "-v --filter canRetrieveValueWithGP" to enable verbose output AND filter tests
        named "canRetrieveValueWithGP"

    -x
        Only with -s unit
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -u
        Update existing typo3gmbh/phpXY:latest docker images. Maintenance call to docker pull latest
        versions of the main php images. The images are updated once in a while and only the youngest
        ones are supported by core testing. Use this if weird test errors occur. Also removes obsolete
        image versions of typo3gmbh/phpXY.

    -v
        Enable verbose script output. Shows variables and docker commands.

    -h
        Show this help.

EOF

# Test if docker-compose exists, else exit out with error
if ! type "docker-compose" > /dev/null; then
  echo "This script relies on docker and docker-compose. Please install" >&2
  exit 1
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called.
THIS_SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
cd "$THIS_SCRIPT_DIR" || exit 1

# Go to directory that contains the local docker-compose.yml file
cd ../testing-docker || exit 1

# Option defaults
ROOT_DIR=`realpath ${PWD}/../../`
TEST_SUITE="unit"
DBMS="postgres"
PHP_VERSION="8.3"
TYPO3_VERSION="12"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
EXTRA_TEST_OPTIONS=""
SCRIPT_VERBOSE=0

# Option parsing
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=();
# Simple option parsing based on getopts (! not getopt)
while getopts ":s:d:p:t:e:xy:huv" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        d)
            DBMS=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            ;;
        t)
            TYPO3_VERSION=${OPTARG}
            ;;
        e)
            EXTRA_TEST_OPTIONS=${OPTARG}
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        h)
            echo "${HELP}"
            exit 0
            ;;
        u)
            TEST_SUITE=update
            ;;
        v)
            SCRIPT_VERBOSE=1
            ;;
        \?)
            INVALID_OPTIONS+=(${OPTARG})
            ;;
        :)
            INVALID_OPTIONS+=(${OPTARG})
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "${HELP}" >&2
    exit 1
fi

# Move "7.2" to "php72", the latter is the docker container name
DOCKER_PHP_IMAGE=`echo "php${PHP_VERSION}" | sed -e 's/\.//'`

# Set $1 to first mass argument, this is the optional test file or test directory to execute
shift $((OPTIND - 1))
if [ -n "${1}" ]; then
    TEST_FILE="Web/typo3conf/ext/enetcache/${1}"
fi

if [ ${SCRIPT_VERBOSE} -eq 1 ]; then
    set -x
fi

# Suite execution
case ${TEST_SUITE} in
    clean)
        rm -rf ../../composer.lock ../../.Build/ ../../composer.json.testing
        ;;
    composerUpdate)
        setUpDockerComposeDotEnv
        cp ../../composer.json ../../composer.json.orig
        if [ -f "../../composer.json.testing" ]; then
            cp ../../composer.json ../../composer.json.orig
        fi
        docker-compose run composer_update
        cp ../../composer.json ../../composer.json.testing
        mv ../../composer.json.orig ../../composer.json
        SUITE_EXIT_CODE=$?
        docker-compose down
        ;;
    functional)
        setUpDockerComposeDotEnv
        case ${DBMS} in
            mariadb)
                docker-compose run functional_mariadb10
                SUITE_EXIT_CODE=$?
                ;;
            postgres)
                docker-compose run functional_postgres10
                SUITE_EXIT_CODE=$?
                ;;
            sqlite)
                # sqlite has a tmpfs as .Build/Web/typo3temp/var/tests/functional-sqlite-dbs/
                # Since docker is executed as root (yay!), the path to this dir is owned by
                # root if docker creates it. Thank you, docker. We create the path beforehand
                # to avoid permission issues.
                mkdir -p ${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/
                docker-compose run functional_sqlite
                SUITE_EXIT_CODE=$?
                ;;
            *)
                echo "Invalid -d option argument ${DBMS}" >&2
                echo >&2
                echo "${HELP}" >&2
                exit 1
        esac
        docker-compose down
        ;;
    lint)
        setUpDockerComposeDotEnv
        docker-compose run lint
        SUITE_EXIT_CODE=$?
        docker-compose down
        ;;
    unit)
        setUpDockerComposeDotEnv
        docker-compose run unit
        SUITE_EXIT_CODE=$?
        docker-compose down
        ;;
    update)
        # pull typo3/core-testing-*:latest versions of those ones that exist locally
        docker images typo3/core-testing-*:latest --format "{{.Repository}}:latest" | xargs -I {} docker pull {}
        # remove "dangling" typo3/core-testing-* images (those tagged as <none>)
        docker images typo3/core-testing-* --filter "dangling=true" --format "{{.ID}}" | xargs -I {} docker rmi {}
        ;;
    *)
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
esac

exit $SUITE_EXIT_CODE