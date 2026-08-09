/* Codeface — "Senior Engineer" automated code reviewer (rule-based, no external AI).
 * Static heuristics over raw source: correctness risks, security, readability,
 * design. Returns findings + a 0–100 score + a senior-style verdict.
 * Node-loadable for unit tests. */
(function (root, factory) {
  var m = factory();
  if (typeof module !== 'undefined' && module.exports) module.exports = m;
  else root.CFReviewer = m;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function linesOf(code) { return code.split('\n'); }

  function findLines(code, re, cap) {
    var lines = linesOf(code), out = [];
    for (var i = 0; i < lines.length; i++) {
      re.lastIndex = 0;
      if (re.test(lines[i])) { out.push(i + 1); if (out.length >= (cap || 3)) break; }
    }
    return out;
  }

  function fmtLines(ls) {
    if (!ls.length) return '';
    return ' (line' + (ls.length > 1 ? 's ' : ' ') + ls.join(', ') + (ls.length >= 3 ? ', …' : '') + ')';
  }

  var RULES = [
    /* ---- security ---- */
    {
      id: 'no-eval', sev: 'critical', lang: 'js',
      test: function (c) { return findLines(c, /\beval\s*\(/); },
      title: 'Never eval() untrusted-shaped input',
      detail: 'eval executes strings as code — one injection and an attacker owns the page. There is almost always a parser or a lookup table instead.',
      fix: 'Replace with JSON.parse, a switch/map, or a precompiled function built from whitelisted parts.',
    },
    {
      id: 'no-innerhtml-concat', sev: 'critical', lang: 'js',
      test: function (c) { return findLines(c, /\.innerHTML\s*(\+)?=.*(\+|`\$\{)/); },
      title: 'innerHTML built from concatenated values = stored XSS waiting to happen',
      detail: 'Interpolating variables into innerHTML lets user input inject markup/script into your DOM.',
      fix: 'Build nodes with document.createElement + textContent, or escape first (& < > " \').',
    },
    {
      id: 'py-mutable-default', sev: 'critical', lang: 'python',
      test: function (c) { return findLines(c, /def\s+\w+\s*\([^)]*=\s*(\[\]|\{\})/); },
      title: 'Mutable default argument',
      detail: 'def f(items=[]) shares ONE list across all calls — values leak between calls.',
      fix: 'Default to None and create the list inside the function body.',
    },
    {
      id: 'py-bare-except', sev: 'critical', lang: 'python',
      test: function (c) { return findLines(c, /^\s*except\s*:/); },
      title: 'Bare except swallows everything — even KeyboardInterrupt',
      detail: 'A bare `except:` hides real bugs and makes failures undebuggable.',
      fix: 'Catch the specific exception you expect and log or re-raise the rest.',
    },
    /* ---- correctness ---- */
    {
      id: 'strict-eq', sev: 'warn', lang: 'js',
      test: function (c) { return findLines(c, /[^=!<>]==[^=]|[^!]!=[^=]/); },
      title: 'Loose equality (== / !=) has coercion landmines',
      detail: '0 == "" is true, null == undefined is true. Seniors read == as a bug until proven harmless.',
      fix: 'Use === and !==; handle null/undefined explicitly if that was the intent.',
    },
    {
      id: 'empty-catch', sev: 'warn', lang: 'js',
      test: function (c) { return findLines(c, /catch\s*\([^)]*\)\s*\{\s*\}/); },
      title: 'Empty catch block — errors vanish silently',
      detail: 'Swallowing exceptions turns failures into Heisenbugs. At minimum, record that it happened.',
      fix: 'Log it, add fallback behavior, or rethrow with context.',
    },
    {
      id: 'dead-after-return', sev: 'warn', lang: 'any',
      test: function (c) {
        var ls = linesOf(c), out = [];
        for (var i = 0; i < ls.length - 1; i++) {
          var t = ls[i].trim();
          if (/^return\b/.test(t)) {
            var n = (ls[i + 1] || '').trim();
            if (n && n[0] !== '}' && !/^\/\//.test(n)) out.push(i + 2);
            if (out.length >= 3) break;
          }
        }
        return out;
      },
      title: 'Unreachable code after return',
      detail: 'Statements after return never execute — either leftover debug or a logic mistake.',
      fix: 'Delete it or restructure; linters flag this as an error in most teams.',
    },
    {
      id: 'py-eq-none', sev: 'warn', lang: 'python',
      test: function (c) { return findLines(c, /==\s*None|!=\s*None/); },
      title: 'Compare to None with `is`, not ==',
      detail: 'A class can override __eq__ and lie about being None; `is` checks identity.',
      fix: 'Use `x is None` / `x is not None`.',
    },
    /* ---- readability ---- */
    {
      id: 'long-lines', sev: 'info', lang: 'any',
      test: function (c) {
        var ls = linesOf(c), out = [];
        for (var i = 0; i < ls.length; i++) if (ls[i].length > 120) { out.push(i + 1); if (out.length >= 3) break; }
        return out;
      },
      title: 'Lines over 120 chars are hard to review',
      detail: 'Diffs and side-by-side reviews fall apart with very long lines.',
      fix: 'Extract an intermediate variable or break the expression over lines.',
    },
    {
      id: 'magic-numbers', sev: 'warn', lang: 'js',
      test: function (c) {
        var ls = linesOf(c), out = [];
        for (var i = 0; i < ls.length; i++) {
          var t = ls[i];
          if (/\bconst\s+[A-Z_]+\s*=/.test(t)) continue;               // named constant — fine
          var m = t.match(/[^.\w](\d{2,}|\d\.\d+)(?!\d*\.)/g);
          if (m && !/^\s*(for\s*\(|\/\/|#)/.test(t)) {
            var bad = m.filter(function (x) { return !/[^\d](10|100|1000|24|60|7)\b/.test(x + ' ') || true; });
            if (bad.length) { out.push(i + 1); if (out.length >= 3) break; }
          }
        }
        return out;
      },
      title: 'Magic numbers without a name',
      detail: 'What does 0.95 mean — a discount? a threshold? a tax? The next reader (you, in 3 months) has to reverse-engineer it.',
      fix: 'Hoist to a named constant: const BULK_DISCOUNT = 0.95;',
    },
    {
      id: 'var-keyword', sev: 'info', lang: 'js',
      test: function (c) { return findLines(c, /^\s*var\s+/); },
      title: '`var` in new code — prefer let/const',
      detail: 'var is function-scoped and hoisted; block-scoped let/const removes a whole class of closure bugs.',
      fix: 'Default to const; use let only when reassigning.',
    },
    {
      id: 'many-params', sev: 'info', lang: 'any',
      test: function (c) { return findLines(c, /function\s*\w*\s*\((?:\s*[^,()]+,){4,}/, 2); },
      title: 'Functions with 5+ parameters are telling you something',
      detail: 'Long parameter lists usually hide a missing object or config type.',
      fix: 'Group related params into one options object with defaults.',
    },
    {
      id: 'todo-left', sev: 'info', lang: 'any',
      test: function (c) { return findLines(c, /\bTODO\b|\bFIXME\b|\bHACK\b/i); },
      title: 'TODO/FIXME left in the code',
      detail: 'Fine while iterating — but it should become a tracked ticket before merge, not permanent décor.',
      fix: 'Resolve it or file it, then remove the comment.',
    },
    {
      id: 'console-log', sev: 'info', lang: 'js',
      test: function (c) { return findLines(c, /console\.log\s*\(/); },
      title: 'console.log left in the code',
      detail: 'Debug output belongs in development, not in a submission a reviewer reads.',
      fix: 'Remove, or gate behind a debug flag.',
    },
    {
      id: 'py-print', sev: 'info', lang: 'python',
      test: function (c) { return findLines(c, /^\s*print\s*\(/); },
      title: 'print() left in library code',
      detail: 'Side-effect output makes functions untestable and logs noisy.',
      fix: 'Return values instead; use logging in real services.',
    },
  ];

  /* structural findings computed once over the file (uses CFMetrics if loaded) */
  function structuralFindings(code, lang) {
    var out = [];
    var M = typeof globalThis !== 'undefined' ? globalThis.CFMetrics
          : (typeof self !== 'undefined' && self ? self.CFMetrics : null);
    if (lang !== 'python' && M) {
      var fns = M.longFunctions(code, 30);
      fns.slice(0, 2).forEach(function (f) {
        out.push({
          rule: 'long-function', sev: 'warn',
          title: '"' + f.name + '" runs ' + f.length + ' lines — too many responsibilities for one function',
          detail: 'Long functions hide bugs because no reader can hold them in their head.',
          fix: 'Extract well-named helpers; aim for < ~20 lines per function.',
          line: f.line,
        });
      });
      var depth = M.maxDepth(code);
      if (depth >= 5) {
        out.push({
          rule: 'deep-nesting', sev: 'warn',
          title: 'Nesting ' + depth + ' levels deep (pyramid of doom)',
          detail: 'Deep if/for nesting multiplies the paths a reader must simulate.',
          fix: 'Invert conditions (guard clauses: `if (bad) continue;`) and extract the inner block.',
        });
      }
    }
    return out;
  }

  /* code: string | {name: content}; lang: 'javascript'|'python'|… */
  function analyze(code, lang) {
    if (code && typeof code === 'object') {
      code = Object.keys(code).map(function (k) { return code[k]; }).join('\n');
    }
    code = String(code || '');
    if (!code.trim()) {
      return { score: 0, verdict: 'Nothing to review yet.', findings: [] };
    }
    lang = lang === 'typescript' ? 'javascript' : lang;
    var isPy = lang === 'python';
    var findings = [];
    RULES.forEach(function (r) {
      if (r.lang === 'python' && !isPy) return;
      if (r.lang === 'js' && isPy) return;
      var lines = r.test(code) || [];
      if (!lines.length) return;
      findings.push({
        rule: r.id, sev: r.sev, title: r.title + fmtLines(lines),
        detail: r.detail, fix: r.fix, line: lines[0],
      });
    });
    findings = findings.concat(structuralFindings(code, isPy ? 'python' : 'js'));

    var crit = 0, warn = 0, info = 0;
    findings.forEach(function (f) {
      if (f.sev === 'critical') crit++;
      else if (f.sev === 'warn') warn++;
      else info++;
    });
    var score = Math.max(5, 100 - crit * 15 - warn * 6 - info * 2);
    var verdict =
      score >= 90 ? 'Solid — a senior would approve this.' :
      score >= 70 ? 'Minor polish requested before merge.' :
      score >= 50 ? 'Request changes — see the comments.' :
                    'This needs a pairing session, not just edits.';
    return { score: score, verdict: verdict, findings: findings, counts: { critical: crit, warn: warn, info: info } };
  }

  return { analyze: analyze };
});
