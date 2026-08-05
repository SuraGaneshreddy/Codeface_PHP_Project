<?php
declare(strict_types=1);

require_once __DIR__ . '/langs.php';

/**
 * Starter-code emitters: from one language-neutral signature spec
 *   sig(['nums' => 'int[]', 'target' => 'int'], 'int[]')
 * generate idiomatic starter code for all 12 languages.
 */

/** Build a signature spec. $params: [name => type]; types: int num str bool + [] variants + int[][]. */
function sig(array $params, string $ret): array {
    return ['params' => $params, 'ret' => $ret];
}

/** Test helper: t([args...], expected, visibleFlag) */
function t(array $args, $expected, int $visible = 0): array {
    return ['args' => $args, 'expected' => $expected, 'visible' => (bool)$visible];
}

/* ---------- type mappers ---------- */

function cf_type(string $type, string $lang): string {
    static $maps = null;
    if ($maps === null) {
        $maps = [
            'ts'     => ['int' => 'number', 'num' => 'number', 'str' => 'string', 'bool' => 'boolean', 'map' => 'Record<string, number>',
                         'int[]' => 'number[]', 'num[]' => 'number[]', 'str[]' => 'string[]', 'bool[]' => 'boolean[]', 'int[][]' => 'number[][]'],
            'jsdoc'  => ['int' => 'number', 'num' => 'number', 'str' => 'string', 'bool' => 'boolean', 'map' => 'Object',
                         'int[]' => 'number[]', 'num[]' => 'number[]', 'str[]' => 'string[]', 'bool[]' => 'boolean[]', 'int[][]' => 'number[][]'],
            'java'   => ['int' => 'int', 'num' => 'double', 'str' => 'String', 'bool' => 'boolean', 'map' => 'Map<String,Integer>',
                         'int[]' => 'int[]', 'num[]' => 'double[]', 'str[]' => 'String[]', 'bool[]' => 'boolean[]', 'int[][]' => 'int[][]'],
            'cpp'    => ['int' => 'int', 'num' => 'double', 'str' => 'string', 'bool' => 'bool', 'map' => 'map<string,int>',
                         'int[]' => 'vector<int>', 'num[]' => 'vector<double>', 'str[]' => 'vector<string>', 'bool[]' => 'vector<bool>', 'int[][]' => 'vector<vector<int>>'],
            'csharp' => ['int' => 'int', 'num' => 'double', 'str' => 'string', 'bool' => 'bool', 'map' => 'Dictionary<string,int>',
                         'int[]' => 'int[]', 'num[]' => 'double[]', 'str[]' => 'string[]', 'bool[]' => 'bool[]', 'int[][]' => 'int[][]'],
            'go'     => ['int' => 'int', 'num' => 'float64', 'str' => 'string', 'bool' => 'bool', 'map' => 'map[string]int',
                         'int[]' => '[]int', 'num[]' => '[]float64', 'str[]' => '[]string', 'bool[]' => '[]bool', 'int[][]' => '[][]int'],
            'php'    => ['int' => 'int', 'num' => 'float', 'str' => 'string', 'bool' => 'bool', 'map' => 'array',
                         'int[]' => 'array', 'num[]' => 'array', 'str[]' => 'array', 'bool[]' => 'array', 'int[][]' => 'array'],
            'kotlin' => ['int' => 'Int', 'num' => 'Double', 'str' => 'String', 'bool' => 'Boolean', 'map' => 'Map<String,Int>',
                         'int[]' => 'IntArray', 'num[]' => 'DoubleArray', 'str[]' => 'Array<String>', 'bool[]' => 'BooleanArray', 'int[][]' => 'Array<IntArray>'],
            'rust'   => ['int' => 'i32', 'num' => 'f64', 'str' => '&str', 'bool' => 'bool', 'map' => 'HashMap<String, i32>',
                         'int[]' => 'Vec<i32>', 'num[]' => 'Vec<f64>', 'str[]' => 'Vec<String>', 'bool[]' => 'Vec<bool>', 'int[][]' => 'Vec<Vec<i32>>'],
        ];
    }
    return $maps[$lang][$type] ?? 'auto';
}

/** True when the signature uses the dictionary type (some languages need imports). */
function cf_sig_uses_map(array $sig): bool {
    if ($sig['ret'] === 'map') return true;
    foreach ($sig['params'] as $ty) if ($ty === 'map') return true;
    return false;
}

