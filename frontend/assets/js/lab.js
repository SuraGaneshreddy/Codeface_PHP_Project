/* Codeface — lab page controller (multi-file legacy/API environment) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var data = JSON.parse(document.getElementById('labData').textContent);
  var host = document.getElementById('editorHost');
  var btnRun = document.getElementById('btnLabRun');
  var btnReset = document.getElementById('btnLabReset');
  var btnReview = document.getElementById('btnLabReview');
  var taskEls = {};
  var tasksList = document.getElementById('labTasks');
  var consoleEl = document.getElementById('labConsole');
  var summaryEl = document.getElementById('labSummary');
  var reviewPanel = document.getElementById('reviewPanel');
  var statusChip = document.getElementById('labStatus');

  data.tasks.forEach(function (t, i) {
    var li = document.createElement('li');
    li.className = 'task-item';
    li.innerHTML = '<span class="task-box">·</span><span class="task-text"></span>';
    li.querySelector('.task-text').textContent = t.text;
    tasksList.appendChild(li);
    taskEls[i] = li;
  });

  var me = CFMultiEdit.create(host, {
    files: data.files,
    storageKey: 'cf:lab:' + data.slug,
    onChange: function () { resetTaskUI(); },
  });

  function resetTaskUI() {
    Object.keys(taskEls).forEach(function (i) {
      taskEls[i].className = 'task-item';
      taskEls[i].querySelector('.task-box').textContent = '·';
    });
    summaryEl.textContent = 'Code changed — run the checks again.';
    summaryEl.className = 'results-summary';
  }

  function order() { return data.files.map(function (f) { return f.name; }); }
  function checks() { return data.tasks.map(function (t) { return { expr: t.check }; }); }

  var running = false;
  btnRun.addEventListener('click', function () {
    if (running) return;
    running = true;
    btnRun.disabled = true;
    summaryEl.textContent = 'Running checks in sandbox…';
    summaryEl.className = 'results-summary running';
    consoleEl.textContent = '';
    CFRunner.runProject({ files: me.getValues(), order: order(), checks: checks() }).then(function (out) {
      running = false;
      btnRun.disabled = false;
      if (out.error && !out.results.length) {
        summaryEl.textContent = '✗ Boot error: ' + out.error;
        summaryEl.className = 'results-summary bad';
        consoleEl.textContent = out.logs || '';
        return;
      }
      consoleEl.textContent = out.logs || '(no console output)';
      var passed = 0;
      data.tasks.forEach(function (t, i) {
        var r = null;
        out.results.forEach(function (x) { if (x.i === i) r = x; });
        var ok = !!(r && r.pass);
        if (ok) passed++;
        var li = taskEls[i];
        li.className = 'task-item ' + (ok ? 'done' : 'fail');
        li.querySelector('.task-box').textContent = ok ? '✓' : '✗';
        li.title = r && r.error ? ('Error: ' + r.error) : '';
      });
      var all = passed === data.tasks.length;
      summaryEl.textContent = all
        ? '✓ All ' + passed + '/' + data.tasks.length + ' checks pass — lab complete!'
        : passed + '/' + data.tasks.length + ' checks passing' + (data.kind === 'debug' ? ' — keep hunting.' : ' — keep integrating.');
      summaryEl.className = 'results-summary ' + (all ? 'ok' : 'bad');
      if (all) markComplete();
    });
  });

  function markComplete() {
    if (data.done) return;
    fetch('../backend/api/labs/complete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf },
      credentials: 'same-origin',
      body: JSON.stringify({ slug: data.slug }),
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.ok) {
        data.done = true;
        if (statusChip) { statusChip.hidden = false; }
        CF.toast('Lab marked complete — nice engineering.', 'ok');
      }
    }).catch(function () {});
  }

  btnReset.addEventListener('click', function () {
    me.resetAll();
    resetTaskUI();
    consoleEl.textContent = '';
  });

  btnReview.addEventListener('click', function () {
    var res = CFReviewer.analyze(me.getValues(), 'javascript');
    renderReview(reviewPanel, res);
    reviewPanel.hidden = false;
    reviewPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });

  function renderReview(panel, res) {
    var items = res.findings.map(function (f) {
      return '<div class="review-item sev-' + f.sev + '">' +
        '<div class="review-title"><span class="sev-dot"></span>' + esc(f.title) + '</div>' +
        '<div class="review-detail">' + esc(f.detail) + '</div>' +
        '<div class="review-fix">→ ' + esc(f.fix) + '</div></div>';
    }).join('');
    panel.innerHTML =
      '<div class="card-head"><h3>Senior review</h3>' +
      '<span class="review-score ' + (res.score >= 90 ? 'ok' : res.score >= 70 ? 'mid' : 'bad') + '">' + res.score + '/100</span></div>' +
      '<p class="review-verdict">' + esc(res.verdict) + '</p>' +
      (items || '<p class="muted">No findings — clean code. The senior nods approvingly.</p>');
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  resetTaskUI();
  summaryEl.textContent = 'Run the checks when you think the codebase is fixed.';
});
