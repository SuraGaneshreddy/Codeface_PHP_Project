/* Codeface — rooms lobby: create room + skill matchmaking */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  /* ---- create room ---- */
  var createForm = document.getElementById('createRoomForm');
  if (createForm) {
    createForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(createForm);
      var btn = createForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      CF.api('../backend/api/rooms/create.php', {
        body: {
          name: fd.get('name'),
          language: fd.get('language'),
          problem_id: parseInt(fd.get('problem_id') || '0', 10) || 0
        }
      }).then(function (r) {
        window.location.href = 'room.php?code=' + encodeURIComponent(r.code);
      }).catch(function (err) {
        CF.toast(err.message, 'err');
        btn.disabled = false;
      });
    });
  }

  /* ---- matchmaking ---- */
  var mmForm = document.getElementById('matchForm');
  var mmStatus = document.getElementById('mmStatus');
  var mmBtn = document.getElementById('mmBtn');
  var poll = null, waiting = false;

  function mmUI(mode, extra) {
    if (mode === 'waiting') {
      waiting = true;
      mmBtn.textContent = 'Cancel search';
      mmBtn.classList.remove('btn-primary');
      mmBtn.classList.add('btn-ghost');
      mmStatus.innerHTML = '<span class="mm-dots"><span></span><span></span><span></span></span> ' +
        'Searching for a ' + CF.escapeHtml(extra.difficulty) + ' ' + CF.escapeHtml(extra.language) + ' partner…';
      mmStatus.className = 'mm-status show';
    } else {
      waiting = false;
      mmBtn.textContent = 'Find a match';
      mmBtn.classList.add('btn-primary');
      mmBtn.classList.remove('btn-ghost');
      mmStatus.className = 'mm-status';
      mmStatus.innerHTML = '';
    }
  }

  function goRoom(code) {
    if (poll) clearInterval(poll);
    CF.toast('Match found! Taking you to the room…');
    window.location.href = 'room.php?code=' + encodeURIComponent(code);
  }

  if (mmForm) {
    mmForm.addEventListener('submit', function (e) {
      e.preventDefault();

      if (waiting) { // cancel
        CF.api('../backend/api/matchmaking/cancel.php', { body: {} })
          .catch(function () {})
          .then(function () { if (poll) clearInterval(poll); mmUI('idle'); });
        return;
      }

      var fd = new FormData(mmForm);
      var payload = { language: fd.get('language'), difficulty: fd.get('difficulty') };
      mmBtn.disabled = true;
      CF.api('../backend/api/matchmaking/join.php', { body: payload })
        .then(function (r) {
          mmBtn.disabled = false;
          if (r.status === 'matched') { goRoom(r.room_code); return; }
          mmUI('waiting', payload);
          poll = setInterval(function () {
            CF.api('../backend/api/matchmaking/status.php')
              .then(function (s) {
                if (s.status === 'matched') goRoom(s.room_code);
                else if (s.status === 'idle') { clearInterval(poll); mmUI('idle'); }
              })
              .catch(function () {});
          }, 2000);
        })
        .catch(function (err) {
          mmBtn.disabled = false;
          CF.toast(err.message, 'err');
        });
    });

    // if the tab closes mid-search, don't keep others waiting on us
    window.addEventListener('beforeunload', function () {
      if (!waiting) return;
      try {
        var f = new FormData();
        f.append('csrf', CF.csrf);
        navigator.sendBeacon('../backend/api/matchmaking/cancel.php', f);
      } catch (e) {}
    });
  }
});
