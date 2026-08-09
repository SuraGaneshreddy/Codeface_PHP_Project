<?php
// Learn tracks 5: ADVANCE lessons (positions 9-12) for all 15 tracks.
// Positions are assigned in array order during seeding after tracks1-4.
return [

'javascript' => ['lessons' => [
L('closures-scope', 'Closures & scope, for real', 8,
  <<<'CF_C'
<p>A <strong>closure</strong> is a function that remembers the variables of the scope where it was born. This is how real apps keep "private" state without classes: counters, caches, once-only initializers. If you can read a closure, you can read half of npm.</p>
CF_C,
  <<<'CF_E'
// order-id generator with private state
function makeIdGen(prefix) {
  let seq = 0;                 // private: nobody outside can touch it
  return function () {
    seq++;
    return prefix + '-' + String(seq).padStart(4, '0');
  };
}

const orderId = makeIdGen('ORD');
console.log(orderId());        // ORD-0001
console.log(orderId());        // ORD-0002

const invoiceId = makeIdGen('INV'); // a second, independent memory
console.log(invoiceId());      // INV-0001
console.log(orderId());        // ORD-0003
CF_E,
  <<<'CF_O'
ORD-0001
ORD-0002
INV-0001
ORD-0003
CF_O,
  <<<'CF_T'
// Build makeLimiter(max) that returns a function: each call returns
// how many calls are still allowed before hitting max (then 0 forever).
// makeLimiter(2) → 1, 0, 0, 0
function makeLimiter(max) {
  // your code
}
CF_T,
  'retry-backoff-schedule'),
L('this-prototypes', 'this, prototypes & classes without fear', 8,
  <<<'CF_C'
<p>In JS, objects delegate to a <strong>prototype</strong> chain: lookup walks up until it finds the property. Classes are sugar over this. The one rule that prevents 90% of bugs: <code>this</code> is decided by <em>how you call</em> the function (<code>cart.add()</code> → <code>cart</code>), not where it was written.</p>
CF_C,
  <<<'CF_E'
class Cart {
  constructor(owner) { this.owner = owner; this.items = []; }
  add(sku, price) { this.items.push({ sku, price }); return this; } // return this → chaining
  total() { return this.items.reduce((s, i) => s + i.price, 0); }
}

const c = new Cart('Asha');
c.add('SKU-1', 499).add('SKU-2', 199);   // chaining works because add returns this
console.log(c.owner + ' owes ' + c.total());

const detached = c.total;   // careful: unbound method
try { detached(); } catch (e) { console.log('unbound call fails: ' + e.constructor.name); }
console.log(c.total.call({ items: [{ price: 100 }] }));  // explicit this rescue
CF_E,
  <<<'CF_O'
Asha owes 698
unbound call fails: TypeError
100
CF_O,
  '',
  ''),
L('modules-es', 'Modules: import / export like a professional', 7,
  <<<'CF_C'
<p>One file per responsibility. <code>export</code> the few names others need, <code>import</code> them where used. Default export = "the main thing"; named exports = "also these helpers". Circular imports are a design smell — extract the shared piece into a third module.</p>
CF_C,
  <<<'CF_E'
// --- money.js (in a real project, its own file) ---
// export TAX_RATES, money
const TAX_RATES = { food: 0.05, electronics: 0.18 };
function money(v) { return '₹' + v.toFixed(2); }

// --- checkout.js ---
// import { TAX_RATES, money } from './money.js'
function lineTotal(item) {
  return item.price * item.qty * (1 + (TAX_RATES[item.cat] ?? 0));
}

const bill = [
  { name: 'Apple',  cat: 'food', price: 40, qty: 2 },
  { name: 'Widget', cat: 'electronics', price: 100, qty: 5 },
];
for (const it of bill) console.log(it.name, money(lineTotal(it)));
CF_E,
  <<<'CF_O'
Apple ₹84.00
Widget ₹590.00
CF_O,
  '',
  ''),
L('regex-js', 'Regular expressions that earn their keep', 7,
  <<<'CF_C'
<p>Regex is a <strong>precision tool</strong>, not a lifestyle: great for "does this GST number match the shape", terrible for parsing HTML. Learn five pieces — classes <code>[]</code>, quantifiers <code>+ * {n}</code>, groups <code>()</code>, anchors <code>^ $</code>, and <code>exec</code>/<code>match</code>/<code>replace</code> — and you can handle 95% of real validation.</p>
CF_C,
  <<<'CF_E'
const GST = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
console.log(GST.test('24AABCT1332L1Z5'));   // true
console.log(GST.test('hello'));             // false

// extract: pull every ₹ amount out of a note
const note = 'Refund ₹1,250.00 plus ₹49.50 fee to ravi@shop.in';
const amounts = note.match(/₹[\d,]+(?:\.\d{2})?/g) || [];
console.log(amounts);

// redact the email for logs
console.log(note.replace(/[\w.]+@[\w.]+/, '<email>'));
CF_E,
  <<<'CF_O'
true
false
[ '₹1,250.00', '₹49.50' ]
Refund ₹1,250.00 plus ₹49.50 fee to <email>
CF_O,
  <<<'CF_T'
// Validate a phone: allow "98765 43210" or "9876543210", 10 digits, not starting 0.
const ok = (v) => { /* your regex */ };
console.log(ok('98765 43210'), ok('0123456789'), ok('123'));
CF_T,
  'phone-normalizer'),
],
],

'typescript' => ['lessons' => [
L('interfaces-real', 'Interfaces that describe real payloads', 7,
  <<<'CF_C'
<p>An interface is a <strong>contract</strong>: "any object with these fields of these types". Model the payloads your app actually moves (orders, users, API responses) once, and the compiler becomes a reviewer that never sleeps. Use <code>?</code> for optional fields, <code>readonly</code> for "do not mutate".</p>
CF_C,
  <<<'CF_E'
interface OrderItem { sku: string; price: number; qty: number; }
interface Order {
  readonly id: string;
  customer: string;
  items: OrderItem[];
  coupon?: string;            // may be absent
}

function orderTotal(o: Order): number {
  let t = o.items.reduce((s, i) => s + i.price * i.qty, 0);
  if (o.coupon === 'SAVE10') t = t * 0.9;
  return Math.round(t * 100) / 100;
}

const o: Order = { id: 'O-1001', customer: 'Asha', coupon: 'SAVE10',
  items: [{ sku: 'W1', price: 100, qty: 5 }, { sku: 'A2', price: 40, qty: 2 }] };
console.log(orderTotal(o));   // 580 × 0.9 = 522
CF_E,
  <<<'CF_O'
522
CF_O,
  <<<'CF_T'
// Add an interface Invoice { order: Order; tax: number; grand: number }
// and a function makeInvoice(o: Order): Invoice
CF_T,
  'shopping-cart-total'),
L('unions-discriminated', 'Discriminated unions: the killer TS pattern', 8,
  <<<'CF_C'
<p>Model "one of several shapes" with a shared <strong>tag field</strong> (<code>kind</code>). A <code>switch</code> with <code>never</code> in the default arm makes the compiler <em>prove</em> you handled every variant — add a new variant later and every missed site errors instantly.</p>
CF_C,
  <<<'CF_E'
type Payment =
  | { kind: 'upi'; vpa: string }
  | { kind: 'card'; last4: string; network: string }
  | { kind: 'cod' };                      // cash on delivery, no fields

function label(p: Payment): string {
  switch (p.kind) {
    case 'upi':  return 'UPI → ' + p.vpa;
    case 'card': return p.network + ' •• ' + p.last4;
    case 'cod':  return 'Cash on delivery';
    default:     const _x: never = p; return _x; // exhaustiveness proof
  }
}
const methods: Payment[] = [
  { kind: 'upi', vpa: 'asha@okaxis' },
  { kind: 'card', last4: '4242', network: 'VISA' },
  { kind: 'cod' },
];
methods.forEach(m => console.log(label(m)));
CF_E,
  <<<'CF_O'
UPI → asha@okaxis
VISA •• 4242
Cash on delivery
CF_O,
  '',
  ''),
L('generics-constraints', 'Generics with constraints', 8,
  <<<'CF_C'
<p>Generics = "works for any type, without lying about which one". Add a <strong>constraint</strong> (<code>extends</code>) when you need a capability: <code>&lt;T extends { price: number }&gt;</code> says "any shape that at least has a numeric price". Reusable <em>and</em> type-safe.</p>
CF_C,
  <<<'CF_E'
function sumPrice<T extends { price: number }>(items: T[]): number {
  return items.reduce((s, i) => s + i.price, 0);
}
function pick<T, K extends keyof T>(obj: T, key: K): T[K] {
  return obj[key];            // key must actually exist on T — compiler enforces it
}

const cart = [{ sku: 'A', price: 100 }, { sku: 'B', price: 250 }];
console.log(sumPrice(cart));                    // 350
const order = { id: 'O-9', total: 899.5 };
console.log(pick(order, 'total'));              // 899.5
// pick(order, 'nope') → compile error: 'nope' is not a key of order
CF_E,
  <<<'CF_O'
350
899.5
CF_O,
  '',
  ''),
L('utility-types', 'Utility types you will use daily', 6,
  <<<'CF_C'
<p>TypeScript ships transformers so you never repeat a shape: <code>Partial&lt;T&gt;</code> (all optional — patch bodies), <code>Pick&lt;T,K&gt;</code> (public view), <code>Omit&lt;T,K&gt;</code>, <code>Readonly&lt;T&gt;</code>, and <code>Record&lt;K,V&gt;</code> for maps. Build DTOs by transforming the one canonical interface.</p>
CF_C,
  <<<'CF_E'
interface Product { id: number; sku: string; name: string; price: number; stock: number; }

type ProductPatch = Partial<Product>;                       // for PATCH endpoints
type ProductCard = Pick<Product, 'sku' | 'name' | 'price'>; // for listing cards
type StockMap    = Record<string, number>;                  // sku → qty

function applyPatch(p: Product, patch: ProductPatch): Product {
  return { ...p, ...patch };
}

const p: Product = { id: 1, sku: 'W-1', name: 'Widget', price: 499, stock: 12 };
const p2 = applyPatch(p, { price: 449 });
console.log(p2.price);                                   // 449
const card: ProductCard = { sku: p2.sku, name: p2.name, price: p2.price };
const stock: StockMap = { 'W-1': 12, 'W-2': 4 };
console.log(card, stock);
CF_E,
  <<<'CF_O'
449
{ sku: 'W-1', name: 'Widget', price: 449 } { 'W-1': 12, 'W-2': 4 }
CF_O,
  '',
  ''),
],
],

'python' => ['lessons' => [
L('oop-python', 'Classes & dunder methods the Pythonic way', 8,
  <<<'CF_C'
<p>Python classes stay honest through <strong>dunder methods</strong>: <code>__init__</code> builds, <code>__repr__</code> is for developers (debuggable!), <code>__eq__</code> makes <code>==</code> work. Small, data-holding classes beat dicts-of-strings because refactors are greppable and typos fail fast.</p>
CF_C,
  <<<'CF_E'
class Money:
    def __init__(self, amount: float, currency: str = 'INR'):
        self.amount = round(amount, 2)
        self.currency = currency
    def __repr__(self):
        return f'{self.currency} {self.amount:.2f}'
    def __add__(self, other):
        assert self.currency == other.currency, 'currency mismatch'
        return Money(self.amount + other.amount, self.currency)

price = Money(499.0)
print(price)                    # INR 499.00
total = price + Money(1.0)
print(total)                    # INR 500.00
print(total.amount)             # 500.0
CF_E,
  <<<'CF_O'
INR 499.00
INR 500.00
500.0
CF_O,
  <<<'CF_T'
# Add __mul__ so Money(10) * 3 == Money(30) → prints INR 30.00.
# (The editor pre-loads a compact Money; your job is the __mul__ method.)
class Money:
    def __init__(self, amount: float, currency: str = 'INR'):
        self.amount = round(amount, 2)
        self.currency = currency
    def __repr__(self):
        return f'{self.currency} {self.amount:.2f}'
    def __mul__(self, n):              # ← try writing this one yourself
        return Money(self.amount * n, self.currency)

print(Money(10) * 3)     # INR 30.00
print(Money(2.5) * 4)    # INR 10.00
CF_T,
  'money-format'),
L('generators-iterators', 'Iterators & generators: process streams, not lists', 7,
  <<<'CF_C'
<p>A <strong>generator</strong> (<code>yield</code>) produces items one at a time, lazily — the difference between reading a 10 GB log line-by-line and loading it into RAM until your laptop fan takes off. Pipeline generators exactly like shell pipes: filter → transform → consume.</p>
CF_C,
  <<<'CF_E'
def read_events():
    for line in ['INFO boot ok', 'ERROR disk full', 'INFO user login', 'ERROR db timeout']:
        yield line                    # lazy: one line at a time

def only_errors(lines):
    for ln in lines:
        if ln.startswith('ERROR'):
            yield ln

def extract_message(lines):
    for ln in lines:
        yield ln.split(' ', 1)[1]

for msg in extract_message(only_errors(read_events())):
    print(msg)
CF_E,
  <<<'CF_O'
disk full
db timeout
CF_O,
  <<<'CF_T'
# Write top_up(nums, k) — a generator yielding running balance after each
# top-up in nums. list(top_up([100, 50, -20], 500)) → [600, 650, 630]
CF_T,
  'log-level-filter'),
L('decorators', 'Decorators: wrap behavior, don’t copy it', 7,
  <<<'CF_C'
<p>A decorator is just a function returning a wrapped function — <code>@wrap</code> is sugar for <code>f = wrap(f)</code>. Use them for cross-cutting concerns: timing, auth checks, retries, caching. One written well beats fifty copy-pasted <code>try</code> blocks.</p>
CF_C,
  <<<'CF_E'
import functools, time

def timed(fn):
    @functools.wraps(fn)          # keeps __name__ intact — always do this
    def wrapper(*args, **kwargs):
        start = time.perf_counter()
        out = fn(*args, **kwargs)
        print(f'{fn.__name__} took {(time.perf_counter()-start)*1000:.1f}ms')
        return out
    return wrapper

@timed
def slow_report(rows):
    return sum(r * (i + 1) for i, r in enumerate(rows))

print('total:', slow_report([10, 20, 30]))
print('name preserved:', slow_report.__name__)
CF_E,
  <<<'CF_O'
slow_report took 0.0ms
total: 140
name preserved: slow_report
CF_O,
  '',
  ''),
L('regex-python', 'Regex in Python: re module essentials', 7,
  <<<'CF_C'
<p><code>re</code> gives you four verbs: <code>search</code> (find anywhere), <code>fullmatch</code> (validate shape), <code>findall</code> (extract all), <code>sub</code> (rewrite). Prefer raw strings <code>r'\d+'</code> and named groups <code>(?P&lt;user&gt;...)</code> — future-you will read this code at 2 AM.</p>
CF_C,
  <<<'CF_E'
import re

LOG = '2026-08-06 21:14:05 ERROR user=neha msg="db timeout" cost=1.24s'

m = re.search(r'user=(?P<user>\w+).*cost=(?P<cost>[\d.]+)s', LOG)
print(m['user'], float(m['cost']))

addr = 'ops@firm.in, ravi@shop.in'
emails = re.findall(r'[\w.]+@[\w.]+', addr)
print(emails)

print(re.sub(r'\d{4}-\d{2}-\d{2}', '<date>', LOG))   # redact the date
CF_E,
  <<<'CF_O'
neha 1.24
['ops@firm.in', 'ravi@shop.in']
<date> 21:14:05 ERROR user=neha msg="db timeout" cost=1.24s
CF_O,
  <<<'CF_T'
# Extract all order ids like ORD-1042 from a mixed string with findall.
s = 'paid ORD-1042, retry ORD-1088; ignore INV-9'
# expected: ['ORD-1042', 'ORD-1088']
CF_T,
  'csv-sum-column'),
],
],

'java' => ['lessons' => [
L('collections-deep-java', 'Collections deep dive: Map & Set mastery', 7,
  <<<'CF_C'
<p> interviews and codebases run on four collections: <code>ArrayList</code> (ordered bag), <code>HashMap</code> (key→value in O(1)), <code>HashSet</code> (uniqueness), <code>LinkedHashMap</code> (insertion-ordered map). Master <code>computeIfAbsent</code>, <code>merge</code> and <code>getOrDefault</code> — they replace whole loops.</p>
CF_C,
  <<<'CF_E'
import java.util.*;

Map<String, Integer> units = new HashMap<>();
for (String[] s : new String[][]{{"N","A"},{"N","B"},{"S","A"},{"N","A"}}) {
    units.merge(s[1], 1, Integer::sum);          // count per product
}
System.out.println(units);                        // {A=3, B=1}

Map<String, Set<String>> tags = new HashMap<>();
tags.computeIfAbsent("A", k -> new HashSet<>()).add("sale");
tags.computeIfAbsent("A", k -> new HashSet<>()).add("sale");
tags.computeIfAbsent("A", k -> new HashSet<>()).add("hot");
System.out.println(tags.get("A"));               // [sale, hot] — a Set keeps one
CF_E,
  <<<'CF_O'
{A=3, B=1}
[sale, hot]
CF_O,
  '',
  'group-anagrams'),
L('streams-java', 'Streams & lambdas: declarative Java', 7,
  <<<'CF_C'
<p>Streams turn loops into <strong>pipelines</strong>: <code>filter → map → reduce/collect</code>. You read the transformations, not the mechanics. Keep lambdas pure (no side effects), and reach for <code>Collectors.groupingBy</code> the moment you would write nested grouping loops.</p>
CF_C,
  <<<'CF_E'
import java.util.*;
import java.util.stream.*;

record Sale(String region, String product, int units) {}

var sales = List.of(
    new Sale("N", "A", 2), new Sale("N", "B", 3),
    new Sale("S", "A", 1), new Sale("N", "A", 4));

Map<String, Integer> byRegion = sales.stream()
    .collect(Collectors.groupingBy(Sale::region,
        Collectors.summingInt(Sale::units)));
System.out.println(byRegion);                        // {S=1, N=9}

List<String> hot = sales.stream()
    .filter(s -> s.units() >= 3).map(Sale::product)
    .distinct().sorted().toList();
System.out.println(hot);                             // [A, B]
CF_E,
  <<<'CF_O'
{S=1, N=9}
[A, B]
CF_O,
  '',
  'sales-summary'),
L('exceptions-java', 'Exceptions you design, not just throw', 7,
  <<<'CF_C'
<p>Checked exceptions (<code>throws</code>) are for recoverable, external problems (IO, parse). Unchecked (<code>RuntimeException</code>) for programmer bugs. Create small domain exceptions so a <code>catch</code> can be precise — and always attach the <em>context</em> (which order? which sku?) that turns a stack trace into an answer.</p>
CF_C,
  <<<'CF_E'
class OutOfStockException extends RuntimeException {
    OutOfStockException(String sku, int have, int want) {
        super("sku=" + sku + " have=" + have + " want=" + want);
    }
}

static int reserve(String sku, int stock, int qty) {
    if (qty > stock) throw new OutOfStockException(sku, stock, qty);
    return stock - qty;
}

public static void main(String[] a) {
    try {
        System.out.println(reserve("W-1", 5, 3));
        System.out.println(reserve("W-1", 2, 9));
    } catch (OutOfStockException e) {
        System.out.println("cannot fulfill: " + e.getMessage());
    }
}
CF_E,
  <<<'CF_O'
2
cannot fulfill: sku=W-1 have=2 want=9
CF_O,
  '',
  ''),
L('io-nio-java', 'Files & NIO.2 without the pain', 6,
  <<<'CF_C'
<p>Modern file code is <code>java.nio.file</code>: <code>Files.readAllLines</code>, <code>Files.write</code>, <code>Files.walk</code>. And <code>try-with-resources</code> closes streams even on exceptions — the try/finally dance is over since Java 7.</p>
CF_C,
  <<<'CF_E'
import java.nio.file.*;
import java.util.*;

// write then read a small CSV, count rows
Path f = Files.createTempFile("orders", ".csv");
Files.write(f, List.of("sku,qty", "W-1,5", "W-2,3"));

List<String> lines = Files.readAllLines(f);
int total = lines.stream().skip(1)
    .mapToInt(l -> Integer.parseInt(l.split(",")[1])).sum();
System.out.println("rows=" + (lines.size() - 1) + " qty=" + total);
Files.deleteIfExists(f);
CF_E,
  <<<'CF_O'
rows=2 qty=8
CF_O,
  '',
  'csv-sum-column'),
],
],

'c' => ['lessons' => [
L('pointers-arithmetic', 'Pointer arithmetic: arrays are sugar', 8,
  <<<'CF_C'
<p>In C, <code>a[i]</code> <em>is</em> <code>*(a + i)</code>. A pointer + n moves n <em>elements</em> (not bytes). Once that clicks, strings stop being mysterious: they are just <code>char*</code> walking until <code>'\0'</code>. Respect the rule: never dereference what you didn't allocate (and what you <code>free</code>d).</p>
CF_C,
  <<<'CF_E'
#include <stdio.h>

int main(void) {
    int prices[5] = {499, 199, 899, 50, 250};
    int *p = prices;

    int total = 0;
    for (int i = 0; i < 5; i++) total += *(p + i);   // same as p[i]
    printf("total=%d\n", total);

    char sku[] = "SKU-1042";
    char *c = sku;
    while (*c != '\0' && (*c < '0' || *c > '9')) c++;  // walk to first digit
    printf("digits part: %s\n", c);

    printf("last=%d\n", *(prices + 4));               // pointer walk to the end
    return 0;
}
CF_E,
  <<<'CF_O'
total=1897
digits part: 1042
last=250
CF_O,
  '',
  ''),
L('malloc-memory', 'malloc/free: owning memory like an adult', 8,
  <<<'CF_C'
<p>Heap memory has three laws: check the result, free exactly once, never touch after free. Break one and you get leaks, double-free crashes, or use-after-free — the bugs sanitisers exist to catch. A struct + one owning function for alloc &amp; free keeps it boring.</p>
CF_C,
  <<<'CF_E'
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

typedef struct { char sku[16]; int qty; } StockRow;

StockRow *load(size_t n) {
    StockRow *rows = malloc(n * sizeof *rows);    // sizeof the POINTED type
    if (!rows) { fprintf(stderr, "oom\n"); exit(1); }
    strcpy(rows[0].sku, "W-1"); rows[0].qty = 5;
    strcpy(rows[1].sku, "W-2"); rows[1].qty = 3;
    return rows;
}

int main(void) {
    StockRow *rows = load(2);
    int total = 0;
    for (int i = 0; i < 2; i++) total += rows[i].qty;
    printf("qty=%d\n", total);
    free(rows);                                    // exactly once
    rows = NULL;                                   // habit: kill the dangling pointer
    return 0;
}
CF_E,
  <<<'CF_O'
qty=8
CF_O,
  '',
  ''),
L('structs-typedef', 'structs, unions & typedef: modeling data in C', 7,
  <<<'CF_C'
<p>A <code>struct</code> bundles fields; a <code>union</code> overlays them in the same bytes (size = largest member); <code>typedef</code> gives short names. Tagged unions (struct with a kind field + union payload) are how C says "this value is one of several shapes".</p>
CF_C,
  <<<'CF_E'
#include <stdio.h>

typedef struct {
    int kind;                  /* 1 = money, 2 = percent */
    union { double money; int percent; } v;
} Discount;

double apply(double price, Discount d) {
    if (d.kind == 1) return price - d.v.money;
    return price * (100 - d.v.percent) / 100.0;
}

int main(void) {
    Discount flat = { .kind = 1, .v.money = 100 };
    Discount pct  = { .kind = 2, .v.percent = 10 };
    printf("%.2f\n", apply(1500.0, flat));
    printf("%.2f\n", apply(1500.0, pct));
    return 0;
}
CF_E,
  <<<'CF_O'
1400.00
1350.00
CF_O,
  '',
  'coupon-discount'),
L('file-io-c', 'Binary & text file I/O in C', 7,
  <<<'CF_C'
<p><code>fopen/fgets/fscanf/fwrite</code> + a fixed record struct cover most real C file work. The discipline: check every <code>fopen</code>, prefer one record struct for binary (<code>fread</code>/<code>fwrite</code> whole structs), and <code>fclose</code> on every exit path.</p>
CF_C,
  <<<'CF_E'
#include <stdio.h>

int main(void) {
    FILE *f = fopen("/tmp/orders.csv", "w");
    if (!f) return 1;
    fputs("sku,qty\nW-1,5\nW-2,3\n", f);
    fclose(f);

    f = fopen("/tmp/orders.csv", "r");
    if (!f) return 1;
    char line[64];
    int rows = 0, qty = 0;
    while (fgets(line, sizeof line, f)) {
        char sku[16]; int q;
        if (sscanf(line, "%15[^,],%d", sku, &q) == 2) { rows++; qty += q; }
    }
    fclose(f);
    printf("rows=%d qty=%d\n", rows, qty);
    return 0;
}
CF_E,
  <<<'CF_O'
rows=2 qty=8
CF_O,
  '',
  'csv-sum-column'),
],
],

'cpp' => ['lessons' => [
L('raii-cpp', 'RAII: C++’s superpower', 8,
  <<<'CF_C'
<p><strong>RAII</strong> = resource lifetime tied to object lifetime: acquire in the constructor, release in the destructor. Destructors run on <em>every</em> exit path — return, break, exception — so files close, locks unlock and memory frees itself. This is why modern C++ has no <code>delete</code> litter.</p>
CF_C,
  <<<'CF_E'
#include <iostream>
#include <fstream>
#include <string>

class LogFile {                       // wraps the resource
    std::ofstream f;
public:
    explicit LogFile(const std::string& path) : f(path) {
        if (!f) throw std::runtime_error("cannot open");
    }
    void write(const std::string& s) { f << s << '\n'; }
    ~LogFile() { std::cout << "log closed (RAII)\n"; }   // always runs
};

int main() {
    {
        LogFile log("/tmp/run.log");
        log.write("ORDER OK ORD-1042");
        log.write("ORDER OK ORD-1043");
    }   // ← destructor here, file flushed & closed
    std::ifstream in("/tmp/run.log"); std::string l; int n = 0;
    while (std::getline(in, l)) n++;
    std::cout << "lines=" << n << "\n";
}
CF_E,
  <<<'CF_O'
log closed (RAII)
lines=2
CF_O,
  '',
  ''),
L('stl-algorithms', 'STL algorithms: loops you never write again', 7,
  <<<'CF_C'
<p><code>std::sort</code>, <code>count_if</code>, <code>transform</code>, <code>accumulate</code>, <code>find_if</code> cover 90% of hand loops — and they can't off-by-one. Pass a lambda for the custom bit, let the library do the walking.</p>
CF_C,
  <<<'CF_E'
#include <algorithm>
#include <iostream>
#include <numeric>
#include <string>
#include <vector>

int main() {
    std::vector<int> units = {2, 3, 1, 4, 2};
    std::cout << "total=" << std::accumulate(units.begin(), units.end(), 0) << "\n";
    std::cout << "big="   << std::count_if(units.begin(), units.end(),
                               [](int u){ return u >= 3; }) << "\n";
    std::sort(units.begin(), units.end(), std::greater<int>());
    for (int u : units) std::cout << u << ' ';
    std::cout << "\n";

    std::vector<std::string> skus = {"W-1", "W-2"};
    auto it = std::find_if(skus.begin(), skus.end(),
                           [](const std::string& s){ return s == "W-2"; });
    std::cout << (it != skus.end() ? *it : "missing") << "\n";
}
CF_E,
  <<<'CF_O'
total=12
big=2
4 3 2 2 1
W-2
CF_O,
  '',
  'top-k-frequent'),
L('smart-pointers', 'Smart pointers: ownership you can read', 7,
  <<<'CF_C'
<p>One owner? <code>unique_ptr</code> (moves only, frees on scope exit). Shared graph? <code>shared_ptr</code> (refcounted; watch cycles). Non-owning observer? <code>weak_ptr</code>/raw. Rule of thumb for 2026 C++: new code should contain zero raw <code>new</code>.</p>
CF_C,
  <<<'CF_E'
#include <iostream>
#include <memory>
#include <string>
#include <vector>

struct StockRow { std::string sku; int qty; };

int main() {
    auto row = std::make_unique<StockRow>(StockRow{"W-1", 5});
    row->qty += 2;
    std::cout << row->sku << " qty=" << row->qty << "\n";

    auto shared = std::make_shared<int>(42);
    {
        auto ref = shared;                      // both own it
        std::cout << "refs=" << shared.use_count() << "\n";
    }
    std::cout << "refs=" << shared.use_count() << "\n";   // back to 1, alive
}   // unique_ptr & shared_ptr free themselves here. no delete. ever.
CF_E,
  <<<'CF_O'
W-1 qty=7
refs=2
refs=1
CF_O,
  '',
  ''),
L('templates-cpp', 'Templates: generic C++ without the fear', 7,
  <<<'CF_C'
<p>Function/class templates generate code per type at compile time — zero-cost generics. Write one <code>sumOf</code> for ints and doubles; the compiler instantiates both and type-checks usage at the call site.</p>
CF_C,
  <<<'CF_E'
#include <iostream>
#include <string>
#include <vector>

template <typename T>
T sumOf(const std::vector<T>& v) {
    T total{};
    for (const T& x : v) total += x;
    return total;
}
template <typename T>
T biggest(const std::vector<T>& v) {
    T b = v[0];
    for (const T& x : v) if (x > b) b = x;
    return b;
}

int main() {
    std::cout << sumOf(std::vector<int>{1, 2, 3, 4}) << "\n";
    std::cout << sumOf(std::vector<double>{1.5, 2.25}) << "\n";
    std::cout << biggest(std::vector<std::string>{"apple", "mango", "banana"}) << "\n";
}
CF_E,
  <<<'CF_O'
10
3.75
mango
CF_O,
  '',
  ''),
],
],

'csharp' => ['lessons' => [
L('records-structs', 'Records, structs & enums: modeling in C#', 7,
  <<<'CF_C'
<p>Choose by semantics: <code>class</code> = identity + behavior, <code>record</code> = value data with free equality &amp; <code>with</code>-copies, <code>struct</code> = small value type, <code>enum</code> = closed set of states. The right choice makes invalid states unrepresentable.</p>
CF_C,
  <<<'CF_E'
enum OrderStatus { Pending, Paid, Shipped }
record Money(decimal Amount, string Currency = "INR") {
    public static Money operator +(Money a, Money b) =>
        a.Currency == b.Currency ? new(a.Amount + b.Amount, a.Currency)
                                 : throw new InvalidOperationException("currency mismatch");
}

var price = new Money(499m);
var total = price + new Money(1m);
Console.WriteLine(total);                    // Money { Amount = 500.00, Currency = INR }

var o1 = ( Id: "O-1", Status: OrderStatus.Paid );
var o2 = o1 with { Status = OrderStatus.Shipped };   // non-destructive update
Console.WriteLine($"{o1.Status} → {o2.Status}");
CF_E,
  <<<'CF_O'
Money { Amount = 500.00, Currency = INR }
Paid → Shipped
CF_O,
  '',
  ''),
L('async-csharp', 'async/await: tasks without thread math', 7,
  <<<'CF_C'
<p><code>async</code> methods return <code>Task</code>; <code>await</code> pauses without blocking a thread. Use it for I/O (HTTP, DB, files), never for CPU math. <code>Task.WhenAll</code> runs IO in parallel and awaits the lot — the difference between 3 seconds and 9.</p>
CF_C,
  <<<'CF_E'
using System.Net.Http;

static async Task<int> QuoteAsync(string product, int ms) {
    await Task.Delay(ms);                 // pretend vendor API
    return product.Length * ms;           // deterministic "price"
}

var sw = System.Diagnostics.Stopwatch.StartNew();
var a = QuoteAsync("widget", 120);
var b = QuoteAsync("gadget", 120);
var prices = await Task.WhenAll(a, b);    // ~120ms total, not 240
Console.WriteLine($"prices={prices[0]},{prices[1]} elapsed≈{sw.ElapsedMilliseconds/40*40}ms");
CF_E,
  <<<'CF_O'
prices=840,720 elapsed≈120ms
CF_O,
  '',
  ''),
L('events-delegates', 'Delegates & events: decoupled notifications', 7,
  <<<'CF_C'
<p>A <code>delegate</code> is a type-safe function pointer; an <code>event</code> is a delegate with subscriber semantics. This is how UI buttons, message buses and domain events stay decoupled: the order service doesn't know who listens.</p>
CF_C,
  <<<'CF_E'
class OrderService {
    public event Action<string>? OrderPlaced;
    public void Place(string id) {
        Console.WriteLine($"placing {id}");
        OrderPlaced?.Invoke(id);          // notify subscribers, null-safe
    }
}

var svc = new OrderService();
svc.OrderPlaced += id => Console.WriteLine($"  email team heard {id}");
svc.OrderPlaced += id => Console.WriteLine($"  warehouse heard {id}");
svc.Place("ORD-1042");
CF_E,
  <<<'CF_O'
placing ORD-1042
  email team heard ORD-1042
  warehouse heard ORD-1042
CF_O,
  '',
  ''),
L('linq-advanced', 'LINQ beyond the basics', 7,
  <<<'CF_C'
<p><code>GroupBy</code>, <code>Join</code>, <code>Aggregate</code>, <code>SelectMany</code> turn day-long reporting loops into five readable lines. Watch deferred execution: nothing runs until you enumerate — <code>ToList()</code> when you need it twice.</p>
CF_C,
  <<<'CF_E'
var sales = new[] {
    new { Region = "N", Product = "A", Units = 2 },
    new { Region = "N", Product = "B", Units = 3 },
    new { Region = "S", Product = "A", Units = 1 },
    new { Region = "N", Product = "A", Units = 4 },
};

var report = sales
    .GroupBy(s => s.Region)
    .Select(g => $"{g.Key}: {g.Sum(x => x.Units)} units, top {g.GroupBy(x=>x.Product).OrderByDescending(p=>p.Sum(x=>x.Units)).First().Key}");
foreach (var line in report.OrderBy(x => x)) Console.WriteLine(line);

var allProducts = sales.SelectMany(s => new[] { s.Product }).Distinct().OrderBy(x => x);
Console.WriteLine(string.Join(",", allProducts));
CF_E,
  <<<'CF_O'
N: 9 units, top A
S: 1 units, top A
A,B
CF_O,
  '',
  'sales-summary'),
],
],

'go' => ['lessons' => [
L('interfaces-go', 'Interfaces in Go: satisfaction is implicit', 8,
  <<<'CF_C'
<p> Go interfaces are <strong>implicit</strong>: a type satisfies one just by having the methods — no <code>implements</code>. Keep them small (<code>io.Reader</code> has ONE method) and accept interfaces, return structs. That single habit is why Go code composes so well.</p>
CF_C,
  <<<'CF_E'
package main

import "fmt"

type Formatter interface{ Format(map[string]int) string }

type CSVFormatter struct{}
func (CSVFormatter) Format(m map[string]int) string {
    out := ""
    for k, v := range m { out += k + "," + fmt.Sprint(v) + "\n" }
    return out
}
type JSONFormatter struct{}
func (JSONFormatter) Format(m map[string]int) string {
    out := "{"
    first := true
    for k, v := range m { if !first { out += "," }; out += `"` + k + `":` + fmt.Sprint(v); first = false }
    return out + "}"
}

func printReport(f Formatter, data map[string]int) { fmt.Print(f.Format(data)) }

func main() {
    data := map[string]int{"W-1": 5}
    printReport(CSVFormatter{}, data)
    printReport(JSONFormatter{}, data)
}
CF_E,
  <<<'CF_O'
W-1,5
{"W-1":5}
CF_O,
  '',
  ''),
L('goroutines-channels', 'Goroutines & channels, gently', 8,
  <<<'CF_C'
<p>A <code>go f()</code> call starts a lightweight thread; channels move values between them safely. The mantra: <em>don't communicate by sharing memory; share memory by communicating</em>. Worker pool + <code>sync.WaitGroup</code> covers most concurrency you will write.</p>
CF_C,
  <<<'CF_E'
package main

import ("fmt"; "sync")

func worker(id int, jobs <-chan int, results chan<- int, wg *sync.WaitGroup) {
    defer wg.Done()
    for j := range jobs {
        results <- j * j            // square each order amount
    }
}

func main() {
    jobs := make(chan int, 3)
    results := make(chan int, 3)
    var wg sync.WaitGroup
    for w := 0; w < 2; w++ { wg.Add(1); go worker(w, jobs, results, &wg) }
    go func() { for _, j := range []int{2, 3, 4} { jobs <- j }; close(jobs) }()
    go func() { wg.Wait(); close(results) }()
    total := 0
    for r := range results { total += r }
    fmt.Println("sum of squares:", total)
}
CF_E,
  <<<'CF_O'
sum of squares: 29
CF_O,
  '',
  ''),
L('testing-go', 'Testing in Go: testing package culture', 6,
  <<<'CF_C'
<p> Tests live in <code>foo_test.go</code> as <code>TestXxx(t *testing.T)</code>; table-driven tests are the house style — one slice of cases, one loop. <code>go test ./...</code> runs everything; subtests via <code>t.Run</code> name each case in failures.</p>
CF_C,
  <<<'CF_E'
package main

import ("fmt"; "testing")

func Discount(sub float64, pct int) float64 { return sub * float64(100-pct) / 100 }

func TestDiscount(t *testing.T) {
    cases := []struct{ sub float64; pct int; want float64 }{
        {1500, 10, 1350}, {1000, 0, 1000}, {999.99, 5, 949.9905},
    }
    for _, c := range cases {
        t.Run(fmt.Sprintf("%v@%d", c.sub, c.pct), func(t *testing.T) {
            if got := Discount(c.sub, c.pct); got != c.want {
                t.Errorf("got %v want %v", got, c.want)
            }
        })
    }
}

func main() { fmt.Println("run: go test -v") }
CF_E,
  <<<'CF_O'
run: go test -v
CF_O,
  '',
  ''),
L('http-json-go', 'net/http & encoding/json: a tiny API in 30 lines', 7,
  <<<'CF_C'
<p>The standard library is the framework: <code>http.HandleFunc</code> routes, <code>json.NewEncoder</code> writes responses, <code>json.NewDecoder</code> reads bodies. Set headers <em>before</em> <code>WriteHeader</code>, and decode into typed structs.</p>
CF_C,
  <<<'CF_E'
package main

import ("encoding/json"; "fmt"; "net/http"; "net/http/httptest")

type Quote struct {
    Sku string  `json:"sku"`
    Price float64 `json:"price"`
}

func quoteHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(Quote{Sku: r.URL.Query().Get("sku"), Price: 499})
}

func main() {
    srv := httptest.NewServer(http.HandlerFunc(quoteHandler))
    defer srv.Close()
    resp, _ := http.Get(srv.URL + "?sku=W-1")
    var q Quote
    json.NewDecoder(resp.Body).Decode(&q)
    fmt.Printf("%s @ %.0f (status %d)\n", q.Sku, q.Price, resp.StatusCode)
}
CF_E,
  <<<'CF_O'
W-1 @ 499 (status 200)
CF_O,
  '',
  'http-status-label'),
],
],

