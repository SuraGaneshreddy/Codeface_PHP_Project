<?php
declare(strict_types=1);

/**
 * Pro Labs — multi-file, real-world engineering environments (V4).
 *
 * Two kinds:
 *   debug — a small legacy "codebase" with planted bugs; checks FAIL until fixed.
 *   api   — integrate against a provided (readonly) mock API; stub must be built.
 *
 * Each lab: slug, title, kind, difficulty, minutes, summary, brief (HTML),
 *   files[]  => {name, readonly, content}   (execution order = array order)
 *   tasks[]  => {text, check}               (check = JS expression run after files)
 */

function cf_labs(): array {
    static $labs = null;
    if ($labs !== null) return $labs;

    $labs = [

        // ---------------------------------------------------------- 1
        [
            'slug' => 'legacy-cart-bug',
            'title' => 'Legacy Cart Bug Hunt',
            'kind' => 'debug', 'difficulty' => 'medium', 'minutes' => 25,
            'summary' => 'A 3-file cart service from 2019 has a discount bug. Find it, fix it, keep the public API stable.',
            'brief' => '<p>You inherited a pricing service. The spec says: <strong>subtotal → flat ₹100 discount if subtotal ≥ ₹1000 → then 18% tax</strong>, prices arrive as strings, rupee output rounded to 2 decimals. Support reports three mismatches. The bugs are in the legacy code — refactor lightly, don’t rewrite <code>api.js</code>.</p>',
            'files' => [
                ['name' => 'money.js', 'readonly' => false, 'content' => <<<'CF_E'
// money.js — legacy money helpers (2019). Touch with care.
function parseMoney(str) {
  // parse a rupee string like "499.00" into a number
  return parseInt(str, 10);           // NOTE: written before paise existed
}

function formatMoney(v) {
  return v.toFixed(2);
}
CF_E],
                ['name' => 'cart.js', 'readonly' => false, 'content' => <<<'CF_E'
// cart.js — cart totals. Depends on money.js (loaded first).
// SPEC: subtotal = Σ price*qty
//       if subtotal >= 1000  → flat ₹100 discount (before tax)
//       total = round2((subtotal - discount) * 1.18)
function cartTotal(items) {
  var sub = 0;
  for (var i = 0; i < items.length; i++) {
    sub += parseMoney(items[i].price) * items[i].qty;
  }
  var taxed = sub * 1.18;
  if (sub > 1000) {          // hmm, support says "1000 onwards"…
    taxed -= 100;            // discount taken AFTER tax — is that right?
  }
  return formatMoney(taxed);
}
CF_E],
                ['name' => 'api.js', 'readonly' => true, 'content' => <<<'CF_E'
// api.js — READONLY façade. External partners call orderQuote().
// Do not change its signature; fixing the codebase must not break it.
function orderQuote(items) {
  return { total: cartTotal(items), currency: 'INR' };
}
CF_E],
            ],
            'tasks' => [
                ['text' => 'Fractional price parsed correctly: ₹899.50 × 1 → "1061.41"', 'check' => 'orderQuote([{price:\'899.50\',qty:1}]).total === "1061.41"'],
                ['text' => 'Boundary: subtotal exactly ₹1000 gets the discount → "1062.00"', 'check' => 'orderQuote([{price:\'1000.00\',qty:1}]).total === "1062.00"'],
                ['text' => 'Discount before tax: ₹500 × 3 → (1500−100)×1.18 → "1652.00"', 'check' => 'orderQuote([{price:\'500.00\',qty:3}]).total === "1652.00"'],
                ['text' => 'Mixed basket: 4×250.25 + 2×0.50 → subtotal 1002 → "1064.36"', 'check' => 'orderQuote([{price:\'250.25\',qty:4},{price:\'0.50\',qty:2}]).total === "1064.36"'],
            ],
        ],

        // ---------------------------------------------------------- 2
        [
            'slug' => 'timezone-double-conversion',
            'title' => 'Timezone Double-Conversion',
            'kind' => 'debug', 'difficulty' => 'medium', 'minutes' => 20,
            'summary' => 'Bookings land two hours late… or is it early? Untangle a UTC conversion that runs twice with the wrong sign.',
            'brief' => '<p>Local times must be stored as UTC <em>once</em>: <code>UTC = local − offset</code>. IST is <code>+330</code> minutes. Somewhere below, the offset is applied with the wrong sign (and one caller converts an already-UTC time again). Slots are <code>"HH:MM"</code> strings.</p>',
            'files' => [
                ['name' => 'time.js', 'readonly' => false, 'content' => <<<'CF_E'
// time.js — legacy time helpers.
// UTC = local - offsetMin   (IST offsetMin = +330)
function localToUtcMinutes(hhmm, offsetMin) {
  var p = hhmm.split(':');
  var local = (+p[0]) * 60 + (+p[1]);
  var utc = local + offsetMin;          // sign correct?
  return ((utc % 1440) + 1440) % 1440;  // wrap around midnight
}
function minutesToHHMM(mins) {
  var h = Math.floor(mins / 60), m = mins % 60;
  return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
}
CF_E],
                ['name' => 'booking.js', 'readonly' => false, 'content' => <<<'CF_E'
// booking.js — stores slot times in UTC.
function slotToUtc(hhmmLocal, offsetMin) {
  return minutesToHHMM(localToUtcMinutes(hhmmLocal, offsetMin));
}
// normalizeStored is called on values ALREADY in UTC — it must be a no-op.
function normalizeStored(hhmmUtc, offsetMin) {
  // someone "fixed" a complaint by converting again here…
  return minutesToHHMM(localToUtcMinutes(hhmmUtc, offsetMin));
}
CF_E],
                ['name' => 'api.js', 'readonly' => true, 'content' => <<<'CF_E'
// api.js — READONLY. Mobile app calls these.
function apiBookLocal(hhmmLocal, offsetMin) { return slotToUtc(hhmmLocal, offsetMin); }
function apiReadStored(hhmmUtc, offsetMin)  { return normalizeStored(hhmmUtc, offsetMin); }
CF_E],
            ],
            'tasks' => [
                ['text' => '10:30 IST (offset 330) stores as "05:00"', 'check' => 'apiBookLocal("10:30", 330) === "05:00"'],
                ['text' => 'Midnight rollover: 00:30 IST stores as "19:00" (previous day)', 'check' => 'apiBookLocal("00:30", 330) === "19:00"'],
                ['text' => 'Reading a stored UTC time must NOT convert again ("05:00" stays "05:00")', 'check' => 'apiReadStored("05:00", 330) === "05:00"'],
                ['text' => 'Negative offsets work too: 13:45 at −300 (Santiago) → "18:45"', 'check' => 'apiBookLocal("13:45", -300) === "18:45"'],
            ],
        ],

        // ---------------------------------------------------------- 3
        [
            'slug' => 'api-integration-weather',
            'title' => 'API Integration: Weather Aggregator',
            'kind' => 'api', 'difficulty' => 'easy', 'minutes' => 20,
            'summary' => 'Wire up a weather report against a provided mock API. Mixed units, one stub file to fill — no rewrites of the client.',
            'brief' => '<p><code>client.js</code> is a <strong>readonly</strong> mock API. <code>normalize.js</code> has a unit helper. Your job is <code>report.js</code>: implement <code>buildCityReport()</code> returning <code>{cities:[{name,avgC}], hottest}</code>, where <code>avgC</code> is the mean of that city’s readings converted to °C, rounded to 1 decimal (use <code>Math.round(v*10)/10</code>), and <code>hottest</code> is the name with the highest avgC.</p>',
            'files' => [
                ['name' => 'client.js', 'readonly' => true, 'content' => <<<'CF_E'
// client.js — READONLY mock API. Pretend this is a network client.
var WeatherAPI = (function () {
  var DB = {
    Pune:  { units: 'C', readings: [30, 32, 34] },
    Oslo:  { units: 'F', readings: [41, 46.4, 50] },
    Doha:  { units: 'C', readings: [41, 43, 42] }
  };
  return {
    cities: function () { return Object.keys(DB); },
    getCity: function (name) { return DB[name] || null; }
  };
})();
CF_E],
                ['name' => 'normalize.js', 'readonly' => false, 'content' => <<<'CF_E'
// normalize.js — unit helpers.
function toC(v, units) { return units === 'F' ? (v - 32) * 5 / 9 : v; }
CF_E],
                ['name' => 'report.js', 'readonly' => false, 'content' => <<<'CF_E'
// report.js — YOUR FILE. Implement buildCityReport() using WeatherAPI + toC.
function buildCityReport() {
  // TODO: return { cities: [{name, avgC}...], hottest: '<name>' }
  return null;
}
CF_E],
            ],
            'tasks' => [
                ['text' => 'Report covers all 3 cities', 'check' => 'buildCityReport().cities.length === 3'],
                ['text' => 'Oslo readings converted from °F: avgC ≈ 7.7', 'check' => 'Math.abs(buildCityReport().cities.filter(function(c){return c.name==="Oslo";})[0].avgC - 7.7) < 0.05'],
                ['text' => 'Hottest city identified: "Doha" (42.0 °C)', 'check' => 'buildCityReport().hottest === "Doha"'],
                ['text' => 'Pune mean is 32.0 °C', 'check' => 'buildCityReport().cities.filter(function(c){return c.name==="Pune";})[0].avgC === 32'],
            ],
        ],

        // ---------------------------------------------------------- 4
        [
            'slug' => 'api-integration-pagination',
            'title' => 'API Integration: Paginated Store',
            'kind' => 'api', 'difficulty' => 'easy', 'minutes' => 15,
            'summary' => 'Consume a paginated inventory API end-to-end: fetch every page safely, then build a lookup index.',
            'brief' => '<p><code>StoreAPI.list(page)</code> (readonly) returns <code>{items:[…], pages:N}</code> — pages are <strong>1-based</strong>, and asking beyond <code>pages</code> throws. Implement <code>fetchAll()</code> (concatenate all pages, in order) and <code>indexBySku()</code> (map <code>sku → item</code>) in <code>inventory.js</code>.</p>',
            'files' => [
                ['name' => 'store-api.js', 'readonly' => true, 'content' => <<<'CF_E'
// store-api.js — READONLY paginated mock (1-based pages, like the real vendor).
var StoreAPI = (function () {
  var DATA = [];
  var NAMES = ['Adapter','Cable','Stand','Hub','Sleeve','Charger','Dock','Mouse','Mat','Strap'];
  for (var i = 1; i <= 10; i++) DATA.push({ sku: 'SKU-' + i, name: NAMES[i-1], price: 100 + i * 40 });
  var PER = 3;
  return {
    list: function (page) {
      var pages = Math.ceil(DATA.length / PER);
      if (page < 1 || page > pages) throw new Error('page out of range: ' + page);
      return { items: DATA.slice((page - 1) * PER, page * PER), pages: pages };
    }
  };
})();
CF_E],
                ['name' => 'inventory.js', 'readonly' => false, 'content' => <<<'CF_E'
// inventory.js — YOUR FILE.
function fetchAll() {
  // TODO: fetch page 1..pages and concatenate items in order
  return [];
}
function indexBySku() {
  // TODO: return { 'SKU-1': {…}, … } built from fetchAll()
  return {};
}
CF_E],
            ],
            'tasks' => [
                ['text' => 'fetchAll() retrieves all 10 items across 4 pages', 'check' => 'fetchAll().length === 10'],
                ['text' => 'Order preserved: first item is SKU-1, last is SKU-10', 'check' => 'fetchAll()[0].sku === "SKU-1" && fetchAll()[9].sku === "SKU-10"'],
                ['text' => 'indexBySku() lookup works: SKU-7 price is 380', 'check' => 'indexBySku()["SKU-7"].price === 380'],
                ['text' => 'Calling fetchAll() twice is safe (no state leaks)', 'check' => 'fetchAll().length === 10 && fetchAll().length === 10'],
            ],
        ],

        // ---------------------------------------------------------- 5
        [
            'slug' => 'legacy-inventory-nan',
            'title' => 'Legacy Inventory: The NaN Epidemic',
            'kind' => 'debug', 'difficulty' => 'hard', 'minutes' => 30,
            'summary' => 'Reorder alerts silently stopped firing. Trace a NaN that starts at CSV parsing and poisons the whole pipeline.',
            'brief' => '<p>The warehouse CSV has <strong>quoted numbers with commas</strong> (<code>"1,200"</code>). The parser below splits naively, columns shift, quantities become <code>NaN</code>, and every <code>&lt;</code> comparison against NaN is false — so nothing ever alerts. Fix parsing (do not change the alert thresholds in <code>alerts.js</code>) and treat <code>"</code>-wrapped fields as one value with the comma removed.</p>',
            'files' => [
                ['name' => 'csv.js', 'readonly' => false, 'content' => <<<'CF_E'
// csv.js — legacy "parser". Known-bad on quoted fields.
function parseCSV(text) {
  return text.trim().split('\n').map(function (line) {
    return line.split(',');           // breaks on "1,200"
  });
}
function toQty(cell) {
  return Number(cell);                // Number('"1') → NaN
}
CF_E],
                ['name' => 'stock.js', 'readonly' => false, 'content' => <<<'CF_E'
// stock.js — builds stock records from CSV rows.
// Columns: sku,name,qty,reorder_point
function loadStock(csvText) {
  return parseCSV(csvText).map(function (r) {
    return { sku: r[0], name: r[1], qty: toQty(r[2]), rop: toQty(r[3]) };
  });
}
var WAREHOUSE_CSV =
  'W-1,Widget,"1,200",300\n' +
  'W-2,Gadget,45,50\n' +
  'W-3,Sprocket,"2,050",400\n' +
  'W-4,Doohickey,10,25';
CF_E],
                ['name' => 'alerts.js', 'readonly' => true, 'content' => <<<'CF_E'
// alerts.js — READONLY business rule: alert when qty is below reorder point.
function lowStockAlerts(csvText) {
  return loadStock(csvText)
    .filter(function (s) { return s.qty < s.rop; })
    .map(function (s) { return s.sku; });
}
CF_E],
            ],
            'tasks' => [
                ['text' => 'Quoted "1,200" parses as quantity 1200', 'check' => 'loadStock(WAREHOUSE_CSV)[0].qty === 1200'],
                ['text' => 'Reorder points parse too: Widget rop = 300', 'check' => 'loadStock(WAREHOUSE_CSV)[0].rop === 300'],
                ['text' => 'Alerts fire for the two understocked SKUs: W-2 then W-4', 'check' => 'JSON.stringify(lowStockAlerts(WAREHOUSE_CSV)) === JSON.stringify(["W-2","W-4"])'],
                ['text' => 'well-stocked "2,050" does not alert', 'check' => 'lowStockAlerts(WAREHOUSE_CSV).indexOf("W-3") === -1'],
            ],
        ],

        // ---------------------------------------------------------- 6
        [
            'slug' => 'legacy-auth-token',
            'title' => 'Legacy Auth: Sessions That Never Expire',
            'kind' => 'debug', 'difficulty' => 'medium', 'minutes' => 20,
            'summary' => 'A session guard that lets expired tokens through. Two unit-confusion bugs between what the issuer stores and what the guard compares.',
            'brief' => '<p>Tokens carry <code>exp</code> in <strong>seconds</strong> (Unix epoch). <code>Date.now()</code> is <strong>milliseconds</strong>. Also: an empty/expired session must be denied, a valid one allowed. <code>guard.js</code> decides with <code>secondsLeft(session) &gt; 0</code>.</p>',
            'files' => [
                ['name' => 'token.js', 'readonly' => true, 'content' => <<<'CF_E'
// token.js — READONLY issuer-side helpers (contract: exp is SECONDS).
function makeSession(uid, expSeconds) { return { uid: uid, exp: expSeconds }; }
var FUTURE = 2000000000;   // 2033-05-18T03:33:20Z
var PAST   = 1577836800;   // 2020-01-01T00:00:00Z
CF_E],
                ['name' => 'session.js', 'readonly' => false, 'content' => <<<'CF_E'
// session.js — expiry math lives here.
// exp is in SECONDS; Date.now() is MILLISECONDS.
function secondsLeft(session) {
  if (!session) return 0;
  return session.exp - Date.now();      // units! seconds minus ms?
}

function isExpired(session) {
  return secondsLeft(session) <= 0;
}
CF_E],
                ['name' => 'guard.js', 'readonly' => true, 'content' => <<<'CF_E'
// guard.js — READONLY gate used by every route.
function requireAuth(session) {
  return isExpired(session) ? 'denied' : 'allowed';
}
CF_E],
            ],
            'tasks' => [
                ['text' => 'A 2033 token is not expired', 'check' => 'isExpired(makeSession(7, FUTURE)) === false'],
                ['text' => 'A 2020 token is expired', 'check' => 'isExpired(makeSession(7, PAST)) === true'],
                ['text' => 'Guard allows the valid session', 'check' => 'requireAuth(makeSession(7, FUTURE)) === "allowed"'],
                ['text' => 'Missing session is denied (not a crash, not allowed)', 'check' => 'requireAuth(null) === "denied"'],
            ],
        ],
    ];

    return $labs;
}

function cf_get_lab(string $slug): ?array {
    foreach (cf_labs() as $l) if ($l['slug'] === $slug) return $l;
    return null;
}
