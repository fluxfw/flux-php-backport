<?php

namespace FluxPhpBackport\Adapter;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PhpBackport
{

    private const EMPTY = "\s";
    private const INDENT = "    ";
    private const NEW_LINE = "\n";
    private const SPACE = "[ \t]";
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

        $folder = $argv[1] ?? "";
        if (empty($folder)) {
            echo "Please pass a folder" . static::NEW_LINE;
            die(1);
        }

        echo "Port PHP 8.1 back to PHP 7.4" . static::NEW_LINE . static::NEW_LINE;

        $EXT = [
            "php"
        ];

        $AFTER_PARAMETER_TYPE = "[" . static::EMPTY . "),;=]";
        $AFTER_RETURN_TYPE = "[" . static::EMPTY . "{;|=]";
        $BEFORE_PARAMETER_TYPE = "[" . static::EMPTY . "(,]";
        $BEFORE_RETURN_TYPE_1 = "\)";
        $BEFORE_RETURN_TYPE_2 = static::EMPTY . "*:" . static::EMPTY . "*\??";
        $BEFORE_RETURN_TYPE = $BEFORE_RETURN_TYPE_1 . $BEFORE_RETURN_TYPE_2;
        $PARAM_NAME = "[A-Za-z_][A-Za-z0-9_]*";

        $REPLACES = [
            [
                "Replace constructor properties with legacy syntax",
                "/(" . static::SPACE . "*\/\*[^\/*]*\*\/" . static::EMPTY . "*)?"
                . "(" . static::SPACE . "*" . static::VISIBILITY . static::EMPTY . "+function" . static::EMPTY . "+__construct" . static::EMPTY . "*\()"
                . "([^)]+)"
                . "(\)" . static::EMPTY . "*{)/",
                function (array $matches) : string {
                    $properties = [];
                    $parameters = [];
                    $assignments = [];

                    foreach (explode(",", $matches[4]) as $parameter) {
                        $parameter = trim($parameter);

                        if (empty($parameter)) {
                            continue;
                        }

                        if (preg_match("/^" . static::VISIBILITY . static::EMPTY . "/", $parameter) > 0) {
                            $properties[] = $parameter . ";";
                            $parameter_parts = array_reverse(preg_split("/" . static::EMPTY . "+/", $parameter));
                            $parameters[] = $parameter_parts[1] . " " . $parameter_parts[0] . ",";
                            $assignments[] = '$this->' . substr($parameter_parts[0], 1) . " = " . $parameter_parts[0] . ";";
                        } else {
                            $parameters[] = $parameter . ",";
                        }
                    }

                    if (empty($properties) || empty($parameters) || empty($assignments)) {
                        return $matches[0];
                    }

                    $parameters[count($parameters) - 1] = rtrim($parameters[count($parameters) - 1], ",");

                    return implode(static::NEW_LINE, array_map(fn(string $property) : string => static::INDENT . $property, $properties)) . static::NEW_LINE . static::NEW_LINE . static::NEW_LINE
                        . $matches[1] . $matches[2]
                        . static::NEW_LINE . implode(static::NEW_LINE, array_map(fn(string $parameter) : string => static::INDENT . static::INDENT . $parameter, $parameters)) . static::NEW_LINE
                        . static::INDENT . $matches[5]
                        . static::NEW_LINE . implode(static::NEW_LINE, array_map(fn(string $assignment) : string => static::INDENT . static::INDENT . $assignment, $assignments));
                }
            ],
            [
                "Remove readonly property modifier",
                "/(" . static::VISIBILITY . static::EMPTY . "+)(readonly)(" . static::EMPTY . "+)/",
                "$1/*$3*/$4"
            ],
            [
                "Remove mixed parameter type",
                "/(" . $BEFORE_PARAMETER_TYPE . ")(mixed)(" . static::EMPTY . "*\\\$" . $PARAM_NAME . $AFTER_PARAMETER_TYPE . ")/",
                "$1/*$2*/$3"
            ],
            [
                "Remove mixed return type",
                "/(" . $BEFORE_RETURN_TYPE_1 . ")(" . $BEFORE_RETURN_TYPE_2 . "mixed)(" . $AFTER_RETURN_TYPE . ")/",
                "$1/*$2*/$3"
            ],
            [
                "Replace static return type with self",
                "/(" . $BEFORE_RETURN_TYPE . ")(static)(" . $AFTER_RETURN_TYPE . ")/",
                "$1/*$2*/self$3"
            ]
        ];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower(pathinfo($file->getFileName(), PATHINFO_EXTENSION)), $EXT)) {
                continue;
            }

            echo "Process " . $file->getPathName() . static::NEW_LINE;

            $code = $old_code = file_get_contents($file->getPathName());

            foreach ($REPLACES as [$title, $search, $replace]) {
                if (preg_match($search, $code) < 1) {
                    continue;
                }

                echo "- " . $title . static::NEW_LINE;

                if (is_callable($replace)) {
                    $new_code = preg_replace_callback($search, $replace, $code);
                } else {
                    $new_code = preg_replace($search, $replace, $code);
                }

                if (is_string($new_code)) {
                    $code = $new_code;
                }
            }

            if ($old_code !== $code) {
                echo "- Store";
                file_put_contents($file->getPathName(), $code);
            } else {
                echo "- No changes";
            }
            echo static::NEW_LINE . static::NEW_LINE;
        }

        echo static::NEW_LINE;
    }
}