'ruby' => ['lessons' => [
L('modules-mixins', 'Modules & mixins: Ruby’s other inheritance', 7,
  <<<'CF_C'
<p>A module is a bundle of methods; <code>include</code> mixes them into instances, <code>extend</code> into the class itself. Cross-cutting powers (comparisons, formatting, logging) become one included line instead of copy-paste — see <code>Comparable</code> below.</p>
CF_C,
  <<<'CF_E'
class Money
  include Comparable            # gives <, >, ==, between? from one <=>
  attr_reader :paise
  def initialize(rupees) @paise = (rupees * 100).round end
  def <=>(other) paise <=> other.paise end
  def to_s = "₹#{format('%.2f', @paise / 100.0)}"
end

prices = [Money.new(499), Money.new(50), Money.new(199)]
puts prices.min                 # ₹50.00
puts prices.max                 # ₹499.00
puts (Money.new(100)..Money.new(300)).cover?(Money.new(199))   # true
CF_E,
  <<<'CF_O'
₹50.00
₹499.00
true
CF_O,
  <<<'CF_T'
# Make a Score class including Comparable on `points`; check
# Score.new(10) > Score.new(9) prints true
CF_T,
  'money-format'),
L('enumerable-power', 'Enumerable: Ruby’s query language', 7,
  <<<'CF_C'
<p><code>map select reject reduce group_by sort_by each_with_object tally</code> — internal iteration is Ruby's signature. If you write <code>for</code> in Ruby, pause: there is an Enumerable method that says it better.</p>
CF_C,
  <<<'CF_E'
sales = [
  { region: 'N', product: 'A', units: 2 },
  { region: 'N', product: 'B', units: 3 },
  { region: 'S', product: 'A', units: 1 },
  { region: 'N', product: 'A', units: 4 },
]

by_region = sales.group_by { |s| s[:region] }
                 .transform_values { |rows| rows.sum { |r| r[:units] } }
puts by_region                                   # {"N"=>9, "S"=>1}

hot = sales.select { |s| s[:units] >= 3 }.map { |s| s[:product] }.uniq.sort
puts hot.inspect                                 # ["A", "B"]

puts sales.tally { |s| s[:product] }.inspect     # {"A"=>3, "B"=>1}
CF_E,
  <<<'CF_O'
{"N"=>9, "S"=>1}
["A", "B"]
{"A"=>3, "B"=>1}
CF_O,
  '',
  'top-k-frequent'),
L('metaprogramming-lite', 'Metaprogramming, responsibly', 7,
  <<<'CF_C'
<p><code>attr_accessor</code>, <code>define_method</code>, and <code>method_missing</code> let code write code. Used sparingly they remove noisy boilerplate (define 10 currency getters in one loop); used wildly they make grep useless. Rule: metaprogram where structure repeats, never where logic differs.</p>
CF_C,
  <<<'CF_E'
class Product
  ATTRS = %i[sku name price stock]
  attr_accessor(*ATTRS)                     # 8 methods, one line

  def initialize(h) ATTRS.each { |a| send("#{a}=", h[a]) } end
  def to_h = ATTRS.to_h { |a| [a, send(a)] }
end

p1 = Product.new(sku: 'W-1', name: 'Widget', price: 499, stock: 12)
puts p1.sku                                  # W-1
puts p1.to_h.inspect

# define_method for derived views:
%i[price_with_gst price_with_discount].each do |m|
  Product.define_method(m) do
    m == :price_with_gst ? price * 1.18 : price * 0.9
  end
end
puts p1.price_with_gst.round(2)              # 588.82
CF_E,
  <<<'CF_O'
W-1
{:sku=>"W-1", :name=>"Widget", :price=>499, :stock=>12}
588.82
CF_O,
  '',
  ''),
L('blocks-files', 'Files, blocks & resource safety', 6,
  <<<'CF_C'
<p>Ruby's block form of <code>File.open</code> auto-closes even on exceptions — the idiomatic sibling of Python's <code>with</code>. <code>CSV</code> module handles quoting correctly; never split CSV by comma yourself.</p>
CF_C,
  <<<'CF_E'
require 'csv'

File.write('/tmp/orders.csv', "sku,qty\nW-1,5\nW-2,3\n")

rows = CSV.read('/tmp/orders.csv', headers: true)
total = rows.sum { |r| r['qty'].to_i }
puts "rows=#{rows.size} qty=#{total}"

File.open('/tmp/out.txt', 'w') do |f|       # closes itself at `end`
  total.times { |i| f.puts "line #{i + 1}" }
end
puts File.readlines('/tmp/out.txt').size     # 8
CF_E,
  <<<'CF_O'
rows=2 qty=8
8
CF_O,
  '',
  'csv-sum-column'),
],
],

