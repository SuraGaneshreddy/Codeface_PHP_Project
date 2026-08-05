<?php
// Learn tracks 1/2: javascript, typescript, python, java, c, cpp
return [
'javascript' => ['lessons' => [
L('values', 'Name real things (variables & values)', 6,
  '<p>Programs model <strong>real things</strong>: prices, quantities, flags. In JavaScript, <code>const</code> is your default (values that won\'t be reassigned) and <code>let</code> is for values that change. Good names beat clever code — name things after what they mean in the real world, not what type they are.</p>',
  "// a coffee cart's morning order\nconst pricePerLatte = 4.50;\nlet cupsSold = 0;\n\n// a rush of 6 orders\ncupsSold = cupsSold + 6;\nconst revenue = pricePerLatte * cupsSold;\nconst isSoldOut = cupsSold >= 50;\n\nconsole.log('revenue:', revenue);   // 27\nconsole.log('sold out?', isSoldOut); // false",
  "revenue: 27\nsold out? false",
  "const pricePerLatte = 4.50;\nlet cupsSold = 0;\n\n// simulate a busy day — change the numbers and run\ncupsSold = cupsSold + 12;\nconsole.log('revenue:', pricePerLatte * cupsSold);\nconsole.log('cups:', cupsSold);\n\n// your turn: add a const taxRate = 0.07 and log the tax collected",
  'sum-even'),
L('functions', 'Small machines (functions)', 7,
  '<p>A function is a named recipe: inputs in, result out. Real codebases are mostly small functions wired together. The arrow-function style <code>const f = (x) =&gt; ...</code> and classic <code>function f(x) {}</code> both work — learn to read both.</p>',
  "function applyDiscount(price, percentOff) {\n  return price * (1 - percentOff / 100);\n}\n\nconst formatMoney = (amount) => '$' + amount.toFixed(2);\n\nconst sale = applyDiscount(59.99, 20);\nconsole.log(formatMoney(sale));        // $47.99\nconsole.log(formatMoney(applyDiscount(10, 0))); // $10.00",
  "$47.99\n$10.00",
  "function applyDiscount(price, percentOff) {\n  return price * (1 - percentOff / 100);\n}\n\n// run the machine on a few products\nconsole.log(applyDiscount(59.99, 20));  // 47.992\nconsole.log(applyDiscount(200, 15));\n\n// your turn: write `buyTwoGetOneFree(prices)` that returns the\n// total of the two most expensive items in the array",
  'best-time-stock'),
L('collections', 'Lists and lookup tables', 8,
  '<p>Two structures cover 90% of daily work: <strong>arrays</strong> (ordered lists) and <strong>objects/maps</strong> (key → value lookups). A basket is a list of items; a price list is a lookup from item to price. Combining them is the classic pattern behind totals, indexes, and caches.</p>',
  "const basket = ['apple', 'milk', 'apple'];\nconst prices = { apple: 0.50, milk: 2.25, bread: 1.80 };\n\nlet total = 0;\nfor (const item of basket) {\n  total += prices[item] ?? 0;\n}\nconsole.log('total:', total);\nconsole.log('unique items:', new Set(basket).size);",
  "total: 3.25\nunique items: 2",
  "const basket = ['apple', 'milk', 'apple'];\nconst prices = { apple: 0.50, milk: 2.25, bread: 1.80 };\n\nlet total = 0;\nfor (const item of basket) total += prices[item] ?? 0;\nconsole.log('total:', total);\n\n// your turn: count how many times 'apple' appears in the basket\n// then try basket.filter(i => i === 'apple').length",
  'shopping-cart-total'),
L('strings-loops', 'Text pipelines (strings & loops)', 7,
  '<p>Usernames, slugs, normalised emails — so much software is <em>text in, text out</em>. The pattern: split into pieces, transform each piece, join back. Strings are immutable in JS; every method returns a new one.</p>',
  "function toUsername(displayName) {\n  const parts = displayName.trim().toLowerCase().split(' ');\n  const first = parts[0][0];\n  const last = parts[parts.length - 1];\n  return first + last.replace(/[^a-z]/g, '');\n}\n\nconsole.log(toUsername('Ada Lovelace'));     // alovelace\nconsole.log(toUsername('  Grace Hopper '));  // ghopper",
  "alovelace\nghopper",
  "function toUsername(displayName) {\n  const parts = displayName.trim().toLowerCase().split(' ');\n  return parts[0][0] + parts[parts.length - 1].replace(/[^a-z]/g, '');\n}\n\n['Ada Lovelace', 'Grace Hopper', 'Cher'].forEach(n => console.log(toUsername(n)));\n\n// your turn: handle one-word names like 'Cher' without crashing",
  'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>Combine everything: parse raw records, aggregate with a map, and print a tiny report. This is 80% of what junior-backend and scripting work actually looks like.</p>',
  "const rows = [\n  ['2026-07-01', 'food', 12.50],\n  ['2026-07-01', 'transport', 3.20],\n  ['2026-07-02', 'food', 9.00],\n  ['2026-07-03', 'books', 30.00],\n];\n\nconst byCategory = {};\nlet grand = 0;\nfor (const [, cat, amt] of rows) {\n  byCategory[cat] = (byCategory[cat] || 0) + amt;\n  grand += amt;\n}\nconst top = Object.entries(byCategory).sort((a, b) => b[1] - a[1])[0];\nconsole.log('total: $' + grand.toFixed(2));\nconsole.log('top category:', top[0], '→ $' + top[1].toFixed(2));",
  "total: $54.70\ntop category: books → $30.00",
  "const rows = [\n  ['2026-07-01', 'food', 12.50],\n  ['2026-07-01', 'transport', 3.20],\n  ['2026-07-02', 'food', 9.00],\n  ['2026-07-03', 'books', 30.00],\n];\nconst byCategory = {};\nlet grand = 0;\nfor (const [, cat, amt] of rows) {\n  byCategory[cat] = (byCategory[cat] || 0) + amt;\n  grand += amt;\n}\nconsole.log('total: $' + grand.toFixed(2));\nconsole.log('categories:', Object.keys(byCategory).join(', '));\n// your turn: find the category with the highest spend and log it",
  'sales-summary'),
]],
'typescript' => ['lessons' => [
L('values', 'Types describe real things', 6,
  '<p>TypeScript is JavaScript plus <strong>labels</strong>: you tell the compiler what shape every value has, and it refuses to build when reality disagrees. Types live in <code>: Type</code> annotations after names — they vanish at runtime.</p>',
  "const pricePerLatte: number = 4.50;\nlet cupsSold: number = 0;\nconst shopName: string = 'Beans & Co';\nconst isOpen: boolean = true;\n\ncupsSold = 6;\nconst revenue: number = pricePerLatte * cupsSold;\n// cupsSold = 'six';  ← TypeScript error BEFORE it ever runs\nconsole.log(shopName, revenue);",
  "Beans & Co 27",
  '', 'sum-even'),
L('interfaces', 'Shapes with names (interfaces)', 7,
  '<p>Real apps pass around <strong>records</strong>: users, orders, events. An <code>interface</code> names that shape once, and every function then says exactly what it expects. This is the difference between code you can refactor and code you fear.</p>',
  "interface Order {\n  id: number;\n  item: string;\n  qty: number;\n  unitPrice: number;\n}\n\nfunction orderTotal(o: Order): number {\n  return o.qty * o.unitPrice;\n}\n\nconst o: Order = { id: 101, item: 'latte', qty: 2, unitPrice: 4.5 };\nconsole.log(orderTotal(o));",
  "9",
  '', 'best-time-stock'),
L('collections', 'Typed lists & records', 8,
  '<p><code>number[]</code> is a list of numbers; <code>Record&lt;string, number&gt;</code> is a dictionary from string to number. Generics like <code>Array&lt;Order&gt;</code> keep your helpers honest across thousands of call sites.</p>',
  "const basket: string[] = ['apple', 'milk', 'apple'];\nconst prices: Record<string, number> = { apple: 0.5, milk: 2.25 };\n\nconst total: number = basket.reduce((sum, item) => sum + (prices[item] ?? 0), 0);\nconsole.log(total.toFixed(2));",
  "3.25",
  '', 'shopping-cart-total'),
L('unions', 'Model the messy real world (unions)', 7,
  '<p>Sometimes a value is one of a few things: a payment is cash <em>or</em> card; a request succeeded <em>or</em> failed. <strong>Union types</strong> (<code>string | number</code>) and <strong>literal unions</strong> (<code>\'info\' | \'warn\' | \'error\'</code>) model this precisely — the compiler then checks every case you handle.</p>',
  "type LogLevel = 'info' | 'warn' | 'error';\n\ninterface LogLine { level: LogLevel; message: string; }\n\nconst lines: LogLine[] = [\n  { level: 'info', message: 'server started' },\n  { level: 'error', message: 'disk almost full' },\n];\n\nconst errors = lines.filter(l => l.level === 'error');\nconsole.log(errors.length);",
  "1",
  '', 'log-level-filter'),
L('mini-expenses', 'Mini project: typed expense report', 12,
  '<p>The expense report again — but this time the compiler guarantees every amount is a number and every category is a string. Notice how little the logic changes; the value is in the guarantees.</p>',
  "interface Expense { date: string; category: string; amount: number; }\n\nconst rows: Expense[] = [\n  { date: '2026-07-01', category: 'food', amount: 12.5 },\n  { date: '2026-07-03', category: 'books', amount: 30 },\n];\n\nconst byCategory = rows.reduce<Record<string, number>>((acc, e) => {\n  acc[e.category] = (acc[e.category] ?? 0) + e.amount;\n  return acc;\n}, {});\nconsole.log(byCategory);",
  "{ food: 12.5, books: 30 }",
  '', 'sales-summary'),
]],
'python' => ['lessons' => [
L('values', 'Name real things (variables)', 6,
  '<p>Python keeps it simple: no declarations, just <code>name = value</code>. Names are labels on values, types travel with the value. Convention matters: <code>snake_case</code> for variables and functions, <code>UPPER_CASE</code> for constants.</p>',
  "PRICE_PER_LATTE = 4.50\ncups_sold = 0\n\n# lunch rush\ncups_sold += 6\nrevenue = PRICE_PER_LATTE * cups_sold\nis_sold_out = cups_sold >= 50\n\nprint('revenue:', revenue)\nprint('sold out?', is_sold_out)",
  "revenue: 27.0\nsold out? False",
  "PRICE_PER_LATTE = 4.50\ncups_sold = 12\nprint('revenue:', PRICE_PER_LATTE * cups_sold)\n\n# your turn: add TAX_RATE = 0.07 and print the tax collected",
  'sum-even'),
L('functions', 'Small machines (def)', 7,
  '<p><code>def</code> defines a function; <code>return</code> hands the result back. Python functions are values too — you can pass them around, put them in lists. Keep them tiny and name them like verbs.</p>',
  "def apply_discount(price, percent_off):\n    return price * (1 - percent_off / 100)\n\ndef format_money(amount):\n    return f'\${amount:.2f}'\n\nprint(format_money(apply_discount(59.99, 20)))\nprint(format_money(apply_discount(10, 0)))",
  "$47.99\n$10.00",
  "def apply_discount(price, percent_off):\n    return price * (1 - percent_off / 100)\n\nprint(apply_discount(59.99, 20))\nprint(apply_discount(200, 15))\n\n# your turn: write buy_two_get_one_free(prices) returning\n# the total of the two most expensive prices",
  'best-time-stock'),
L('collections', 'Lists and dicts', 8,
  '<p><code>list</code> is your ordered sequence; <code>dict</code> is the lookup table you will reach for daily. Adding up a basket against a price dict is the pattern behind invoices, analytics, caches, and indexes.</p>',
  "basket = ['apple', 'milk', 'apple']\nprices = {'apple': 0.50, 'milk': 2.25, 'bread': 1.80}\n\ntotal = sum(prices.get(item, 0) for item in basket)\nprint('total:', total)\nprint('unique:', len(set(basket)))",
  "total: 3.25\nunique: 2",
  "basket = ['apple', 'milk', 'apple']\nprices = {'apple': 0.50, 'milk': 2.25, 'bread': 1.80}\n\ntotal = 0\nfor item in basket:\n    total += prices.get(item, 0)\nprint('total:', round(total, 2))\n\n# your turn: count apples with basket.count('apple')",
  'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p>Splitting, stripping, joining — text work is constant in real systems. Strings are immutable, every method returns a new one, and <code>for ch in text</code> walks characters without an index.</p>',
  "def to_username(display_name):\n    parts = display_name.strip().lower().split()\n    if len(parts) == 1:          # one-word names like 'Cher'\n        return parts[0]\n    return parts[0][0] + parts[-1]\n\nprint(to_username('Ada Lovelace'))\nprint(to_username('  Grace Hopper  '))\nprint(to_username('Cher'))",
  "alovelace\nghopper\ncher",
  "def to_username(display_name):\n    parts = display_name.strip().lower().split()\n    return parts[0][0] + parts[-1]\n\nfor name in ['Ada Lovelace', 'Grace Hopper', 'Cher']:\n    print(to_username(name))\n\n# your turn: keep only letters a-z in the output",
  'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>Everything together: rows in, dict aggregation, formatted report out. This is the exact shape of countless automation scripts people get paid for.</p>',
  "rows = [\n    ('2026-07-01', 'food', 12.50),\n    ('2026-07-01', 'transport', 3.20),\n    ('2026-07-02', 'food', 9.00),\n    ('2026-07-03', 'books', 30.00),\n]\n\nby_category = {}\nfor _, cat, amount in rows:\n    by_category[cat] = by_category.get(cat, 0) + amount\n\ntop = max(by_category, key=by_category.get)\nprint(f'total: \${sum(by_category.values()):.2f}')\nprint(f'top category: {top} → \${by_category[top]:.2f}')",
  "total: $54.70\ntop category: books → $30.00",
  "rows = [\n    ('2026-07-01', 'food', 12.50),\n    ('2026-07-01', 'transport', 3.20),\n    ('2026-07-02', 'food', 9.00),\n    ('2026-07-03', 'books', 30.00),\n]\nby_category = {}\nfor _, cat, amount in rows:\n    by_category[cat] = by_category.get(cat, 0) + amount\nprint('total:', round(sum(by_category.values()), 2))\nprint('categories:', ', '.join(by_category))\n# your turn: print the top-spending category",
  'sales-summary'),
]],
'java' => ['lessons' => [
L('values', 'Types first, names next', 6,
  '<p>Java makes you declare every type up front — <code>int</code>, <code>double</code>, <code>boolean</code>, <code>String</code>. It feels strict, and that strictness is the point: whole categories of bugs simply cannot compile.</p>',
  <<<'CF_E'
double pricePerLatte = 4.50;
int cupsSold = 0;

// lunch rush
cupsSold += 6;
double revenue = pricePerLatte * cupsSold;
boolean soldOut = cupsSold >= 50;

System.out.println("revenue: " + revenue);
System.out.println("sold out? " + soldOut);
CF_E,
  <<<'CF_O'
revenue: 27.0
sold out? false
CF_O,
  '', 'sum-even'),
L('methods', 'Small machines (methods)', 7,
  '<p>Java functions live inside classes and are called <strong>methods</strong>. The signature declares the return type and each parameter&rsquo;s type — documentation the compiler enforces.</p>',
  <<<'CF_E'
static double applyDiscount(double price, int percentOff) {
    return price * (1 - percentOff / 100.0);
}

static String formatMoney(double amount) {
    return String.format("$%.2f", amount);
}

public static void main(String[] args) {
    System.out.println(formatMoney(applyDiscount(59.99, 20)));
}
CF_E,
  <<<'CF_O'
$47.99
CF_O,
  '', 'best-time-stock'),
L('collections', 'List and HashMap', 8,
  '<p><code>ArrayList&lt;&gt;</code> grows as needed; <code>HashMap&lt;String, Double&gt;</code> is the industrial-strength lookup table. Generics in angle brackets say what lives inside.</p>',
  <<<'CF_E'
import java.util.*;

List<String> basket = List.of("apple", "milk", "apple");
Map<String, Double> prices = Map.of("apple", 0.50, "milk", 2.25, "bread", 1.80);

double total = 0;
for (String item : basket) {
    total += prices.getOrDefault(item, 0.0);
}
System.out.println("total: " + total);
CF_E,
  <<<'CF_O'
total: 3.25
CF_O,
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p>Java strings are immutable and rich: <code>trim</code>, <code>split</code>, <code>toLowerCase</code>, <code>String.join</code>. The split-transform-join pipeline is everywhere — logging, imports, slugs.</p>',
  <<<'CF_E'
static String toUsername(String displayName) {
    String[] parts = displayName.trim().toLowerCase().split("\\s+");
    return parts[0].substring(0, 1) + parts[parts.length - 1];
}

System.out.println(toUsername("Ada Lovelace"));
System.out.println(toUsername("  Grace Hopper  "));
CF_E,
  <<<'CF_O'
alovelace
ghopper
CF_O,
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>The same report with real types: a small <code>record</code> models an expense, a HashMap aggregates. Verbose, explicit — and impossible to misread.</p>',
  <<<'CF_E'
import java.util.*;

record Expense(String date, String category, double amount) {}

List<Expense> rows = List.of(
    new Expense("2026-07-01", "food", 12.50),
    new Expense("2026-07-03", "books", 30.00)
);
Map<String, Double> byCategory = new HashMap<>();
for (Expense e : rows) {
    byCategory.merge(e.category(), e.amount(), Double::sum);
}
System.out.println(byCategory);
CF_E,
  <<<'CF_O'
{food=12.5, books=30.0}
CF_O,
  '', 'sales-summary'),
]],
'c' => ['lessons' => [
L('values', 'Memory you can touch', 6,
  '<p>In C you name the <strong>exact</strong> size of everything: <code>int</code> counts, <code>double</code> measures, <code>char</code> is a single byte. printf prints with format placeholders — <code>%d</code> for ints, <code>%.2f</code> for rounded doubles.</p>',
  <<<'CF_E'
#include <stdio.h>

int main(void) {
    double price_per_latte = 4.50;
    int cups_sold = 6;

    double revenue = price_per_latte * cups_sold;
    printf("revenue: %.2f\n", revenue);
    printf("cups: %d\n", cups_sold);
    return 0;
}
CF_E,
  <<<'CF_O'
revenue: 27.00
cups: 6
CF_O,
  '', 'sum-even'),
L('functions', 'Small machines (functions)', 7,
  '<p>Declare the return type, the name, then typed parameters. C compiles top to bottom, so helpers go above <code>main</code> (or get a forward declaration).</p>',
  <<<'CF_E'
#include <stdio.h>

double apply_discount(double price, int percent_off) {
    return price * (1 - percent_off / 100.0);
}

int main(void) {
    printf("%.2f\n", apply_discount(59.99, 20));
    return 0;
}
CF_E,
  <<<'CF_O'
47.99
CF_O,
  '', 'best-time-stock'),
L('arrays', 'Arrays & loops (no training wheels)', 8,
  '<p>A C array is raw memory plus a length <em>you</em> track. No bounds checking, no <code>.length</code> — which is exactly why C teaches you what every other language does for you.</p>',
  <<<'CF_E'
#include <stdio.h>

int main(void) {
    double prices[] = {0.50, 2.25, 0.50};
    int n = sizeof prices / sizeof prices[0];

    double total = 0;
    for (int i = 0; i < n; i++) total += prices[i];
    printf("total: %.2f\n", total);
    return 0;
}
CF_E,
  <<<'CF_O'
total: 3.25
CF_O,
  '', 'shopping-cart-total'),
L('strings-c', 'Strings are byte arrays', 7,
  '<p>A C string is a <code>char</code> array ending with the invisible <code>\'\0\'</code> terminator. <code>string.h</code> gives you <code>strlen</code>, <code>strcpy</code>, <code>strcmp</code> — and walking with an index teaches real text handling.</p>',
  <<<'CF_E'
#include <stdio.h>
#include <string.h>

int main(void) {
    char name[] = "Ada Lovelace";
    for (int i = 0; name[i] != '\0'; i++) {
        if (name[i] == ' ') printf("space at %d\n", i);
    }
    printf("length: %zu\n", strlen(name));
    return 0;
}
CF_E,
  <<<'CF_O'
space at 3
length: 12
CF_O,
  '', 'username-gen'),
L('mini-expenses', 'Mini project: totals by hand', 12,
  '<p>No dictionaries here — the classic C pattern is parallel arrays (names[], amounts[]), matched by index. Clunky, explicit, and the reason collections libraries exist in every newer language.</p>',
  <<<'CF_E'
#include <stdio.h>

int main(void) {
    char *categories[] = {"food", "food", "books"};
    double amounts[] = {12.50, 9.00, 30.00};
    int n = 3;

    double total = 0;
    for (int i = 0; i < n; i++) total += amounts[i];
    printf("total: %.2f in %d receipts\n", total, n);
    printf("first category: %s\n", categories[0]);
    return 0;
}
CF_E,
  <<<'CF_O'
total: 51.50 in 3 receipts
first category: food
CF_O,
  '', 'sales-summary'),
]],
'cpp' => ['lessons' => [
L('values', 'Types + the STL safety net', 6,
  '<p>Modern C++ keeps C&rsquo;s honesty and adds ergonomic containers. <code>auto</code> lets the compiler deduce obvious types — less noise, same strictness.</p>',
  <<<'CF_E'
#include <iostream>

int main() {
    double price_per_latte = 4.50;
    int cups_sold = 6;

    auto revenue = price_per_latte * cups_sold;
    std::cout << "revenue: " << revenue << "\n";
    std::cout << "sold out? " << std::boolalpha << (cups_sold >= 50) << "\n";
}
CF_E,
  <<<'CF_O'
revenue: 27
sold out? false
CF_O,
  '', 'sum-even'),
L('functions', 'Small machines (functions)', 7,
  '<p>Return type, name, parameters — same grammar as C, plus references (<code>const string&amp;</code>) to pass big things without copying them.</p>',
  <<<'CF_E'
#include <iostream>

double apply_discount(double price, int percent_off) {
    return price * (1 - percent_off / 100.0);
}

int main() {
    std::cout << apply_discount(59.99, 20) << "\n";
}
CF_E,
  <<<'CF_O'
47.992
CF_O,
  '', 'best-time-stock'),
L('collections', 'vector and map', 8,
  '<p><code>std::vector</code> is the growing array; <code>std::map</code> is the ordered lookup. The STL is the reason C++ dominates competitive programming — algorithms come free.</p>',
  <<<'CF_E'
#include <iostream>
#include <vector>
#include <map>
#include <string>

int main() {
    std::vector<std::string> basket = {"apple", "milk", "apple"};
    std::map<std::string, double> prices = {{"apple", 0.5}, {"milk", 2.25}};

    double total = 0;
    for (const auto& item : basket) total += prices[item];
    std::cout << "total: " << total << "\n";
}
CF_E,
  <<<'CF_O'
total: 3.25
CF_O,
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p><code>std::string</code> is a real string object (finally). Streams split text into words with <code>&gt;&gt;</code>; building output with <code>stringstream</code> keeps number-to-text painless.</p>',
  <<<'CF_E'
#include <iostream>
#include <sstream>
#include <string>

int main() {
    std::string line = "Ada Lovelace";
    std::stringstream ss(line);
    std::string first, last;
    ss >> first >> last;
    std::cout << char(tolower(first[0])) << last << "\n";
}
CF_E,
  <<<'CF_O'
alovelace
CF_O,
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>A struct for the record, a map for the aggregation, a loop for the truth. Same pattern as everywhere — sharper tools.</p>',
  <<<'CF_E'
#include <iostream>
#include <vector>
#include <map>
#include <string>

struct Expense { std::string date, category; double amount; };

int main() {
    std::vector<Expense> rows = {{"07-01", "food", 12.5}, {"07-03", "books", 30.0}};
    std::map<std::string, double> by_category;
    for (const auto& e : rows) by_category[e.category] += e.amount;
    for (const auto& [cat, sum] : by_category)
        std::cout << cat << ": " << sum << "\n";
}
CF_E,
  <<<'CF_O'
books: 30
food: 12.5
CF_O,
  '', 'sales-summary'),
]],
];
