/* Codeface — multi-file tabbed editor (Labs & Refactor Gym).
 * Wraps CFEditor: one tab bar + one editor per file (created lazily),
 * per-file autosave in localStorage, readonly support. */
window.CFMultiEdit = (function () {
  'use strict';

  /* opts: {files:[{name,content,readonly}], storageKey, onChange(name,value)} */
  function create(host, opts) {
    var tabs = document.createElement('div');
    tabs.className = 'file-tabs';
    var panes = document.createElement('div');
    panes.className = 'file-panes';
    host.appendChild(tabs);
    host.appendChild(panes);

    var editors = {};   // name -> {handle, pane, btn, value, readonly, original}
    var activeName = null;

    function storeKey(name) { return opts.storageKey + ':' + name; }

    function savedValue(name, original) {
      try {
        var v = localStorage.getItem(storeKey(name));
        if (v !== null) return v;
      } catch (e) {}
      return original;
    }

    function setActive(name) {
      if (activeName === name) return;
      activeName = name;
      Object.keys(editors).forEach(function (n) {
        editors[n].pane.style.display = n === name ? '' : 'none';
        editors[n].btn.classList.toggle('active', n === name);
      });
      ensureEditor(name).then(function () {
        if (editors[name].handle && editors[name].handle.focus) editors[name].handle.focus();
      });
    }

    function ensureEditor(name) {
      var rec = editors[name];
      if (rec.handlePromise) return rec.handlePromise;
      rec.handlePromise = CFEditor.create(rec.pane, {
        language: 'javascript',
        value: rec.value,
        readOnly: rec.readonly,
        onChange: rec.readonly ? null : function (v) {
          rec.value = v;
          try { localStorage.setItem(storeKey(name), v); } catch (e) {}
          if (opts.onChange) opts.onChange(name, v);
        },
      }).then(function (handle) {
        rec.handle = handle;
        return handle;
      });
      return rec.handlePromise;
    }

    opts.files.forEach(function (f) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'file-tab' + (f.readonly ? ' readonly' : '');
      btn.innerHTML = '';
      btn.textContent = f.name;
      if (f.readonly) {
        var lock = document.createElement('span');
        lock.className = 'ro-badge';
        lock.textContent = 'ro';
        btn.appendChild(lock);
      }
      btn.addEventListener('click', function () { setActive(f.name); });

      var pane = document.createElement('div');
      pane.className = 'file-pane editor-host';
      pane.style.display = 'none';

      tabs.appendChild(btn);
      panes.appendChild(pane);

      editors[f.name] = {
        pane: pane, btn: btn,
        value: savedValue(f.name, f.content),
        original: f.content,
        readonly: !!f.readonly,
        handle: null, handlePromise: null,
      };
    });

    if (opts.files.length) setActive(opts.files[0].name);

    return {
      setActive: setActive,
      /* {name: currentContent} for every file */
      getValues: function () {
        var out = {};
        Object.keys(editors).forEach(function (n) {
          var rec = editors[n];
          out[n] = rec.handle && rec.handle.getValue ? rec.handle.getValue() : rec.value;
        });
        return out;
      },
      resetAll: function () {
        Object.keys(editors).forEach(function (n) {
          var rec = editors[n];
          rec.value = rec.original;
          try { localStorage.removeItem(storeKey(n)); } catch (e) {}
          if (rec.handle && rec.handle.setValue) rec.handle.setValue(rec.original);
        });
        if (opts.onChange) opts.onChange(null, null);
      },
    };
  }

  return { create: create };
})();