'php' => ['lessons' => [
L('oop-typed-php', 'Typed OOP: modern PHP classes', 7,
  <<<'CF_C'
<p>PHP 8 classes are fully typed: constructor <strong>promotion</strong>, <code>readonly</code>, union types, <code>match</code>. An anemic-but-typed model beats a clever untyped one — the engine rejects the wrong shape before your code even runs.</p>
CF_C,
  <<<'CF_E'
final class Money {
    public function __construct(
        public readonly float $amount,
        public readonly string $currency = 'INR',
    ) {}
    public function add(Money $m): Money {
        if ($m->currency !== $this->currency) throw new DomainException('mismatch');
        return new Money($this->amount + $m->amount, $this->currency);
    }
    public function __toString(): string {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }
}

$price = new Money(499);
$total = $price->add(new Money(1));
echo $total, "\n";                       // INR 500.00
echo get_class($total), "\n";
CF_E,
  <<<'CF_O'
INR 500.00
Money
CF_O,
  '',
  'money-format'),
L('autoload-structure', 'Autoloading & project structure (no framework needed)', 7,
  <<<'CF_C'
<p><code>spl_autoload_register</code> maps <code>App\Money</code> → <code>src/Money.php</code>; one convention kills a hundred requires. Add a tiny <code>src/</code> + PSR-4-ish rule and your vanilla project scales like a framework's.</p>
CF_C,
  <<<'CF_E'
spl_autoload_register(function (string $class): void {
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) require $path;
});

// Imagine src/App/Money.php exists (namespace App):
// $m = new App\Money(499);   // file loaded on first use, on demand

// Same pattern powers this very platform's lib/: require paths derived
// from names, zero manual bookkeeping:
$map = ['App\\Money' => 'src/App/Money.php', 'App\\Cart' => 'src/App/Cart.php'];
foreach ($map as $class => $file) echo $class, ' → ', $file, "\n";
CF_E,
  <<<'CF_O'
App\Money → src/App/Money.php
App\Cart → src/App/Cart.php
CF_O,
  '',
  ''),
L('forms-superglobals-php', 'Forms & superglobals, safely', 7,
  <<<'CF_C'
<p><code>$_GET/$_POST/$_SERVER</code> are user input — treat every byte as hostile: read with <code>filter_input</code>/casting, re-emit with <code>htmlspecialchars</code>, and never trust <code>$_FILES</code> extensions. One rule above all: output escaping is non-negotiable.</p>
CF_C,
  <<<'CF_E'
// Simulated POST (in a real page these come from the browser)
$_POST = ['qty' => '5', 'note' => '<b>fragile</b>'];

// 1. read defensively — cast + bounds
$qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => 99]]) ?? 1;

