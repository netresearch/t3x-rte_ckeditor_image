#!/bin/bash

# Copyright (c) 2025-2026 Netresearch DTT GmbH
# SPDX-License-Identifier: AGPL-3.0-or-later

# convenience script for cleaning up after running test suite locally

# currently not necessary, but might be if ci adds platform requirement
#composer config --unset platform.php
#composer config --unset platform

rm -rf .Build
rm -f composer.lock
rm -f Build/testing-docker/.env