/** Generate the starter code for one problem signature in one language. */
function cf_starter(string $fnCamel, array $sig, string $lang): string {
    $params = $sig['params'];
    $ret    = $sig['ret'];
    $fn     = cf_fn_name($fnCamel, $lang);

    switch ($lang) {
        case 'javascript':
            $doc = "/**\n";
            foreach ($params as $n => $ty) $doc .= " * @param {" . cf_type($ty, 'jsdoc') . "} {$n}\n";
            $doc .= " * @return {" . cf_type($ret, 'jsdoc') . "}\n */\n";
            return $doc . "function {$fnCamel}(" . implode(', ', array_keys($params)) . ") {\n  // TODO\n}\n";

        case 'typescript':
            $ps = [];
            foreach ($params as $n => $ty) $ps[] = "{$n}: " . cf_type($ty, 'ts');
            return "function {$fnCamel}(" . implode(', ', $ps) . "): " . cf_type($ret, 'ts') . " {\n  // TODO\n}\n";

        case 'python':
            $docArgs = implode(', ', array_map(function ($n) use ($params) { return "{$n}: {$params[$n]}"; }, array_keys($params)));
            return "def {$fn}(" . implode(', ', array_keys($params)) . "):\n    \"\"\" {$docArgs} -> {$ret} \"\"\"\n    # TODO\n    pass\n";

        case 'ruby':
            return "def {$fn}(" . implode(', ', array_keys($params)) . ")\n  # TODO\nend\n";

        case 'php':
            $ps = [];
            foreach ($params as $n => $ty) $ps[] = cf_type($ty, 'php') . ' $' . $n;
            return "<?php\nfunction {$fn}(" . implode(', ', $ps) . "): " . cf_type($ret, 'php') . " {\n    // TODO\n}\n";

        case 'java':
            $ps = [];
            foreach ($params as $n => $ty) $ps[] = cf_type($ty, 'java') . ' ' . $n;
            $imports = cf_sig_uses_map($sig) ? "import java.util.*;\n\n" : '';
            return $imports . "class Solution {\n    public " . cf_type($ret, 'java') . " {$fnCamel}(" . implode(', ', $ps) . ") {\n        // TODO\n        return null;\n    }\n}\n";

        case 'csharp':
            $ps = [];
            foreach ($params as $n => $ty) $ps[] = cf_type($ty, 'csharp') . ' ' . $n;
            $imports = cf_sig_uses_map($sig) ? "using System.Collections.Generic;\n\n" : '';
            return $imports . "public class Solution {\n    public " . cf_type($ret, 'csharp') . " {$fn}(" . implode(', ', $ps) . ") {\n        // TODO\n        return null;\n    }\n}\n";

        case 'cpp':
            $ps = [];
            foreach ($params as $n => $ty) {
                $pt = cf_type($ty, 'cpp');
                $ref = (str_contains($ty, '[') || $ty === 'map' || $ty === 'str') ? '& ' : ' ';
                $ps[] = $pt . $ref . $n;
            }
            $inc = "#include <vector>\n#include <string>\n" . (cf_sig_uses_map($sig) ? "#include <map>\n" : '') . "using namespace std;\n\n";
            $retTy = cf_type($ret, 'cpp');
            $retDefault = (str_contains($ret, '[') || $ret === 'map') ? 'return {};' : "return {$retTy}();";
            return $inc . "{$retTy} {$fn}(" . implode(', ', $ps) . ") {\n    // TODO\n    {$retDefault}\n}\n";

        case 'go':
            $ps = [];
            foreach ($params as $n => $ty) $ps[] = $n . ' ' . cf_type($ty, 'go');
            $goRet = cf_type($ret, 'go');
            $retDefault = (str_contains($ret, '[') || $ret === 'map') ? 'nil' : ($goRet === 'string' ? '""' : ($goRet === 'bool' ? 'false' : '0'));
            return "func {$fn}(" . implode(', ', $ps) . ") {$goRet} {\n    // TODO\n    return {$retDefault}\n}\n";

        case 'kotlin':
            $ps = [];
            foreach ($params as $n => $ty) $ps[] = "{$n}: " . cf_type($ty, 'kotlin');
            return "fun {$fnCamel}(" . implode(', ', $ps) . "): " . cf_type($ret, 'kotlin') . " {\n    // TODO\n    TODO(\"implement me\")\n}\n";

        case 'rust':
            if (cf_sig_uses_map($sig)) {
                $ps = [];
                foreach ($params as $n => $ty) $ps[] = "{$n}: " . cf_type($ty, 'rust');
                $retTy = $ret === 'str' ? 'String' : cf_type($ret, 'rust');
                return "use std::collections::HashMap;\n\nfn {$fn}(" . implode(', ', $ps) . ") -> {$retTy} {\n    // TODO\n    unimplemented!()\n}\n";
            }
            $ps = [];
            foreach ($params as $n => $ty) $ps[] = "{$n}: " . cf_type($ty, 'rust');
            $retTy = $ret === 'str' ? 'String' : cf_type($ret, 'rust');
            return "fn {$fn}(" . implode(', ', $ps) . ") -> {$retTy} {\n    // TODO\n    unimplemented!()\n}\n";

        case 'c':
            if (cf_sig_uses_map($sig)) {
                return "// This challenge uses dictionaries, which are not native to C.\n// Sketch your approach here — or solve it in another language tab.\n";
            }
            // C idiom: arrays come with an explicit size parameter
            $ps = [];
            foreach ($params as $n => $ty) {
                if (str_contains($ty, '[')) {
                    $base = str_replace(['[', ']'], '', $ty);
                    $ctype = ['int' => 'int', 'num' => 'double', 'str' => 'char*', 'bool' => 'bool'][$base] ?? 'int';
                    $ps[] = ($ty === 'str[]' ? 'char**' : $ctype . '*') . ' ' . $n;
                    $ps[] = 'int ' . $n . 'Size';
                } elseif ($ty === 'str') {
                    $ps[] = 'const char* ' . $n;
                } else {
                    $ps[] = (['int' => 'int', 'num' => 'double', 'bool' => 'bool'][$ty] ?? 'int') . ' ' . $n;
                }
            }
            $psStr = implode(', ', $ps);
            $cret = str_contains($ret, '[') ? 'int*' : (['int' => 'int', 'num' => 'double', 'bool' => 'bool', 'str' => 'char*'][$ret] ?? 'int');
            return "#include <stdio.h>\n#include <stdlib.h>\n#include <stdbool.h>\n#include <string.h>\n\n{$cret} {$fn}({$psStr}) {\n    // TODO" . (str_contains($ret, '[') ? ' — return a malloc\'d array' : '') . "\n    return " . (str_contains($ret, '[') ? 'NULL' : ($cret === 'bool' ? 'false' : ($cret === 'char*' ? 'NULL' : '0'))) . ";\n}\n";
    }
    return "// {$lang} starter unavailable\n";
}

