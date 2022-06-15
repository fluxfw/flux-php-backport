<?php

namespace FluxPhpBackport\Adapter;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PhpBackport
{

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
            ["Change static return type to self", "/(\))(\s*:\s*\??)(static)([\s{;|])/", "$1/*$2$3*/$2self$4"]
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
