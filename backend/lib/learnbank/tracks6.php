<?php
// Learn tracks 6: PRO lessons (positions 13-16) for all 15 tracks.
// Pro = production techniques + a capstone mini-project that uses the whole track.
return [

'javascript' => ['lessons' => [
L('async-patterns', 'Async patterns: promises, Promise.all & timeouts', 8,
  <<<'CF_C'
<p>Day-job async is three moves: <code>Promise.all</code> for parallel vendors, <code>Promise.race</code> for timeouts, and <code>async/await</code> for readable sequencing. Sequential awaits in a loop are the #1 accidental slowdown in real codebases — parallelize independent calls.</p>
CF_C,
  <<<'CF_E'
const vendor = (name, price, ms) => new Promise(res => setTimeout(() => res({ name, price }), ms));

async function bestQuote() {
  const quotes = await Promise.all([               // all three fire at once
    vendor('A', 499, 90), vendor('B', 459, 60), vendor('C', 519, 40),
  ]);
  const best = quotes.reduce((m, q) => (q.price < m.price ? q : m));
  const waits = [90, 60, 40];
  console.log(`best: ${best.name} @ ₹${best.price} — parallel ≈ slowest (${Math.max(...waits)}ms), not the sum (${waits.reduce((a, b) => a + b, 0)}ms)`);
}
async function withTimeout(p, ms) {
  return Promise.race([p, new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), ms))]);
}

bestQuote();
withTimeout(vendor('SLOW', 100, 500), 120).catch(e => console.log('aborted:', e.message));
CF_E,
  <<<'CF_O'
best: B @ ₹459 — parallel ≈ slowest (90ms), not the sum (190ms)
aborted: timeout
CF_O,
  '',
  'retry-backoff-schedule'),
L('design-mini-pubsub', 'Mini project: an event bus (pub/sub)', 8,
  <<<'CF_C'
<p>An event bus decouples modules: checkout says <code>emit('order:paid')</code>, email/inventory/analytics subscribe without checkout knowing they exist. 20 lines that run inside every UI framework and message queue you will ever use.</p>
CF_C,
  <<<'CF_E'
function createBus() {
  const subs = {};
  return {
    on(evt, fn) { (subs[evt] ||= []).push(fn); return () => this.off(evt, fn); },
    off(evt, fn) { subs[evt] = (subs[evt] || []).filter(f => f !== fn); },
    emit(evt, data) { (subs[evt] || []).forEach(f => f(data)); },
  };
}

const bus = createBus();
bus.on('order:paid', o => console.log('invoice for', o.id));
const unsubAnalytics = bus.on('order:paid', o => console.log('metric +1 for', o.id));
unsubAnalytics();                                  // analytics unsubscribes
bus.emit('order:paid', { id: 'ORD-1042' });        // only invoice hears it
CF_E,
  <<<'CF_O'
invoice for ORD-1042
CF_O,
  <<<'CF_T'
// Add emitOnce support: once(evt, fn) — auto-unsubscribes after first fire.
CF_T,
  'lru-sequence'),
L('defensive-json', 'Defensive JSON: parse, validate, survive', 7,
  <<<'CF_C'
<p>API payloads lie. Wrap <code>JSON.parse</code> in try/catch, validate the three fields you need before using them, and default the rest. Ten lines of validation at the boundary replaces a week of "undefined is not an object" hunting.</p>
CF_C,
  <<<'CF_E'
function readCart(json) {
  let raw;
  try { raw = JSON.parse(json); }
  catch { return { items: [], error: 'corrupt payload' }; }
  const items = Array.isArray(raw?.items) ? raw.items : [];
  return {
    items: items.filter(i => typeof i.price === 'number' && i.price >= 0 && typeof i.sku === 'string'),
  };
}

const good = readCart('{"items":[{"sku":"W-1","price":499},{"bad":true},{"sku":"W-2","price":199}]}');
console.log(good.items);       // the broken middle row is dropped
const bad = readCart('{oops');
console.log(bad);              // { items: [], error: 'corrupt payload' }
CF_E,
  <<<'CF_O'
[ { sku: 'W-1', price: 499 }, { sku: 'W-2', price: 199 } ]
{ items: [], error: 'corrupt payload' }
CF_O,
  '',
  'jwt-payload-decode'),
L('capstone-invoice-js', 'Capstone: invoice pipeline (end to end)', 15,
  <<<'CF_C'
<p>Assemble the track: items in → parse & discount → tax table → formatted bilingual money out — closures for rates, map/reduce for math, classes for the invoice, regex for the GSTIN. This is 80% of billing code in production.</p>
CF_C,
  <<<'CF_E'
const TAX = { food: 0.05, electronics: 0.18 };
const money = (v) => '₹' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

class Invoice {
  static fromRows(rows) {
    return new Invoice(rows.map(r => ({
      ...r,
      net: r.price * r.qty,
      tax: r.price * r.qty * (TAX[r.cat] ?? 0),
    })));
  }
  constructor(lines) { this.lines = lines; }
  totals() {
    const t = this.lines.reduce((a, l) => ({ net: a.net + l.net, tax: a.tax + l.tax }),
                                { net: 0, tax: 0 });
    return { ...t, grand: t.net + t.tax };
  }
  render() {
    return [...this.lines.map(l => `${l.name} x${l.qty} = ${money(l.net + l.tax)}`),
            `TOTAL: ${money(this.totals().grand)}`].join('\n');
  }
}

const inv = Invoice.fromRows([
  { name: 'Apple', cat: 'food', price: 40, qty: 2 },
  { name: 'Widget', cat: 'electronics', price: 100, qty: 5 },
]);
console.log(inv.render());
CF_E,
  <<<'CF_O'
Apple x2 = ₹84.00
Widget x5 = ₹590.00
TOTAL: ₹674.00
CF_O,
  '',
  'sku-subtotals'),
],
],

'typescript' => ['lessons' => [
L('typed-store', 'Mini project: a typed store/module pattern', 8,
  <<<'CF_C'
<p>The store pattern with types: a tiny state container with subscribe/dispatch, where the action union is <strong>discriminated</strong> — the compiler writes your reducers' exhaustiveness check. This is Redux's skeleton in 30 safe lines.</p>
CF_C,
  <<<'CF_E'
type CartItem = { sku: string; price: number; qty: number };
type Action =
  | { kind: 'add'; item: CartItem }
  | { kind: 'remove'; sku: string }
  | { kind: 'clear' };

function reducer(state: CartItem[], a: Action): CartItem[] {
  switch (a.kind) {
    case 'add':    return [...state, a.item];
    case 'remove': return state.filter(i => i.sku !== a.sku);
    case 'clear':  return [];
    default:       const _x: never = a; return _x;
  }
}

let state: CartItem[] = [];
state = reducer(state, { kind: 'add', item: { sku: 'W-1', price: 499, qty: 1 } });
state = reducer(state, { kind: 'add', item: { sku: 'W-2', price: 199, qty: 2 } });
state = reducer(state, { kind: 'remove', sku: 'W-1' });
console.log(state.map(i => i.sku + 'x' + i.qty).join(', '));
CF_E,
  <<<'CF_O'
W-2x2
CF_O,
  '',
  'shopping-cart-total'),
L('typed-api-client', 'Mini project: a typed API client', 8,
  <<<'CF_C'
<p>Type the boundary, relax inside: define the wire shapes as interfaces, parse defensively into them once, then enjoy autocomplete everywhere else. A 3-endpoint client shows the pattern.</p>
CF_C,
  <<<'CF_E'
interface QuoteDto { sku: string; price: number; }
interface Api { quote(sku: string): Promise<QuoteDto>; }

// fake fetch: same shape as fetch(), deterministic
const client: Api = {
  async quote(sku) {
    const wire: unknown = JSON.parse(`{"sku":"${sku}","price":499}`);
    const q = wire as QuoteDto;                        // trust boundary
    if (typeof q.price !== 'number') throw new Error('bad quote payload');
    return q;                                          // typed from here on
  },
};

async function main() {
  const q = await client.quote('W-1');
  console.log(`${q.sku} costs ₹${q.price.toFixed(2)}`);  // autocomplete knows .price
}
main();
CF_E,
  <<<'CF_O'
W-1 costs ₹499.00
CF_O,
  '',
  'jwt-payload-decode'),
L('ts-debugging', 'Debugging type errors like a pro', 7,
  <<<'CF_C'
<p>Read the error from the <em>bottom line up</em>; hover the variable, not the error. <code>satisfies</code> keeps literals narrow without widening, and a <code>const</code>-assertion turns a plain object into precise types — the two tricks that kill 80% of annoying TS friction.</p>
CF_C,
  <<<'CF_E'
const CODES = { ok: 200, notFound: 404, server: 500 } as const;
type Code = (typeof CODES)[keyof typeof CODES];     // 200 | 404 | 500

function label(c: Code): string {
  return c === 200 ? 'OK' : c === 404 ? 'MISSING' : 'ERROR';
}
console.log(label(200), label(404));

const config = { retries: 3, backoff: 'exp' } satisfies { retries: number; backoff: 'exp' | 'fixed' };
console.log(config.backoff.toUpperCase());          // literal 'exp', not plain string
// label(418) → compile error: 418 not in the Code union. caught, not shipped.
CF_E,
  <<<'CF_O'
OK MISSING
EXP
CF_O,
  '',
  'http-status-label'),
L('capstone-ts-report', 'Capstone: type-safe sales report', 15,
  <<<'CF_C'
<p> Capstone: ingest raw wire data → validate into domain types → group/sum/print. Discriminated unions for row kinds, generics for groupBy, utility types for the public view. Types as tests you don't have to write.</p>
CF_C,
  <<<'CF_E'
type SaleRow = { kind: 'sale'; region: string; product: string; units: number };
type SkipRow = { kind: 'skip'; reason: string };
type WireRow = SaleRow | SkipRow;

function ingest(raw: WireRow[]): SaleRow[] {
  return raw.filter((r): r is SaleRow => r.kind === 'sale' && r.units > 0);
}
function groupSum(rows: SaleRow[]): Record<string, number> {
  return rows.reduce((m, r) => ({ ...m, [r.region]: (m[r.region] ?? 0) + r.units }), {} as Record<string, number>);
}

const report = groupSum(ingest([
  { kind: 'sale', region: 'N', product: 'A', units: 2 },
  { kind: 'skip', reason: 'cancelled' },
  { kind: 'sale', region: 'N', product: 'A', units: 4 },
  { kind: 'sale', region: 'S', product: 'B', units: 3 },
]));
console.log(report);
CF_E,
  <<<'CF_O'
{ N: 6, S: 3 }
CF_O,
  '',
  'sales-summary'),
],
],

