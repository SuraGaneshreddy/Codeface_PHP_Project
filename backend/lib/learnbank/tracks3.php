<?php
// Learn tracks 3/4: lessons 6–8 for the 12 original tracks (deepening pass).
// Positions are assigned in array order during seeding, so these become lessons 6, 7, 8.
return [

'javascript' => ['lessons' => [
L('errors', 'Break things safely (errors & defensive code)', 7,
  '<p>Real input lies: users type <code>"abc"</code> where you expect a number, APIs return missing fields. <code>throw</code> turns a silent wrong answer into a loud, catchable failure. The pattern: validate at the edge of your system, then let the inside trust its data.</p>',
  <<<'CF_E'
// flaky coupon service — validate before you trust
function applyCoupon(subtotal, code) {
  const coupons = { SAVE25: 25, VIP40: 40 };
  if (typeof subtotal !== 'number' || subtotal < 0) {
    throw new Error('subtotal must be a non-negative number');
  }
  if (!(code in coupons)) throw new Error('unknown coupon: ' + code);
  return subtotal * (1 - coupons[code] / 100);
}

try {
  console.log(applyCoupon(80, 'SAVE25'));
  console.log(applyCoupon(80, 'NOPE'));
} catch (err) {
  console.log('caught:', err.message);
}
console.log('shop keeps running');
CF_E,
  <<<'CF_O'
60
caught: unknown coupon: NOPE
shop keeps running
CF_O,
  <<<'CF_T'
function applyCoupon(subtotal, code) {
  const coupons = { SAVE25: 25, VIP40: 40 };
  if (!(code in coupons)) throw new Error('unknown coupon: ' + code);
  return subtotal * (1 - coupons[code] / 100);
}

for (const code of ['SAVE25', 'NOPE']) {
  try {
    console.log(code, '->', applyCoupon(80, code));
  } catch (err) {
    console.log(code, '->', 'caught:', err.message);
  }
}

// your turn: also throw when subtotal is negative or not a number
CF_T,
  'password-strength-score'),
L('async', 'Waiting well (promises & async/await)', 8,
  '<p>Network calls take time; JavaScript does not stop the world to wait. A <code>Promise</code> is an IOU for a value, and <code>async</code>/<code>await</code> lets you write the waiting in a flat, readable style. <code>Promise.all</code> runs independent waits <em>at the same time</em> — the difference between 380ms and 120ms of waiting.</p>',
  <<<'CF_E'
// fetch three shipping quotes in parallel
const wait = (ms, value) => new Promise(resolve => setTimeout(() => resolve(value), ms));

async function cheapestQuote(weightKg) {
  const quotes = await Promise.all([
    wait(120, { carrier: 'FastBox',  price: 4.20 * weightKg }),
    wait(60,  { carrier: 'SlowMule', price: 2.50 * weightKg }),
    wait(200, { carrier: 'MidWay',   price: 3.10 * weightKg }),
  ]);
  return quotes.reduce((best, q) => (q.price < best.price ? q : best));
}

cheapestQuote(2).then(q => console.log(`ship with ${q.carrier} for $${q.price.toFixed(2)}`));
CF_E,
  <<<'CF_O'
ship with SlowMule for $5.00
CF_O,
  '',
  'retry-backoff-schedule'),
L('mini-inventory', 'Mini project: inventory sync', 12,
  '<p>Everything together: merge a delivery feed into stock, then flag what needs reordering. This pattern — fold a list of events into a lookup table — powers carts, caches, analytics, and queues.</p>',
  <<<'CF_E'
const warehouse = { apples: 40, milk: 12, bread: 0 };
const deliveries = [['milk', 24], ['bread', 10], ['eggs', 60]];

for (const [item, qty] of deliveries) {
  warehouse[item] = (warehouse[item] || 0) + qty;
}

console.log('inventory:', JSON.stringify(warehouse));
for (const [item, qty] of Object.entries(warehouse)) {
  if (qty < 20) console.log(`reorder ${item} (only ${qty} left)`);
}
CF_E,
  <<<'CF_O'
inventory: {"apples":40,"milk":36,"bread":10,"eggs":60}
reorder bread (only 10 left)
CF_O,
  <<<'CF_T'
const warehouse = { apples: 40, milk: 12, bread: 0 };
const deliveries = [['milk', 24], ['bread', 10], ['eggs', 60]];

for (const [item, qty] of deliveries) {
  warehouse[item] = (warehouse[item] || 0) + qty;
}
console.log('inventory:', JSON.stringify(warehouse));

// your turn: log "reorder <item> (only <qty> left)" for every item under 20
// hint: for (const [item, qty] of Object.entries(warehouse)) ...
CF_T,
  'cart-merge-dups'),
]],

'typescript' => ['lessons' => [
L('narrowing', 'Ask before you trust (type narrowing)', 7,
  '<p>A union says a value <em>could be</em> one of several shapes; <strong>narrowing</strong> is how you prove which one you are holding before you use it. The cleanest tool is the <strong>discriminated union</strong>: every variant carries a literal tag (<code>method: \'card\'</code>) and a <code>switch</code> on that tag gives you full type safety per branch — the compiler even checks you handled every case.</p>',
  <<<'CF_E'
type Payment =
  | { method: 'cash'; amount: number }
  | { method: 'card'; amount: number; last4: string }
  | { method: 'giftcard'; amount: number; code: string };

function receiptLine(p: Payment): string {
  switch (p.method) {
    case 'cash':     return `cash — $${p.amount.toFixed(2)} (change due)`;
    case 'card':     return `card •••• ${p.last4} — $${p.amount.toFixed(2)}`;
    case 'giftcard': return `gift card ${p.code} — $${p.amount.toFixed(2)}`;
  }
}

console.log(receiptLine({ method: 'card', amount: 12.5, last4: '4242' }));
console.log(receiptLine({ method: 'cash', amount: 20 }));
CF_E,
  <<<'CF_O'
card •••• 4242 — $12.50
cash — $20.00 (change due)
CF_O,
  '',
  'http-status-label'),
L('generics', 'Write it once, type it for everything (generics)', 8,
  '<p>Sometimes the logic is the same no matter the type: plucking a column from rows, wrapping a value in a box. <strong>Generics</strong> let a function keep the relationship between input and output types instead of collapsing to <code>any</code>. <code>K extends keyof T</code> means “<code>key</code> must actually be a field of the row” — misspellings become compile errors.</p>',
  <<<'CF_E'
function pluck<T, K extends keyof T>(rows: T[], key: K): T[K][] {
  return rows.map(r => r[key]);
}

const orders = [
  { id: 1, item: 'latte', total: 4.5 },
  { id: 2, item: 'muffin', total: 3.25 },
];

console.log(pluck(orders, 'item'));
console.log(pluck(orders, 'total'));
// pluck(orders, 'totl');   ← compile error: 'totl' is not a key
CF_E,
  <<<'CF_O'
[ 'latte', 'muffin' ]
[ 4.5, 3.25 ]
CF_O,
  '',
  'pick-route-distance'),
L('mini-api', 'Mini project: typed webhook handler', 12,
  '<p>The classic TypeScript job: events arrive as untyped JSON, and you must route them safely. Declare the shapes, narrow on the event type, and the compiler guarantees you never read a field that does not exist on that event.</p>',
  <<<'CF_E'
interface WebhookEvent {
  type: 'order.created' | 'order.refunded';
  orderId: string;
  amount: number;
}

function handleEvent(e: WebhookEvent): string {
  const money = `$${e.amount.toFixed(2)}`;
  return e.type === 'order.created'
    ? `new order ${e.orderId}: ${money} — notify kitchen`
    : `refund ${e.orderId}: ${money} — restock items`;
}

const batch: WebhookEvent[] = [
  { type: 'order.created', orderId: 'A-100', amount: 42.5 },
  { type: 'order.refunded', orderId: 'A-097', amount: 9.99 },
];

console.log(batch.map(handleEvent).join('\n'));
CF_E,
  <<<'CF_O'
new order A-100: $42.50 — notify kitchen
refund A-097: $9.99 — restock items
CF_O,
  '',
  'diff-line-stats'),
]],

'python' => ['lessons' => [
L('comprehensions', 'One line, whole pipeline (comprehensions)', 7,
  '<p>“For each row, compute this, keep those” appears constantly — comprehensions say it in one expression. A <strong>list comprehension</strong> builds a list, a <strong>dict comprehension</strong> builds a lookup, and adding an <code>if</code> filters as you go. Short does not mean clever: if it needs a comment to explain, write the loop.</p>',
  <<<'CF_E'
order_items = [
    ('latte', 2, 4.50),
    ('muffin', 1, 3.25),
    ('tea', 3, 2.75),
]

line_totals = [qty * price for _, qty, price in order_items]
big_lines = [item for item, qty, price in order_items if qty * price >= 8]
receipt = {item: qty * price for item, qty, price in order_items}

print('line totals:', line_totals)
print('big lines:', big_lines)
print('latte cost:', receipt['latte'])
CF_E,
  <<<'CF_O'
line totals: [9.0, 3.25, 8.25]
big lines: ['latte', 'tea']
latte cost: 9.0
CF_O,
  <<<'CF_T'
order_items = [
    ('latte', 2, 4.50),
    ('muffin', 1, 3.25),
    ('tea', 3, 2.75),
]

line_totals = [qty * price for _, qty, price in order_items]
print('line totals:', line_totals)

# your turn: a list of items whose line total is >= 8, then a dict
# mapping item -> line total
CF_T,
  'markdown-headings'),
L('errors', 'Fail loudly, recover politely (exceptions)', 7,
  '<p>Parsing anything from the outside world — CSVs, JSON, user uploads — means every line can be junk. Raise <code>ValueError</code> with a <em>useful message</em> the moment data breaks the contract, and catch exactly that case where you can do something sensible (skip the row, retry, default). Never catch-and-ignore: silent corruption is worse than a crash.</p>',
  <<<'CF_E'
def parse_row(line):
    parts = line.split(',')
    if len(parts) != 3:
        raise ValueError(f'expected 3 columns, got {len(parts)}')
    date, category, amount = parts
    return date, category, float(amount)

good, bad = 0, 0
for line in ['2026-07-01,food,12.50', 'broken-line', '2026-07-03,books,30.00']:
    try:
        date, cat, amt = parse_row(line)
        good += 1
        print(f'ok: {date} {cat} ${amt:.2f}')
    except ValueError as e:
        bad += 1
        print(f'skipped: {e}')

print(f'{good} rows imported, {bad} skipped')
CF_E,
  <<<'CF_O'
ok: 2026-07-01 food $12.50
skipped: expected 3 columns, got 1
ok: 2026-07-03 books $30.00
2 rows imported, 1 skipped
CF_O,
  <<<'CF_T'
def parse_row(line):
    parts = line.split(',')
    if len(parts) != 3:
        raise ValueError(f'expected 3 columns, got {len(parts)}')
    date, category, amount = parts
    return date, category, float(amount)

rows = ['2026-07-01,food,12.50', 'broken', 'bad,cat,xyz']
for line in rows:
    try:
        print(parse_row(line))
    except ValueError as e:
        print('skipped:', e)

# your turn: a bad amount ('xyz') currently raises ValueError from float() —
# catch it and re-raise with the line number in the message
CF_T,
  'retry-backoff-schedule'),
L('mini-logs', 'Mini project: web server log analyzer', 12,
  '<p>Everything together in the most classic scripting task there is: read lines, tally by level, surface what needs a human. <code>collections.Counter</code> is a dict specialised for counting — you will reach for it weekly.</p>',
  <<<'CF_E'
from collections import Counter

log_lines = [
    '2026-07-01 10:00:01 INFO server started',
    '2026-07-01 10:00:04 ERROR disk almost full',
    '2026-07-01 10:01:12 INFO request ok',
    '2026-07-01 10:02:55 WARN memory high',
    '2026-07-01 10:03:11 ERROR disk full',
]

levels = Counter(line.split()[2] for line in log_lines)
print('level counts:', dict(levels))

errors = [line for line in log_lines if ' ERROR ' in line]
print(f'{len(errors)} errors:')
for e in errors:
    print(' -', e.split(' ', 3)[3])
CF_E,
  <<<'CF_O'
level counts: {'INFO': 2, 'ERROR': 2, 'WARN': 1}
2 errors:
 - disk almost full
 - disk full
CF_O,
  <<<'CF_T'
log_lines = [
    '2026-07-01 10:00:01 INFO server started',
    '2026-07-01 10:00:04 ERROR disk almost full',
    '2026-07-01 10:01:12 INFO request ok',
]
counts = {}
for line in log_lines:
    level = line.split()[2]
    counts[level] = counts.get(level, 0) + 1
print('level counts:', counts)

# your turn: print just the ERROR lines, message only
CF_T,
  'log-rotate-names'),
]],

'java' => ['lessons' => [
L('classes', 'Bundle data with behavior (classes)', 8,
  '<p>A class packages the fields that describe a thing with the methods that act on it — a <code>Product</code> knows its own price and can sell itself. Keeping constructor-initialized state private-ish and exposing intention-revealing methods (<code>label()</code>, not <code>getNamePlusPriceString()</code>) is the beginning of every maintainable Java codebase.</p>',
  <<<'CF_E'
class Product {
    final String name;
    final double price;
    int stock;

    Product(String name, double price, int stock) {
        this.name = name;
        this.price = price;
        this.stock = stock;
    }

    String label() {
        return name + " — $" + String.format("%.2f", price) + " (" + stock + " left)";
    }
}

Product p = new Product("latte", 4.50, 12);
p.stock -= 2; // two lattes sold
System.out.println(p.label());
CF_E,
  <<<'CF_O'
latte — $4.50 (10 left)
CF_O,
  '',
  'round-robin-assign'),
L('exceptions', 'Checked failures (exceptions & finally)', 8,
  '<p>Java wants failure plans written down: recoverable problems are <strong>checked exceptions</strong> you must declare or catch. In everyday code the pattern is simpler — validate input by throwing <code>IllegalArgumentException</code> with a helpful message, handle it at the boundary, and use <code>finally</code> for cleanup that must happen either way.</p>',
  <<<'CF_E'
java.util.Map<String, Integer> coupons = java.util.Map.of("SAVE25", 25);

try {
    String code = "NOPE";
    Integer pct = coupons.get(code);
    if (pct == null) throw new IllegalArgumentException("unknown coupon: " + code);
    System.out.println("discounted: " + (80 * (1 - pct / 100.0)));
} catch (IllegalArgumentException e) {
    System.out.println("caught: " + e.getMessage());
} finally {
    System.out.println("checkout continues");
}
CF_E,
  <<<'CF_O'
caught: unknown coupon: NOPE
checkout continues
CF_O,
  '',
  'rate-allow'),
L('mini-inventory', 'Mini project: inventory sync', 12,
  '<p>The delivery-merge problem again, Java style: <code>LinkedHashMap</code> keeps first-seen order (predictable reports), <code>merge</code> expresses “add to whatever is there” in one call, and <code>var</code> (Java 10+) keeps the loop readable without giving up static types.</p>',
  <<<'CF_E'
java.util.Map<String, Integer> warehouse = new java.util.LinkedHashMap<>();
warehouse.put("apples", 40);
warehouse.put("milk", 12);

String[][] deliveries = {{"milk", "24"}, {"bread", "10"}, {"eggs", "60"}};
for (String[] d : deliveries) {
    warehouse.merge(d[0], Integer.parseInt(d[1]), Integer::sum);
}

System.out.println(warehouse);
for (var entry : warehouse.entrySet()) {
    if (entry.getValue() < 20) System.out.println("reorder " + entry.getKey());
}
CF_E,
  <<<'CF_O'
{apples=40, milk=36, bread=10, eggs=60}
reorder bread
CF_O,
  '',
  'cart-merge-dups'),
]],

'c' => ['lessons' => [
L('headers-io', 'Getting data in and out (stdio)', 8,
  '<p>C gives you no batteries — <code>printf</code> and format specifiers are the whole user interface. <code>%d</code> for ints, <code>%.2f</code> for rounded doubles, <code>%s</code> for strings. Every C program you will ever debug starts as printing values to see what the machine actually has.</p>',
  <<<'CF_E'
#include <stdio.h>

int main(void) {
    int cups = 12;
    double price = 4.5;
    char item[] = "latte";

    printf("item: %s\n", item);
    printf("cups: %d\n", cups);
    printf("revenue: $%.2f\n", cups * price);
    return 0;
}
CF_E,
  <<<'CF_O'
item: latte
cups: 12
revenue: $54.00
CF_O,
  '',
  'sum-even'),
L('pointers', 'Pointers: addresses, not magic', 9,
  '<p>A pointer is just a variable holding an address. You need one when a function must <em>change the caller&rsquo;s variable</em> — C passes copies by default. <code>&amp;x</code> takes the address (“here is where it lives”), <code>*p</code> dereferences (“go to that address and use the value”). Once this clicks, arrays, strings, and swap bugs all make sense.</p>',
  <<<'CF_E'
#include <stdio.h>

void applyDiscount(double *price, double pctOff) {
    *price = *price * (1 - pctOff / 100); /* write through the address */
}

int main(void) {
    double latte = 4.50;
    applyDiscount(&latte, 20);            /* hand over where latte lives */
    printf("sale price: $%.2f\n", latte);
    return 0;
}
CF_E,
  <<<'CF_O'
sale price: $3.60
CF_O,
  '',
  'best-time-stock'),
L('structs', 'Records with no compiler help (structs)', 8,
  '<p>A <code>struct</code> bundles related fields into one type — C&rsquo;s only data modeling tool. Access fields with <code>.</code> on the struct itself, or <code>-&gt;</code> through a pointer. Everything bigger (JSON, database rows, game entities) is structs all the way down.</p>',
  <<<'CF_E'
#include <stdio.h>
#include <string.h>

typedef struct {
    char name[20];
    double price;
    int stock;
} Product;

int main(void) {
    Product p;
    strcpy(p.name, "latte");
    p.price = 4.50;
    p.stock = 12;

    Product *ptr = &p;
    ptr->stock -= 2;   /* same as (*ptr).stock -= 2 */

    printf("%s — $%.2f (%d left)\n", p.name, p.price, p.stock);
    return 0;
}
CF_E,
  <<<'CF_O'
latte — $4.50 (10 left)
CF_O,
  '',
  'majority-element'),
]],

'cpp' => ['lessons' => [
L('stl', 'The STL is your exoskeleton (vector & map)', 9,
  '<p>Raw pointers and manual arrays are where C++ bugs live; <code>std::vector</code> and <code>std::map</code> are where programs get written. Range-based <code>for</code>, <code>count</code> on maps, and structured bindings (<code>for (auto [k, v] : m)</code>) make everyday aggregation almost script-like — with C++ speed.</p>',
  <<<'CF_E'
#include <iostream>
#include <map>
#include <string>
#include <vector>

int main() {
    std::vector<std::string> basket = {"apple", "milk", "apple", "bread", "apple"};
    std::map<std::string, int> counts;

    for (const std::string& item : basket) counts[item]++;

    for (const auto& [item, n] : counts)
        std::cout << item << " x " << n << "\n";
    return 0;
}
CF_E,
  <<<'CF_O'
apple x 3
bread x 1
milk x 1
CF_O,
  '',
  'top-k-frequent'),
L('refs', 'References: pointers, civilized', 8,
  '<p>A reference (<code>T&amp;</code>) is an alias to the original variable — no copy, no null, no <code>*</code> syntax. Pass large objects as <code>const T&amp;</code> to read them cheaply, plain <code>T&amp;</code> when the function should mutate the caller&rsquo;s value. Most modern C++ APIs speak references, not raw pointers.</p>',
  <<<'CF_E'
#include <iostream>
#include <string>

void applyDiscount(double& price, double pctOff) {
    price *= (1 - pctOff / 100);   /* writes to the caller's variable */
}

int main() {
    double latte = 4.50;
    applyDiscount(latte, 20);

    std::string name = "latte";
    const std::string& alias = name;   /* read-only view, zero copies */

    std::cout << alias << " on sale: $" << latte << "\n";
    return 0;
}
CF_E,
  <<<'CF_O'
latte on sale: $3.6
CF_O,
  '',
  'best-time-stock'),
L('strings-modern', 'std::string: text without terror', 8,
  '<p>In C, strings are hand-managed byte arrays; <code>std::string</code> grows, compares, and concatenates safely. Learn the workhorse methods: <code>substr</code>, <code>find</code>, <code>size</code>, and <code>+=</code> — then a split/join helper is five lines instead of fifty.</p>',
  <<<'CF_E'
#include <iostream>
#include <string>

int main() {
    std::string email = "ada@codeface.dev";
    size_t at = email.find('@');

    std::string user = email.substr(0, at);
    std::string domain = email.substr(at + 1);

    std::cout << "user: " << user << "\n";
    std::cout << "domain: " << domain << "\n";
    std::cout << "shout: ";
    for (char& c : user) c = toupper(c);
    std::cout << user << "\n";
    return 0;
}
CF_E,
  <<<'CF_O'
user: ada
domain: codeface.dev
shout: ADA
CF_O,
  '',
  'username-gen'),
]],

'csharp' => ['lessons' => [
L('linq', 'LINQ: queries inside the language', 9,
  '<p>C#&rsquo;s superpower is treating collections like a tiny database: <code>Where</code> (filter), <code>Select</code> (transform), <code>Sum</code>, <code>GroupBy</code>, <code>OrderBy</code>. The expression reads top-to-bottom like the data flows — and it is lazy until you materialize with <code>ToList()</code> or a loop.</p>',
  <<<'CF_E'
var rows = new (string Cat, double Amt)[] {
    ("food", 12.50), ("transport", 3.20), ("food", 9.00), ("books", 30.00)
};

var report = rows
    .GroupBy(r => r.Cat)
    .Select(g => (Cat: g.Key, Total: g.Sum(r => r.Amt)))
    .OrderByDescending(x => x.Total);

foreach (var x in report)
    Console.WriteLine($"{x.Cat,-10} ${x.Total:F2}");
CF_E,
  <<<'CF_O'
books      $30.00
food       $21.50
transport  $3.20
CF_O,
  '',
  'group-anagrams'),
L('props', 'Properties: fields with manners', 8,
  '<p>C# almost never uses raw public fields — a <strong>property</strong> (<code>public int Stock { get; private set; }</code>) looks like a field from outside but is really a getter/setter pair. Auto-properties, computed properties (<code>=&gt; ...</code>), and init-only setters keep objects valid from birth to death.</p>',
  <<<'CF_E'
class Product
{
    public string Name { get; }
    public double Price { get; }
    public int Stock { get; private set; }

    public Product(string name, double price, int stock)
        => (Name, Price, Stock) = (name, price, stock);

    public bool LowStock => Stock < 5;
    public string Label() => $"{Name} — ${Price:F2} ({Stock} left)";
    public void Sell(int n) => Stock -= n;
}

var p = new Product("muffin", 3.25, 6);
p.Sell(2);
Console.WriteLine(p.Label());
Console.WriteLine($"low stock? {p.LowStock}");
CF_E,
  <<<'CF_O'
muffin — $3.25 (4 left)
low stock? True
CF_O,
  '',
  'restock-alerts'),
L('mini-ops', 'Mini project: ops dashboard stats', 12,
  '<p>Put records, LINQ, and formatting together: model sign-in events, find problem users, render a compact report. This is the daily texture of C# enterprise work — strongly-typed rows in, grouped aggregates out.</p>',
  <<<'CF_E'
record SignIn(string User, bool Ok);

var events = new[] {
    new SignIn("ada", true),  new SignIn("ada", false), new SignIn("ada", false),
    new SignIn("grace", true), new SignIn("grace", true), new SignIn("hopper", false),
};

var fails = events
    .Where(e => !e.Ok)
    .GroupBy(e => e.User)
    .Select(g => (User: g.Key, Fails: g.Count()))
    .OrderByDescending(x => x.Fails);

foreach (var f in fails)
    Console.WriteLine($"{f.User}: {f.Fails} failed sign-ins");
Console.WriteLine($"success rate: {100.0 * events.Count(e => e.Ok) / events.Length:F0}%");
CF_E,
  <<<'CF_O'
ada: 2 failed sign-ins
hopper: 1 failed sign-ins
success rate: 50%
CF_O,
  '',
  'rate-allow'),
]],

'go' => ['lessons' => [
L('errors', 'Errors are values (if err != nil)', 9,
  '<p>Go has no exceptions — functions <em>return</em> errors, and callers check them immediately: <code>if err != nil</code>. It looks repetitive, and that is the point: failure paths stay visible instead of hiding in stack unwinds. <code>fmt.Errorf("...: %w", err)</code> wraps context around the cause.</p>',
  <<<'CF_E'
package main

import (
	"fmt"
)

func applyCoupon(subtotal float64, code string) (float64, error) {
	coupons := map[string]int{"SAVE25": 25, "VIP40": 40}
	pct, ok := coupons[code]
	if !ok {
		return 0, fmt.Errorf("unknown coupon: %s", code)
	}
	return subtotal * (1 - float64(pct)/100), nil
}

func main() {
	if total, err := applyCoupon(80, "SAVE25"); err == nil {
		fmt.Printf("charged: $%.2f\n", total)
	}
	if _, err := applyCoupon(80, "NOPE"); err != nil {
		fmt.Println("caught:", err)
	}
}
CF_E,
  <<<'CF_O'
charged: $60.00
caught: unknown coupon: NOPE
CF_O,
  '',
  'retry-backoff-schedule'),
L('structs', 'Structs & methods: Go’s one way to model', 8,
  '<p>Go keeps one modeling tool: a <code>struct</code> for fields, and <strong>methods with a receiver</strong> for behavior. A pointer receiver <code>(p *Product)</code> can mutate; a value receiver <code>(p Product)</code> cannot. No classes, no inheritance — composition via embedded structs when you need to share.</p>',
  <<<'CF_E'
package main

import "fmt"

type Product struct {
	Name  string
	Price float64
	Stock int
}

func (p *Product) Sell(n int) { p.Stock -= n }          // mutates: pointer receiver
func (p Product) Label() string {                        // reads: value receiver
	return fmt.Sprintf("%s — $%.2f (%d left)", p.Name, p.Price, p.Stock)
}

func main() {
	p := Product{Name: "latte", Price: 4.50, Stock: 12}
	p.Sell(2)
	fmt.Println(p.Label())
}
CF_E,
  <<<'CF_O'
latte — $4.50 (10 left)
CF_O,
  '',
  'round-robin-assign'),
L('mini-cli', 'Mini project: log summarizer CLI', 12,
  '<p>Everything Go is loved for in one small script: <code>strings</code> helpers, a <code>map[string]int</code> tally, explicit handling of the few failure modes, and out the door. Tools like kubectl and docker are this pattern at scale.</p>',
  <<<'CF_E'
package main

import (
	"fmt"
	"strings"
)

func main() {
	logLines := []string{
		"INFO server started",
		"ERROR disk almost full",
		"INFO request ok",
		"WARN memory high",
		"ERROR disk full",
	}

	counts := map[string]int{}
	var firstError string
	for _, line := range logLines {
		level := strings.SplitN(line, " ", 2)[0]
		counts[level]++
		if level == "ERROR" && firstError == "" {
			firstError = strings.SplitN(line, " ", 2)[1]
		}
	}

	fmt.Println("counts:", counts)
	fmt.Println("first error:", firstError)
}
CF_E,
  <<<'CF_O'
counts: map[ERROR:2 INFO:2 WARN:1]
first error: disk almost full
CF_O,
  '',
  'log-level-filter'),
]],

'ruby' => ['lessons' => [
L('blocks', 'Blocks: Ruby’s secret sauce', 8,
  '<p>Every Ruby collection method takes a <strong>block</strong> — an anonymous chunk of code in <code>{ |x| ... }</code> or <code>do |x| ... end</code>. <code>each</code>, <code>map</code>, <code>select</code>, <code>reject</code>, <code>group_by</code>: you describe <em>what</em> per element, Ruby handles <em>how</em> to iterate. It is the most expressive everyday iteration in any mainstream language.</p>',
  <<<'CF_E'
rows = [
  ['food', 12.50], ['transport', 3.20], ['food', 9.00], ['books', 30.00]
]

by_cat = rows.group_by { |cat, _| cat }
totals = by_cat.transform_values { |rs| rs.sum { |_, amt| amt } }

totals.sort_by { |_, total| -total }.each do |cat, total|
  puts "#{cat.ljust(10)} $#{'%.2f' % total}"
end
CF_E,
  <<<'CF_O'
books      $30.00
food       $21.50
transport  $3.20
CF_O,
  '',
  'group-anagrams'),
L('classes', 'Classes with personality', 8,
  '<p>Ruby classes are open and readable: <code>attr_reader</code> generates getters, <code>initialize</code> is the constructor, <code>@vars</code> are per-object state. String interpolation (<code>"#{...}"</code>) and tiny predicate methods (<code>low_stock?</code>) make business logic read like prose.</p>',
  <<<'CF_E'
class Product
  attr_reader :name, :price, :stock

  def initialize(name, price, stock)
    @name, @price, @stock = name, price, stock
  end

  def sell(n)
    @stock -= n
  end

  def low_stock?
    @stock < 5
  end

  def label
    "#{@name} — $#{'%.2f' % @price} (#{@stock} left)"
  end
end

p = Product.new('muffin', 3.25, 6)
p.sell(2)
puts p.label
puts "low stock? #{p.low_stock?}"
CF_E,
  <<<'CF_O'
muffin — $3.25 (4 left)
low stock? true
CF_O,
  '',
  'restock-alerts'),
L('mini-slugs', 'Mini project: title slugifier', 12,
  '<p>A real Rails-era job: turn blog titles into URL slugs. Chained string transforms, a regex character class, and <code>reject</code>/<code>join</code> over words — the Ruby text-processing sweet spot that made an entire generation of web frameworks.</p>',
  <<<'CF_E'
def slugify(title)
  title
    .downcase
    .gsub(/[^a-z0-9\s-]/, '')  # drop punctuation
    .split                     # words
    .join('-')
end

titles = [
  'Hello, World!',
  '10 Tips for Better Coffee (You Won’t Believe #4)',
  'Ruby 3.4: What’s New?'
]

titles.each { |t| puts slugify(t) }
CF_E,
  <<<'CF_O'
hello-world
10-tips-for-better-coffee-you-wont-believe-4
ruby-34-whats-new
CF_O,
  '',
  'slugify-title'),
]],

'php' => ['lessons' => [
L('classes', 'Classes & typed properties', 8,
  '<p>Modern PHP (8.x) is a real OOP language: typed properties, constructor promotion, read-only fields. <code>public function __construct(private string $name)</code> declares the property <em>and</em> assigns it in one signature — the boilerplate is gone.</p>',
  <<<'CF_E'
<?php
class Product {
    public function __construct(
        private string $name,
        private float $price,
        private int $stock,
    ) {}

    public function sell(int $n): void {
        $this->stock -= $n;
    }

    public function label(): string {
        return sprintf('%s — $%.2f (%d left)', $this->name, $this->price, $this->stock);
    }
}

$p = new Product('latte', 4.50, 12);
$p->sell(2);
echo $p->label(), PHP_EOL;
CF_E,
  <<<'CF_O'
latte — $4.50 (10 left)
CF_O,
  '',
  'round-robin-assign'),
L('array-fns', 'The array function arsenal', 8,
  '<p>PHP&rsquo;s arrays are ordered maps, and the standard library knows it: <code>array_map</code>, <code>array_filter</code>, <code>array_reduce</code>, <code>array_column</code>, <code>array_sum</code>, <code>usort</code>. Composing two or three of them replaces most hand-rolled loops over database result sets.</p>',
  <<<'CF_E'
<?php
$rows = [
    ['cat' => 'food', 'amt' => 12.50],
    ['cat' => 'transport', 'amt' => 3.20],
    ['cat' => 'food', 'amt' => 9.00],
    ['cat' => 'books', 'amt' => 30.00],
];

$food = array_filter($rows, fn($r) => $r['cat'] === 'food');
$total = array_sum(array_column($rows, 'amt'));
$big = array_filter($rows, fn($r) => $r['amt'] >= 10);

printf("food lines: %d\n", count($food));
printf("grand total: $%.2f\n", $total);
echo 'big spenders: ', implode(', ', array_column($big, 'cat')), PHP_EOL;
CF_E,
  <<<'CF_O'
food lines: 2
grand total: $54.70
big spenders: food, books
CF_O,
  '',
  'csv-sum-column'),
L('mini-upload', 'Mini project: normalize an upload', 12,
  '<p>The eternal PHP job: a messy CSV-ish upload arrives, and you normalize, validate, and summarize it — exactly what <code>$_POST</code> handlers in this very app do, minus the HTTP part.</p>',
  <<<'CF_E'
<?php
$upload = "  Ada , ada@x.dev , 32 \nGrace, grace@x.dev, 41\nbad line\nHopper, hopper@x.dev, 27";

$users = [];
foreach (explode("\n", $upload) as $i => $line) {
    $parts = array_map('trim', explode(',', $line));
    if (count($parts) !== 3 || !filter_var($parts[1], FILTER_VALIDATE_EMAIL)) {
        echo "skipped line ", $i + 1, "\n";
        continue;
    }
    [$name, $email, $age] = $parts;
    $users[] = ['name' => $name, 'email' => strtolower($email), 'age' => (int)$age];
}

printf("imported %d users\n", count($users));
printf("average age: %.1f\n", array_sum(array_column($users, 'age')) / count($users));
CF_E,
  <<<'CF_O'
skipped line 3
imported 3 users
average age: 33.3
CF_O,
  '',
  'csv-row-build'),
]],

'kotlin' => ['lessons' => [
L('null-safety', 'The billion-dollar fix (null safety)', 9,
  '<p>Kotlin&rsquo;s type system tracks nullness: <code>String</code> can never be null, <code>String?</code> can. You must handle the null before use — <code>?.</code> (safe call, yields null), <code>?:</code> (Elvis, supplies a default), <code>!!</code> (I accept the crash). Most NPEs become compile errors, not 3 a.m. alerts.</p>',
  <<<'CF_E'
fun discountFor(email: String?): Int {
    val domain = email?.substringAfter('@', "")   // "" when email is null
    return when {
        domain.endsWith(".edu") -> 50
        domain.isEmpty()        -> 0
        else                    -> 10
    }
}

fun main() {
    println(discountFor("ada@mit.edu"))
    println(discountFor("grace@shop.com"))
    println(discountFor(null))
}
CF_E,
  <<<'CF_O'
50
10
0
CF_O,
  '',
  'coupon-discount'),
L('data-classes', 'data class: records for free', 8,
  '<p>Declare <code>data class</code> and Kotlin generates <code>equals</code>, <code>hashCode</code>, a readable <code>toString</code>, and <code>copy()</code> for modified clones. Combined with <code>val</code> (immutable) by default and <code>let</code>/<code>apply</code> scoping, you get tiny, safe model types — the daily bread of Android and Ktor backends.</p>',
  <<<'CF_E'
data class Product(val name: String, val price: Double, val stock: Int)

fun main() {
    val muffin = Product("muffin", 3.25, 12)
    val sold = muffin.copy(stock = muffin.stock - 2)   // new instance, original intact

    println(sold.name)
    println("stock: ${sold.stock}")
    println("same product? ${muffin == sold}")
}
CF_E,
  <<<'CF_O'
muffin
stock: 10
same product? false
CF_O,
  '',
  'cart-merge-dups'),
L('mini-cart', 'Mini project: checkout summary', 12,
  '<p>Kotlin collection operators at work: <code>filter</code>, <code>sumOf</code>, <code>groupBy</code>, string templates — a checkout summary in the amount of code other languages spend on imports.</p>',
  <<<'CF_E'
data class Line(val item: String, val qty: Int, val unit: Double) {
    val total get() = qty * unit
}

fun main() {
    val cart = listOf(
        Line("latte", 2, 4.50),
        Line("muffin", 1, 3.25),
        Line("tea", 3, 2.75),
    )

    val grand = cart.sumOf { it.total }
    val multi = cart.filter { it.qty > 1 }.map { it.item }

    println("lines: ${cart.size}")
    println("multi-qty: ${multi.joinToString()}")
    println("grand total: $%.2f".format(grand))
}
CF_E,
  <<<'CF_O'
lines: 3
multi-qty: latte, tea
grand total: $20.50
CF_O,
  '',
  'shopping-cart-total'),
]],

'rust' => ['lessons' => [
L('ownership', 'Ownership: who frees the memory?', 10,
  '<p>Rust&rsquo;s big idea: every value has exactly one <strong>owner</strong>; when the owner goes out of scope, the value is dropped. Assignment <em>moves</em> ownership, and borrowing (<code>&amp;x</code>) lends access without giving it up. The borrow checker feels strict — until you notice it has eliminated whole bug species (use-after-free, double-free, data races).</p>',
  <<<'CF_E'
fn main() {
    let order = String::from("2x latte");

    let summary = summarize(&order);   // borrow: order still usable
    println!("{summary}");

    let archived = order;              // moves ownership
    // println!("{order}");            // would NOT compile — moved away
    println!("archived: {archived}");
}

fn summarize(s: &String) -> String {   // takes a borrow, not ownership
    format!("summary of '{}': {} chars", s, s.len())
}
CF_E,
  <<<'CF_O'
summary of '2x latte': 8 chars
archived: 2x latte
CF_O,
  '',
  ''),
L('enums-match', 'Enums & match: model every case', 9,
  '<p>Rust enums are <strong>tagged unions</strong>: each variant can carry its own data, and <code>match</code> forces you to handle <em>every</em> variant — forget one and it will not compile. <code>Option&lt;T&gt;</code> (<code>Some</code>/<code>None</code>) replaces null everywhere; <code>Result&lt;T, E&gt;</code> replaces exceptions.</p>',
  <<<'CF_E'
enum Payment {
    Cash(f64),
    Card { last4: String, amount: f64 },
    GiftCard { code: String, amount: f64 },
}

fn describe(p: &Payment) -> String {
    match p {
        Payment::Cash(amt) => format!("cash — ${amt:.2} (change due)"),
        Payment::Card { last4, amount } => format!("card •••• {last4} — ${amount:.2}"),
        Payment::GiftCard { code, amount } => format!("gift card {code} — ${amount:.2}"),
    }
}

fn main() {
    let payments = [
        Payment::Card { last4: "4242".into(), amount: 12.50 },
        Payment::Cash(20.0),
        Payment::GiftCard { code: "GIFT9".into(), amount: 9.99 },
    ];
    for p in &payments {
        println!("{}", describe(p));
    }
}
CF_E,
  <<<'CF_O'
card •••• 4242 — $12.50
cash — $20.00 (change due)
gift card GIFT9 — $9.99
CF_O,
  '',
  'http-status-label'),
L('mini-ledger', 'Mini project: points ledger', 12,
  '<p>Everything together: <code>HashMap</code> for balances, an enum for entry types, <code>match</code> for behavior, and the borrow checker keeping it honest. This is the shape of real systems code — event streams folded into state.</p>',
  <<<'CF_E'
use std::collections::HashMap;

enum Entry { Earn { user: String, pts: i32 }, Spend { user: String, pts: i32 } }

fn main() {
    let entries = [
        Entry::Earn  { user: "ada".into(),   pts: 100 },
        Entry::Earn  { user: "grace".into(), pts: 40 },
        Entry::Spend { user: "ada".into(),   pts: 30 },
        Entry::Earn  { user: "ada".into(),   pts: 10 },
    ];

    let mut balances: HashMap<String, i32> = HashMap::new();
    for e in entries {
        let bal = balances.entry(match &e {
            Entry::Earn { user, .. } | Entry::Spend { user, .. } => user.clone(),
        }).or_insert(0);
        match e {
            Entry::Earn  { pts, .. } => *bal += pts,
            Entry::Spend { pts, .. } => *bal -= pts,
        }
    }

    let mut rows: Vec<_> = balances.iter().collect();
    rows.sort_by(|a, b| b.1.cmp(a.1));
    for (user, bal) in rows {
        println!("{user}: {bal} pts");
    }
}
CF_E,
  <<<'CF_O'
ada: 80 pts
grace: 40 pts
CF_O,
  '',
  'lru-sequence'),
]],
];
