/* Codeface — shared utilities (loaded on every page) */
window.CF = (function () {
  'use strict';
  var csrfMeta = document.querySelector('meta[name="csrf"]');
  var csrf = csrfMeta ? csrfMeta.content : '';

  /** fetch wrapper: JSON in/out, CSRF header, throws Error(with .status/.data) on non-2xx */
  function api(path, opts) {
    opts = opts || {};
    var fetchOpts = {
      method: opts.method || (opts.body || opts.form ? 'POST' : 'GET'),
      headers: { 'X-CSRF-Token': csrf },
      credentials: 'same-origin'
    };
    if (opts.body) {
      fetchOpts.headers['Content-Type'] = 'application/json';
      fetchOpts.body = JSON.stringify(opts.body);
    } else if (opts.form) {
      fetchOpts.body = opts.form;
    }
    return fetch(path, fetchOpts).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (data) {
        if (!res.ok) {
          var err = new Error((data && data.error) || ('Request failed (HTTP ' + res.status + ')'));
          err.status = res.status;
          err.data = data;
          throw err;
        }
        return data;
      });
    });
  }

  /** Transient toast notifications */
  function toast(msg, type, ms) {
    type = type || 'ok'; ms = ms || 3500;
    var stack = document.getElementById('toastStack');
    if (!stack) return;
    var el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.textContent = msg;
    stack.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('show'); });
    setTimeout(function () {
      el.classList.remove('show');
      setTimeout(function () { el.remove(); }, 250);
    }, ms);
  }

  function debounce(fn, ms) {
    var t = null;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** DB timestamps are UTC 'YYYY-MM-DD HH:MM:SS'; ISO strings pass through. */
  function parseTs(s) {
    if (!s) return null;
    if (String(s).indexOf('T') !== -1) return new Date(s);
    return new Date(String(s).replace(' ', 'T') + 'Z');
  }

  function timeAgo(date) {
    if (!date) return '';
    var secs = Math.max(0, (Date.now() - date.getTime()) / 1000);
    if (secs < 10) return 'just now';
    if (secs < 60) return Math.floor(secs) + 's ago';
    var mins = secs / 60;
    if (mins < 60) return Math.floor(mins) + 'm ago';
    var hrs = mins / 60;
    if (hrs < 24) return Math.floor(hrs) + 'h ago';
    var days = hrs / 24;
    if (days < 30) return Math.floor(days) + 'd ago';
    return date.toLocaleDateString();
  }

  /** Rewrite every <time data-ts="..."> as "x ago" with full timestamp on hover. */
  function timeagoAll() {
    document.querySelectorAll('time[data-ts]').forEach(function (el) {
      var d = parseTs(el.getAttribute('data-ts'));
      if (d) { el.textContent = timeAgo(d); el.title = d.toLocaleString(); }
    });
  }

  function duration(ms) {
    var s = Math.max(0, Math.round(ms / 1000));
    if (s < 60) return s + 's';
    var m = Math.floor(s / 60); s %= 60;
    if (m < 60) return m + 'm ' + s + 's';
    var h = Math.floor(m / 60); m %= 60;
    if (h < 24) return h + 'h ' + m + 'm';
    return Math.floor(h / 24) + 'd ' + (h % 24) + 'h';
  }

  return {
    api: api, toast: toast, debounce: debounce, escapeHtml: escapeHtml,
    parseTs: parseTs, timeAgo: timeAgo, timeagoAll: timeagoAll, duration: duration,
    csrf: csrf
  };
})();
