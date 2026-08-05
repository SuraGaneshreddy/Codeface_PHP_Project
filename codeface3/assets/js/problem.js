/* Codeface — problem page controller (multi-language) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var data = JSON.parse(document.getElementById('problemData').textContent);
  var host = document.getElementById('editorHost');
  var resultsEl = document.getElementById('results');
  var summaryEl = document.getElementById('resultsSummary');
  var timeEl = document.getElementById('resultsTime');
  var btnRun = document.getElementById('btnRun');
  var btnSubmit = document.getElementById('btnSubmit');
  var btnReset = document.getElementById('btnReset');
  var btnRef = document.getElementById('btnRef');
  var refPanel = document.getElementById('refPanel');
  var langSel = document.getElementById('langSel');
  var runnerNote = document.getElementById('runnerNote');

  var RUNNABLE = { js: 1, ts: 1, python: 1 };
  var langMeta = {};
  data.languages.forEach(function (l) { langMeta[l.id] = l; });

  function keyOf(lang) { return 'cf:code:' + data.slug + ':' + lang; }
  function runnable(lang) { return !!RUNNABLE[langMeta[lang].runner]; }

  // build language selector
  data.languages.forEach(function (l) {
    var o = document.createElement('option');
    o.value = l.id;
    o.textContent = l.name + (RUNNABLE[l.runner] ? '' : ' (reference)');
    langSel.appendChild(o);
  });
  var busy = false; // setBusy() owns this after the editor boots

  var currentLang = 'javascript';
  try { currentLang = localStorage.getItem('cf:lang:last') || 'javascript'; } catch (e) {}
  if (!data.starters[currentLang]) currentLang = 'javascript';
  langSel.value = currentLang;

  function initialValue(lang) {
    var saved = null;
    try { saved = localStorage.getItem(keyOf(lang)); } catch (e) {}
    return saved !== null ? saved : (data.starters[lang] || '// no starter for this language\n');
  }

  function updateNotes() {
    if (runnable(currentLang)) {
      runnerNote.textContent = currentLang === 'typescript'
        ? 'runs locally 🔒 (types stripped, simple style only)'
        : (currentLang === 'python' ? 'runs locally 🔒 (first run downloads the Python runtime)' : 'runs locally 🔒');
      btnSubmit.disabled = busy;
      btnSubmit.title = '';
    } else {
      runnerNote.textContent = 'reference mode — in-browser run is available for JS / TS / Python';
      btnSubmit.disabled = true;
      btnSubmit.title = 'Submissions run in JavaScript, TypeScript or Python';
    }
  }
  btnSubmit.disabled = true;
  btnSubmit.title = 'Submissions run in JavaScript, TypeScript or Python';

  // reference panel (JS reference solution)
  document.getElementById('refCode').textContent = data.solution || '// no reference recorded';
  btnRef.addEventListener('click', function () {
    refPanel.classList.toggle('hidden');
    btnRef.classList.toggle('btn-ghost');
    btnRef.classList.toggle('btn-outline');
  });
  function openRef() {
    refPanel.classList.remove('hidden');
    btnRef.classList.remove('btn-ghost');
    btnRef.classList.add('btn-outline');
  }

  CFEditor.create(host, {
    language: langMeta[currentLang].monaco,
    value: initialValue(currentLang),
    onChange: CF.debounce(function (v) {
      try { localStorage.setItem(keyOf(currentLang), v); } catch (e) {}
    }, 300)
  }).then(function (editor) {
    if (!editor.monaco) CF.toast('Monaco CDN unreachable — using the offline fallback editor.', 'info', 5000);
    updateNotes();

    langSel.addEventListener('change', function () {
      currentLang = langSel.value;
      try { localStorage.setItem('cf:lang:last', currentLang); } catch (e) {}
      editor.setLanguage(langMeta[currentLang].monaco);
      editor.setValue(initialValue(currentLang));
      resultsEl.innerHTML = '';
      summaryEl.textContent = 'Run the tests to see results.';
      timeEl.textContent = '';
      updateNotes();
    });

    btnReset.addEventListener('click', function () {
      if (!window.confirm('Reset to the starter code for ' + langMeta[currentLang].name + '?')) return;
      editor.setValue(data.starters[currentLang] || '');
      try { localStorage.removeItem(keyOf(currentLang)); } catch (e) {}
      CF.toast('Editor reset.');
    });

    function setBusy(b) {
      busy = b;
      btnRun.disabled = b; btnReset.disabled = b;
      if (runnable(currentLang)) btnSubmit.disabled = b;
    }

    function runAll(submit) {
      if (busy) return;
      if (!runnable(currentLang)) {
        openRef();
        CF.toast('Execution runs in-browser for JavaScript, TypeScript and Python — compare your code against the reference solution and the visible tests.', 'info', 6000);
        return;
      }
      setBusy(true);
      resultsEl.innerHTML = '';
      summaryEl.textContent = currentLang === 'python' ? 'Loading Python runtime & running…' : 'Running tests…';
      timeEl.textContent = '';
      var listEl = document.createElement('div');
      resultsEl.appendChild(listEl);
      var t0 = performance.now();

      CFRunner.run({
        code: editor.getValue(),
        tests: data.tests,
        fnName: data.fnNames[currentLang],
        lang: currentLang,
        onResult: function (m) { CFRunner.renderItem(listEl, m); }
      }).then(function (out) {
        var elapsed = Math.round(performance.now() - t0);
        timeEl.textContent = elapsed + 'ms total · runs in your browser';
        var counts = CFRunner.renderSummary(summaryEl, out.results, data.tests.length, out);
        setBusy(false);
        if (submit && !out.bootError && !out.timedOut) {
          CF.api('api/submissions.php', {
            body: {
              problem_id: data.problemId,
              code: editor.getValue(),
              passed: counts.passed,
              total: counts.total,
              runtime_ms: elapsed
            }
          }).then(function (r) {
            if (r.status === 'pass') {
              CF.toast(r.first_solve
                ? '🎉 Solved! +' + r.points + ' pts — new rating ' + r.rating
                : 'All tests passing — attempt saved (already solved before).', 'ok', 5000);
            } else {
              CF.toast('Attempt saved — ' + counts.passed + '/' + counts.total + ' passing. Keep going!', 'info');
            }
          }).catch(function (e) { CF.toast('Could not save submission: ' + e.message, 'err'); });
        }
      });
    }

    btnRun.addEventListener('click', function () { runAll(false); });
    btnSubmit.addEventListener('click', function () { runAll(true); });
    host.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runAll(false); }
    });
  });
});
