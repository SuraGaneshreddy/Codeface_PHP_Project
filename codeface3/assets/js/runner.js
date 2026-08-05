/*
 * Codeface — runner orchestration + results rendering.
 * Routes by language:
 *   javascript → Web Worker running the code directly
 *   typescript → best-effort type stripping, then the JS worker
 *   python     → Pyodide (WASM CPython) worker, lazy-loaded from CDN
 * Also: runSnippet() executes arbitrary code and returns stdout (Learn lessons).
 */
window.CFRunner = (function () {
  'use strict';

  /* Best-effort TS→JS: handles typed function params, return annotations,
     var declarations, simple generics (Record<string, number>), interface/type
     blocks. Simple function-style code only — documented limitation. */
  var TS_TYPE = '[A-Za-z_$][\\w$.[\\]]*(?:<[^()=;{}]*>)?';
  function stripTypes(code) {
    code = code.replace(/^\s*import\s[^\n]*$/mg, '');
    code = code.replace(/\binterface\s+\w+\s*\{[^}]*\}/g, '');
    code = code.replace(/^\s*(export\s+)?type\s+\w+\s*=[^;]*;/mg, '');
    code = code.replace(new RegExp('\\)\\s*:\\s*' + TS_TYPE + '\\s*\\{', 'g'), ') {');
    code = code.replace(new RegExp('\\)\\s*:\\s*' + TS_TYPE + '\\s*=>', 'g'), ') =>');
    code = code.replace(new RegExp('([\\(,]\\s*[A-Za-z_$][\\w$]*)\\s*:\\s*' + TS_TYPE + '(?=[,)=;])', 'g'), '$1');
    code = code.replace(new RegExp('\\b(let|const|var)\\s+([A-Za-z_$][\\w$]*)\\s*:\\s*' + TS_TYPE + '(?=\\s*[=;,])', 'g'), '$1 $2');
    return code;
  }

  function spawn(workerFile, msg, opts, resolve) {
    var worker;
    try { worker = new Worker(workerFile); }
    catch (e) { resolve({ ok: false, bootError: 'Web Workers are not available in this browser.', results: [] }); return; }

    var results = new Array((opts.tests || []).length);
    var settled = false;
    var timeout = opts.timeout || Math.max(6000, (opts.tests || []).length * 2000);
    var timer = setTimeout(function () {
      finish({ timedOut: true, bootError: opts.python
        ? 'Timed out — Python runtime may still be downloading, or the code looped forever.'
        : 'Stopped: time limit exceeded (infinite loop?).' });
    }, timeout);

    function finish(extra) {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      try { worker.terminate(); } catch (e) {}
      var out = { ok: true, results: results };
      for (var k in (extra || {})) out[k] = extra[k];
      resolve(out);
    }

    worker.onmessage = function (ev) {
      var m = ev.data;
      if (m.type === 'loading') {
        if (opts.onLoading) opts.onLoading();
      } else if (m.type === 'boot') {
        if (opts.onBoot) opts.onBoot(m);
        if (!m.ok) finish({ bootError: m.error });
      } else if (m.type === 'result') {
        results[m.index] = m;
        if (opts.onResult) opts.onResult(m);
      } else if (m.type === 'done') {
        finish({});
      } else if (m.type === 'snippet') {
        finish({ snippet: true, logs: m.logs, snippetOk: m.ok, error: m.error });
      }
    };
    worker.onerror = function (e) {
      finish({ bootError: String(e.message || 'worker error') });
    };
    worker.postMessage(msg);
  }

  /** opts: {code, tests, fnName, lang, timeout, onBoot, onResult, onLoading} */
  function run(opts) {
    var lang = opts.lang || 'javascript';
    return new Promise(function (resolve) {
      if (lang === 'python') {
        opts.timeout = opts.timeout || Math.max(45000, (opts.tests || []).length * 3000);
        opts.python = true;
        spawn('assets/js/runner-py-worker.js', { code: opts.code, tests: opts.tests, fnName: opts.fnName }, opts, resolve);
      } else {
        var code = opts.code;
        if (lang === 'typescript') code = stripTypes(code);
        spawn('assets/js/runner-worker.js', { code: code, tests: opts.tests, fnName: opts.fnName }, opts, resolve);
      }
    });
  }

  /** opts: {code, lang, timeout, onLoading} → {logs, error?} (Learn "try it" blocks) */
  function runSnippet(opts) {
    var lang = opts.lang || 'javascript';
    return new Promise(function (resolve) {
      var wrap = [];
      var innerResolve = function (out) { resolve(out); };
      var o = {
        tests: [],
        timeout: opts.timeout || (lang === 'python' ? 45000 : 6000),
        python: lang === 'python',
        onLoading: opts.onLoading,
      };
      var code = lang === 'typescript' ? stripTypes(opts.code) : opts.code;
      var workerFile = lang === 'python' ? 'assets/js/runner-py-worker.js' : 'assets/js/runner-worker.js';
      spawn(workerFile, { mode: 'snippet', code: code }, o, function (out) {
        if (out.timedOut || out.bootError) {
          innerResolve({ ok: false, logs: out.logs || '', error: out.bootError || 'Time limit exceeded.' });
        } else {
          innerResolve({ ok: out.snippetOk !== false, logs: out.logs || '', error: out.error || null });
        }
      });
    });
  }

  /* ---------- shared result rendering ---------- */
  function renderItem(listEl, m) {
    var item = document.createElement('div');
    item.className = 'result-item ' + (m.pass ? 'pass' : 'fail');
    var html =
      '<div class="result-head">' +
        '<span class="result-pill">' + (m.pass ? 'PASS' : 'FAIL') + '</span>' +
        '<code class="result-call">' + CF.escapeHtml(m.call) + '</code>' +
        '<span class="rt">' + m.timeMs + 'ms</span>' +
      '</div>';
    if (!m.pass) {
      html += '<div class="result-detail">' +
        '<div><span class="lbl">expected</span> <code>' + CF.escapeHtml(m.expected) + '</code></div>' +
        '<div><span class="lbl">got</span> <code>' + CF.escapeHtml(m.got) + '</code></div>' +
      '</div>';
    }
    if (m.stdout) {
      html += '<pre class="console-out">' + CF.escapeHtml(m.stdout) + '</pre>';
    }
    item.innerHTML = html;
    listEl.appendChild(item);
  }

  function renderSummary(el, results, total, extra) {
    var passed = 0;
    results.forEach(function (r) { if (r && r.pass) passed++; });
    if (extra && extra.bootError) {
      el.innerHTML = '<span class="err-text">⚠ ' + CF.escapeHtml(extra.bootError) + '</span>';
      return { passed: 0, total: total };
    }
    var cls = passed === total ? 'ok-text' : 'err-text';
    var text = passed + '/' + total + ' tests passing';
    if (extra && extra.timedOut) text += ' · stopped early';
    el.innerHTML = '<span class="' + cls + '">' + text + '</span>';
    return { passed: passed, total: total };
  }

  return { run: run, runSnippet: runSnippet, renderItem: renderItem, renderSummary: renderSummary, stripTypes: stripTypes };
})();
