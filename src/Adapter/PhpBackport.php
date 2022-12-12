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

        $flux_legacy_enum_namespace = $argv[2] ?? "";
        if (empty($flux_legacy_enum_namespace)) {
            echo "Please pass a namespace for flux-legacy-enum" . static::NEW_LINE;
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
        $UNION_TYPES = $PARAM_NAME . "(" . static::EMPTY . "*\|" . static::EMPTY . "*" . $PARAM_NAME . ")+";

        $flux_legacy_enum_classes = [];
        $REPLACES = [
            [
                "Replace constructor properties with legacy syntax",
                "/(" . static::SPACE . "*\/\*\*[^\/]*\*\/" . static::EMPTY . "*)?"
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
                            $parameter_parts = array_reverse(preg_split("/" . static::EMPTY . "+/", $parameter));
                            $parameters[] = $parameter_parts[1] . " " . $parameter_parts[0] . ",";

                            $parameter_doc_matches = [];
                            if (preg_match("/\*" . static::EMPTY . "*@param(.+" . preg_quote($parameter_parts[0], "/") . ".*)" . static::NEW_LINE . "/", $matches[1], $parameter_doc_matches) > 0) {
                                $parameter_doc = "/**" . static::NEW_LINE
                                    . static::INDENT . " * @var " . trim(str_replace($parameter_parts[0], "", $parameter_doc_matches[1])) . static::NEW_LINE
                                    . static::INDENT . " */" . static::NEW_LINE . static::INDENT;
                            } else {
                                $parameter_doc = "";
                            }
                            $properties[] = $parameter_doc . $parameter . ";";

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
                "Replace backed enums with flux-legacy-enum",
                "/(namespace" . static::EMPTY . "+([^" . static::EMPTY . ";]+);\s" . static::EMPTY . ")" .
                "enum" . static::EMPTY . "+(" . $PARAM_NAME . ")"
                . static::EMPTY . "*:" . static::EMPTY . "*(string|int)" . static::EMPTY . "*"
                . "([^{]*)\{" . static::EMPTY . "*"
                . "([^}]+)"
                . static::EMPTY . "*\}/",
                function (array $matches) use ($flux_legacy_enum_namespace, &$flux_legacy_enum_classes) : string {
                    $flux_legacy_enum_classes[$matches[2] . "\\" . $matches[3]] = [$matches[2], $matches[3]];

                    $methods = [];
                    foreach (explode(static::NEW_LINE, $matches[6]) as $line) {
                        $line = trim($line);
                        if (empty($line)) {
                            continue;
                        }

                        if (!(preg_match("/^case" . static::EMPTY . "/", $line) > 0 && str_ends_with($line, ";"))) {
                            continue;
                        }

                        $line = rtrim(ltrim(substr($line, 4)), ";");
                        [$name, $value] = preg_split("/" . static::EMPTY . "+/", $line, 2);
                        [, $value] = explode("=", $value, 2);
                        $value = trim(trim($value), "\"'");
                        $methods[] = "* @method static static " . $name . "() " . $value;
                    }

                    $enum_class = $matches[4] === "int" ? "LegacyIntBackedEnum" : "LegacyStringBackedEnum";

                    return $matches[1]
                        . "use " . $flux_legacy_enum_namespace . "\\Adapter\\Backed\\" . $enum_class . ";" . static::NEW_LINE . static::NEW_LINE
                        . (!empty($methods) ? "/**" . static::NEW_LINE . implode(static::NEW_LINE, array_map(fn(string $method) : string => " " . $method, $methods)) . static::NEW_LINE
                            . " */" . static::NEW_LINE : "")
                        . "class " . $matches[3] . " extends " . $enum_class . " " . $matches[5] . "{" . static::NEW_LINE
                        . static::NEW_LINE . "}";
                }
            ],
            [
                "Replace unit enums with flux-legacy-enum",
                "/(namespace" . static::EMPTY . "+([^" . static::EMPTY . ";]+);\s" . static::EMPTY . ")" .
                "enum" . static::EMPTY . "+(" . $PARAM_NAME . ")"
                . static::EMPTY . "*\{" . static::EMPTY . "*"
                . "([^}]+)"
                . static::EMPTY . "*\}/",
                function (array $matches) use ($flux_legacy_enum_namespace, &$flux_legacy_enum_classes) : string {
                    $flux_legacy_enum_classes[$matches[2] . "\\" . $matches[3]] = [$matches[2], $matches[3]];

                    $methods = [];
                    foreach (explode(static::NEW_LINE, $matches[5]) as $line) {
                        $line = trim($line);
                        if (empty($line)) {
                            continue;
                        }

                        if (!(preg_match("/^case" . static::EMPTY . "/", $line) > 0 && str_ends_with($line, ";"))) {
                            continue;
                        }

                        $name = rtrim(ltrim(substr($line, 4)), ";");
                        $methods[] = "* @method static static " . $name . "()";
                    }

                    return $matches[1]
                        . "use " . $flux_legacy_enum_namespace . "\\Adapter\\Backed\\LegacyUnitEnum;" . static::NEW_LINE . static::NEW_LINE
                        . (!empty($methods) ? "/**" . static::NEW_LINE . implode(static::NEW_LINE, array_map(fn(string $method) : string => " " . $method, $methods)) . static::NEW_LINE
                            . " */" . static::NEW_LINE : "")
                        . "class " . $matches[3] . " extends LegacyUnitEnum " . $matches[4] . "{" . static::NEW_LINE
                        . static::NEW_LINE . "}";
                }
            ],
            [
                "Remove class modifier",
                "/(readonly)(" . static::EMPTY . "+class)/",
                "*$1*/$2"
            ],
            [
                "Remove readonly property modifier",
                "/(" . static::VISIBILITY . static::EMPTY . "+)(readonly)(" . static::EMPTY . "+)/",
                "$1/*$3*/$4"
            ],
            [
                "Remove SensitiveParameter attribute",
                "/(" . static::EMPTY . "+)(#\[SensitiveParameter\])(" . static::EMPTY . "+)/",
                "$1/*$2*/$3"
            ],
            [
                "Remove union parameter types",
                "/(" . $BEFORE_PARAMETER_TYPE . ")(" . $UNION_TYPES . ")(" . static::EMPTY . "*\\\$" . $PARAM_NAME . $AFTER_PARAMETER_TYPE . ")/",
                "$1/*$2*/$4"
            ],
            [
                "Remove union return types",
                "/(" . $BEFORE_RETURN_TYPE_1 . ")(" . $BEFORE_RETURN_TYPE_2 . $UNION_TYPES . ")(" . $AFTER_RETURN_TYPE . ")/",
                "$1/*$2*/$4"
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
            ],
            [
                "Remove CurlHandle parameter type",
                "/(" . $BEFORE_PARAMETER_TYPE . ")(CurlHandle)(" . static::EMPTY . "*\\\$" . $PARAM_NAME . $AFTER_PARAMETER_TYPE . ")/",
                "$1/*$2*/$3"
            ],
            [
                "Remove CurlHandle return type",
                "/(" . $BEFORE_RETURN_TYPE_1 . ")(" . $BEFORE_RETURN_TYPE_2 . "CurlHandle)(" . $AFTER_RETURN_TYPE . ")/",
                "$1/*$2*/$3"
            ],
            [
                "Change PhpVersionChecker",
                "/(PhpVersionChecker::new\(\s*[\"'])>=8\.[012]([\"']\s*\))/",
                "$1>=7.4$2"
            ]
        ];

        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower(pathinfo($file->getFileName(), PATHINFO_EXTENSION)), $EXT)) {
                continue;
            }

            if (str_contains($file->getPathName(), "/vendor/")
                || !(str_contains($file->getPathName(), "/bin/") || str_contains($file->getPathName(), "/classes/")
                    || str_contains($file->getPathName(), "/src/"))
            ) {
                continue;
            }

            $code = $old_code = file_get_contents($file->getPathName());

            if (!(str_contains($code, "Flux") || str_contains($code, "flux"))) {
                continue;
            }

            echo "Process " . $file->getPathName() . static::NEW_LINE;

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

            $files[] = $file;
        }

        if (!empty($flux_legacy_enum_classes)) {
            foreach ($files as $file) {
                echo "Process " . $file->getPathName() . static::NEW_LINE;

                $code = $old_code = file_get_contents($file->getPathName());

                foreach ($flux_legacy_enum_classes as $flux_legacy_enum_class) {
                    if (!str_contains($code, $flux_legacy_enum_class[0])) {
                        continue;
                    }

                    $new_code = preg_replace("/([^A-Za-z0-9_]"
                        . preg_quote($flux_legacy_enum_class[1])
                        . static::EMPTY . "*::" . static::EMPTY
                        . "*[A-Z0-9_" . static::EMPTY . "]+)"
                        . "([^(])/", "$1()$2", $code);

                    if (is_string($new_code)) {
                        $code = $new_code;
                    }
                }

                if ($old_code !== $code) {
                    echo "- Replace enum case accesses with methods" . static::NEW_LINE;
                    echo "- Store";
                    file_put_contents($file->getPathName(), $code);
                } else {
                    echo "- No changes";
                }
                echo static::NEW_LINE . static::NEW_LINE;
            }
        }

        echo static::NEW_LINE;
    }
}
