/*
 * Codeface — editor factory.
 * Loads Monaco from CDN (the one allowed external asset). If the CDN is
 * unreachable (offline XAMPP, blocked network), falls back to a styled
 * <textarea> with an identical adapter API, so every page keeps working.
 */
window.CFEditor = (function () {
  'use strict';
  var CDN = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs';
  var monacoPromise = null;

  function loadMonaco() {
    if (monacoPromise) return monacoPromise;
    monacoPromise = new Promise(function (resolve) {
      function fail() { resolve(null); }
      if (window.monaco && window.monaco.editor) { resolve(window.monaco); return; }
      var s = document.createElement('script');
      s.src = CDN + '/loader.js';
      s.async = true;
      s.onerror = fail;
      document.head.appendChild(s);
      var waited = 0;
      (function wait() {
        if (window.require) {
          try {
            window.require.config({ paths: { vs: CDN } });
            window.require(['vs/editor/editor.main'], function () { resolve(window.monaco); }, fail);
          } catch (e) { fail(); }
        } else if ((waited += 100) > 8000) {
          fail();
        } else {
          setTimeout(wait, 100);
        }
      })();
    });
    return monacoPromise;
  }

  function mapLang(l) {
    if (l === 'python') return 'python';
    if (l === 'java') return 'java';
    return 'javascript';
  }

  /**
   * create(host, {language, value, onChange, readOnly}) →
   *   {monaco, setValue, getValue, setLanguage, focus, dispose}
   */
  function create(host, options) {
    options = options || {};
    var language = options.language || 'javascript';
    var value = options.value || '';
    var onChange = options.onChange || null;
    var readOnly = !!options.readOnly;

    return loadMonaco().then(function (m) {
      if (m && m.editor) {
        var ed = m.editor.create(host, {
          value: value,
          language: mapLang(language),
          theme: 'vs-dark',
          automaticLayout: true,
          minimap: { enabled: false },
          fontSize: 14,
          fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
          scrollBeyondLastLine: false,
          readOnly: readOnly,
          padding: { top: 12 },
          renderWhitespace: 'selection',
          tabSize: 2,
          insertSpaces: true,
          wordWrap: 'on'
        });
        if (onChange) ed.onDidChangeModelContent(function () { onChange(ed.getValue()); });
        return {
          monaco: true,
          setValue: function (v) { ed.setValue(v); },
          getValue: function () { return ed.getValue(); },
          setLanguage: function (l) { m.editor.setModelLanguage(ed.getModel(), mapLang(l)); },
          focus: function () { ed.focus(); },
          dispose: function () { ed.dispose(); }
        };
      }
      // ---- textarea fallback ----
      host.classList.add('editor-fallback');
      var ta = document.createElement('textarea');
      ta.className = 'editor-textarea';
      ta.value = value;
      ta.spellcheck = false;
      ta.readOnly = readOnly;
      if (onChange) ta.addEventListener('input', function () { onChange(ta.value); });
      // Tab inserts two spaces
      ta.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
          e.preventDefault();
          var st = ta.selectionStart, en = ta.selectionEnd;
          ta.setRangeText('  ', st, en, 'end');
          if (onChange) onChange(ta.value);
        }
      });
      host.appendChild(ta);
      return {
        monaco: false,
        setValue: function (v) { ta.value = v; },
        getValue: function () { return ta.value; },
        setLanguage: function () {},
        focus: function () { ta.focus(); },
        dispose: function () { ta.remove(); }
      };
    });
  }

  return { create: create };
})();
