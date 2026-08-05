<?php
declare(strict_types=1);

/**
 * Central language registry — single source of truth for the 12 supported
 * languages. Used by: practice starters, room pads, matchmaking, Learn tracks.
 *
 * 'runner' says what the in-browser judge can execute:
 *   'js'     → Web Worker (instant)
 *   'ts'     → types stripped, then the JS worker (best-effort, simple code only)
 *   'python' → Pyodide (WASM CPython), lazy-loaded from CDN on first run
 *   null     → no in-browser execution: starter + tests + reference solution only
 */
function cf_languages(): array {
    static $langs = null;
    if ($langs !== null) return $langs;
    $langs = [
        'javascript' => ['name' => 'JavaScript', 'monaco' => 'javascript', 'runner' => 'js',     'case' => 'camel',
            'blurb' => 'The language of the web. Instant feedback, runs everywhere — the fastest way to build fluency.'],
        'typescript' => ['name' => 'TypeScript', 'monaco' => 'typescript', 'runner' => 'ts',     'case' => 'camel',
            'blurb' => 'JavaScript with a type system that catches bugs before your users do.'],
        'python'     => ['name' => 'Python',     'monaco' => 'python',     'runner' => 'python', 'case' => 'snake',
            'blurb' => 'Readable, batteries-included, and loved for scripting, data, and automation.'],
        'java'       => ['name' => 'Java',       'monaco' => 'java',       'runner' => null,     'case' => 'camel',
            'blurb' => 'The enterprise workhorse. Strict types, verbose on purpose, everywhere in big codebases.'],
        'c'          => ['name' => 'C',          'monaco' => 'cpp',        'runner' => null,     'case' => 'snake',
            'blurb' => 'Close to the metal. You manage the memory, you feel the machine.'],
        'cpp'        => ['name' => 'C++',        'monaco' => 'cpp',        'runner' => null,     'case' => 'snake',
            'blurb' => 'C with superpowers — the STL makes serious algorithms pleasant.'],
        'csharp'     => ['name' => 'C#',         'monaco' => 'csharp',     'runner' => null,     'case' => 'pascal',
            'blurb' => 'An elegant C-family language with great tooling, from desktop to Unity games.'],
        'go'         => ['name' => 'Go',         'monaco' => 'go',         'runner' => null,     'case' => 'pascal',
            'blurb' => 'Small, fast, and built for servers. Less language, more shipping.'],
        'ruby'       => ['name' => 'Ruby',       'monaco' => 'ruby',       'runner' => null,     'case' => 'snake',
            'blurb' => 'Optimized for programmer happiness. Expressive, friendly, pragmatic.'],
        'php'        => ['name' => 'PHP',        'monaco' => 'php',        'runner' => null,     'case' => 'snake',
            'blurb' => 'The web’s original server engine — still powers most of the internet.'],
        'kotlin'     => ['name' => 'Kotlin',     'monaco' => 'kotlin',     'runner' => null,     'case' => 'camel',
            'blurb' => 'Modern Java with less typing. Android’s first language.'],
        'rust'       => ['name' => 'Rust',       'monaco' => 'rust',       'runner' => null,     'case' => 'snake',
            'blurb' => 'Memory safety without a garbage collector. Hard mode that pays off.'],
    ];
    return $langs;
}

function cf_lang_ids(): array { return array_keys(cf_languages()); }

/** id => display name (keeps the old ROOM_LANGUAGES shape for existing code). */
function cf_language_names(): array {
    $out = [];
    foreach (cf_languages() as $id => $m) $out[$id] = $m['name'];
    return $out;
}

if (!defined('ROOM_LANGUAGES')) {
    define('ROOM_LANGUAGES', cf_language_names());
}

/** Can this language be executed in the browser right now? */
function cf_runner_available(string $lang): bool {
    $r = cf_languages()[$lang]['runner'] ?? null;
    return in_array($r, ['js', 'ts', 'python'], true);
}

/** Convert a camelCase function name into the language's idiomatic case. */
function cf_fn_name(string $camel, string $lang): string {
    $case = cf_languages()[$lang]['case'] ?? 'camel';
    if ($case === 'camel' || $camel === '') return $camel;
    $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
    if ($case === 'snake') return $snake;
    // pascal
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $snake)));
}
