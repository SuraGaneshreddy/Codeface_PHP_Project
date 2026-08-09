/* Codeface — lesson page (try-it runner + completion) */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var data = JSON.parse(document.getElementById('lessonData').textContent);

  /* ---- try-it editor ---- */
  if (data.runnable && data.try) {
    var outEl = document.getElementById('tryOut');
    var btnRun = document.getElementById('btnTryRun');
    CFEditor.create(document.getElementById('tryEditor'), {
      language: data.monaco === 'typescript' ? 'typescript' : data.lang,
      value: data.try
    }).then(function (editor) {
      var running = false;
      btnRun.addEventListener('click', function () {
        if (running) return;
        running = true;
        btnRun.disabled = true;
        outEl.textContent = data.lang === 'python' ? 'Loading Python runtime (first run downloads ~10 MB)…' : 'Running…';
        CFRunner.runSnippet({
          code: editor.getValue(),
          lang: data.lang,
          onLoading: function () { outEl.textContent = 'Downloading & booting Python runtime — only happens once…'; }
        }).then(function (out) {
          running = false;
          btnRun.disabled = false;
          var text = out.logs || '';
          if (out.error) text += (text ? '\n' : '') + '⚠ ' + out.error;
          outEl.textContent = text || '(no output — add a print/console.log)';
        });
      });
    });
  }

  /* ---- completion toggle ---- */
  var btn = document.getElementById('btnComplete');
  if (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      CF.api('../backend/api/learn/complete.php', { body: { lesson_id: data.lessonId } })
        .then(function (r) {
          btn.disabled = false;
          var pill = document.getElementById('statusPill');
          if (r.completed) {
            btn.textContent = '✓ Completed — undo';
            btn.classList.remove('btn-primary'); btn.classList.add('btn-ghost');
            pill.textContent = '✓ completed'; pill.classList.add('done-pill');
            CF.toast('Lesson complete! (' + r.track_done + '/' + r.track_total + ' in this track)');
          } else {
            btn.textContent = 'Mark lesson complete';
            btn.classList.add('btn-primary'); btn.classList.remove('btn-ghost');
            pill.textContent = 'in progress'; pill.classList.remove('done-pill');
            CF.toast('Marked as not completed.', 'info');
          }
        })
        .catch(function (e) {
          btn.disabled = false;
          CF.toast(e.message, 'err');
        });
    });
  }
});
