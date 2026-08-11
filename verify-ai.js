#!/usr/bin/env node
/*
 * Codeface AI-content verification harness (developer tool).
 * Mirrors frontend/assets/js/runner-worker.js EXACTLY (canon, judge mode, project mode)
 * and loads frontend/assets/js/metrics.js for metric parity vs PHP cf_ai_metrics.
 *
 * Usage:
 *   php tools/verify-ai-dump.php   # writes tools/_ai_dump_a.json
 *   node tools/verify-ai.js
 *
 * Proves, for 2 batches generated for probe user 7:
 *  1. PROBLEMS — reference solution_js boots & passes every baked test
 *     (canonical JSON deep-equality, same as the browser worker).
 *  2. LABS — shipped repos fail ≥1 task (the bug is real); files patched
 *     with the staff 'fix' map pass every task (worker project mode).
 *  3. REFACTORS — messy repos pass every safety check (ugly but correct);
 *     staff fixes pass every check; PHP metrics === JS metrics byte-for-byte;
 *     refactorScore(messy) = 55 floor; refactorScore(fix) ≥ 90.
 */
'use strict';
const fs = require('fs');
const path = require('path');

/* ---- worker canon, verbatim copy of runner-worker.js ---- */
function canon(v) {
  if (v === null || typeof v !== 'object') return JSON.stringify(v) || 'undefined';
  if (Array.isArray(v)) return '[' + v.map(canon).join(',') + ']';
  var keys = Object.keys(v).sort();
  return '{' + keys.map(function (k) { return JSON.stringify(k) + ':' + canon(v[k]); }).join(',') + '}';
}

const ROOT = path.join(__dirname, '..');
const M = require(path.join(ROOT, 'frontend/assets/js/metrics.js'));
const dump = JSON.parse(fs.readFileSync(path.join(__dirname, '_ai_dump_a.json'), 'utf8'));

let pass = 0, fail = 0;
const failures = [];
function ok(cond, label, extra) {
  if (cond) pass++;
  else { fail++; failures.push(label + (extra ? ' :: ' + extra : '')); }
}
function projectRun(filesArr, checks) {
  const order = filesArr.map(f => f.name);
  const files = {}; filesArr.forEach(f => { files[f.name] = f.content; });
  const bodyParts = ['"use strict";'];
  order.forEach(n => bodyParts.push('\n// ==== ' + n + ' ====\n' + (files[n] || '')));
  bodyParts.push('\nvar __results = [];');
  checks.forEach((c, i) => {
    bodyParts.push(
      '\ntry { __results.push({ i: ' + i + ', pass: !!(( ' + c + ' ))}); }' +
      ' catch (__eP' + i + ') { __results.push({ i: ' + i + ', pass: false, error: String(__eP' + i + ' && __eP' + i + '.message ? __eP' + i + '.message : __eP' + i + ') }); }'
    );
  });
  bodyParts.push('\nreturn __results;');
  try {
    return { results: (new Function(bodyParts.join('\n')))(), error: null };
  } catch (e) {
    return { results: null, error: String(e && e.message ? e.message : e) };
  }
}

/* ================= 1. PROBLEMS ================= */
let pTests = 0;
for (const spec of dump.problems) {
  const fnName = spec.fn;
  let fn = null, boot = null;
  try {
    fn = (new Function('"use strict";\n' + spec.solution_js + '\n;return (typeof ' + fnName + ' === "function") ? ' + fnName + ' : null;'))();
  } catch (e) { boot = String(e.message || e); }
  ok(fn !== null, `${spec.slug}: solution boots`, boot);
  if (!fn) continue;
  for (const t of spec.tests) {
    pTests++;
    let got, err = null;
    try { got = fn.apply(null, t.args.map(a => JSON.parse(JSON.stringify(a)))); }
    catch (e) { err = String(e.message || e); }
    ok(!err && canon(got) === canon(t.expected),
       `${spec.slug}: test ${JSON.stringify(t.args)} → ${JSON.stringify(t.expected)}`,
       err ? ('threw ' + err) : ('got ' + JSON.stringify(got)));
  }
  for (const [lang, starter] of Object.entries(spec.starters || {})) {
    if (lang !== 'javascript') continue;
    let sFn = null, sErr = null;
    try {
      sFn = (new Function('"use strict";\n' + starter + '\n;return (typeof ' + fnName + ' === "function") ? ' + fnName + ' : null;'))();
    } catch (e) { sErr = String(e.message || e); }
    ok(sFn !== null, `${spec.slug}: ${lang} starter boots & defines ${fnName}`, sErr || 'fn missing');
  }
}

