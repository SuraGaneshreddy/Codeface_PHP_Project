/* Codeface — static code metrics for the Refactor Gym.
 * Rough-but-honest heuristics over raw source (no parser, documented limits).
 * Also loadable in Node for unit tests: module.exports at the bottom. */
(function (root, factory) {
  var m = factory();
  if (typeof module !== 'undefined' && module.exports) module.exports = m;
  else root.CFMetrics = m;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function stripComments(code) {
    return code
      .replace(/\/\*[\s\S]*?\*\//g, '')
      .replace(/(^|[^:"'`])\/\/[^\n]*/g, '$1'); // keep http:// inside strings safe-ish
  }

  /* cyclomatic-ish complexity: 1 + decision points */
  function complexity(code) {
    var c = stripComments(code);
    var n = 1;
    n += (c.match(/\bif\b/g) || []).length;
    n += (c.match(/\bfor\b/g) || []).length;
    n += (c.match(/\bwhile\b/g) || []).length;
    n += (c.match(/\bcase\b/g) || []).length;
    n += (c.match(/\bcatch\b/g) || []).length;
    n += (c.match(/&&|\|\|/g) || []).length;
    n += (c.match(/\?[^.:]/g) || []).length; // ternaries (rough)
    return n;
  }

  function loc(code) {
    var lines = stripComments(code).split('\n');
    var n = 0;
    for (var i = 0; i < lines.length; i++) if (lines[i].trim() !== '') n++;
    return n;
  }

  /* max brace nesting depth */
  function maxDepth(code) {
    var c = stripComments(code).replace(/'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|`(?:\\.|[^`\\])*`/g, '""');
    var d = 0, max = 0;
    for (var i = 0; i < c.length; i++) {
      if (c[i] === '{') { d++; if (d > max) max = d; }
      else if (c[i] === '}') { d = Math.max(0, d - 1); }
    }
    return max;
  }

  /* % of meaningful lines that appear more than once (trimmed, len>=10) */
  function dupPct(code) {
    var lines = stripComments(code).split('\n');
    var seen = {}, dup = 0, total = 0;
    for (var i = 0; i < lines.length; i++) {
      var t = lines[i].trim();
      if (t.length < 10 || t === '}' || t === '{}') continue;
      total++;
      if (seen[t]) dup++; else seen[t] = 1;
    }
    return total ? Math.round((dup / total) * 100) : 0;
  }

  /* single-letter or cryptic names in declarations */
  function crypticNames(code) {
    var c = stripComments(code);
    var hits = [];
    var re = /\b(?:var|let|const)\s+([a-z])\b/g, m;
    while ((m = re.exec(c))) {
      if (['i', 'j', 'k', 'x', 'y'].indexOf(m[1]) === -1) hits.push(m[1]); // loop vars tolerated
    }
    return hits;
  }

  /* long functions via simple brace matching from each 'function' keyword */
  function longFunctions(code, maxLen) {
    var c = stripComments(code);
    var idx = 0, found = [];
    while (true) {
      var at = c.indexOf('function', idx);
      if (at === -1) break;
      var open = c.indexOf('{', at);
      if (open === -1) break;
      var depth = 0, end = open;
      for (var j = open; j < c.length; j++) {
        if (c[j] === '{') depth++;
        else if (c[j] === '}') { depth--; if (depth === 0) { end = j; break; } }
      }
      var startLine = c.slice(0, at).split('\n').length;
      var endLine = c.slice(0, end).split('\n').length;
      if (endLine - startLine + 1 > (maxLen || 25)) {
        var name = (c.slice(at, open).match(/function\s+([A-Za-z_$][\w$]*)/) || [null, '(anonymous)'])[1];
        found.push({ name: name, line: startLine, length: endLine - startLine + 1 });
      }
      idx = end + 1;
    }
    return found;
  }

  /* everything at once over a {name: content} map */
  function analyzeFiles(filesMap) {
    var code = Object.keys(filesMap).map(function (k) { return filesMap[k]; }).join('\n');
    return {
      loc: loc(code),
      comp: complexity(code),
      depth: maxDepth(code),
      dup: dupPct(code),
      cryptic: crypticNames(code).length,
      longFns: longFunctions(code, 25).length,
    };
  }

  /* Refactor score: tests gate first, then cleanup quality vs baseline. */
  function refactorScore(metrics, base, testsPassed, testsTotal) {
    var frac = testsTotal ? testsPassed / testsTotal : 0;
    if (frac < 1) return { score: Math.round(55 * frac), quality: 0 };
    var compRatio = metrics.comp / Math.max(1, base.comp);
    var dupRatio = (metrics.dup + 1) / (base.dup + 1);
    var quality = 1.4 - 0.7 * compRatio - 0.7 * dupRatio;
    quality = Math.max(0, Math.min(1, quality));
    return { score: 55 + Math.round(45 * quality), quality: Math.round(quality * 100) };
  }

  return {
    complexity: complexity, loc: loc, maxDepth: maxDepth, dupPct: dupPct,
    crypticNames: crypticNames, longFunctions: longFunctions,
    analyzeFiles: analyzeFiles, refactorScore: refactorScore,
  };
});
