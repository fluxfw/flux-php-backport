#!/usr/bin/env php
<?php

require_once __DIR__ . "/../autoload.php";

use FluxPhpBackport\Adapter\PhpBackport;

PhpBackport::new()
    ->run();