// 2. echo with escaping — always
$note = $_POST['note'] ?? '';
echo 'qty=', $qty, "\n";
echo 'note raw: ', $note, "\n";
echo 'note safe: ', htmlspecialchars($note, ENT_QUOTES, 'UTF-8'), "\n";
CF_E,
  <<<'CF_O'
qty=5
note raw: <b>fragile</b>
note safe: &lt;b&gt;fragile&lt;/b&gt;
CF_O,
  '',
  ''),
L('pdo-deep-php', 'PDO beyond the basics', 7,
  <<<'CF_C'
<p>Four habits make PDO production-grade: <code>ERRMODE_EXCEPTION</code> (failures become exceptions, not false), prepare every statement, fetch as you iterate for big results, and use <strong>transactions</strong> when two writes must succeed or both fail.</p>
CF_C,
  <<<'CF_E'
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE stock (sku TEXT PRIMARY KEY, qty INT NOT NULL)');
$pdo->exec("INSERT INTO stock VALUES ('W-1', 5), ('W-2', 3)");

// transaction: two writes, all-or-nothing
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE stock SET qty = qty - 1 WHERE sku = ?')->execute(['W-1']);
    $pdo->prepare('UPDATE stock SET qty = qty + 1 WHERE sku = ?')->execute(['W-2']);
    $pdo->commit();
} catch (Throwable $e) { $pdo->rollBack(); throw $e; }