'python' => ['lessons' => [
L('testing-python', 'Testing: assert, pytest style & the AAA pattern', 7,
  <<<'CF_C'
<p> Tests are executable specs: Arrange → Act → Assert. Plain <code>assert</code> works today; pytest upgrades it with diffs, parametrization (<code>@pytest.mark.parametrize</code>), and fixtures. Test the contract, not the implementation.</p>
CF_C,
  <<<'CF_E'
def discount(subtotal: float, pct: int) -> float:
    assert 0 <= pct <= 100, 'pct out of range'
    return round(subtotal * (100 - pct) / 100, 2)

# AAA table — what pytest's parametrize automates:
cases = [
    (1500.0, 10, 1350.0),
    (1000.0, 0, 1000.0),
    (999.99, 5, 949.99),
]
for sub, pct, want in cases:
    got = discount(sub, pct)                 # Act
    assert got == want, f'{sub}@{pct}: got {got}, want {want}'
    print(f'✓ {sub} -{pct}% = {got}')

try:
    discount(100, 120)
except AssertionError as e:
    print('✓ rejects bad pct:', e)
CF_E,
  <<<'CF_O'
✓ 1500.0 -10% = 1350.0
✓ 1000.0 -0% = 1000.0
✓ 999.99 -5% = 949.99
✓ rejects bad pct: pct out of range
CF_O,
  <<<'CF_T'
# Write 3 AAA cases for a slugify(title) you wrote (or borrowed): spaces→-, lowercase, strip symbols.
CF_T,
  'slugify-title'),
L('argparse-cli', 'argparse: scripts that behave like tools', 7,
  <<<'CF_C'
<p>A professional script takes <code>--flags</code>, prints <code>--help</code>, and returns an exit code (0 = ok, 2 = bad usage). <code>argparse</code> gives all three for free; <code>if __name__ == '__main__':</code> keeps the file importable.</p>
CF_C,
  <<<'CF_E'
import argparse

def build_parser():
    p = argparse.ArgumentParser(prog='reprice', description='adjust prices by region')
    p.add_argument('--rate', type=float, required=True, help='multiplier like 1.18')
    p.add_argument('--round', dest='digits', type=int, default=2)
    return p

def apply_rate(prices, rate, digits):
    return [round(p * rate, digits) for p in prices]

if __name__ == '__main__':
    args = build_parser().parse_args(['--rate', '1.18'])  # simulated CLI args
    print(apply_rate([100, 49.9], args.rate, args.digits))
    # real run: python reprice.py --rate 1.18
CF_E,
  <<<'CF_O'
[118.0, 58.88]
CF_O,
  <<<'CF_T'
# Add an optional --region flag with choices=['IN','US'] and print it.
CF_T,
  '',
  ''),
L('packaging-structure', 'Project structure: packages, __init__ & imports', 6,
  <<<'CF_C'
<p>Code scales when files organize by <em>domain</em>: <code>shop/money.py</code>, <code>shop/cart.py</code> with <code>shop/__init__.py</code> marks the package; imports are absolute (<code>from shop.money import Money</code>). Flat brain, deep folders — never the reverse.</p>
CF_C,
  <<<'CF_E'
# shop/
#   __init__.py     (empty marker)
#   money.py        class Money ...
#   cart.py         from shop.money import Money
# main.py           from shop.cart import Cart

# Same idea, simulated in one cell with types.ModuleType:
import types
money = types.ModuleType('shop.money')
exec('def fmt(v): return f"INR {v:.2f}"', money.__dict__)
print(money.fmt(499))                       # INR 499.00

# rule of thumb: ≤ 300 lines per module, one domain per folder,
# and __init__.py re-exports the public surface.
CF_E,
  <<<'CF_O'
INR 499.00
CF_O,
  '',
  ''),
L('capstone-expense-py', 'Capstone: expense report CLI', 15,
  <<<'CF_C'
<p>Everything at once: dataclass records, a generator pipeline over CSV rows, error handling with context, regex validation, and argparse flags. This is the shape of every data script you will write at work.</p>
CF_C,
  <<<'CF_E'
import csv, io
from dataclasses import dataclass

RAW = io.StringIO('date,category,amount\n2026-08-01,food,499.50\n2026-08-02,travel,1200\n2026-08-03,food,88.25\n')

@dataclass
class Expense:
    date: str
    category: str
    amount: float

def rows(source):
    for r in csv.DictReader(source):
        try:
            yield Expense(r['date'], r['category'].strip().lower(), float(r['amount']))
        except (ValueError, KeyError) as e:
            print('SKIP bad row:', r, e)

def report(source):
    totals = {}
    for exp in rows(source):
        totals[exp.category] = round(totals.get(exp.category, 0) + exp.amount, 2)
    for cat in sorted(totals):
        print(f'{cat:10} ₹{totals[cat]:,.2f}')
    print(f'{"TOTAL":10} ₹{sum(totals.values()):,.2f}')

report(RAW)
CF_E,
  <<<'CF_O'
food       ₹587.75
travel     ₹1,200.00
TOTAL      ₹1,787.75
CF_O,
  '',
  'csv-sum-column'),
],
],

'java' => ['lessons' => [
L('concurrency-java', 'Concurrency basics: executor & futures', 8,
  <<<'CF_C'
<p>Don't hand-crank <code>new Thread()</code>: give work to an <code>ExecutorService</code>, get <code>Future</code>s back, shut the pool down. <code>invokeAll</code> parallelizes N independent vendor calls and waits for all — the production shape of fan-out.</p>
CF_C,
  <<<'CF_E'
import java.util.*;
import java.util.concurrent.*;

public class Main {
  public static void main(String[] a) throws Exception {
    var pool = Executors.newFixedThreadPool(2);
    var quotes = List.of(
      pool.submit(() -> { Thread.sleep(90); return "vendorA=499"; }),
      pool.submit(() -> { Thread.sleep(60); return "vendorB=459"; }));
    long t0 = System.currentTimeMillis();
    for (var f : quotes) System.out.println(f.get());   // join all
    System.out.println("elapsed≈" + (System.currentTimeMillis() - t0) / 40 * 40 + "ms");
    pool.shutdown();
  }
}
CF_E,
  <<<'CF_O'
vendorA=499
vendorB=459
elapsed≈80ms
CF_O,
  '',
  ''),
L('generics-repo-java', 'Mini project: a generic in-memory repository', 8,
  <<<'CF_C'
<p>The repository pattern: one interface <code>Repository&lt;T, ID&gt;</code> hides storage behind save/find/delete. Generic + tested once, reused for User, Order, Product — and swapping to a DB later touches exactly one file.</p>
CF_C,
  <<<'CF_E'
import java.util.*;

public class Main {
  interface Repository<T, ID> {
    T save(T entity);
    Optional<T> findById(ID id);
    List<T> findAll();
    boolean delete(ID id);
  }
  record Product(int id, String sku, double price) {}

  static class MemProductRepo implements Repository<Product, Integer> {
    private final Map<Integer, Product> data = new LinkedHashMap<>();
    public Product save(Product p) { data.put(p.id(), p); return p; }
    public Optional<Product> findById(Integer id) { return Optional.ofNullable(data.get(id)); }
    public List<Product> findAll() { return List.copyOf(data.values()); }
    public boolean delete(Integer id) { return data.remove(id) != null; }
  }

  public static void main(String[] a) {
    Repository<Product, Integer> repo = new MemProductRepo();
    repo.save(new Product(1, "W-1", 499));
    repo.save(new Product(2, "W-2", 199));
    repo.delete(2);
    System.out.println(repo.findById(1).orElseThrow().sku());
    System.out.println("count=" + repo.findAll().size());
  }
}
CF_E,
  <<<'CF_O'
W-1
count=1
CF_O,
  '',
  ''),
L('debugging-java', 'Reading stack traces like a detective', 7,
  <<<'CF_C'
<p>Stack traces are read <strong>top frame first</strong>: what's your deepest line, what value couldn't be null, what index was out of range. Add context when rethrowing (<code>throw new X(query, e)</code>) so the trace answers, not accuses.</p>
CF_C,
  <<<'CF_E'
public class Main {
  static int parseQty(String cell, int row) {
    try { return Integer.parseInt(cell.trim()); }
    catch (NumberFormatException e) {
      throw new IllegalArgumentException("row " + row + ": bad qty '" + cell + "'", e);
    }
  }
  public static void main(String[] a) {
    String[] lines = {"W-1,5", "W-2,three", "W-3,2"};
    int row = 0, total = 0;
    for (String ln : lines) {
      row++;
      try { total += parseQty(ln.split(",")[1], row); }
      catch (IllegalArgumentException e) { System.out.println(e.getMessage()); }
    }
    System.out.println("total=" + total);
  }
}
CF_E,
  <<<'CF_O'
row 2: bad qty 'three'
total=7
CF_O,
  '',
  ''),
L('capstone-billing-java', 'Capstone: billing service', 15,
  <<<'CF_C'
<p>Capstone: records for models, streams for math, custom exceptions for stock rules, grouping for the receipt. A tiny layered service: domain → repository-ish map → service → print. The enterprise shape, minus the XML.</p>
CF_C,
  <<<'CF_E'
import java.util.*;
import java.util.stream.*;

public class Main {
  record Item(String sku, String cat, double price, int qty) {}
  static final Map<String, Double> TAX = Map.of("food", 0.05, "electronics", 0.18);

  static class Billing {
    static double lineNet(Item i) { return i.price() * i.qty() * (1 + TAX.getOrDefault(i.cat(), 0.0)); }
    static String receipt(List<Item> items) {
      double grand = items.stream().mapToDouble(Billing::lineNet).sum();
      String lines = items.stream()
          .map(i -> i.sku() + " x" + i.qty() + " = " + String.format("%.2f", lineNet(i)))
          .collect(Collectors.joining(" | "));
      return lines + " || TOTAL: " + String.format("%.2f", grand);
    }
  }

  public static void main(String[] a) {
    System.out.println(Billing.receipt(List.of(
        new Item("Apple", "food", 40, 2), new Item("Widget", "electronics", 100, 5))));
  }
}
CF_E,
  <<<'CF_O'
Apple x2 = 84.00 | Widget x5 = 590.00 || TOTAL: 674.00
CF_O,
  '',
  'sku-subtotals'),
],
],

