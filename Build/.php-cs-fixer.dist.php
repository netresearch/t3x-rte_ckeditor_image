<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$createConfig = require __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/config.php';

return $createConfig(<<<'EOF'
    Copyright (c) 2025-2026 Netresearch DTT GmbH
    SPDX-License-Identifier: AGPL-3.0-or-later
    EOF, __DIR__ . '/..');