foreach ($pdo->query('SELECT * FROM stock ORDER BY sku') as $row) {
    echo $row['sku'], ': ', $row['qty'], "\n";
}
CF_E,
  <<<'CF_O'
W-1: 4
W-2: 4
CF_O,
  '',
  'restock-alerts'),
],
],

'kotlin' => ['lessons' => [
L('scope-functions', 'Scope functions: let/run/also/apply/with', 7,
  <<<'CF_C'
<p>Kotlin's five scope functions remove temp-variable noise: <code>let</code> transforms, <code>apply</code> configures (returns receiver), <code>also</code> side-effects, <code>run</code>/<code>with</code> compute. The check: does it read better with the scope function than without? If yes, use it; if you need a comment to explain it, don't.</p>
CF_C,
  <<<'CF_E'
data class Order(var id: String, var total: Double, var status: String)

val o = Order("O-1", 0.0, "draft").apply {   // configure in-place, returns o
    total = 699.0
    status = "paid"
}
val label = o.let { "${it.id} → ₹${it.total} [${it.status}]" }   // transform
println(label)

println(mutableListOf(2, 3, 1).also { it.sort() })        // side-effect then keep value

val invoice = with(StringBuilder()) {                      // multi-call builder
    append("INV-9\n"); append("total 699\n"); toString()
}
print(invoice)
CF_E,
  <<<'CF_O'
O-1 → ₹699.0 [paid]
[1, 2, 3]
INV-9
total 699
CF_O,
  '',
  ''),
L('coroutines-intro', 'Coroutines: async without callbacks', 8,
  <<<'CF_C'
<p>A coroutine is a suspendable computation; <code>launch</code> starts one, <code>delay</code> yields without blocking threads, <code>runBlocking</code> bridges sync worlds. Structured concurrency means a cancelled parent cancels its children — no orphan threads leaking work.</p>
CF_C,
  <<<'CF_E'
import kotlinx.coroutines.*

fun main() = runBlocking {
    val t0 = System.currentTimeMillis()
    val quotes = (1..3).map { sku ->
        async {                       // three vendor replies in parallel
            delay(100)
            "W-$sku: ${100 * sku}"
        }
    }.awaitAll()                       // ~100ms total, not 300
    quotes.forEach(::println)
    println("elapsed≈${(System.currentTimeMillis() - t0) / 50 * 50}ms")
}
CF_E,
  <<<'CF_O'
W-1: 100
W-2: 200
W-3: 300
elapsed≈100ms
CF_O,
  '',
  ''),
L('sealed-generics', 'Sealed classes & generics: exhaustive by design', 7,
  <<<'CF_C'
<p>A <code>sealed class</code> closes the hierarchy — <code>when</code> becomes exhaustive with no <code>else</code>, and adding a variant breaks every unhandled site at compile time. This + generics is Kotlin's answer to 90% of runtime type errors.</p>
CF_C,
  <<<'CF_E'
sealed class Result<out T> {
    data class Ok<T>(val value: T) : Result<T>()
    data class Err(val message: String) : Result<Nothing>()
}

fun divide(a: Int, b: Int): Result<Double> =
    if (b == 0) Result.Err("division by zero") else Result.Ok(a.toDouble() / b)

fun show(r: Result<Double>): String = when (r) {   // no else needed — compiler proves it
    is Result.Ok -> "ok=${r.value}"
    is Result.Err -> "err=${r.message}"
}

fun main() {
    println(show(divide(10, 4)))
    println(show(divide(10, 0)))
}
CF_E,
  <<<'CF_O'
ok=2.5
err=division by zero
CF_O,
  '',
  ''),
L('collections-fp-kotlin', 'Collections the Kotlin way', 6,
  <<<'CF_C'
<p><code>filter map groupBy associate sumOf sortedByDescending chunked</code> — read a pipeline top-to-bottom like a sentence. Prefer immutable <code>listOf</code>; create new lists with transforms instead of mutating shared state.</p>
CF_C,
  <<<'CF_E'
data class Sale(val region: String, val product: String, val units: Int)

fun main() {
    val sales = listOf(Sale("N","A",2), Sale("N","B",3), Sale("S","A",1), Sale("N","A",4))

    val byRegion = sales.groupBy { it.region }
        .mapValues { (_, rows) -> rows.sumOf { it.units } }
    println(byRegion)                                 // {N=9, S=1}

    val top = sales.groupingBy { it.product }.fold(0) { acc, s -> acc + s.units }
        .toList().sortedByDescending { it.second }.first()
    println("top product: ${top.first} (${top.second})")
}
CF_E,
  <<<'CF_O'
{N=9, S=1}
top product: A (6)
CF_O,
  '',
  'sales-summary'),
],
],