'c' => ['lessons' => [
L('string-lib-c', 'Mini project: a mini string library', 8,
  <<<'CF_C'
<p>Rebuilding three libc classics teaches pointer discipline like nothing else: walk once, return early, never write past <code>dst</code> capacity. Then design a usage example that would catch your own off-by-one.</p>
CF_C,
  <<<'CF_E'
#include <stdio.h>
#include <stddef.h>

size_t cf_len(const char *s) { const char *p = s; while (*p) p++; return (size_t)(p - s); }

void cf_copy(char *dst, const char *src, size_t cap) {   /* strncpy that ALWAYS terminates */
    size_t i = 0;
    if (cap == 0) return;
    while (i + 1 < cap && src[i]) { dst[i] = src[i]; i++; }
    dst[i] = '\0';
}
int cf_eq(const char *a, const char *b) {
    while (*a && *a == *b) { a++; b++; }
    return *a == *b;
}

int main(void) {
    char buf[8];
    cf_copy(buf, "SKU-1042", sizeof buf);
    printf("len=%zu copy=%s eq=%d\n", cf_len("SKU-1042"), buf, cf_eq("SKU", "SKU-1042"));
    printf("%d %d\n", cf_eq("SKU", "SKU"), cf_eq("SKU", "sku"));
    return 0;
}
CF_E,
  <<<'CF_O'
len=8 copy=SKU-104 eq=0
1 0
CF_O,
  '',
  ''),
L('dbg-gdb-san', 'Debugging C: sanitizers & print forensics', 7,
  <<<'CF_C'
<p> <code>-fsanitize=address,undefined</code> turns silent memory corruption into a first-class error report with a stack. Compile with it in dev, never ship without running it once. <code>gdb ./a.out → run → bt</code> answers "where exactly" for the rest.</p>
CF_C,
  <<<'CF_E'
/* gcc -fsanitize=address,undefined -g demo.c && ./a.out           */
#include <stdio.h>
#include <stdlib.h>

int main(void) {
    int *row = malloc(3 * sizeof *row);
    row[0] = 5; row[1] = 3; row[2] = 2;
    /* row[3] = 9;  ← ASan reports: heap-buffer-overflow WRITE at +12 */
    int total = 0;
    for (int i = 0; i < 3; i++) total += row[i];
    printf("total=%d\n", total);
    free(row);
    /* free(row); ← ASan reports: double-free */
    printf("clean exit\n");
    return 0;
}
CF_E,
  <<<'CF_O'
total=10
clean exit
CF_O,
  '',
  ''),
L('header-files-c', 'Header files done right: interface vs implementation', 6,
  <<<'CF_C'
<p>A <code>.h</code> file is a promise (declarations), the <code>.c</code> keeps the secret (definitions). Include guards stop double-inclusion; only <code>extern</code> for shared variables. Splitting early means your mini projects compile in seconds, not minutes.</p>
CF_C,
  <<<'CF_E'
/* --- money.h --- */
#ifndef CF_MONEY_H
#define CF_MONEY_H
double cf_total(const double *prices, int n);   /* the promise */
#endif

/* --- money.c --- */
double cf_total(const double *prices, int n) {
    double t = 0;
    for (int i = 0; i < n; i++) t += prices[i];
    return t;
}

/* --- main.c --- */
#include <stdio.h>
/* #include "money.h" */
int main(void) {
    double cart[3] = {499.0, 199.0, 50.0};
    printf("total=%.2f\n", cf_total(cart, 3));
    return 0;
}
/* build: gcc main.c money.c -o shop */
CF_E,
  <<<'CF_O'
total=748.00
CF_O,
  '',
  ''),
L('capstone-ledger-c', 'Capstone: bank ledger in C', 15,
  <<<'CF_C'
<p>Capstone: a struct ledger file — append transactions, replay balances, reject overdrafts. Structs + malloc + binary files + error paths; the exact skills that power embedded/firmware codebases.</p>
CF_C,
  <<<'CF_E'
#include <stdio.h>
#include <string.h>

typedef struct { char acct[8]; int cents; } Tx;      /* cents: ints never lie about money */

static int apply(Tx *ledger, int n, const char *acct, int cents) {
    long bal = 0;
    for (int i = 0; i < n; i++)
        if (strcmp(ledger[i].acct, acct) == 0) bal += ledger[i].cents;
    if (bal + cents < 0) return -1;                   /* overdraft denied */
    strcpy(ledger[n].acct, acct);
    ledger[n].cents = cents;
    return n + 1;
}

int main(void) {
    Tx ledger[8]; int n = 0;
    n = apply(ledger, n, "A", 10000);   /* +₹100.00  */
    n = apply(ledger, n, "A", -4500);   /* -₹45.00   */
    int r = apply(ledger, n, "A", -9000);
    printf("overdraft attempt: %s\n", r == -1 ? "rejected" : "accepted");
    long bal = 0;
    for (int i = 0; i < n; i++) if (ledger[i].acct[0] == 'A') bal += ledger[i].cents;
    printf("A balance: ₹%ld.%02ld\n", bal / 100, bal % 100);
    return 0;
}
CF_E,
  <<<'CF_O'
overdraft attempt: rejected
A balance: ₹55.00
CF_O,
  '',
  'lru-sequence'),
],
],

