<?php
declare(strict_types=1);

/**
 * Refactor Gym — code-maintenance challenges (V4).
 *
 * Each challenge ships a messy-but-WORKING repo. All checks PASS on the
 * original code: the goal is to improve structure (complexity, duplication,
 * naming) WITHOUT breaking behavior. Score = tests × quality improvement
 * vs the stored baseline (computed with assets/js/metrics.js).
 *
 * files[]: {name, content}   base: {comp: cyclomatic-ish, dup: duplicated-line %}
 */

function cf_refactors(): array {
    static $all = null;
    if ($all !== null) return $all;

    $all = [

        // ---------------------------------------------------------- 1
        [
            'slug' => 'god-function-checkout',
            'title' => 'The God Function',
            'summary' => 'One 60-line function prices, taxes, discounts AND formats receipts.',
            'goal' => 'Split tax / discount / receipt building into small named helpers. Kill the magic rates with a table. Behavior must stay byte-identical.',
            'base' => ['comp' => 12, 'dup' => 0],
            'files' => [
                ['name' => 'checkout.js', 'content' => <<<'CF_E'
// checkout.js — written in a hurry during a sale. One function does EVERYTHING.
function processOrder(items, coupon) {
  var total = 0;
  var lines = [];
  for (var i = 0; i < items.length; i++) {
    var p = items[i].price * items[i].qty;
    if (items[i].cat === 'food') { p = p * 1.05; }
    if (items[i].cat === 'electronics') { p = p * 1.18; }
    if (items[i].cat === 'clothes') { p = p * 1.12; }
    if (items[i].qty >= 5) { p = p * 0.95; }
    total = total + p;
    lines.push(items[i].name + ' x' + items[i].qty + ' = ' + p.toFixed(2));
  }
  if (coupon === 'SAVE10') {
    total = total * 0.9;
  } else {
    if (coupon === 'SAVE5') {
      total = total - 5;
      if (total < 0) { total = 0; }
    }
  }
  var receipt = '';
  for (var j = 0; j < lines.length; j++) {
    receipt = receipt + lines[j];
    if (j < lines.length - 1) { receipt = receipt + ' | '; }
  }
  receipt = receipt + ' || TOTAL: ' + total.toFixed(2);
  return { total: Math.round(total * 100) / 100, receipt: receipt };
}
CF_E],
            ],
            'checks' => [
                ['text' => 'Electronics bulk line: 5×100 → 590, qty-discount → 560.50', 'check' => 'processOrder([{name:"Widget",cat:"electronics",price:100,qty:5}],"NONE").total === 560.5'],
                ['text' => 'Food line: 2×40 → 84.00', 'check' => 'processOrder([{name:"Apple",cat:"food",price:40,qty:2}],"NONE").total === 84'],
                ['text' => 'SAVE10 coupon on combined order → 580.05', 'check' => 'processOrder([{name:"Widget",cat:"electronics",price:100,qty:5},{name:"Apple",cat:"food",price:40,qty:2}],"SAVE10").total === 580.05'],
                ['text' => 'Receipt format preserved byte-for-byte', 'check' => 'processOrder([{name:"Widget",cat:"electronics",price:100,qty:5}],"NONE").receipt === "Widget x5 = 560.50 || TOTAL: 560.50"'],
                ['text' => 'SAVE5 floors at 0 on tiny orders', 'check' => 'processOrder([{name:"Pen",cat:"food",price:2,qty:1}],"SAVE5").total === 0'],
            ],
        ],

        // ---------------------------------------------------------- 2
        [
            'slug' => 'stringy-pricing',
            'title' => 'Money as Strings',
            'summary' => 'Prices like "₹1,299.00" get re-parsed by hand in 6 different places.',
            'goal' => 'One parse/format pair, used everywhere. Add Indian-grouping formatting once instead of copy-pasting the regex.',
            'base' => ['comp' => 7, 'dup' => 26],
            'files' => [
                ['name' => 'pricing.js', 'content' => <<<'CF_E'
// pricing.js — prices are strings like "₹1,299.00" all over the codebase.
function billTotal(lines) {
  var t = 0;
  for (var i = 0; i < lines.length; i++) {
    var n = parseFloat(lines[i].price.replace('₹', '').replace(/,/g, ''));
    t = t + n * lines[i].qty;
  }
  return t;
}
function billLineText(line) {
  var n = parseFloat(line.price.replace('₹', '').replace(/,/g, ''));
  var amt = n * line.qty;
  var s = amt.toFixed(2);
  s = s.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return line.label + ': ₹' + s;
}
function billGrandText(lines) {
  var t = 0;
  for (var i = 0; i < lines.length; i++) {
    var n = parseFloat(lines[i].price.replace('₹', '').replace(/,/g, ''));
    t = t + n * lines[i].qty;
  }
  var s = t.toFixed(2);
  s = s.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return 'GRAND TOTAL: ₹' + s;
}
CF_E],
            ],
            'checks' => [
                ['text' => 'Total with thousands separator: ₹1,299.00 × 2 + ₹50.50 → 2648.50', 'check' => 'billTotal([{price:"₹1,299.00",qty:2},{price:"₹50.50",qty:1}]) === 2648.5'],
                ['text' => 'Line format: "Laptop: ₹25,980.00"', 'check' => 'billLineText({label:"Laptop",price:"₹12,990.00",qty:2}) === "Laptop: ₹25,980.00"'],
                ['text' => 'Grand text: "GRAND TOTAL: ₹2,648.50"', 'check' => 'billGrandText([{price:"₹1,299.00",qty:2},{price:"₹50.50",qty:1}]) === "GRAND TOTAL: ₹2,648.50"'],
                ['text' => 'Plain small amounts still work', 'check' => 'billLineText({label:"Pen",price:"₹10.00",qty:3}) === "Pen: ₹30.00"'],
            ],
        ],

        // ---------------------------------------------------------- 3
        [
            'slug' => 'nested-report-builder',
            'title' => 'Pyramid of Doom Report',
            'summary' => 'A sales report built inside 5-deep if nesting — add a product and you touch 4 places.',
            'goal' => 'Flatten with early continues and small accumulators. Same output shape, fraction of the nesting.',
            'base' => ['comp' => 7, 'dup' => 13],
            'files' => [
                ['name' => 'report.js', 'content' => <<<'CF_E'
// report.js — sales aggregation. It works. Nobody knows how.
function buildReport(sales) {
  var out = {};
  for (var i = 0; i < sales.length; i++) {
    if (sales[i] !== null) {
      if (sales[i].units > 0) {
        var r = sales[i].region;
        if (r !== '') {
          if (!out[r]) {
            out[r] = { total: 0, products: {} };
            out[r].total = out[r].total + sales[i].units;
            out[r].products[sales[i].product] = sales[i].units;
          } else {
            out[r].total = out[r].total + sales[i].units;
            if (!out[r].products[sales[i].product]) {
              out[r].products[sales[i].product] = sales[i].units;
            } else {
              out[r].products[sales[i].product] = out[r].products[sales[i].product] + sales[i].units;
            }
          }
        }
      }
    }
  }
  return out;
}
CF_E],
            ],
            'checks' => [
                ['text' => 'Region totals: N = 9 units', 'check' => 'buildReport([{region:"N",product:"A",units:2},{region:"N",product:"B",units:3},{region:"S",product:"A",units:1},{region:"N",product:"A",units:4}]).N.total === 9'],
                ['text' => 'Product merge inside region: N.A = 6', 'check' => 'buildReport([{region:"N",product:"A",units:2},{region:"N",product:"A",units:4}]).N.products.A === 6'],
                ['text' => 'Zero/negative units are skipped', 'check' => 'Object.keys(buildReport([{region:"N",product:"A",units:0}])).length === 0'],
                ['text' => 'Null rows and empty regions are skipped safely', 'check' => 'Object.keys(buildReport([null,{region:"",product:"A",units:5}])).length === 0'],
            ],
        ],

        // ---------------------------------------------------------- 4
        [
            'slug' => 'global-state-soup',
            'title' => 'Global State Soup',
            'summary' => 'Session state lives in two loose globals with cryptic short names.',
            'goal' => 'Wrap state in a module pattern (IIFE) exposing the same four functions. Names that say what they are. No behavioral change.',
            'base' => ['comp' => 9, 'dup' => 0],
            'files' => [
                ['name' => 'session.js', 'content' => <<<'CF_E'
// session.js — globals everywhere. It works, until someone else also declares "u".
var u = null;
var lg = [];
function login(name, role) { u = { n: name, r: role }; lg.push('in:' + name); return true; }
function logout() { if (u) { lg.push('out:' + u.n); } u = null; }
function currentUser() { return u ? u.n : null; }
function auditLog() { return lg.slice(); }
function can(act) {
  if (!u) return false;
  if (u.r === 'admin') { return act === 'view' || act === 'edit' || act === 'delete'; }
  if (u.r === 'user') { return act === 'view' || act === 'edit'; }
  return act === 'view';
}
CF_E],
            ],
            'checks' => [
                ['text' => 'login + currentUser', 'check' => 'login("asha","admin") === true && currentUser() === "asha"'],
                ['text' => 'admin can delete', 'check' => 'can("delete") === true'],
                ['text' => 'logout clears user and blocks actions', 'check' => '(logout(), currentUser() === null && can("view") === false)'],
                ['text' => 'user role can edit but not delete', 'check' => 'login("bhavesh","user") && can("edit") === true && can("delete") === false'],
                ['text' => 'audit log recorded the events in order', 'check' => 'auditLog().join(",") === "in:asha,out:asha,in:bhavesh"'],
            ],
        ],

        // ---------------------------------------------------------- 5
        [
            'slug' => 'copy-paste-validators',
            'title' => 'Copy-Paste Validators',
            'summary' => 'Three validators cloned from the same Stack Overflow answer — same skeleton, same quirks, three files worth of repetition.',
            'goal' => 'Extract a rule-runner (array of predicates with messages) so each validator is just data. Fix nothing behaviorally.',
            'base' => ['comp' => 22, 'dup' => 30],
            'files' => [
                ['name' => 'validators.js', 'content' => <<<'CF_E'
// validators.js — email | phone | pin. Spot the pattern. (You can't miss it.)
function validateEmail(v) {
  var ok = true;
  if (ok && typeof v !== 'string') ok = false;
  if (ok && v.indexOf('@') < 1) ok = false;
  if (ok && v.indexOf('.', v.indexOf('@')) < v.indexOf('@') + 2) ok = false;
  if (ok && v.length > 254) ok = false;
  return ok;
}
function validatePhone(v) {
  var ok = true;
  if (ok && typeof v !== 'string') ok = false;
  if (ok) {
    var d = v.replace(/\D/g, '');
    if (ok && d.length !== 10) ok = false;
    if (ok && d.charAt(0) === '0') ok = false;
  }
  return ok;
}
function validatePin(v) {
  var ok = true;
  if (ok && typeof v !== 'string') ok = false;
  if (ok && !/^\d{6}$/.test(v)) ok = false;
  if (ok && v === '000000') ok = false;
  return ok;
}
CF_E],
            ],
            'checks' => [
                ['text' => 'valid email passes, junk rejected', 'check' => 'validateEmail("dev@codeface.io") === true && validateEmail("nope@") === false && validateEmail("@x.io") === false'],
                ['text' => 'phone: 10 digits, not starting with 0', 'check' => 'validatePhone("98765 43210") === true && validatePhone("0123456789") === false && validatePhone("123") === false'],
                ['text' => 'pin: 6 digits, not 000000', 'check' => 'validatePin("395001") === true && validatePin("000000") === false && validatePin("1234") === false'],
                ['text' => 'non-strings rejected everywhere', 'check' => 'validateEmail(5) === false && validatePhone(null) === false && validatePin({}) === false'],
            ],
        ],

        // ---------------------------------------------------------- 6
        [
            'slug' => 'sync-spaghetti-stats',
            'title' => 'Spaghetti Stats (Dead Code Special)',
            'summary' => 'A stats module haunted by dead branches, unused variables and a commented-out sort "experiment".',
            'goal' => 'Delete the dead code, name the pipeline steps (mean / median / percentile) and prove nothing changed numerically.',
            'base' => ['comp' => 4, 'dup' => 0],
            'files' => [
                ['name' => 'stats.js', 'content' => <<<'CF_E'
// stats.js — computes mean / median / p95. Plus archaeology.
function computeStats(nums) {
  var unused = [];
  var tmp = 0;
  var i;
  if (false) { console.log('debug mode', nums); }        // dead branch
  for (i = 0; i < nums.length; i++) { tmp = tmp + nums[i]; }
  var mean = tmp / nums.length;
  var sorted = nums.slice().sort(function (a, b) { return a - b; });
  // var sorted = nums.slice().sort();   // old experiment — do not enable
  var mid = Math.floor(sorted.length / 2);
  var median;
  if (sorted.length % 2 === 1) {
    median = sorted[mid];
  } else {
    median = (sorted[mid - 1] + sorted[mid]) / 2;
  }
  var idx = Math.ceil(0.95 * sorted.length) - 1;
  var p95 = sorted[idx];
  var result = {};
  result.mean = mean;
  result.median = median;
  result.p95 = p95;
  unused.push('haunted');
  return result;
}
CF_E],
            ],
            'checks' => [
                ['text' => '1..20: mean & median 10.5', 'check' => '(function(){ var a=[]; for(var i=1;i<=20;i++)a.push(i); var s=computeStats(a); return s.mean===10.5 && s.median===10.5; })()'],
                ['text' => '1..20: p95 = 19', 'check' => '(function(){ var a=[]; for(var i=1;i<=20;i++)a.push(i); return computeStats(a).p95===19; })()'],
                ['text' => 'odd count: [5,1,9,3,7] median 5, p95 9', 'check' => '(function(){ var s=computeStats([5,1,9,3,7]); return s.median===5 && s.p95===9; })()'],
                ['text' => 'numeric sort survives big values (50 before 9 would be a bug)', 'check' => 'computeStats([50,9,100]).median === 50'],
            ],
        ],
    ];

    return $all;
}

function cf_get_refactor(string $slug): ?array {
    foreach (cf_refactors() as $c) if ($c['slug'] === $slug) return $c;
    return null;
}