'rust' => ['lessons' => [
L('lifetimes', 'Lifetimes: the 20% that explains the 80%', 8,
  <<<'CF_C'
<p>A lifetime annotation just tells the compiler "this reference lives at least as long as that one". You rarely write them (elision covers function calls) but when you must, ask: which input does the returned reference borrow from? Name that one.</p>
CF_C,
  <<<'CF_E'
fn longest<'a>(x: &'a str, y: &'a str) -> &'a str {
    if x.len() >= y.len() { x } else { y }   // returns one of the INPUTS → same lifetime
}
fn first_word(s: &str) -> &str {              // elided lifetime: borrows from s
    s.split_whitespace().next().unwrap_or("")
}

fn main() {
    let a = String::from("ORD-1042");
    let out;
    {
        let b = String::from("sku");
        out = longest(&a, &b);
        println!("longest inside: {out}");
    }   // if b died here while `out` borrowed it → compile error. Rust stops you.
    let sku = String::from("W-42 Widget");
    println!("first: {}", first_word(&sku));
}
CF_E,
  <<<'CF_O'
longest inside: ORD-1042
first: W-42
CF_O,
  '',
  ''),
L('traits-generics-rs', 'Traits & generics in Rust', 8,
  <<<'CF_C'
<p>Traits are interfaces with superpowers: implementations can live in the type's crate <em>or</em> the trait's crate (orphan rule). Generic bounds like <code>T: PartialOrd + Copy</code> ask for exactly the capabilities you use — compile-time duck typing.</p>
CF_C,
  <<<'CF_E'
use std::fmt::Display;

#[derive(Debug)]
struct Sku(String);
impl Display for Sku {
    fn fmt(&self, f: &mut std::fmt::Formatter) -> std::fmt::Result { write!(f, "[{}]", self.0) }
}

fn biggest<T: PartialOrd + Copy>(v: &[T]) -> T {
    let mut b = v[0];
    for &x in v { if x > b { b = x } }
    b
}

fn main() {
    let s = Sku("W-1".into());
    println!("display: {s}, debug: {s:?}");
    println!("biggest: {}", biggest(&[3, 9, 4]));
    println!("biggest: {}", biggest(&[1.5, 2.75, 0.25]));
}
CF_E,
  <<<'CF_O'
display: [W-1], debug: Sku("W-1")
biggest: 9
biggest: 2.75
CF_O,
  '',
  ''),
L('iterators-rust', 'Iterators & adapters: zero-cost pipelines', 7,
  <<<'CF_C'
<p>Rust pipelines (<code>filter/map/collect</code>) compile to the same code as the hand loop — expressiveness without runtime cost. <code>sum()</code>, <code>fold</code>, <code>collect::&lt;HashMap&gt;()</code> are your reporting toolkit; the compiler enforces that closures only borrow safely.</p>
CF_C,
  <<<'CF_E'
use std::collections::HashMap;

fn main() {
    let sales = [("N", "A", 2), ("N", "B", 3), ("S", "A", 1), ("N", "A", 4)];

    let by_region: HashMap<_, i32> = sales.iter().fold(HashMap::new(), |mut m, (r, _, u)| {
        *m.entry(r).or_insert(0) += u; m
    });
    println!("{by_region:?}");                       // {"N": 9, "S": 1}

    let units: Vec<_> = sales.iter().filter(|(_, _, u)| *u >= 3).map(|(_, p, u)| (p, u)).collect();
    println!("{units:?}");                           // [("B", 3), ("A", 4)]

    let total: i32 = sales.iter().map(|(_, _, u)| u).sum();
    println!("total={total}");
}
CF_E,
  <<<'CF_O'
{"S": 1, "N": 9}
[("B", 3), ("A", 4)]
total=10
CF_O,
  '',
  'sales-summary'),
L('result-option-rs', 'Result & Option: errors as values', 8,
  <<<'CF_C'
<p>Rust has no null and (mostly) no exceptions: fallible functions return <code>Result&lt;T, E&gt;</code>, absent values are <code>Option&lt;T&gt;</code>. The <code>?</code> operator propagates errors in one character, and <code>unwrap_or/map_or</code> handle absence explicitly — crashes become compile-time obligations.</p>
CF_C,
  <<<'CF_E'
fn parse_qty(s: &str) -> Result<i32, std::num::ParseIntError> { s.trim().parse() }

fn total_qty(lines: &[&str]) -> Result<i32, std::num::ParseIntError> {
    let mut total = 0;
    for ln in lines {
        let qty = ln.rsplit(',').next().unwrap_or("0");
        total += parse_qty(qty)?;        // first bad row aborts with context
    }
    Ok(total)
}

fn main() {
    println!("{:?}", total_qty(&["W-1,5", "W-2,3"]));  // Ok(8)
    let bad = total_qty(&["W-1,,", "W-2,3"]);
    println!("is_err: {}", bad.is_err());

    let name: Option<&str> = Some("Asha");
    println!("user: {}", name.map_or("guest", |n| n));
    let missing: Option<&str> = None;
    println!("user: {}", missing.unwrap_or("guest"));
}
CF_E,
  <<<'CF_O'
Ok(8)
is_err: true
user: Asha
user: guest
CF_O,
  '',
  ''),
],
],