'cpp' => ['lessons' => [
L('move-semantics', 'Move semantics: why C++11 changed everything', 8,
  <<<'CF_C'
<p>Copying a <code>vector</code> with 1M rows is expensive; <strong>moving</strong> steals its guts in O(1). The compiler moves for you on returns and temporaries — know the rule: after <code>std::move(x)</code>, <code>x</code> is "valid but unspecified" — don't read it.</p>
CF_C,
  <<<'CF_E'
#include <iostream>
#include <string>
#include <utility>
#include <vector>

std::vector<int> load_prices() {                 // returned by value: moved, not copied
    return {499, 199, 899};
}

int main() {
    auto prices = load_prices();                 // no copy
    std::string big = "INV-0001:widget:gadget:sprocket";
    std::string moved = std::move(big);          // steal the buffer
    std::cout << "moved=" << moved << "\n";
    std::cout << "old size now " << big.size() << " (don't rely on it)\n";

    std::vector<std::string> v;
    std::string sku = "W-1042";
    v.push_back(std::move(sku));                 // move INTO the vector
    std::cout << v[0] << "\n";
}
CF_E,
  <<<'CF_O'
moved=INV-0001:widget:gadget:sprocket
old size now 0 (don't rely on it)
W-1042
CF_O,
  '',
  ''),
L('error-handling-cpp', 'Error handling in C++: exceptions + optional', 7,
  <<<'CF_C'
<p> The modern split: exceptions for exceptional paths across layers, <code>std::optional&lt;T&gt;</code> for "may be absent" at a single spot. Catch by <code>const&</code>, throw <code>std::*_error</code> types with context, never swallow silently.</p>
CF_C,
  <<<'CF_E'
#include <iostream>
#include <optional>
#include <stdexcept>
#include <string>

std::optional<double> parse_price(const std::string& s) {
    try { double v = std::stod(s); return v >= 0 ? std::optional<double>(v) : std::nullopt; }
    catch (...) { return std::nullopt; }
}

int reserve(int stock, int qty) {
    if (qty > stock) throw std::runtime_error("insufficient stock: have " +
        std::to_string(stock) + " want " + std::to_string(qty));
    return stock - qty;
}

int main() {
    if (auto p = parse_price("499.50")) std::cout << "price=" << *p << "\n";
    else std::cout << "no price\n";
    std::cout << (parse_price("abc") ? "parsed" : "no price") << "\n";
    try { reserve(2, 9); }
    catch (const std::runtime_error& e) { std::cout << "order failed: " << e.what() << "\n"; }
}
CF_E,
  <<<'CF_O'
price=499.5
no price
order failed: insufficient stock: have 2 want 9
CF_O,
  '',
  ''),
L('build-cmake', 'Building for real: cmake & multi-file projects', 6,
  <<<'CF_C'
<p> One <code>CMakeLists.txt</code> compiles every <code>.cpp</code> with the right flags on any OS. Know the shape (project, standard, executable, libs), and add <code>-Wall -Wextra -Werror</code> — warnings are the code review you skip.</p>
CF_C,
  <<<'CF_E'
# CMakeLists.txt (commented tour — run: cmake -B build && cmake --build build)
cmake_minimum_required(VERSION 3.16)
project(shop CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

add_compile_options(-Wall -Wextra -Werror)     # treat warnings as CI failures

# one library + one app:
#   src/money.cpp  src/cart.cpp  → libshop
#   app/main.cpp                 → shop-cli
add_executable(shop-cli app/main.cpp src/money.cpp src/cart.cpp)
target_include_directories(shop-cli PRIVATE include)

# message(STATUS "files compiled: money.cpp cart.cpp main.cpp")
CF_E,
  <<<'CF_O'
(commented reference — a real CMakeLists you can paste into any C++ side project)
CF_O,
  '',
  ''),
L('capstone-orderbook-cpp', 'Capstone: order book lite', 15,
  <<<'CF_C'
<p>Capstone: structs + maps + STL algorithms into a matching-engine toy: enqueue bids/asks, match best prices, print the tape. Data-structure literacy measured in microseconds — the interview and the job.</p>
CF_C,
  <<<'CF_E'
#include <iostream>
#include <queue>
#include <string>

struct Bid { int price; int qty; };
struct Cmp {
    bool operator()(const Bid& a, const Bid& b) const { return a.price < b.price; }
};

class OrderBook {
    std::priority_queue<Bid, std::vector<Bid>, Cmp> bids;   // max-heap by price
public:
    void bid(int price, int qty) { bids.push({price, qty}); }
    void take_liquidity(int want) {
        while (want > 0 && !bids.empty()) {
            auto top = bids.top(); bids.pop();
            int take = std::min(want, top.qty);
            std::cout << "fill " << take << " @ " << top.price << "\n";
            want -= take;
        }
        if (want) std::cout << "unfilled: " << want << "\n";
    }
};

int main() {
    OrderBook ob;
    ob.bid(100, 5); ob.bid(102, 3); ob.bid(99, 10);
    ob.take_liquidity(6);        // best (highest) bids fill first
}
CF_E,
  <<<'CF_O'
fill 3 @ 102
fill 3 @ 100
CF_O,
  '',
  'best-time-stock'),
],
],

'csharp' => ['lessons' => [
L('extension-methods', 'Extension methods & fluent helpers', 7,
  <<<'CF_C'
<p>Extension methods bolt behavior onto types you don't own: <code>string.ToSlug()</code>, <code>IEnumerable.Batch()</code>. Keep them pure, discoverable, and in one <code>Extensions</code> folder — an extension nobody finds is an extension nobody uses.</p>
CF_C,
  <<<'CF_E'
using System.Linq;

public static class TextExt {
    public static string ToSlug(this string s) =>
        string.Join('-', s.Trim().ToLower().Split(' ', StringSplitOptions.RemoveEmptyEntries))
              .Replace(":", "");
    public static string Money(this decimal v) => "₹" + v.ToString("N2");
}

var title = "iPhone 15 Pro: Max Review";
Console.WriteLine(title.ToSlug());
Console.WriteLine(499.5m.Money());

var skus = new[] { "W-1", "W-2", "W-3" }.Where(s => s != "W-2").Select(s => s.ToLower());
Console.WriteLine(string.Join(",", skus));
CF_E,
  <<<'CF_O'
iphone-15-pro-max-review
₹499.50
w-1,w-3
CF_O,
  '',
  'slugify-title'),
L('exceptions-design-cs', 'Designing exception strategies', 7,
  <<<'CF_C'
<p>Fail fast at the boundary, translate at the layer seam, and include context. <code>ArgumentException</code>/<code>InvalidOperationException</code>/custom domain exceptions — each says something different to the caller. Filter with <code>when</code> instead of rethrowing.</p>
CF_C,
  <<<'CF_E'
class PaymentException : Exception {
    public PaymentException(string orderId, string why)
        : base($"order={orderId} reason={why}") {}
}

static string Charge(string orderId, decimal amount, string card) {
    if (string.IsNullOrWhiteSpace(card)) throw new PaymentException(orderId, "card missing");
    if (amount <= 0) throw new ArgumentException("amount must be positive", nameof(amount));
    return $"charged {amount:N2} on ••{card[^4..]}";
}

foreach (var (id, amt, card) in new[] { ("O-1", 499m, "4111111111114242"), ("O-2", -5m, "x"), ("O-3", 10m, "") }) {
    try { Console.WriteLine(Charge(id, amt, card)); }
    catch (PaymentException e) { Console.WriteLine("payment issue: " + e.Message); }
    catch (ArgumentException e) { Console.WriteLine("bad input: " + e.Message); }
}
CF_E,
  <<<'CF_O'
charged 499.00 on ••4242
bad input: amount must be positive (Parameter 'amount')
payment issue: order=O-3 reason=card missing
CF_O,
  '',
  ''),
L('http-json-cs', 'HttpClient & System.Text.Json', 7,
  <<<'CF_C'
<p> One static <code>HttpClient</code> (socket exhaustion is real), records matching the wire shape, <code>GetFromJsonAsync</code> for happy paths. Check <code>EnsureSuccessStatusCode()</code> — a 500 page parsed as JSON is tomorrow's mystery bug.</p>
CF_C,
  <<<'CF_E'
public record Quote(string Sku, decimal Price);

private static readonly HttpClient Http = new HttpClient();

// offline twin of a real call — same shape:
static async Task<Quote?> GetQuoteAsync(HttpMessageHandler fake, string sku) {
    var client = new HttpClient(fake) { BaseAddress = new Uri("https://vendor.test") };
    return await client.GetFromJsonAsync<Quote>($"/quote?sku={sku}");
}

// Real code in an app:
// var q = await Http.GetFromJsonAsync<Quote>($"https://api.vendor.dev/quote?sku={sku}");
// Console.WriteLine($"{q!.Sku} @ {q.Price:0.00}");
Console.WriteLine("pattern: one static HttpClient + GetFromJsonAsync<T>");
CF_E,
  <<<'CF_O'
pattern: one static HttpClient + GetFromJsonAsync<T>
CF_O,
  '',
  'jwt-payload-decode'),
L('capstone-pos-cs', 'Capstone: point-of-sale core', 15,
  <<<'CF_C'
<p>Capstone: records + LINQ + enums + exceptions into a POS: scan items, apply member discount, tax slabs, receipt. The exercise every junior C# role hands you in week one.</p>
CF_C,
  <<<'CF_E'
enum Tier { None, Silver, Gold }
record Money(decimal Amount) {
    public static Money operator +(Money a, Money b) => new(a.Amount + b.Amount);
    public override string ToString() => $"₹{Amount:N2}";
}
record Line(string Name, string Cat, Money Price, int Qty) {
    public decimal Gross => Price.Amount * Qty;
}

static decimal DiscountRate(Tier t) => t switch { Tier.Gold => 0.12m, Tier.Silver => 0.06m, _ => 0m };
static decimal TaxRate(string cat) => cat == "food" ? 0.05m : 0.18m;

var cart = new[] { new Line("Apple", "food", new(40), 2), new Line("Widget", "electronics", new(100), 5) };
var afterDisc = cart.Sum(l => l.Gross) * (1 - DiscountRate(Tier.Gold));
var tax = cart.Sum(l => l.Gross * TaxRate(l.Cat) * (1 - DiscountRate(Tier.Gold)));
Console.WriteLine($"subtotal after Gold discount: ₹{afterDisc:N2}");
Console.WriteLine($"tax: ₹{tax:N2}  grand: ₹{afterDisc + tax:N2}");
CF_E,
  <<<'CF_O'
subtotal after Gold discount: ₹510.40
tax: ₹81.04  grand: ₹591.44
CF_O,
  '',
  'shopping-cart-total'),
],
],

'go' => ['lessons' => [
L('errors-wrap-go', 'Error wrapping & sentinel errors', 7,
  <<<'CF_C'
<p>Idiomatic Go returns <code>(T, error)</code> everywhere; <code>fmt.Errorf("…: %w", err)</code> wraps with context, <code>errors.Is/As</code> unwraps. Keep sentinel errors exported (<code>var ErrNotFound</code>) so callers can branch on meaning, not message text.</p>
CF_C,
  <<<'CF_E'
package main

import ("errors"; "fmt")

var ErrOutOfStock = errors.New("out of stock")

func reserve(sku string, stock, qty int) (int, error) {
    if qty > stock { return 0, fmt.Errorf("reserve %s: %w (have %d want %d)", sku, ErrOutOfStock, stock, qty) }
    return stock - qty, nil
}

func main() {
    if left, err := reserve("W-1", 5, 3); err == nil { fmt.Println("left:", left) }
    _, err := reserve("W-1", 2, 9)
    fmt.Println("failed:", err)
    if errors.Is(err, ErrOutOfStock) { fmt.Println("→ show 'notify me' button") }
}
CF_E,
  <<<'CF_O'
left: 2
failed: reserve W-1: out of stock (have 2 want 9)
→ show 'notify me' button
CF_O,
  '',
  'restock-alerts'),
L('json-deep-go', 'encoding/json in depth: tags, custom types', 7,
  <<<'CF_C'
<p>Struct tags drive the wire format; <code>json:",omitempty"</code> drops zero values, custom <code>MarshalJSON</code> fixes ugly domain types (money as paise), and <code>DisallowUnknownFields()</code> makes configs fail loudly on typos.</p>
CF_C,
  <<<'CF_E'
package main

import ("encoding/json"; "fmt")

type Paise int64
func (p Paise) MarshalJSON() ([]byte, error) {
    return json.Marshal(float64(p) / 100.0), nil   // money out as rupees
}

type Line struct {
    Sku    string `json:"sku"`
    Qty    int    `json:"qty"`
    Price  Paise  `json:"price"`
    Coupon string `json:"coupon,omitempty"`
}

func main() {
    lines := []Line{{Sku: "W-1", Qty: 2, Price: 49900}, {Sku: "W-2", Qty: 1, Price: 19900, Coupon: "SAVE5"}}
    b, _ := json.Marshal(lines)
    fmt.Println(string(b))

    var back []Line
    _ = json.Unmarshal(b, &back)
    fmt.Println(back[0].Sku, back[0].Price)
}
CF_E,
  <<<'CF_O'
[{"sku":"W-1","qty":2,"price":499},{"sku":"W-2","qty":1,"price":199,"coupon":"SAVE5"}]
W-1 49900
CF_O,
  '',
  'jwt-payload-decode'),
L('cli-tools-go', 'Mini project: CLI tooling with flag', 7,
  <<<'CF_C'
<p>The <code>flag</code> package + stdin scanning make Go the perfect "little tool" language: one binary, zero deps, runs everywhere. Structure it main-thin: flags → run(argv) → testable core.</p>
CF_C,
  <<<'CF_E'
package main

import ("bufio"; "flag"; "fmt"; "os"; "strings")

func run(csvLines []string, col int) int {
    total := 0
    for i, ln := range csvLines {
        if i == 0 { continue }
        parts := strings.Split(ln, ",")
        var v int
        fmt.Sscanf(parts[col], "%d", &v)
        total += v
    }
    return total
}

func main() {
    col := flag.Int("col", 1, "column to sum")
    flag.Parse()
    fmt.Println("column:", *col)
    fmt.Println("sum:", run([]string{"sku,qty", "W-1,5", "W-2,3"}, *col))
    _ = bufio.NewReader(os.Stdin)  // real tool streams stdin here
}
CF_E,
  <<<'CF_O'
column: 1
sum: 8
CF_O,
  '',
  'csv-sum-column'),
L('capstone-logpipe-go', 'Capstone: concurrent log pipeline', 15,
  <<<'CF_C'
<p>Capstone: read → parse → filter errors → count by message — across a worker pool with channels and a WaitGroup, wrapped errors, and a flag-driven CLI. The full "Go at work" loop in one project.</p>
CF_C,
  <<<'CF_E'
package main

import ("fmt"; "strings"; "sync")

var lines = []string{
    "INFO boot ok", "ERROR disk full", "INFO login",
    "ERROR db timeout", "ERROR disk full", "WARN slow query",
}

func countErrors(lines []string, workers int) map[string]int {
    jobs := make(chan string)
    res := make(chan map[string]int, workers)
    var wg sync.WaitGroup
    for w := 0; w < workers; w++ {
        wg.Add(1)
        go func() {
            defer wg.Done()
            local := map[string]int{}
            for ln := range jobs {
                if strings.HasPrefix(ln, "ERROR") { local[strings.TrimPrefix(ln, "ERROR ")]++ }
            }
            res <- local
        }()
    }
    for _, ln := range lines { jobs <- ln }
    close(jobs)
    wg.Wait(); close(res)
    totals := map[string]int{}
    for part := range res { for k, v := range part { totals[k] += v } }
    return totals
}

func main() {
    counts := countErrors(lines, 2)
    for msg, n := range counts { fmt.Printf("%-11s ×%d\n", msg, n) }
}
CF_E,
  <<<'CF_O'
db timeout  ×1
disk full   ×2
CF_O,
  '',
  'log-level-filter'),
],
],

'ruby' => ['lessons' => [
L('dsl-config', 'Mini project: a tiny config DSL', 7,
  <<<'CF_C'
<p>Ruby's blocks + <code>instance_eval</code> make readable mini-languages: <code>Shop.setup { |c| c.tax = 0.18 }</code>. Rails' config files are exactly this — and yours can be too, in 20 lines.</p>
CF_C,
  <<<'CF_E'
class Config
  attr_accessor :tax_rate, :currency, :free_shipping_over
  def initialize
    @tax_rate = 0.0
    @currency = 'INR'
    @free_shipping_over = nil
  end
end

class Shop
  def self.setup
    cfg = Config.new
    yield cfg                    # the caller configures the blank object
    cfg
  end
end

CFG = Shop.setup do |c|
  c.tax_rate = 0.18
  c.free_shipping_over = 500
end

puts "tax=#{CFG.tax_rate} currency=#{CFG.currency} free over ₹#{CFG.free_shipping_over}"
CF_E,
  <<<'CF_O'
tax=0.18 currency=INR free over ₹500
CF_O,
  <<<'CF_T'
# Extend: add c.region = 'IN' and a validate! method raising if tax_rate > 0.3
CF_T,
  '',
  ''),
L('testing-ruby', 'Testing with minitest', 6,
  <<<'CF_C'
<p>Ruby ships a test framework: <code>require 'minitest/autorun'</code>, assert-first, describe/it for specs. One file per class keeps tests close to the code of honor.</p>
CF_C,
  <<<'CF_E'
require 'minitest/autorun'

def discount(subtotal, pct)
  raise ArgumentError, 'pct out of range' unless (0..100).cover?(pct)
  (subtotal * (100 - pct) / 100.0).round(2)
end

class DiscountTest < Minitest::Test
  def test_standard
    assert_equal 1350.0, discount(1500, 10)
  end
  def test_zero_is_fine
    assert_equal 1000.0, discount(1000, 0)
  end
  def test_rejects_bad_pct
    assert_raises(ArgumentError) { discount(100, 120) }
  end
end
# (running this file executes the suite — Run to see it)
CF_E,
  <<<'CF_O'
# running: 3 runs, 3 assertions, 0 failures
CF_O,
  <<<'CF_T'
# Add a test: discount(999.99, 5) == 949.99
CF_T,
  '',
  ''),
L('gems-and-bundler', 'Gems & Bundler: dependencies without drama', 6,
  <<<'CF_C'
<p>A <code>Gemfile</code> pins what, Bundler installs <em>exactly that everywhere</em> (<code>.lock</code> file is law). <code>require</code> loads installed code; semantic versioning (<code>'~&gt; 3.0'</code> = 3.x but not 4) is how you avoid surprise major upgrades.</p>
CF_C,
  <<<'CF_E'
# Gemfile          (a reference you can paste)
source "https://rubygems.org"

gem "csv"                    # in stdlib for now, gemified in modern rubies
gem "money",  "~> 6.16"      # money handling with currencies
gem "minitest", "~> 5.0", group: :test

# Terminal:
#   bundle install         → creates Gemfile.lock (EXACT versions — commit it!)
#   bundle exec ruby app.rb  → run inside the locked environment
#
# in app.rb:
#   require "money"
#   puts Money.new(49900, "INR").format   # ₹499.00
puts "Gemfile.lock = reproducibility contract between 'works on my machine' and production"
CF_E,
  <<<'CF_O'
Gemfile.lock = reproducibility contract between 'works on my machine' and production
CF_O,
  '',
  ''),
L('capstone-ledger-rb', 'Capstone: ledger app with reports', 15,
  <<<'CF_C'
<p>Capstone: classes + Comparable + Enumerable into a double-entry-ish ledger: post entries, balances per account, trial balance, formatted report. Reads like a story, runs like a bank.</p>
CF_C,
  <<<'CF_E'
Entry = Struct.new(:account, :amount, :memo)

class Ledger
  include Enumerable
  def initialize = @entries = []
  def post(account, amount, memo) = (@entries << Entry.new(account, amount, memo))
  def each(&b) = @entries.each(&b)
  def balance(account) = select { |e| e.account == account }.sum(&:amount)
  def trial = map(&:account).uniq.sort.map { |a| "#{a}: ₹#{format('%.2f', balance(a) / 100.0)}" }
end

lg = Ledger.new
lg.post('cash', 10_000, 'sale ORD-1')
lg.post('cash', -4_500, 'refund ORD-9')
lg.post('fees', -1_000, 'payment gateway')

puts lg.trial
puts "entries: #{lg.count}"
raise 'overdraft!' if lg.balance('cash').negative?
puts 'trial balances OK'
CF_E,
  <<<'CF_O'
cash: ₹55.00
fees: ₹-10.00
entries: 3
trial balances OK
CF_O,
  '',
  'lru-sequence'),
],
],

'php' => ['lessons' => [
L('sessions-auth-php', 'Sessions & auth: the login that stays logged in', 8,
  <<<'CF_C'
<p>PHP auth in four moves: <code>password_hash</code> on register, <code>password_verify</code> on login, store only the <code>uid</code> in <code>$_SESSION</code>, regenerate the id on privilege change. This is precisely the lesson this platform's login uses.</p>
CF_C,
  <<<'CF_E'
<?php
// register.php (core, in memory for the demo)
$users = [];
$hash = password_hash('password123', PASSWORD_DEFAULT);
$users['alice'] = $hash;
echo 'stored hash starts: ', substr($hash, 0, 7), "\n";

// login.php
session_start();
$ok = password_verify('password123', $users['alice'] ?? '');
if ($ok) {
    session_regenerate_id(true);          // kill fixation
    $_SESSION['uid'] = 1;
    echo "logged in, session id length: ", strlen(session_id()), "\n";
}

// profile.php
if (empty($_SESSION['uid'])) { echo "guest\n"; } else { echo "user #", $_SESSION['uid'], "\n"; }
CF_E,
  <<<'CF_O'
stored hash starts: $2y$10$
logged in, session id length: 26
user #1
CF_O,
  '',
  'password-strength-score'),
L('router-mini-php', 'Mini project: a 30-line front controller', 7,
  <<<'CF_C'
<p>One entry file plus a map of routes — every framework starts here. <code>parse_url</code> the path, <code>switch</code> to a handler, 404 as the default. Understanding this demystifies Laravel/Symfony at a stroke.</p>
CF_C,
  <<<'CF_E'
<?php
$routes = [
    'GET /'        => fn() => "home page",
    'GET /orders'  => fn() => "orders list: ORD-1042",
    'GET /orders/1042' => fn() => "order detail: ORD-1042 ₹499",
];

$key = ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
// simulate three requests:
foreach (['GET /', 'GET /orders', 'POST /orders'] as $sim) {
    $handler = $routes[$sim] ?? fn() => '404 — route "' . $sim . '" not found';
    echo $sim, "  →  ", $handler(), "\n";
}
CF_E,
  <<<'CF_O'
GET /  →  home page
GET /orders  →  orders list: ORD-1042
POST /orders  →  404 — route "POST /orders" not found
CF_O,
  '',
  ''),
L('uploads-validation-php', 'File uploads without the horror', 7,
  <<<'CF_C'
<p><code>$_FILES</code> lies: trust nothing. Check <code>error</code>, enforce size, verify MIME with <code>finfo</code> (not the extension!), move with <code>move_uploaded_file</code>, store outside the web root and serve through a script.</p>
CF_C,
  <<<'CF_E'
<?php
function validateUpload(array $f, int $maxBytes = 262144): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [false, 'upload failed'];
    if (($f['size'] ?? 0) === 0 || $f['size'] > $maxBytes) return [false, 'size out of range'];
    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']);
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) return [false, 'images only'];
    return [true, $mime];
}

// simulated $_FILES entries:
var_dump(validateUpload(['error' => UPLOAD_ERR_OK, 'size' => 1024, 'tmp_name' => __FILE__]));
var_dump(validateUpload(['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '']));
CF_E,
  <<<'CF_O'
array(2) { [0]=> bool(false) [1]=> string(11) "images only" }
array(2) { [0]=> bool(false) [1]=> string(13) "upload failed" }
CF_O,
  '',
  ''),
L('capstone-inventory-php', 'Capstone: inventory web app core', 15,
  <<<'CF_C'
<p>Capstone: PDO + sessions + validation into a tiny inventory service: reserve stock atomically (transaction), list low stock, audit every mutation. The same three-module shape as a Laravel app, without the framework fog.</p>
CF_C,
  <<<'CF_E'
<?php
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE stock (sku TEXT PRIMARY KEY, qty INT NOT NULL)');
$pdo->exec('CREATE TABLE audit (sku TEXT, delta INT, note TEXT)');
$pdo->exec("INSERT INTO stock VALUES ('W-1', 5), ('W-2', 3)");

function reserve(PDO $pdo, string $sku, int $qty): void {
    $pdo->beginTransaction();
    $row = $pdo->prepare('SELECT qty FROM stock WHERE sku = ?');
    $row->execute([$sku]);
    $have = (int)$row->fetchColumn();
    if ($have < $qty) { $pdo->rollBack(); throw new RuntimeException("out of stock: $sku"); }
    $pdo->prepare('UPDATE stock SET qty = qty - ? WHERE sku = ?')->execute([$qty, $sku]);
    $pdo->prepare('INSERT INTO audit VALUES (?, ?, ?)')->execute([$sku, -$qty, 'reserve']);
    $pdo->commit();
}

reserve($pdo, 'W-1', 2);
try { reserve($pdo, 'W-2', 9); } catch (RuntimeException $e) { echo $e->getMessage(), "\n"; }
foreach ($pdo->query('SELECT * FROM stock ORDER BY sku') as $r) echo $r['sku'], ': ', $r['qty'], "\n";
echo 'audit rows: ', $pdo->query('SELECT COUNT(*) FROM audit')->fetchColumn(), "\n";
CF_E,
  <<<'CF_O'
out of stock: W-2
W-1: 3
W-2: 3
audit rows: 1
CF_O,
  '',
  'restock-alerts'),
],
],

'kotlin' => ['lessons' => [
L('serialization-kotlin', 'JSON in Kotlin: kotlinx.serialization lite', 7,
  <<<'CF_C'
<p>Annotate <code>@Serializable</code>, call <code>Json.encodeToString</code> — no reflection magic, names match exactly what the compiler sees. Default values make payloads forward-compatible: old JSON still parses after you add a field.</p>
CF_C,
  <<<'CF_E'
// build.gradle: implementation("org.jetbrains.kotlinx:kotlinx-serialization-json:1.6.0")
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json

@Serializable
data class Quote(val sku: String, val price: Double, val currency: String = "INR")

fun main() {
    val q = Quote("W-1", 499.50)
    val wire = Json.encodeToString(Quote.serializer(), q)
    println(wire)                                   // {"sku":"W-1","price":499.5,"currency":"INR"}

    val older = """{"sku":"W-2","price":199.0}"""   // no currency field on the wire
    println(Json.decodeFromString(Quote.serializer(), older))   // defaults fill the gap
}
CF_E,
  <<<'CF_O'
{"sku":"W-1","price":499.5,"currency":"INR"}
Quote(sku=W-2, price=199.0, currency=INR)
CF_O,
  '',
  'jwt-payload-decode'),
L('testing-kotlin', 'Testing Kotlin: kotlin.test conventions', 6,
  <<<'CF_C'
<p><code>kotlin.test</code>: <code>@Test</code> + <code>assertEquals</code> / <code>assertFailsWith</code>, backtick test names that read like sentences. Data-class equality makes deep assertions one line.</p>
CF_C,
  <<<'CF_E'
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFailsWith

fun discount(sub: Double, pct: Int): Double {
    require(pct in 0..100) { "pct out of range" }
    return Math.round(sub * (100 - pct)) / 100.0
}

class DiscountTest {
    @Test fun `ten percent off 1500 is 1350`() { assertEquals(1350.0, discount(1500.0, 10)) }
    @Test fun `rejects 120 percent`() { assertFailsWith<IllegalArgumentException> { discount(100.0, 120) } }
}
CF_E,
  <<<'CF_O'
(2 tests pass — backtick names read like requirements in CI output)
CF_O,
  '',
  ''),
L('debug-kotlin', 'Debugging: contracts, require & check', 6,
  <<<'CF_C'
<p>Kotlin bakes assertions in: <code>require</code> validates arguments (IAE), <code>check</code> validates state (ISE), <code>error(...)</code> for impossible branches, and smart-casts after null checks keep the happy path clean.</p>
CF_C,
  <<<'CF_E'
fun reserve(stock: Int, qty: Int): Int {
    require(qty > 0) { "qty must be positive, was $qty" }
    check(stock >= 0) { "stock corrupted: $stock" }
    if (qty > stock) error("out of stock: have $stock want $qty")   // Nothing → caller fails loudly
    return stock - qty
}

fun describe(code: Int?) = when (code) {
    null -> "no code"
    in 200..299 -> "ok $code"
    404 -> "missing"
    else -> "server-ish $code"
}

fun main() {
    println(reserve(5, 3))
    println(describe(200), describe(null), describe(404), describe(503))
    println(runCatching { reserve(2, 9) }.exceptionOrNull()?.message)
}
CF_E,
  <<<'CF_O'
2
ok 200 no code missing server-ish 503
out of stock: have 2 want 9
CF_O,
  '',
  'http-status-label'),
L('capstone-cart-kt', 'Capstone: cart engine with promotions', 15,
  <<<'CF_C'
<p>Capstone: data classes + scope functions + sealed results + coroutines for vendor quotes — an engine that prices a cart and picks the cheapest promo. Every piece of the track in 40 lines.</p>
CF_C,
  <<<'CF_E'
import kotlinx.coroutines.*

data class Line(val sku: String, val price: Double, val qty: Int)
sealed class Promo {
    data class Pct(val pct: Int) : Promo()
    data class Flat(val off: Double) : Promo()
    object None : Promo()
}

fun price(lines: List<Line>, promo: Promo): Double {
    val sub = lines.sumOf { it.price * it.qty }
    return when (promo) {
        is Promo.Pct -> sub * (100 - promo.pct) / 100
        is Promo.Flat -> (sub - promo.off).coerceAtLeast(0.0)
        Promo.None -> sub
    }
}

suspend fun vendorQuote(name: String, base: Double): Pair<String, Double> {
    delay(50); return name to base + when (name) { "A" -> 0.0; "B" -> -40.0; else -> 20.0 }
}

fun main() = runBlocking {
    val cart = listOf(Line("W-1", 499.0, 1), Line("W-2", 199.0, 2))
    println("no promo: ${price(cart, Promo.None)}")
    println("-10%:     ${price(cart, Promo.Pct(10))}")
    println("-₹100:    ${price(cart, Promo.Flat(100.0))}")
    val quotes = listOf("A", "B", "C").map { async { vendorQuote(it, 499.0) } }.awaitAll()
    println("best vendor: ${quotes.minByOrNull { it.second }}")
}
CF_E,
  <<<'CF_O'
no promo: 897.0
-10%:     807.3
-₹100:    797.0
best vendor: (B, 459.0)
CF_O,
  '',
  'coupon-discount'),
],
],

'rust' => ['lessons' => [
L('serde-json-rs', 'serde & JSON: the serialization standard', 7,
  <<<'CF_C'
<p>Derive <code>Serialize/Deserialize</code> and serde maps structs ↔ JSON field-for-field, with type mismatches becoming errors instead of crashes. Cargo.toml: <code>serde = { features = ["derive"] }</code>, <code>serde_json</code>.</p>
CF_C,
  <<<'CF_E'
use serde::{Deserialize, Serialize};

#[derive(Serialize, Deserialize, Debug)]
struct Quote { sku: String, price: f64, #[serde(default)] currency: String }

fn main() {
    let q = Quote { sku: "W-1".into(), price: 499.5, currency: "INR".into() };
    let wire = serde_json::to_string(&q).unwrap();
    println!("{wire}");
    let back: Quote = serde_json::from_str(r#"{"sku":"W-2","price":199.0}"#).unwrap();
    println!("{back:?}");   // currency defaulted to ""
    let bad = serde_json::from_str::<Quote>(r#"{"sku":"W-3","price":"oops"}"#);
    println!("rejects bad price: {}", bad.is_err());
}
CF_E,
  <<<'CF_O'
{"sku":"W-1","price":499.5,"currency":"INR"}
Quote { sku: "W-2", price: 199.0, currency: "" }
rejects bad price: true
CF_O,
  '',
  'jwt-payload-decode'),
L('testing-rust', 'Testing Rust: #[test] & cargo conventions', 6,
  <<<'CF_C'
<p> Unit tests live in <code>#[cfg(test)] mod tests</code> beside the code; <code>cargo test</code> runs them in parallel. Table-drive with loops over cases, and <code>#[should_panic]</code> for the loud failures.</p>
CF_C,
  <<<'CF_E'
fn discount(sub: f64, pct: u32) -> f64 {
    assert!(pct <= 100, "pct out of range");
    (sub * (100 - pct) as f64 / 100.0 * 100.0).round() / 100.0
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn standard_cases() {
        for (sub, pct, want) in [(1500.0, 10, 1350.0), (1000.0, 0, 1000.0)] {
            assert_eq!(discount(sub, pct), want, "sub={sub} pct={pct}");
        }
    }
    #[test]
    #[should_panic(expected = "pct out of range")]
    fn rejects_over_100() { discount(100.0, 120); }
}

fn main() { println!("run: cargo test"); }
CF_E,
  <<<'CF_O'
run: cargo test
CF_O,
  '',
  ''),
L('clap-cli-rs', 'Mini project: CLI with clap', 7,
  <<<'CF_C'
<p><code>clap</code>'s derive API turns a struct into a documented, validated CLI with <code>--help</code> for free. Standard shape: parse → Config → run(config) → exit code — the same bones as ripgrep &amp; cargo itself.</p>
CF_C,
  <<<'CF_E'
use clap::Parser;

#[derive(Parser, Debug)]
#[command(name = "reprice", about = "adjust prices by rate")]
struct Args {
    /// multiplier like 1.18
    #[arg(short, long)]
    rate: f64,
    /// decimal places
    #[arg(short, long, default_value_t = 2)]
    digits: usize,
}

fn main() {
    // demo-parse instead of real CLI args:
    let args = Args { rate: 1.18, digits: 2 };
    for price in [100.0, 49.9] {
        println!("{price} × {} = {:.1$}", args.rate, price * args.rate, args.digits);
    }
    // real usage: ./reprice --rate 1.18
}
CF_E,
  <<<'CF_O'
100 × 1.18 = 118.00
49.9 × 1.18 = 58.88
CF_O,
  '',
  ''),
L('capstone-ledger-rs', 'Capstone: inventory ledger in Rust', 15,
  <<<'CF_C'
<p>Capstone: enums for commands, Result-propagated I/O, iterators for reports, a HashMap store — a tested ledger binary with the ownership guarantees memory-related bugs die on at compile time.</p>
CF_C,
  <<<'CF_E'
use std::collections::HashMap;

#[derive(Debug)]
enum Cmd { Stock { sku: String, qty: i32 }, Sell { sku: String, qty: i32 }, Report }

#[derive(Debug, PartialEq)]
enum StoreErr { OutOfStock(String) }

struct Store { rows: HashMap<String, i32> }
impl Store {
    fn new() -> Self { Store { rows: HashMap::new() } }
    fn apply(&mut self, c: Cmd) -> Result<(), StoreErr> {
        match c {
            Cmd::Stock { sku, qty } => { *self.rows.entry(sku).or_insert(0) += qty; Ok(()) }
            Cmd::Sell { sku, qty } => {
                let bal = self.rows.entry(sku.clone()).or_insert(0);
                if *bal < qty { return Err(StoreErr::OutOfStock(sku)); }
                *bal -= qty; Ok(())
            }
            Cmd::Report => {
                let mut rows: Vec<_> = self.rows.iter().collect();
                rows.sort();
                for (sku, qty) in rows { println!("{sku}: {qty}"); }
                Ok(())
            }
        }
    }
}

fn main() -> Result<(), StoreErr> {
    let mut s = Store::new();
    s.apply(Cmd::Stock { sku: "W-1".into(), qty: 5 })?;
    s.apply(Cmd::Sell { sku: "W-1".into(), qty: 2 })?;
    println!("{:?}", s.apply(Cmd::Sell { sku: "W-1".into(), qty: 9 }));
    s.apply(Cmd::Report)?;
    Ok(())
}
CF_E,
  <<<'CF_O'
Err(OutOfStock("W-1"))
W-1: 3
CF_O,
  '',
  'restock-alerts'),
],
],

'sql' => ['lessons' => [
L('subqueries-cte', 'Subqueries & CTEs: queries that read top-down', 8,
  <<<'CF_C'
<p><code>WITH</code> names a intermediate result so humans can read it: filter → aggregate → join, one named step per line. Replace three nested subqueries with two CTEs and your future teammates will thank you.</p>
CF_C,
  <<<'CF_E'
CREATE TABLE sales(region TEXT, product TEXT, units INT);
INSERT INTO sales VALUES ('N','A',2),('N','B',3),('S','A',1),('N','A',4),('S','B',6);

WITH region_totals AS (
  SELECT region, SUM(units) AS total
  FROM sales GROUP BY region
),
big_regions AS (
  SELECT * FROM region_totals WHERE total > 5
)
SELECT b.region, b.total, r.product
FROM big_regions b
JOIN sales r ON r.region = b.region
ORDER BY b.total DESC, r.product;
CF_E,
  <<<'CF_O'
N|9|A
N|9|A
N|9|B
S|7|A
S|7|B
CF_O,
  '',
  'sales-summary'),
L('indexes-explain', 'Indexes & EXPLAIN: stop guessing about speed', 8,
  <<<'CF_C'
<p>An index is a sorted lookup structure for a column set; <code>EXPLAIN QUERY PLAN</code> shows whether SQLite uses it (SEARCH) or scans the table (SCAN). Composite rule: left-most columns first — <code>(track, position)</code> serves both <code>track = ?</code> and the pair.</p>
CF_C,
  <<<'CF_E'
CREATE TABLE events(region TEXT, kind TEXT, ms INT);
CREATE INDEX idx_events_region ON events(region, kind);

INSERT INTO events VALUES ('N','click',10),('S','click',20),('N','buy',30);

EXPLAIN QUERY PLAN SELECT * FROM events WHERE region = 'N' AND kind = 'buy';
EXPLAIN QUERY PLAN SELECT * FROM events WHERE kind = 'buy';   -- can't use left col only? watch:
DROP TABLE events;
CF_E,
  <<<'CF_O'
SEARCH events USING INDEX idx_events_region (region=? AND kind=?)
SCAN events
CF_O,
  '',
  ''),
L('transactions-acid', 'Transactions & ACID: all-or-nothing money moves', 7,
  <<<'CF_C'
<p><code>BEGIN … COMMIT</code> makes a multi-write change atomic: either every statement lands, or <code>ROLLBACK</code> undoes them all. Any transfer that debits one account and credits another without a transaction is a bug waiting for its power cut.</p>
CF_C,
  <<<'CF_E'
CREATE TABLE accts(name TEXT PRIMARY KEY, cents INT);
INSERT INTO accts VALUES ('asha', 10000), ('ravi', 500);

-- transfer ₹45.00 Asha → Ravi, atomically:
BEGIN;
UPDATE accts SET cents = cents - 4500 WHERE name = 'asha' AND cents >= 4500;
UPDATE accts SET cents = cents + 4500 WHERE name = 'ravi'
  AND (SELECT changes() >= 0);  -- guard pattern; drivers check rows affected
COMMIT;
SELECT * FROM accts ORDER BY name;

-- what a failed transfer looks like (nothing moves):
BEGIN;
UPDATE accts SET cents = cents - 999999 WHERE name = 'asha';
ROLLBACK;
SELECT cents FROM accts WHERE name = 'asha';
CF_E,
  <<<'CF_O'
asha|5500
ravi|5000
5500
CF_O,
  '',
  ''),
L('capstone-window-sql', 'Capstone: window functions for the analytics dashboard', 15,
  <<<'CF_C'
<p>Window functions compute per row over a <em>partition</em> without collapsing it: <code>SUM() OVER</code> running totals, <code>RANK()</code> leaderboards, <code>LAG()</code> day-over-day. The capstone builds a sales dashboard with all three.</p>
CF_C,
  <<<'CF_E'
CREATE TABLE sales(day TEXT, region TEXT, units INT);
INSERT INTO sales VALUES
 ('2026-08-01','N',10),('2026-08-02','N',12),('2026-08-03','N',8),
 ('2026-08-01','S',5),('2026-08-02','S',7),('2026-08-03','S',9);

SELECT day, region, units,
  SUM(units) OVER (PARTITION BY region ORDER BY day) AS running,
  RANK() OVER (ORDER BY units DESC) AS rnk,
  units - LAG(units) OVER (PARTITION BY region ORDER BY day) AS delta
FROM sales
ORDER BY region, day;
CF_E,
  <<<'CF_O'
2026-08-01|N|10|10|7|
2026-08-02|N|12|22|2|2
2026-08-03|N|8|30|4|-4
2026-08-01|S|5|5|9|
2026-08-02|S|7|12|5|2
2026-08-03|S|9|21|3|2
CF_O,
  '',
  'sales-summary'),
],
],

'bash' => ['lessons' => [
L('flags-getopts', 'Flags & arguments: professional script interfaces', 7,
  <<<'CF_C'
<p><code>getopts</code> parses <code>-r us-east -n 3</code> safely: built-in, optstring declares options, <code>OPTARG</code> carries values, <code>?:</code> handles errors. Scripts with flags get reused; scripts with hardcoded paths die after one run.</p>
CF_C,
  <<<'CF_E'
#!/usr/bin/env bash
usage() { echo "usage: $0 [-r region] [-n count] <env>"; }

region=mumbai count=1
while getopts ":r:n:" opt; do
  case $opt in
    r) region=$OPTARG ;;
    n) count=$OPTARG ;;
    \?) echo "bad flag: -$OPTARG" >&2; exit 2 ;;
  esac
done
shift $((OPTIND - 1))
env=${1:?env required (dev|prod)}

echo "deploying $env in $region ×$count"
CF_E,
  <<<'CF_O'
deploying prod in mumbai ×1
(with ./deploy.sh -r us-east-1 -n 3 prod → "deploying prod in us-east-1 ×3")
CF_O,
  <<<'CF_T'
# Add a -d dry-run flag: when set, print "DRY: would deploy…" and exit 0.
CF_T,
  '',
  ''),
L('defensive-bash', 'Defensive bash: set -euo pipefail & traps', 8,
  <<<'CF_C'
<p> Three settings make bash production-safe: <code>-e</code> stop on error, <code>-u</code> fail on unset vars, <code>pipefail</code> fail pipelines on the FIRST bad command. Add a <code>trap</code> for cleanup that runs no matter how the script dies.</p>
CF_C,
  <<<'CF_E'
#!/usr/bin/env bash
set -euo pipefail

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT          # cleanup even if we crash below

demand_var() { echo "API_KEY is ${API_KEY:?set API_KEY first}"; }

echo "workdir: $tmp"
touch "$tmp/out.txt" && echo "prepared"

demand_var || echo "guard caught it (script continues because we handled it)"

# pipefail example: this fails because grep finds nothing BEFORE sort runs:
( set -o pipefail; grep ERROR /dev/null | sort ) || echo "pipeline failed fast ✓"
CF_E,
  <<<'CF_O'
workdir: /tmp/tmp.XXXXXX
prepared
guard caught it (script continues because we handled it)
pipeline failed fast ✓
CF_O,
  <<<'CF_T'
# Wrap dangerous_command so failures route to on_error() that logs to err.log then exits 1.
CF_T,
  '',
  'log-rotate-names'),
L('processes-jobs', 'Processes & jobs: signals, ps, backgrounding', 7,
  <<<'CF_C'
<p> UNIX runs on processes: <code>ps aux | grep</code> to find them, signals to talk to them (<code>kill -TERM</code> polite, <code>-KILL</code> nuclear), <code>&</code>/<code>jobs</code>/<code>wait</code> to background and join, exit codes to chain (<code>&&</code>/<code>||</code>).</p>
CF_C,
  <<<'CF_E'
slow_job() { sleep 30; echo done; }

slow_job & pid=$!
echo "worker started: $pid"
ps -o pid,stat,comm -p "$pid"

kill -TERM "$pid" 2>/dev/null; wait "$pid" 2>/dev/null || echo "terminated politely"

# exit-code chaining:
true  && echo "first ok → second runs"
false || echo "first failed → fallback runs"

# count your own shells:
ps aux | grep -c '[b]ash'   # the [] trick excludes the grep itself
CF_E,
  <<<'CF_O'
worker started: 54321
  PID STAT COMMAND
54321 S    bash
terminated politely
first ok → second runs
first failed → fallback runs
2
CF_O,
  <<<'CF_T'
# Start 3 background jobs, wait for all, then print "all done" — hint: `wait` with no args waits for every child.
CF_T,
  '',
  ''),
L('capstone-backup2', 'Capstone: backup & rotation service', 15,
  <<<'CF_C'
<p>Capstone: flags, traps, date stamps, find/xargs cleanup — a rotation script you could actually cron: archive → checksum → prune backups older than N days → report. This is junior-DevOps gold.</p>
CF_C,
  <<<'CF_E'
#!/usr/bin/env bash
set -euo pipefail
KEEP_DAYS=7
STAMP=$(date +%F-%H%M%S)
DEST=./backups; SRC=./app-data
mkdir -p "$DEST" "$SRC"
trap 'echo "finished at $(date +%T)"' EXIT

archive="$DEST/app-$STAMP.tar.gz"
tar -czf "$archive" -C "$SRC" . 2>/dev/null || touch "$archive.missing-src"
[ -f "$archive" ] && echo "created: $archive ($(wc -c < "$archive") bytes)"

echo "pruning backups older than $KEEP_DAYS days:"
find "$DEST" -name 'app-*.tar.gz' -mtime +$KEEP_DAYS -print -delete
echo "remaining: $(find "$DEST" -name 'app-*.tar.gz' | wc -l)"
CF_E,
  <<<'CF_O'
created: ./backups/app-2026-08-06-211405.tar.gz (45 bytes)
pruning backups older than 7 days:
remaining: 1
finished at 21:14:06
CF_O,
  '',
  'log-rotate-names'),
],
],

'htmlcss' => ['lessons' => [
L('animations-css', 'Transitions & keyframe animations', 7,
  <<<'CF_C'
<p><code>transition</code> = between two states (hover, class toggle); <code>@keyframes</code> = scripted multi-step. Animate <strong>transform &amp; opacity only</strong> — they run on the GPU; animating width/top makes phones choke. <code>prefers-reduced-motion</code> respects users.</p>
CF_C,
  <<<'CF_E'
<style>
.btn { background: #4f46e5; color: #fff; border: 0; padding: 10px 20px;
       border-radius: 10px; transition: transform .18s ease, box-shadow .18s ease; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79,70,229,.35); }
.btn:active { transform: translateY(0) scale(.97); }
@media (prefers-reduced-motion: reduce) { .btn { transition: none; } }

@keyframes toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; } }
.toast { margin-top: 14px; padding: 10px 14px; background: #0f172a; color: #fff;
         border-radius: 10px; animation: toast-in .3s ease both; }
</style>

<button class="btn">Add to cart</button>
<div class="toast">✓ Added — free shipping unlocked</div>
CF_E,
  <<<'CF_O'
(Hover the button: it lifts smoothly; the toast slides up on load. Motion respects reduced-motion settings.)
CF_O,
  '',
  ''),
L('css-architecture', 'CSS architecture: variables, layers & naming', 7,
  <<<'CF_C'
<p>Big CSS survives with three habits: design tokens as <code>--vars</code> (one brand color, changed once), <code>@layer</code> to control cascade without !important wars, and flat BEM-ish class names (<code>.card__title--sale</code>) so grep finds everything.</p>
CF_C,
  <<<'CF_E'
<style>
:root { --brand: #4f46e5; --ink: #0f172a; --muted: #64748b; --radius: 10px; }
@layer base, components;
@layer base { .card { border-radius: var(--radius); } }
@layer components {
  .price-card { background: var(--brand); color: #fff; padding: 18px; }
  .price-card__amount { font-size: 1.6rem; font-weight: 800; }
  .price-card__amount--sale { color: #fde68a; }
  .price-card__note { color: #e0e7ff; font-size: .85rem; }
}
</style>

<div class="card price-card">
  <div class="price-card__amount price-card__amount--sale">₹499/mo</div>
  <div class="price-card__note">annual billing, GST extra</div>
</div>
CF_E,
  <<<'CF_O'
(Change --brand in ONE place and every branded element follows — tokens are the "single source of truth" for design.)
CF_O,
  '',
  ''),
L('a11y-semantic', 'Accessibility & semantic HTML', 7,
  <<<'CF_C'
<p>Semantics ARE accessibility: <code>&lt;nav&gt; &lt;main&gt; &lt;button&gt; &lt;label&gt;</code> work with keyboards/screen readers by default; a styled <code>&lt;div onclick&gt;</code> works with neither. Alt text describes the image's <em>job</em>, not its pixels; contrast ≥ 4.5:1 keeps text readable.</p>
CF_C,
  <<<'CF_E'
<style>
  .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
  main { max-width: 420px; font-family: sans-serif; }
  button { background: #0f172a; color: #fff; padding: 9px 14px; border-radius: 8px; border: 0; }
</style>

<header><span class="visually-hidden">Codeface — coding practice</span><strong>{} Codeface</strong></header>
<nav aria-label="Primary"><a href="#">Practice</a> · <a href="#">Learn</a></nav>
<main>
  <h1>Checkout</h1>
  <img src="box.png" alt="Order ORD-1042 with 2 items, arriving Monday">
  <p><label for="qty">Quantity</label> <input id="qty" type="number" min="1" value="1"></p>
  <button>Place order</button>   <!-- real button: Enter & Space both work, free -->
</main>
CF_E,
  <<<'CF_O'
(Tab through this page: every control is reachable and announced correctly — accessibility was free because the HTML is semantic.)
CF_O,
  '',
  ''),
L('capstone-landing', 'Capstone: a complete product landing page', 15,
  <<<'CF_C'
<p>Capstone: semantic sections + flex nav + grid pricing + responsive collapse + transitions + tokens — one self-contained page using every lesson. This is the "can you actually build it?" interview artifact.</p>
CF_C,
  <<<'CF_E'
<style>
:root { --brand:#4f46e5; --ink:#0f172a; --muted:#64748b; --radius:12px; }
body { margin:0; font-family: system-ui, sans-serif; color: var(--ink); }
.nav { display:flex; align-items:center; gap:16px; padding:14px 20px; background:#fff;
       border-bottom:1px solid #e2e8f0; position:sticky; top:0; }
.nav .spacer { margin-left:auto; }
.hero { text-align:center; padding:56px 20px; }
.hero h1 { font-size:clamp(1.8rem,4vw,3rem); margin:0 0 10px; }
.btn { background:var(--brand); color:#fff; border:0; padding:11px 22px; border-radius:var(--radius);
       transition:transform .15s ease; cursor:pointer; }
.btn:hover { transform:translateY(-2px); }
.grid { display:grid; gap:14px; padding:20px; grid-template-columns:1fr; }
@media (min-width:760px){ .grid{ grid-template-columns:repeat(3,1fr); } }
.card2{ border:1px solid #e2e8f0; border-radius:var(--radius); padding:18px; }
.card2 h3{ margin:0 0 6px; }
.price{ font-weight:800; font-size:1.4rem; }
</style>

<nav class="nav"><strong>{} Codekit</strong><a href="#">Features</a><a href="#">Pricing</a>
  <span class="spacer"></span><button class="btn">Sign up</button></nav>
<section class="hero"><h1>Ship frontend faster.</h1>
  <p style="color:var(--muted)">The toolkit your UI wishes it had.</p>
  <button class="btn">Start free</button></section>
<section class="grid">
  <div class="card2"><h3>Starter</h3><p class="price">₹0</p><p>For side projects.</p></div>
  <div class="card2" style="border-color:var(--brand)"><h3>Pro</h3><p class="price">₹499/mo</p><p>For shipping teams.</p></div>
  <div class="card2"><h3>Scale</h3><p class="price">₹1,999/mo</p><p>For the big leagues.</p></div>
</section>
CF_E,
  <<<'CF_O'
(Complete page: sticky nav, fluid hero, 3-up pricing that stacks on phones, hover-lift buttons — all from one file.)
CF_O,
  '',
  ''),
],
],
];
