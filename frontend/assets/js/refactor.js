/* Codeface — refactor gym controller (keep tests green, improve metrics, submit score) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var data = JSON.parse(document.getElementById('refactorData').textContent);
  var host = document.getElementById('editorHost');
  var btnRun = document.getElementById('btnRfRun');
  var btnSubmit = document.getElementById('btnRfSubmit');
  var btnReset = document.getElementById('btnRfReset');
  var btnReview = document.getElementById('btnRfReview');
  var reviewPanel = document.getElementById('reviewPanel');
  var consoleEl = document.getElementById('rfConsole');
  var summaryEl = document.getElementById('rfSummary');
  var metricsEl = document.getElementById('rfMetrics');
  var bestEl = document.getElementById('rfBest');
  var checkEls = [];
  var checksList = document.getElementById('rfChecks');

  data.checks.forEach(function (c, i) {
    var li = document.createElement('li');
    li.className = 'task-item';
    li.innerHTML = '<span class="task-box">·</span><span class="task-text"></span>';
    li.querySelector('.task-text').textContent = c.text;
    checksList.appendChild(li);
    checkEls[i] = li;
  });

  var lastRun = null; // {passed, total, metrics, score, quality}

  var me = CFMultiEdit.create(host, {
    files: data.files,
    storageKey: 'cf:rf:' + data.slug,
    onChange: function () { paintMetrics(); markDirty(); },
  });

  function currentMetrics() { return CFMetrics.analyzeFiles(me.getValues()); }

  function paintMetrics() {
    var m = currentMetrics(), b = data.base;
    function row(label, val, bval, better) {
      var cls = better ? 'm-better' : (val > bval ? 'm-worse' : '');
      return '<tr><td>' + label + '</td><td>' + bval + '</td><td class="' + cls + '">' + val + '</td></tr>';
    }
    metricsEl.innerHTML =
      '<table class="rf-table"><thead><tr><th>metric</th><th>baseline</th><th>yours</th></tr></thead><tbody>' +
      row('complexity', m.comp, b.comp, m.comp < b.comp) +
      row('duplicated lines %', m.dup, b.dup, m.dup < b.dup) +
      row('max nesting', m.depth, b.depth, m.depth < b.depth) +
      row('long functions (>25 LOC)', m.longFns, b.longFns, m.longFns < b.longFns) +
      row('cryptic names', m.cryptic, b.cryptic, m.cryptic < b.cryptic) +
      row('LOC', m.loc, b.loc, m.loc < b.loc) +
      '</tbody></table>';
    return m;
  }

  function markDirty() {
    lastRun = null;
    btnSubmit.disabled = true;
    summaryEl.textContent = 'Code changed — run the safety tests again.';
    summaryEl.className = 'results-summary';
    checkEls.forEach(function (li) {
      li.className = 'task-item';
      li.querySelector('.task-box').textContent = '·';
    });
  }

  function orders() { return data.files.map(function (f) { return f.name; }); }
  function checkExprs() { return data.checks.map(function (c) { return { expr: c.check }; }); }

  var running = false;
  btnRun.addEventListener('click', function () {
    if (running) return;
    running = true; btnRun.disabled = true;
    summaryEl.textContent = 'Running safety tests…';
    summaryEl.className = 'results-summary running';
    CFRunner.runProject({ files: me.getValues(), order: orders(), checks: checkExprs() }).then(function (out) {
      running = false; btnRun.disabled = false;
      consoleEl.textContent = out.logs || '';
      if (out.error && !out.results.length) {
        summaryEl.textContent = '✗ Boot error: ' + out.error;
        summaryEl.className = 'results-summary bad';
        return;
      }
      var passed = 0;
      data.checks.forEach(function (c, i) {
        var r = null;
        out.results.forEach(function (x) { if (x.i === i) r = x; });
        var ok = !!(r && r.pass);
        if (ok) passed++;
        checkEls[i].className = 'task-item ' + (ok ? 'done' : 'fail');
        checkEls[i].querySelector('.task-box').textContent = ok ? '✓' : '✗';
      });
      var m = paintMetrics();
      var sc = CFMetrics.refactorScore(m, data.base, passed, data.checks.length);
      lastRun = { passed: passed, total: data.checks.length, metrics: m, score: sc.score, quality: sc.quality };
      summaryEl.textContent = passed === data.checks.length
        ? '✓ All behavior tests still pass. Cleanup score: ' + sc.score + '/100 (quality ' + sc.quality + '%). Submit when ready.'
        : '✗ ' + passed + '/' + data.checks.length + ' pass — behavior changed! Undo or fix before refactoring further.';
      summaryEl.className = 'results-summary ' + (passed === data.checks.length ? 'ok' : 'bad');
      btnSubmit.disabled = passed !== data.checks.length;
    });
  });

  btnSubmit.addEventListener('click', function () {
    if (!lastRun) return;
    btnSubmit.disabled = true;
    fetch('../backend/api/refactor/submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf },
      credentials: 'same-origin',
      body: JSON.stringify({
        slug: data.slug, score: lastRun.score,
        tests_passed: lastRun.passed, tests_total: lastRun.total,
        metrics: lastRun.metrics,
      }),
    }).then(function (r) { return r.json(); }).then(function (j) {
      btnSubmit.disabled = false;
      if (!j.ok) { CF.toast(j.error || 'Submit failed', 'err'); return; }
      bestEl.textContent = 'Best score: ' + j.best + '/100' + (j.improved ? ' — new personal best!' : '');
      CF.toast(j.improved ? 'New personal best: ' + j.best + '/100' : 'Score saved (' + lastRun.score + '). Best stays ' + j.best + '.', 'ok');
    }).catch(function () { btnSubmit.disabled = false; CF.toast('Network error', 'err'); });
  });

  btnReset.addEventListener('click', function () {
    me.resetAll();
    paintMetrics(); markDirty();
    consoleEl.textContent = '';
  });

  btnReview.addEventListener('click', function () {
    var res = CFReviewer.analyze(me.getValues(), 'javascript');
    var items = res.findings.map(function (f) {
      return '<div class="review-item sev-' + f.sev + '">' +
        '<div class="review-title"><span class="sev-dot"></span>' + esc(f.title) + '</div>' +
        '<div class="review-detail">' + esc(f.detail) + '</div>' +
        '<div class="review-fix">→ ' + esc(f.fix) + '</div></div>';
    }).join('');
    reviewPanel.innerHTML =
      '<div class="card-head"><h3>Senior review</h3>' +
      '<span class="review-score ' + (res.score >= 90 ? 'ok' : res.score >= 70 ? 'mid' : 'bad') + '">' + res.score + '/100</span></div>' +
      '<p class="review-verdict">' + esc(res.verdict) + '</p>' +
      (items || '<p class="muted">No findings — the refactoring is clean.</p>');
    reviewPanel.hidden = false;
    reviewPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  paintMetrics(); markDirty();
  summaryEl.textContent = 'Step 1: run the safety tests to confirm the starting repo is green.';
});
