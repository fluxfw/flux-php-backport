<?php

namespace FluxPhpBackport\Adapter;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PhpBackport
{

    private const AFTER_PARAMETER_TYPE = "[\s),;=]";
    private const AFTER_RETURN_TYPE = "[\s{;|=]";
    private const BEFORE_PARAMETER_TYPE = "[\s(,]";
    private const BEFORE_RETURN_TYPE_1 = "\)";
    private const BEFORE_RETURN_TYPE_2 = "\s*:\s*\??";
    private const PARAM_NAME = "[A-Za-z_][A-Za-z0-9_]*";
    private const VISIBILITY = "(public|protected|private)";


    private function __construct()
    {

    }


    public static function new() : static
    {
        return new static();
    }


    public function run() : void
    {
        global $argv;

        $folder = $argv[1];
        if (empty($folder)) {
            echo "Please pass a folder\n";
            die(1);
        }

        echo "Port PHP 8.1 back to PHP 7.4 in " . $folder . "\n\n";

        $replaces = [
            [
                "Change static return type to self",
                "/(" . static::BEFORE_RETURN_TYPE_1 . static::BEFORE_RETURN_TYPE_2 . ")(static)(" . static::AFTER_RETURN_TYPE . ")/",
                "$1/*$2*/self$3"
            ],
            [
                "Remove mixed return type",
                "/(" . static::BEFORE_RETURN_TYPE_1 . ")(" . static::BEFORE_RETURN_TYPE_2 . "mixed)(" . static::AFTER_RETURN_TYPE . ")/",
                "$1/*$2*/$3"
            ],
            [
                "Remove mixed parameter type",
                "/(" . static::BEFORE_PARAMETER_TYPE . ")(mixed)(\s*\\\$" . static::PARAM_NAME . static::AFTER_PARAMETER_TYPE . ")/",
                "$1/*$2*/$3"
            ],
            [
                "Remove readonly property modifier",
                "/(" . static::VISIBILITY . "\s+)(readonly)(\s+)/",
                "$1/*$3*/$4"
            ]
        ];

        $ext = [
            "php"
        ];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower(pathinfo($file->getFileName(), PATHINFO_EXTENSION)), $ext)) {
                continue;
            }

            echo "Process " . $file->getPathName() . "\n";

            $code = $old_code = file_get_contents($file->getPathName());

            foreach ($replaces as [$title, $search, $replace]) {
                if (preg_match($search, $code) < 1) {
                    continue;
                }

                echo "- " . $title . "\n";

                $new_code = preg_replace($search, $replace, $code);

                if (is_string($new_code)) {
                    $code = $new_code;
                }
            }

            if ($old_code !== $code) {
                echo "- Store\n";
                file_put_contents($file->getPathName(), $code);
            } else {
                echo "- No changes\n";
            }

            echo "\n";
        }
    }
}
