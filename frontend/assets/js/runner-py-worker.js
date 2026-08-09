/*
 * Codeface Python runner — Pyodide (WASM CPython) inside a Web Worker.
 * The ~10 MB runtime downloads once per page load (then cached by the browser).
 * Modes mirror the JS worker: judge mode {code, tests, fnName} and
 * snippet mode {mode:'snippet', code}.
 */
'use strict';

var pyodidePromise = null;

function canon(v) {
  if (v === null || typeof v !== 'object') return JSON.stringify(v) || 'undefined';
  if (Array.isArray(v)) return '[' + v.map(canon).join(',') + ']';
  var keys = Object.keys(v).sort();
  return '{' + keys.map(function (k) { return JSON.stringify(k) + ':' + canon(v[k]); }).join(',') + '}';
}

function preview(v) {
  try {
    var s = JSON.stringify(v);
    return s === undefined ? String(v) : (s.length > 500 ? s.slice(0, 500) + '…' : s);
  } catch (e) { return String(v); }
}

var PYODIDE_BASE = 'https://cdn.jsdelivr.net/pyodide/v0.26.4/full/';

function ensurePyodide() {
  if (pyodidePromise) return pyodidePromise;
  self.postMessage({ type: 'loading' });
  pyodidePromise = (async function () {
    importScripts(PYODIDE_BASE + 'pyodide.js');
    var py = await loadPyodide({ indexURL: PYODIDE_BASE });
    await py.runPythonAsync(
      'import json\n' +
      'def __cf_jsonable(o):\n' +
      '    if isinstance(o, (list, tuple)):\n' +
      '        return [__cf_jsonable(x) for x in o]\n' +
      '    if isinstance(o, dict):\n' +
      '        return {str(k): __cf_jsonable(v) for k, v in o.items()}\n' +
      '    if isinstance(o, (int, float, str, bool)) or o is None:\n' +
      '        return o\n' +
      '    return str(o)\n' +
      'def __cf_run(fn_name, args_json):\n' +
      '    try:\n' +
      '        args = json.loads(args_json)\n' +
      '        res = globals()[fn_name](*args)\n' +
      '        return json.dumps({"ok": True, "res": __cf_jsonable(res)})\n' +
      '    except Exception as e:\n' +
      '        return json.dumps({"ok": False, "err": str(e)})\n' +
      'def __cf_has_fn(fn_name):\n' +
      '    return callable(globals().get(fn_name))\n'
    );
    return py;
  })();
  return pyodidePromise;
}

self.onmessage = async function (ev) {
  var d = ev.data;
  var py;
  try {
    py = await ensurePyodide();
  } catch (e) {
    self.postMessage({ type: 'boot', ok: false, error: 'Could not load the Python runtime (CDN unreachable?). ' + String(e && e.message ? e.message : e) });
    return;
  }

  function setCapture() {
    var logs = [];
    py.setStdout({ batched: function (line) { logs.push(line); } });
    py.setStderr({ batched: function (line) { logs.push(line); } });
    return logs;
  }

  /* ---- snippet mode ---- */
  if (d.mode === 'snippet') {
    var logs = setCapture();
    var err = null;
    try { await py.runPythonAsync(d.code); }
    catch (e) { err = String(e && e.message ? e.message : e); }
    self.postMessage({ type: 'snippet', ok: !err, logs: logs.join('\n'), error: err });
    return;
  }

  /* ---- judge mode ---- */
  var bootLogs = setCapture();
  var bootError = null;
  try {
    await py.runPythonAsync(d.code);
    var hasFn = py.runPython('__cf_has_fn(' + JSON.stringify(d.fnName) + ')');
    if (!hasFn) bootError = 'Function "' + d.fnName + '" is not defined — keep the exact name from the starter code.';
  } catch (e) {
    bootError = String(e && e.message ? e.message : e);
  }
  self.postMessage({ type: 'boot', ok: !bootError, error: bootError });
  if (bootError) return;

  for (var i = 0; i < d.tests.length; i++) {
    var t = d.tests[i];
    var logs2 = setCapture();
    var t0 = Date.now();
    var reply;
    try {
      reply = JSON.parse(py.runPython(
        '__cf_run(' + JSON.stringify(d.fnName) + ', ' + JSON.stringify(JSON.stringify(t.args)) + ')'
      ));
    } catch (e2) {
      reply = { ok: false, err: String(e2 && e2.message ? e2.message : e2) };
    }
    self.postMessage({
      type: 'result',
      index: i,
      pass: !!(reply.ok && canon(reply.res) === canon(t.expected)),
      got: reply.ok ? preview(reply.res) : ('Error: ' + reply.err),
      expected: preview(t.expected),
      call: d.fnName + '(' + t.args.map(function (a) { return preview(a); }).join(', ') + ')',
      stdout: logs2.join('\n'),
      timeMs: Date.now() - t0
    });
  }
  self.postMessage({ type: 'done' });
};
