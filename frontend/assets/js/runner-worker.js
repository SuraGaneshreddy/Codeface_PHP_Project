/*
 * Codeface test-runner worker.
 * User code executes HERE — an isolated worker thread with no DOM access,
 * never on the PHP server. Infinite loops are killed by the main thread
 * terminating this worker after an overall timeout.
 *
 * Modes:
 *   default  — run tests against a named function (message: {code, tests, fnName})
 *   snippet  — just execute {mode:'snippet', code}, capturing console output
 */
'use strict';

function canon(v) {
  if (v === null || typeof v !== 'object') return JSON.stringify(v) || 'undefined';
  if (Array.isArray(v)) return '[' + v.map(canon).join(',') + ']';
  var keys = Object.keys(v).sort();
  return '{' + keys.map(function (k) { return JSON.stringify(k) + ':' + canon(v[k]); }).join(',') + '}';
}

function preview(v) {
  try {
    var s = JSON.stringify(v);
    if (s === undefined) return String(v);
    return s.length > 500 ? s.slice(0, 500) + '…' : s;
  } catch (e) {
    return String(v);
  }
}

function makeLogCapture() {
  var logs = [];
  var origLog = console.log;
  console.log = function () {
    var parts = [];
    for (var j = 0; j < arguments.length; j++) {
      var a = arguments[j];
      try { parts.push(typeof a === 'string' ? a : JSON.stringify(a)); }
      catch (e) { parts.push(String(a)); }
    }
    logs.push(parts.join(' '));
  };
  return { logs: logs, restore: function () { console.log = origLog; } };
}

self.onmessage = function (ev) {
  var data = ev.data;

  /* ---- snippet mode: run arbitrary code, show its output (Learn lessons) ---- */
  if (data.mode === 'snippet') {
    var cap = makeLogCapture();
    var err = null;
    try {
      (new Function('"use strict";\n' + data.code))();
    } catch (e) {
      err = String(e && e.message ? e.message : e);
    }
    cap.restore();
    self.postMessage({ type: 'snippet', ok: !err, logs: cap.logs.join('\n'), error: err });
    return;
  }

  /* ---- project mode: multi-file lab/refactor repos --------------------------
   * {mode:'project', files:{name:content}, order:[names], checks:[{expr}]}
   * Files are concatenated in order (simple, documented module model), then
   * each check expression is evaluated in the SAME scope — tasks can assert
   * on the project's functions. State persists across checks in array order.
   */
  if (data.mode === 'project') {
    var capP = makeLogCapture();
    var bodyParts = ['"use strict";'];
    (data.order || []).forEach(function (fname) {
      bodyParts.push('\n// ==== ' + fname + ' ====\n' + (data.files[fname] || ''));
    });
    bodyParts.push('\nvar __results = [];');
    (data.checks || []).forEach(function (c, i) {
      bodyParts.push(
        '\ntry { __results.push({ i: ' + i + ', pass: !!(( ' + c.expr + ' ))}); }' +
        ' catch (__eP' + i + ') { __results.push({ i: ' + i + ', pass: false, error: String(__eP' + i + ' && __eP' + i + '.message ? __eP' + i + '.message : __eP' + i + ') }); }'
      );
    });
    bodyParts.push('\nreturn __results;');
    var projErr = null, results = null;
    try {
      results = (new Function(bodyParts.join('\n')))();
    } catch (e) {
      projErr = String(e && e.message ? e.message : e);
    }
    capP.restore();
    self.postMessage({
      type: 'project',
      ok: !projErr,
      results: results || [],
      logs: capP.logs.join('\n'),
      error: projErr
    });
    return;
  }

  /* ---- judge mode ---- */
  var code = data.code, tests = data.tests, fnName = data.fnName;

  var fn = null, bootError = null;
  try {
    /* eslint-disable no-new-func */
    fn = (new Function('"use strict";\n' + code + '\n;return (typeof ' + fnName + ' === "function") ? ' + fnName + ' : null;'))();
  } catch (e) {
    bootError = String(e && e.message ? e.message : e);
  }
  if (!bootError && !fn) {
    bootError = 'Function "' + fnName + '" is not defined — keep the exact name from the starter code.';
  }
  self.postMessage({ type: 'boot', ok: !bootError, error: bootError });
  if (bootError) return;

  for (var i = 0; i < tests.length; i++) {
    var t = tests[i];
    var cap2 = makeLogCapture();
    var got, err = null, t0 = Date.now();
    try {
      var args = [];
      for (var k = 0; k < t.args.length; k++) args.push(JSON.parse(JSON.stringify(t.args[k])));
      got = fn.apply(null, args);
    } catch (e2) {
      err = String(e2 && e2.message ? e2.message : e2);
    }
    cap2.restore();

    self.postMessage({
      type: 'result',
      index: i,
      pass: !err && canon(got) === canon(t.expected),
      got: err ? ('Error: ' + err) : preview(got),
      expected: preview(t.expected),
      call: fnName + '(' + t.args.map(function (a) { return preview(a); }).join(', ') + ')',
      stdout: cap2.logs.join('\n'),
      timeMs: Date.now() - t0
    });
  }
  self.postMessage({ type: 'done' });
};