'sql' => ['lessons' => [
L('distinct-group-having', 'GROUP BY, HAVING & DISTINCT — analytics foundation', 7,
  <<<'CF_C'
<p><code>GROUP BY</code> collapses rows into buckets; <code>HAVING</code> filters the <em>buckets</em> (WHERE filters rows before grouping — the classic confusion). <code>COUNT(DISTINCT …)</code> counts unique values only. Master rule: every selected non-aggregate column must be in GROUP BY.</p>
CF_C,
  <<<'CF_E'
CREATE TABLE sales(region TEXT, product TEXT, units INT);
INSERT INTO sales VALUES
 ('N','A',2),('N','B',3),('S','A',1),('N','A',4),('S','B',6);

SELECT region, SUM(units) AS total
FROM sales
GROUP BY region
HAVING SUM(units) > 5
ORDER BY total DESC;

SELECT COUNT(*) rows_ct, COUNT(DISTINCT product) products_ct FROM sales;
CF_E,
  <<<'CF_O'
N|9
S|7
5|2
CF_O,
  '',
  'sales-summary'),
L('nulls-three-valued', 'NULL & three-valued logic: the silent bug source', 7,
  <<<'CF_C'
<p><code>NULL</code> is not a value — it's the absence of one. <code>NULL = NULL</code> is UNKNOWN, and <code>NOT IN (list with NULL)</code> returns zero rows (a production-outage classic). Use <code>IS NULL</code>, <code>COALESCE</code> for defaults, and handle NULL before it infects your joins.</p>
CF_C,
  <<<'CF_E'
CREATE TABLE customers(name TEXT, referrer TEXT);
INSERT INTO customers VALUES ('Asha', 'Bela'), ('Ravi', NULL), ('Neha', 'Bela');

-- wrong: referrer = NULL never matches. right: IS NULL
SELECT COUNT(*) AS organic FROM customers WHERE referrer IS NULL;

SELECT name, COALESCE(referrer, '(direct)') AS came_via FROM customers;

-- the NOT IN trap:
SELECT name FROM customers WHERE name NOT IN (SELECT referrer FROM customers);
-- (returns nothing because the subquery contains NULL)
CF_E,
  <<<'CF_O'
1
Asha|Bela
Ravi|(direct)
Neha|Bela
CF_O,
  '',
  ''),
L('case-expression', 'CASE: pivot logic inside the query', 6,
  <<<'CF_C'
<p><code>CASE</code> is an if/else that returns a value — perfect for bucketing revenue, pivoting a column into columns, or overriding messy data at read time instead of 12 app-side ifs.</p>
CF_C,
  <<<'CF_E'
CREATE TABLE orders(id INT, amount REAL);
INSERT INTO orders VALUES (1, 499),(2, 1500),(3, 250),(4, 3000);

SELECT
  CASE
    WHEN amount >= 2000 THEN 'enterprise'
    WHEN amount >= 500  THEN 'standard'
    ELSE 'starter'
  END AS tier,
  COUNT(*) AS orders_ct,
  SUM(amount) AS revenue
FROM orders
GROUP BY tier
ORDER BY revenue DESC;

SELECT SUM(CASE WHEN amount >= 500 THEN amount ELSE 0 END) AS big_order_revenue FROM orders;
CF_E,
  <<<'CF_O'
enterprise|1|3000.0
standard|1|1500.0
starter|2|749.0
4500.0
CF_O,
  '',
  ''),
L('string-date-sql', 'String & date functions that save app code', 6,
  <<<'CF_C'
<p>Every row formatted in SQL is a row your app doesn't crash on: <code>UPPER/LOWER/LENGTH/SUBSTR/REPLACE/TRIM</code>, date extraction, and <code>||</code>-concat. Push formatting down where it's uniform (except money — format money in the app, by locale).</p>
CF_C,
  <<<'CF_E'
CREATE TABLE customers(name TEXT, email TEXT, joined TEXT);
INSERT INTO customers VALUES
 ('  asha rane ', 'ASHA@Firm.in', '2026-08-06 21:14:05'),
 ('neha PATIL', 'neha@shop.in', '2026-07-01 09:00:00');

SELECT
  TRIM(name) AS clean_name,
  LOWER(email) AS email_norm,
  LENGTH(email) AS email_len,
  SUBSTR(joined, 1, 10) AS joined_on
FROM customers ORDER BY joined_on;
CF_E,
  <<<'CF_O'
neha PATIL|neha@shop.in|13|2026-07-01
asha rane|asha@firm.in|12|2026-08-06
CF_O,
  '',
  'username-gen'),
],
],

