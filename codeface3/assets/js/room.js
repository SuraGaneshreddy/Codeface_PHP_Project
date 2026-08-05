/* Codeface — live room controller: SSE sync, shared pads, chat, presence */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var S = JSON.parse(document.getElementById('roomData').textContent);
  var code = S.room.code;
  var me = S.me;

  /* ---------- state ---------- */
  var LANGS = {};
  (S.languages || []).forEach(function (l) { LANGS[l.id] = l; });
  var RUNNABLE = { js: 1, ts: 1, python: 1 };
  var pads = S.pads || {};
  Object.keys(LANGS).forEach(function (l) {
    if (!pads[l]) pads[l] = { content: '', version: 0, editor: null };
  });
  var state = {
    lang: (S.room.language && pads[S.room.language]) ? S.room.language : 'javascript',
    applying: false,
    pushing: false,
    lastChatId: 0
  };
  var noteEl = document.getElementById('editNote');
  function setNote(t) { if (noteEl) noteEl.textContent = t; }

  var editorPromise = CFEditor.create(document.getElementById('editorHost'), {
    language: state.lang,
    value: pads[state.lang].content,
    onChange: function () { if (!state.applying) schedulePush(); }
  });

  /* ---------- pad syncing ---------- */
  var pushTimer = null;
  function schedulePush() {
    clearTimeout(pushTimer);
    pushTimer = setTimeout(pushNow, 450);
  }

  function pushNow() {
    if (!editorRef) return;
    var lang = state.lang, pad = pads[lang];
    var content = editorRef.getValue();
    if (state.pushing || content === pad.content) return;
    state.pushing = true;
    var baseVer = pad.version;
    CF.api('api/rooms/push.php', {
      body: { code: code, language: lang, base_version: baseVer, content: content }
    }).then(function (r) {
      pad.version = r.version;
      pad.content = content;
      pad.editor = me.username;
      setNote('All changes synced · v' + r.version);
    }).catch(function (err) {
      if (err.status === 409 && err.data && typeof err.data.content === 'string') {
        adoptRemote(lang, err.data.content, err.data.version, err.data.editor);
      } else {
        setNote('Sync issue: ' + err.message);
      }
    }).then(function () {  // (finally)
      state.pushing = false;
      if (editorRef && state.lang === lang && editorRef.getValue() !== pads[lang].content) {
        pushNow(); // user kept typing during the push
      }
    });
  }

  function adoptRemote(lang, content, version, editor) {
    var pad = pads[lang] || (pads[lang] = { content: '', version: 0, editor: null });
    if (version < pad.version) return;
    pad.version = version;
    pad.content = content;
    pad.editor = editor || null;
    if (lang === state.lang && editorRef && editorRef.getValue() !== content) {
      state.applying = true;
      var sel = editorRef.monaco ? null : [editorRef.getValue().length, 0];
      editorRef.setValue(content);
      state.applying = false;
      setNote((editor ? '@' + editor : 'Someone') + ' updated the ' + lang + ' pad · v' + version);
    }
  }

  /* ---------- editor boot + language tabs ---------- */
  var editorRef = null;
  editorPromise.then(function (editor) {
    editorRef = editor;

    document.querySelectorAll('#langTabs .lang-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var lang = btn.getAttribute('data-lang');
        if (lang === state.lang) return;
        document.querySelectorAll('#langTabs .lang-tab').forEach(function (b) { b.classList.toggle('on', b === btn); });
        state.lang = lang;
        state.applying = true;
        editor.setValue(pads[lang].content);
        editor.setLanguage(lang === 'javascript' ? 'javascript' : lang);
        state.applying = false;
        setNote('Viewing the ' + lang + ' pad');
      });
    });

    var btnRun = document.getElementById('btnRun');
    if (btnRun && S.problem) {
      btnRun.addEventListener('click', function () { runTests(editor); });
    }
  });

  /* ---------- test running (attached problem; runnable pads only) ---------- */
  function runTests(editor) {
    var meta = LANGS[state.lang];
    if (!meta || !RUNNABLE[meta.runner]) {
      CF.toast('Tests run against the JavaScript, TypeScript or Python pad — switch to one of those tabs.', 'info', 5000);
      return;
    }
    var resultsEl = document.getElementById('results');
    var summaryEl = document.getElementById('resultsSummary');
    var timeEl = document.getElementById('resultsTime');
    resultsEl.innerHTML = '';
    summaryEl.textContent = state.lang === 'python' ? 'Loading Python runtime & running…' : 'Running tests…';
    var listEl = document.createElement('div');
    resultsEl.appendChild(listEl);
    var t0 = performance.now();
    CFRunner.run({
      code: editor.getValue(),
      tests: S.problem.tests,
      fnName: (S.problem.fnNames && S.problem.fnNames[state.lang]) || S.problem.functionName,
      lang: state.lang,
      onResult: function (m) { CFRunner.renderItem(listEl, m); }
    }).then(function (out) {
      timeEl.textContent = Math.round(performance.now() - t0) + 'ms total';
      CFRunner.renderSummary(summaryEl, out.results, S.problem.tests.length, out);
    });
  }

  /* ---------- chat ---------- */
  var chatList = document.getElementById('chatList');
  var chatCount = document.getElementById('chatCount');
  var renderedCount = 0;

  function renderMsg(m) {
    if (m.id <= state.lastChatId) return;
    state.lastChatId = m.id;
    renderedCount++;
    var d = document.createElement('div');
    d.className = 'chat-msg';
    d.innerHTML =
      '<div class="chat-msg-head"><span class="chat-user" style="color:' + CF.escapeHtml(m.color || '#888') + '">' +
        CF.escapeHtml(m.username) + '</span>' +
      '<span class="chat-time">' + CF.timeAgo(CF.parseTs(m.created_at)) + '</span></div>' +
      '<div class="chat-body">' + CF.escapeHtml(m.body) + '</div>';
    chatList.appendChild(d);
    chatList.scrollTop = chatList.scrollHeight;
    if (chatCount) chatCount.textContent = '(' + renderedCount + ')';
  }
  (S.chat || []).forEach(renderMsg);

  var chatForm = document.getElementById('chatForm');
  var chatInput = document.getElementById('chatInput');
  chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = chatInput.value.trim();
    if (!body) return;
    chatInput.value = '';
    CF.api('api/rooms/chat.php', { body: { code: code, body: body } })
      .then(function (r) { renderMsg(r.message); })
      .catch(function (err) { CF.toast('Message failed: ' + err.message, 'err'); chatInput.value = body; });
  });

  /* ---------- presence ---------- */
  var presenceList = document.getElementById('presenceList');
  function renderPresence(members) {
    if (!presenceList) return;
    var sorted = members.slice().sort(function (a, b) { return (b.online ? 1 : 0) - (a.online ? 1 : 0); });
    var onlineCount = 0;
    var html = '';
    sorted.forEach(function (m) {
      if (m.online) onlineCount++;
      html += '<div class="presence-chip' + (m.online ? '' : ' off') + '">' +
        '<span class="avatar" style="background:' + CF.escapeHtml(m.avatar_color) + '">' +
          CF.escapeHtml(m.username.charAt(0).toUpperCase()) + '</span>' +
        '<span class="presence-name">@' + CF.escapeHtml(m.username) +
          (m.role === 'owner' ? ' <span title="room owner">👑</span>' : '') +
          (m.username === me.username ? ' <span class="you-pill">you</span>' : '') +
        '</span>' +
        '<span class="dot ' + (m.online ? 'ok' : 'grey') + '" title="' + (m.online ? 'online' : 'away') + '"></span>' +
      '</div>';
    });
    presenceList.innerHTML = html || '<p class="muted">Nobody here yet — share the code!</p>';
  }
  renderPresence(S.members || []);

  /* ---------- side tabs ---------- */
  document.querySelectorAll('.side-tabs .tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.side-tabs .tab-btn').forEach(function (b) { b.classList.toggle('active', b === btn); });
      document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.add('hidden'); });
      document.getElementById(btn.getAttribute('data-tab')).classList.remove('hidden');
    });
  });

  /* ---------- copy code ---------- */
  var copyBtn = document.getElementById('copyCode');
  if (copyBtn) copyBtn.addEventListener('click', function () {
    function done() { CF.toast('Room code ' + code + ' copied — share it!'); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(done, done);
    } else { done(); }
  });

  /* ---------- realtime: SSE with polling fallback ---------- */
  var connPill = document.getElementById('connPill');
  var connText = document.getElementById('connText');
  function setConn(mode, label) {
    if (connPill) connPill.className = 'conn-pill ' + mode;
    if (connText) connText.textContent = label;
  }

  function applySnapshot(d) {
    if (d.pads) {
      Object.keys(d.pads).forEach(function (lang) {
        var p = d.pads[lang];
        if (!pads[lang] || p.version > pads[lang].version) {
          adoptRemote(lang, p.content, p.version, p.editor);
        }
      });
    }
    if (d.chat) {
      var latest = d.chat.slice(-30);
      latest.forEach(renderMsg);
    }
    if (d.members) renderPresence(d.members);
  }

  var pollTimer = null;
  function startPolling(reason) {
    if (pollTimer) return;
    setConn('poll', 'live (polling) — ' + reason);
    pollTimer = setInterval(function () {
      CF.api('api/rooms/state.php?code=' + encodeURIComponent(code))
        .then(applySnapshot)
        .catch(function () {});
    }, 2500);
  }

  var es = null, sseFails = 0;
  function connect() {
    if (!('EventSource' in window)) { startPolling('EventSource unsupported'); return; }
    setConn('busy', 'connecting…');
    es = new EventSource('api/rooms/stream.php?code=' + encodeURIComponent(code) + '&_=' + Date.now());
    es.onopen = function () { sseFails = 0; setConn('live', 'live'); };
    es.addEventListener('snapshot', function (e) { applySnapshot(JSON.parse(e.data)); });
    es.addEventListener('code', function (e) {
      var d = JSON.parse(e.data);
      if (d.editor === me.username) {
        var pad = pads[d.language];
        if (pad && d.version > pad.version) pad.version = d.version;
        return; // our own edit echoing back
      }
      adoptRemote(d.language, d.content, d.version, d.editor);
    });
    es.addEventListener('chat', function (e) { renderMsg(JSON.parse(e.data)); });
    es.addEventListener('presence', function (e) { renderPresence(JSON.parse(e.data).members); });
    es.onerror = function () {
      sseFails++;
      setConn('busy', 'reconnecting…');
      if (sseFails >= 8 && es) { es.close(); startPolling('SSE unreachable'); }
    };
  }
  connect();

  /* ---------- heartbeat + leave ---------- */
  setInterval(function () {
    CF.api('api/rooms/heartbeat.php', { body: { code: code } }).catch(function () {});
  }, 5000);

  window.addEventListener('beforeunload', function () {
    try {
      var f = new FormData();
      f.append('code', code);
      f.append('csrf', CF.csrf);
      navigator.sendBeacon('api/rooms/leave.php', f);
    } catch (e) {}
  });
});
