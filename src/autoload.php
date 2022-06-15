<?php

namespace FluxPhpBackport;

require_once __DIR__ . "/../libs/flux-autoload-api/autoload.php";

use FluxPhpBackport\Libs\FluxAutoloadApi\Adapter\Autoload\Psr4Autoload;
use FluxPhpBackport\Libs\FluxAutoloadApi\Adapter\Checker\PhpVersionChecker;

PhpVersionChecker::new(
    ">=8.1"
)
    ->checkAndDie(
        __NAMESPACE__
    );

Psr4Autoload::new(
    [
        __NAMESPACE__ => __DIR__
    ]
)
    ->autoload();