'bash' => ['lessons' => [
L('redirection-fd', 'Redirection & file descriptors: stdout, stderr, & beyond', 7,
  <<<'CF_C'
<p> Everything in UNIX is a stream with a number: stdin 0, stdout 1, stderr 2. <code>cmd > out 2>&1</code> merges them (order matters!), <code>cmd1 2>/dev/null</code> silences noise, and <code>tee</code> splits output to file <em>and</em> screen. Log discipline is the difference between "it failed" and "here's why".</p>
CF_C,
  <<<'CF_E'
# capture streams separately
ok() { echo "done ok"; }
fail() { echo "disk missing" >&2; }

ok > app.out 2> app.err      # split by destination
fail > app.out 2>> app.err

echo "--- app.out:"; cat app.out
echo "--- app.err:"; cat app.err

# merge both into one log (the "2>&1" must come AFTER the file redirect)
{ ok; fail; } > full.log 2>&1
echo "--- full.log:"; cat full.log
CF_E,
  <<<'CF_O'
--- app.out:
done ok
--- app.err:
disk missing
--- full.log:
done ok
disk missing
CF_O,
  <<<'CF_T'
# Write your stderr into err.log only (nothing on screen), and stdout to out.log:
# ./job.sh > out.log 2> err.log   ← implement job.sh to print one line to each stream
CF_T,
  'log-rotate-names'),
L('find-xargs', 'find + xargs: batch the filesystem safely', 7,
  <<<'CF_C'
<p><code>find dir -name '*.log' -mtime +7</code> locates; <code>xargs</code> feeds the list to a command as arguments (handling 10,000 files without "argument list too long"). Quote-safe pipeline: <code>-print0</code> + <code>xargs -0</code> survives filenames with spaces.</p>
CF_C,
  <<<'CF_E'
mkdir -p demo/logs demo/data
touch demo/logs/a.log demo/logs/b.log demo/logs/old.log
touch demo/logs/a.log demo/data/keep.csv demo/logs/.hidden
touch -d '10 days ago' demo/logs/old.log

echo "logs older than 7 days:"
find demo/logs -name '*.log' -mtime +7

echo "count all log files:"
find demo/logs -name '*.log' | wc -l

# batch-delete safely with -print0 / xargs -0 (handles spaces)
find demo/logs -name 'old.log' -print0 | xargs -0 rm -v
CF_E,
  <<<'CF_O'
logs older than 7 days:
demo/logs/old.log
count all log files:
3
removed 'demo/logs/old.log'
CF_O,
  <<<'CF_T'
# List the two LARGEST files under demo/ with: find . -type f -printf '%s %p\n' | sort -nr | head -2 (or ls -S on mac)
CF_T,
  'log-rotate-names'),
L('sed-awk', 'sed & awk: the text surgery kit', 8,
  <<<'CF_C'
<p><code>sed 's/x/y/g'</code> rewrites by pattern; <code>awk '{print $2}'</code> thinks in columns. Together they answer "what happened in prod?" faster than opening an IDE: extract fields, total a column, count by status — straight from raw logs.</p>
CF_C,
  <<<'CF_E'
cat > access.log <<'EOF'
10.0.0.1 GET /orders 200 412
10.0.0.2 GET /orders 200 389
10.0.0.1 POST /pay 500 1201
10.0.0.3 GET /orders 404 97
EOF

echo "requests by status:"
awk '{print $4}' access.log | sort | uniq -c | sort -nr

echo "slow requests (>500ms):"
awk '$5 > 500 {print $3, $5"ms"}' access.log

echo "redact ips for sharing:"
sed 's/^[0-9.]* /X.X.X.X /' access.log | head -2
CF_E,
  <<<'CF_O'
requests by status:
      2 200
      1 404
      1 500
slow requests (>500ms):
/pay 1201ms
redact ips for sharing:
X.X.X.X GET /orders 200 412
X.X.X.X GET /orders 200 389
CF_O,
  <<<'CF_T'
# From access.log: print the TOP path by total request time (awk + sort)
CF_T,
  'log-level-filter'),
L('env-cron', 'Environment variables & cron: the automation layer', 6,
  <<<'CF_C'
<p>Config lives in the environment: <code>export API_KEY=…</code>, read with <code>${API_KEY:?missing}</code> (fail loudly) — never hardcode secrets in scripts. <code>cron</code> runs your script on schedule; logs need absolute paths because cron's working directory isn't yours.</p>
CF_C,
  <<<'CF_E'
export SHOP_ENV=prod
export DB_HOST=db.internal

# safe read with default & with hard failure
echo "env: ${SHOP_ENV:-dev}"
echo "db:  ${DB_HOST:?DB_HOST is required}"

# a deploy script that refuses to run unconfigured
deploy() {
  : "${API_TOKEN:?set API_TOKEN first}"
  echo "deploying with token prefix ${API_TOKEN:0:4}…"
}
API_TOKEN=sk_live_99 deploy
CF_E,
  <<<'CF_O'
env: prod
db:  db.internal
deploying with token prefix sk_l…
CF_O,
  <<<'CF_T'
# Cron line to run /opt/backup.sh daily at 02:30:  30 2 * * * /opt/backup.sh >> /var/log/backup.log 2>&1
CF_T,
  ''),
],
],

'htmlcss' => ['lessons' => [
L('responsive-media', 'Responsive design & media queries', 7,
  <<<'CF_C'
<p>Design mobile-first: base styles for small screens, then <code>@media (min-width: 768px)</code> <em>adds</em> complexity for bigger ones. Fluid units (<code>%</code>, <code>fr</code>, <code>clamp()</code>, <code>rem</code>) beat fixed pixels so one stylesheet serves a phone and a TV.</p>
CF_C,
  <<<'CF_E'
<style>
.product-grid { display: grid; gap: 12px; grid-template-columns: 1fr; } /* phone first */
@media (min-width: 640px)  { .product-grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 1024px) { .product-grid { grid-template-columns: repeat(4, 1fr); } }
h2 { font-size: clamp(1.25rem, 2.5vw, 2rem); }   /* fluid type */
</style>

<div class="product-grid">
  <article>Widget ₹499</article><article>Gadget ₹899</article>
  <article>Sprocket ₹250</article><article>Doohickey ₹99</article>
</div>
<h2>Codeface Sale</h2>
CF_E,
  <<<'CF_O'
(Resize the preview: 1 column on phones, 2 on tablets, 4 on desktop — the title scales smoothly with clamp().)
CF_O,
  '',
  ''),
L('flexbox-2', 'Flexbox level 2: alignment is a system', 7,
  <<<'CF_C'
<p> Beyond centering: <code>justify-content</code> distributes along the main axis, <code>align-items</code> across it, <code>gap</code> replaces margin hacks, and <code>margin-left: auto</code> on one child pushes it to the far side — the classic navbar trick.</p>
CF_C,
  <<<'CF_E'
<style>
.nav { display: flex; align-items: center; gap: 18px; padding: 12px 18px;
       background: #0f172a; color: #fff; border-radius: 10px; }
.nav .spacer { margin-left: auto; }              /* pushes auth to the right */
.nav a { color: #cbd5e1; text-decoration: none; }
.nav strong { color: #fff; }
.price-row { display: flex; justify-content: space-between; padding: 6px 0;
             border-bottom: 1px dashed #e2e8f0; }
</style>

<nav class="nav"><strong>Codeface</strong><a href="#">Practice</a><a href="#">Learn</a>
  <span class="spacer"></span><a href="#">Log in</a></nav>

<div class="price-row"><span>Premium plan</span><strong>₹499/mo</strong></div>
<div class="price-row"><span>Team seats ×3</span><strong>₹1,197/mo</strong></div>
CF_E,
  <<<'CF_O'
(Brand left, links beside it, "Log in" pinned right via margin-left:auto; the price rows are dotted leaders lists.)
CF_O,
  '',
  ''),
L('grid-areas', 'CSS Grid areas: draw your layout in ASCII', 7,
  <<<'CF_C'
<p><code>grid-template-areas</code> lets you <em>see</em> the page skeleton in the stylesheet. Reordering areas in a media query rearranges the whole page without touching HTML — the cleanest responsive trick there is.</p>
CF_C,
  <<<'CF_E'
<style>
.page { display: grid; gap: 14px; padding: 14px;
  grid-template-areas: "header header" "sidebar main" "footer footer";
  grid-template-columns: 220px 1fr; }
@media (max-width: 700px) {
  .page { grid-template-areas: "header" "main" "sidebar" "footer";
          grid-template-columns: 1fr; }                 /* stack on phones */
}
.page > * { background: #eef2ff; border-radius: 8px; padding: 12px; }
.header  { grid-area: header; }
.sidebar { grid-area: sidebar; }
.main    { grid-area: main; }
.footer  { grid-area: footer; }
</style>

<div class="page">
  <div class="header">Shop admin</div>
  <div class="sidebar">Filters</div>
  <div class="main">526 orders today</div>
  <div class="footer">© Codeface</div>
</div>
CF_E,
  <<<'CF_O'
(Desktop: header / sidebar+main / footer. Narrow screen: everything stacks — only the CSS changed.)
CF_O,
  '',
  ''),
L('forms-ux', 'Forms that users actually finish', 7,
  <<<'CF_C'
<p>The best validation is <strong>not needing it</strong>: <code>type="email|number|date"</code> gives free checks + right keyboards, <code>label for</code> makes targets clickable, <code>min/required/pattern</code> guide without JS, and <code>fieldset/legend</code> groups sanely. Placeholders are hints, not labels.</p>
CF_C,
  <<<'CF_E'
<style>
  form { max-width: 340px; display: grid; gap: 10px; }
  input, button { padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
  input:invalid:not(:placeholder-shown) { border-color: #dc2626; }   /* red only after typing */
  button { background: #4f46e5; color: #fff; border: 0; }
</style>

<form>
  <label>Work email <input type="email" name="email" placeholder="you@firm.in" required></label>
  <label>Team size <input type="number" name="size" min="1" max="500" placeholder="1–500" required></label>
  <label>GSTIN <input name="gst" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]"
                       placeholder="24AABCT1332L1Z5"></label>
  <button>Create account</button>
</form>
CF_E,
  <<<'CF_O'
(Type a bad email or team size 0 — the browser blocks submit; borders go red only after input, not on load.)
CF_O,
  '',
  ''),
],
],
];