/** Starters for ALL languages for one problem. */
function cf_starters_all(string $fn, array $sig): array {
    $out = [];
    foreach (cf_lang_ids() as $lang) $out[$lang] = cf_starter($fn, $sig, $lang);
    return $out;
}

/** Function names per language (runnable ones matter for the judge). */
function cf_fn_names_all(string $fnCamel): array {
    $out = [];
    foreach (cf_lang_ids() as $lang) $out[$lang] = cf_fn_name($fnCamel, $lang);
    return $out;
}

/** Build a rich HTML description from a compact problem spec. */
function cf_build_description(string $fnCamel, array $sig, string $blurb, array $constraints, array $tests, string $follow = ''): string {
    $html = '<p>' . $blurb . '</p>';
    $visible = array_values(array_filter($tests, function ($t) { return !empty($t['visible']); }));
    if ($visible) {
        $html .= '<h4>Example' . (count($visible) > 1 ? 's' : '') . '</h4><pre><code>';
        $lines = [];
        foreach (array_slice($visible, 0, 3) as $t) {
            $args = array_map(function ($a) { return json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }, $t['args']);
            $lines[] = e($fnCamel . '(' . implode(', ', $args) . ') → ' . json_encode($t['expected'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $html .= implode("\n", $lines) . '</code></pre>';
    }
    if ($constraints) {
        $html .= '<h4>Constraints</h4><ul>';
        foreach ($constraints as $c) $html .= '<li><code>' . e($c) . '</code></li>';
        $html .= '</ul>';
    }
    if ($follow !== '') $html .= '<h4>Follow-up</h4><p>' . e($follow) . '</p>';
    return $html;
}
