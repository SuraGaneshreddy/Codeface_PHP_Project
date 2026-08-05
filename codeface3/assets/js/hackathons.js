/* Codeface — hackathon join/leave + countdowns */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  document.querySelectorAll('.hack-join-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = parseInt(btn.getAttribute('data-id'), 10);
      var joined = btn.getAttribute('data-joined') === '1';
      btn.disabled = true;
      CF.api('api/hackathons/join.php', {
        body: { hackathon_id: id, action: joined ? 'leave' : 'join' }
      }).then(function (r) {
        btn.disabled = false;
        btn.setAttribute('data-joined', r.joined ? '1' : '0');
        btn.textContent = r.joined ? 'Leave' : 'Join';
        btn.classList.toggle('btn-primary', !r.joined);
        btn.classList.toggle('btn-ghost', r.joined);
        var card = btn.closest('.hack-card');
        var counter = card ? card.querySelector('.participant-count') : null;
        if (counter) counter.textContent = r.count + ' joined';
        CF.toast(r.joined ? 'You are in! Good luck.' : 'Left the hackathon.');
      }).catch(function (err) {
        btn.disabled = false;
        CF.toast(err.message, 'err');
      });
    });
  });

  var cds = document.querySelectorAll('[data-countdown]');
  function tick() {
    var now = Date.now();
    cds.forEach(function (el) {
      var target = new Date(el.getAttribute('data-countdown')).getTime();
      var left = target - now;
      el.textContent = left <= 0 ? 'now' : CF.duration(left);
    });
  }
  if (cds.length) { tick(); setInterval(tick, 1000); }
});