/* ================= 2. LABS ================= */
for (const lab of dump.labs) {
  const checks = lab.tasks.map(t => t.check);
  const shipped = projectRun(lab.files, checks);
  ok(shipped.error === null, `${lab.slug}: shipped repo parses/evals`, shipped.error);
  if (shipped.results) {
    const fails = shipped.results.filter(r => !r.pass).length;
    ok(fails >= 1, `${lab.slug}: shipped repo FAILS at least one task (bug is real)`, `${fails}/${checks.length} failed`);
  }
  const fixedFiles = lab.files.map(f => (lab.fix && lab.fix[f.name] !== undefined) ? { name: f.name, content: lab.fix[f.name] } : f);
  const fixed = projectRun(fixedFiles, checks);
  ok(fixed.error === null, `${lab.slug}: fixed repo parses/evals`, fixed.error);
  if (fixed.results) {
    fixed.results.forEach((r, i) => ok(r.pass, `${lab.slug}: fixed passes task ${i + 1} (${lab.tasks[i].text})`, JSON.stringify(r)));
  }
  for (const fname of Object.keys(lab.fix || {})) {
    const f = lab.files.find(x => x.name === fname);
    ok(f && !f.readonly, `${lab.slug}: fix target ${fname} exists and is editable`);
  }
  ok(lab.files.some(f => f.readonly), `${lab.slug}: has a readonly façade file`);
}

/* ================= 3. REFACTORS ================= */
function jsMetrics(content) { return M.analyzeFiles({ 'x.js': content }); }
const KEYS = ['loc', 'comp', 'depth', 'dup', 'cryptic', 'longFns'];
for (const c of dump.refactors) {
  const messy = c.files[0].content;
  const checks = c.checks.map(x => x.check);
  const mRun = projectRun(c.files, checks);
  ok(mRun.error === null, `${c.slug}: messy repo evals`, mRun.error);
  if (mRun.results) mRun.results.forEach((r, i) => ok(r.pass, `${c.slug}: messy passes check ${i + 1}`, JSON.stringify(r)));
  const fRun = projectRun([{ name: c.files[0].name, content: c.fix }], checks);
  ok(fRun.error === null, `${c.slug}: fix evals`, fRun.error);
  if (fRun.results) fRun.results.forEach((r, i) => ok(r.pass, `${c.slug}: fix passes check ${i + 1}`, JSON.stringify(r)));
  const jmMessy = jsMetrics(messy), jmFix = jsMetrics(c.fix);
  for (const k of KEYS) {
    ok(jmMessy[k] === c.metrics_messy[k], `${c.slug}: metrics parity messy.${k}`, `js=${jmMessy[k]} php=${c.metrics_messy[k]}`);
    ok(jmFix[k] === c.metrics_fix[k], `${c.slug}: metrics parity fix.${k}`, `js=${jmFix[k]} php=${c.metrics_fix[k]}`);
  }
  ok(c.base.comp === c.metrics_messy.comp && c.base.dup === c.metrics_messy.dup,
     `${c.slug}: base === measured messy metrics`);
  const T = c.checks.length;
  const sMessy = M.refactorScore(jmMessy, c.base, T, T);
  const sFix = M.refactorScore(jmFix, c.base, T, T);
  ok(sMessy.score === 55, `${c.slug}: messy scores the 55 floor`, `got ${sMessy.score} (q=${sMessy.quality})`);
  ok(sFix.score >= 90, `${c.slug}: staff fix scores ≥90`, `got ${sFix.score} (q=${sFix.quality})`);
}

console.log(`\n==== VERIFY AI CONTENT ====`);
console.log(`problems: ${dump.problems.length}, labs: ${dump.labs.length}, refactors: ${dump.refactors.length}`);
console.log(`problem tests evaluated: ${pTests}`);
console.log(`assertions passed: ${pass}, failed: ${fail}`);
if (fail) {
  console.log('\nFAILURES:');
  failures.slice(0, 60).forEach(f => console.log('  ✗ ' + f));
  if (failures.length > 60) console.log(`  … and ${failures.length - 60} more`);
  process.exit(1);
}
console.log('ALL GREEN ✓');
