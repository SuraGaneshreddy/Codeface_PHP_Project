<?php
// Learn tracks 4/4: three learn-only tracks (not practice languages):
// SQL, Bash (shell), and HTML & CSS. 8 lessons each, real-world scenarios.
return [

'sql' => ['lessons' => [
L('select', 'Talk to the table (SELECT)', 6,
  '<p>Every SQL conversation starts the same way: <code>SELECT</code> which columns, <code>FROM</code> which table. You are not moving data around — you are <em>describing the result you want</em>, and the database figures out how to get it. That declarative mindset is the whole game.</p>',
  <<<'CF_E'
CREATE TABLE products (name TEXT, category TEXT, price REAL, stock INTEGER);
INSERT INTO products VALUES
  ('latte', 'drink', 4.50, 40),
  ('muffin', 'food', 3.25, 12),
  ('tea', 'drink', 2.75, 30);

SELECT name, price FROM products;
CF_E,
  <<<'CF_O'
latte|4.5
muffin|3.25
tea|2.75
CF_O,
  '',
  'sum-even'),
L('where', 'Filter like you mean it (WHERE)', 7,
  '<p><code>WHERE</code> is the bouncer: only rows matching the predicate get through. Combine conditions with <code>AND</code>/<code>OR</code> (and parentheses — operator precedence bites), compare text exactly with <code>=</code>, loosely with <code>LIKE \'%cad%\'</code>, sets with <code>IN</code>.</p>',
  <<<'CF_E'
SELECT name, price FROM products
WHERE category = 'drink';

SELECT name FROM products
WHERE price < 4.00 AND stock >= 20;

SELECT name FROM products WHERE name LIKE 'la%';
CF_E,
  <<<'CF_O'
latte|4.5
tea|2.75
tea
latte
CF_O,
  '',
  'log-level-filter'),
L('order-limit', 'Sort and paginate (ORDER BY, LIMIT)', 7,
  '<p>Tables have no inherent order — if you want “top 5”, you must say so: <code>ORDER BY price DESC</code> sorts, <code>LIMIT 5 OFFSET 10</code> pages. Every leaderboard, feed, and “recent activity” list you have ever built is exactly this query.</p>',
  <<<'CF_E'
SELECT name, price FROM products
ORDER BY price DESC
LIMIT 2;

SELECT name, stock FROM products
ORDER BY stock ASC, name ASC
LIMIT 2;
CF_E,
  <<<'CF_O'
latte|4.5
muffin|3.25
muffin|12
tea|30
CF_O,
  '',
  'top-k-frequent'),
L('aggregates', 'Add it all up (GROUP BY)', 8,
  '<p>One row of raw data tells a story; a million rows tell nothing until you aggregate. <code>COUNT</code>, <code>SUM</code>, <code>AVG</code>, <code>MIN</code>, <code>MAX</code> collapse rows, and <code>GROUP BY category</code> does it <em>per group</em>. Filter groups (not rows) with <code>HAVING</code>.</p>',
  <<<'CF_E'
SELECT category,
       COUNT(*) AS items,
       ROUND(AVG(price), 2) AS avg_price,
       SUM(stock) AS total_stock
FROM products
GROUP BY category
HAVING COUNT(*) >= 1
ORDER BY total_stock DESC;
CF_E,
  <<<'CF_O'
drink|2|3.63|70
food|1|3.25|12
CF_O,
  '',
  'group-anagrams'),
L('joins', 'Connect the dots (JOIN)', 9,
  '<p>Real data is normalized — split across tables to avoid duplication. <code>JOIN ... ON</code> stitches it back together at query time: orders know a <code>customer_id</code>, customers know the name, and the join produces “who bought what” without storing names on every order.</p>',
  <<<'CF_E'
CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE orders (id INTEGER PRIMARY KEY, customer_id INTEGER, total REAL, status TEXT);
INSERT INTO customers VALUES (1, 'ada'), (2, 'grace');
INSERT INTO orders VALUES (101, 1, 12.50, 'paid'), (102, 2, 4.50, 'pending'), (103, 1, 9.99, 'paid');

SELECT c.name, o.id, o.total
FROM orders o
JOIN customers c ON c.id = o.customer_id
WHERE o.status = 'paid'
ORDER BY o.id;
CF_E,
  <<<'CF_O'
ada|101|12.5
ada|103|9.99
CF_O,
  '',
  'meeting-overlap'),
L('modify', 'Change the world (INSERT, UPDATE, DELETE)', 8,
  '<p>Reads are safe; writes are where careers are made. <code>UPDATE products SET stock = stock - 2 WHERE name = \'latte\'</code> — and notice the <code>WHERE</code>: forget it and you discounted <em>every row</em>. Rule one of production databases: write the <code>WHERE</code> first, run a <code>SELECT</code> with it, then update.</p>',
  <<<'CF_E'
UPDATE products SET stock = stock - 2 WHERE name = 'latte';
INSERT INTO products VALUES ('cookie', 'food', 2.50, 24);
DELETE FROM products WHERE stock = 0;

SELECT name, stock FROM products ORDER BY rowid;
CF_E,
  <<<'CF_O'
latte|38
muffin|12
tea|30
cookie|24
CF_O,
  '',
  'restock-alerts'),
L('subqueries', 'Queries inside queries', 8,
  '<p>A subquery is a <code>SELECT</code> used as a value: “all products priced above average” is <code>WHERE price &gt; (SELECT AVG(price) FROM products)</code>. <code>EXISTS</code> tests relationships without duplicating rows. Read them inside-out: innermost query first.</p>',
  <<<'CF_E'
SELECT name, price FROM products
WHERE price > (SELECT AVG(price) FROM products);

SELECT c.name
FROM customers c
WHERE EXISTS (
    SELECT 1 FROM orders o
    WHERE o.customer_id = c.id AND o.status = 'pending'
);
CF_E,
  <<<'CF_O'
latte|4.5
grace
CF_O,
  '',
  'max-avg-subarray'),
L('mini-report', 'Mini project: best-seller report', 12,
  '<p>The question every owner asks: <em>what earns the most?</em> One query — join orders to products, aggregate per item, sort by revenue — and the database answers. This is the SQL you will actually write at work, and it runs identically on this app&rsquo;s schema.</p>',
  <<<'CF_E'
CREATE TABLE products (name TEXT, price REAL);
CREATE TABLE orders (id INTEGER, product_name TEXT, qty INTEGER);
INSERT INTO products VALUES ('latte', 4.50), ('tea', 2.75), ('muffin', 3.25);
INSERT INTO orders VALUES
  (1, 'latte', 20), (2, 'tea', 30), (3, 'latte', 15), (4, 'muffin', 8);

SELECT p.name,
       SUM(o.qty) AS units,
       ROUND(SUM(o.qty * p.price), 2) AS revenue
FROM orders o
JOIN products p ON p.name = o.product_name
GROUP BY p.name
ORDER BY revenue DESC
LIMIT 1;
CF_E,
  <<<'CF_O'
latte|35|157.5
CF_O,
  '',
  'sales-summary'),
]],

'bash' => ['lessons' => [
L('variables', 'Variables & the quoting trap', 7,
  '<p>Bash variables hold strings, full stop. Assign with <code>name=value</code> (no spaces!), read with <code>$name</code>, and <strong>quote expansions</strong> (<code>"$file"</code>) so spaces do not split your values into extra arguments. Bash does integer math in <code>$(( ))</code>; decimals need <code>awk</code> or <code>bc</code>.</p>',
  <<<'CF_E'
#!/bin/bash
name="Ada"
cups=12
price=4.50

echo "hello, $name"
echo "total: $cups cups"

# awk is the standard tool for decimal math in scripts
revenue=$(awk "BEGIN { print $cups * $price }")
echo "revenue: \$$revenue"
CF_E,
  <<<'CF_O'
hello, Ada
total: 12 cups
revenue: $54
CF_O,
  '',
  'sum-even'),
L('pipes', 'Pipes: small tools, big pipelines', 8,
  '<p>The Unix philosophy in one character: <code>|</code> connects a program&rsquo;s output to the next one&rsquo;s input. <code>grep</code> filters lines, <code>wc -l</code> counts them, <code>sort</code>, <code>uniq -c</code>, <code>head</code> shape them. Chained together they replace entire programs — this is the original data pipeline.</p>',
  <<<'CF_E'
# app.log:
#   INFO boot | ERROR disk full | INFO ok | ERROR auth failed | WARN slow

grep ERROR app.log | wc -l     # count the errors

sort app.log | uniq -c | sort -rn | head -3   # most common lines
CF_E,
  <<<'CF_O'
2
      1 WARN slow
      1 INFO ok
      1 INFO boot
CF_O,
  '',
  'log-level-filter'),
L('loops', 'Loop over files & lines', 8,
  '<p>Globs make <code>for</code> loops over files trivial: <code>for f in logs/*.log</code>. The classic gotchas: quote <code>"$f"</code> (filenames have spaces), and remember the loop body runs once <em>per item</em> — guard clauses inside the loop are normal, not lazy.</p>',
  <<<'CF_E'
for file in app.log access.log auth.log; do
  echo "$file: $(grep -c ERROR "$file") errors"
done

i=1
while [ $i -le 3 ]; do
  echo "retry $i"
  i=$((i + 1))
done
CF_E,
  <<<'CF_O'
app.log: 2 errors
access.log: 0 errors
auth.log: 5 errors
retry 1
retry 2
retry 3
CF_O,
  '',
  'retry-backoff-schedule'),
L('conditionals', 'Tests with [ ] and friends', 8,
  '<p><code>if [ "$stock" -lt 5 ]</code> runs the <code>test</code> builtin: <code>-lt -le -eq -ge -gt</code> compare numbers, <code>= !=</code> compare strings, and <code>-f file</code> / <code>-d dir</code> / <code>-z str</code> probe the filesystem. Those spaces inside the brackets are mandatory — <code>[ -f x ]</code> works, <code>[-f x]</code> is a syntax error.</p>',
  <<<'CF_E'
stock=4
if [ "$stock" -lt 5 ]; then
  echo "reorder: only $stock left"
elif [ "$stock" -lt 20 ]; then
  echo "stock ok: $stock"
else
  echo "plenty: $stock"
fi

[ -f app.log ] && echo "log file exists"
CF_E,
  <<<'CF_O'
reorder: only 4 left
log file exists
CF_O,
  '',
  'restock-alerts'),
L('functions-args', 'Functions, args & defaults', 8,
  '<p>Functions take positional args — <code>$1</code>, <code>$2</code> — and so does the script itself. <code>$#</code> is the count, <code>$@</code> is the whole list, and <code>${1:-default}</code> supplies a fallback. This is how one <code>deploy.sh v2.1 prod</code> command stays safe for a hundred different callers.</p>',
  <<<'CF_E'
#!/bin/bash
greet() {
  echo "deploying $1 to $2"
}

greet "v2.1" "prod"

echo "script got $# args"
echo "first arg: $1"
CF_E,
  <<<'CF_O'
deploying v2.1 to prod
script got 2 args
first arg: v2.1
CF_O,
  '',
  'pick-route-distance'),
L('text-tools', 'awk: the spreadsheet in your terminal', 9,
  '<p>When data is columns, <code>awk</code> is unbeatable: <code>awk -F, \'{s[$2]+=$3} END {for (k in s) print s[k], k}\'</code> sums column 3 grouped by column 2 — a full <code>GROUP BY</code> without a database. Pair with <code>cut</code> (pick columns) and <code>sort -rn</code> (numeric, descending) and you have an analytics stack.</p>',
  <<<'CF_E'
# orders.csv: who,what,qty
#   ada,latte,2 | grace,tea,1 | ada,latte,3 | hopper,muffin,4

awk -F, '{s[$2]+=$3} END {for (k in s) print s[k], k}' orders.csv \
  | sort -rn \
  | head -2     # top sellers by units
CF_E,
  <<<'CF_O'
5 latte
4 muffin
CF_O,
  '',
  'sales-summary'),
L('exit-codes', 'Exit codes: the shell’s booleans', 8,
  '<p>Every command returns 0 (success) or non-zero (failure) — that is <em>all</em> the shell&rsquo;s <code>if</code>/</code>&amp;&amp;</code>/</code>||</code> look at. <code>$?</code> holds the last code, <code>&amp;&amp;</code> short-circuits on failure, <code>||</code> on success, and <code>set -e</code> makes a script die at the first error instead of plowing ahead on broken state.</p>',
  <<<'CF_E'
deploy() { echo "deploying..."; return 1; }

if deploy; then
  echo "SUCCESS"
else
  echo "FAILED — rolling back (exit $?)"
fi

false && echo "never prints"
true  || echo "never prints either"
CF_E,
  <<<'CF_O'
deploying...
FAILED — rolling back (exit 1)
CF_O,
  '',
  'rate-allow'),
L('mini-backup', 'Mini project: nightly backup script', 12,
  '<p>The ops staple: timestamp a folder, copy every database, count what you did, fail loudly. Every production cron job in the world is basically this script plus monitoring.</p>',
  <<<'CF_E'
#!/bin/bash
set -e   # stop at the first failure — backups must not half-run

SRC=${1:-data}
DEST="backups/$(date +%F)"
mkdir -p "$DEST"

count=0
for db in "$SRC"/*.sqlite; do
  cp "$db" "$DEST/"
  count=$((count + 1))
  echo "backed up $db"
done
echo "done: $count file(s) -> $DEST"
CF_E,
  <<<'CF_O'
backed up data/app.sqlite
backed up data/logs.sqlite
done: 2 file(s) -> backups/2026-08-04
CF_O,
  '',
  'log-rotate-names'),
]],

'htmlcss' => ['lessons' => [
L('anatomy', 'Anatomy of a page', 6,
  '<p>Every page is the same skeleton: <code>&lt;!DOCTYPE html&gt;</code>, an <code>&lt;html&gt;</code> element with a <code>&lt;head&gt;</code> (metadata — title, charset, CSS links) and a <code>&lt;body&gt;</code> (what users see). The browser parses top to bottom and builds the DOM tree your CSS and JS then act on.</p>',
  <<<'CF_E'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Café Codeface</title>
</head>
<body>
  <h1>Café Codeface</h1>
  <p>Coffee &amp; algorithms, open daily.</p>
</body>
</html>
CF_E,
  <<<'CF_O'
(renders: browser tab titled "Café Codeface"; the page shows a large
heading "Café Codeface" and a paragraph "Coffee & algorithms, open daily.")
CF_O,
  '',
  'sum-even'),
L('text-links', 'Text, links & images', 7,
  '<p>Semantic tags carry meaning, not just looks: <code>&lt;h1&gt;</code>–<code>&lt;h6&gt;</code> outline the page for screen readers and search engines, <code>&lt;a href&gt;</code> links, <code>&lt;img alt&gt;</code> describes images for everyone. Choose tags by <em>what the content is</em>, style it later with CSS.</p>',
  <<<'CF_E'
<h2>Today’s special</h2>
<p>Try the <strong>oat latte</strong> — <em>smaller footprint, bigger flavor</em>.</p>

<a href="/menu.html">Full menu</a>
<img src="latte.jpg" alt="A latte with leaf latte art" width="200">

<p>Coded with ☕ by <a href="https://example.com/devs">the baristas</a>.</p>
CF_E,
  <<<'CF_O'
(renders: a heading, a paragraph with bold "oat latte" and italic tagline,
a "Full menu" link, a 200px-wide photo, and a footer line with two links.)
CF_O,
  '',
  'markdown-headings'),
L('lists-tables', 'Lists & tables', 7,
  '<p><code>&lt;ul&gt;</code>/<code>&lt;ol&gt;</code> group related items; <code>&lt;table&gt;</code> is for <em>data</em> (prices, schedules) — never for page layout. <code>&lt;th&gt;</code> marks header cells; <code>colspan</code>/<code>rowspan</code> merge cells. Browsers, readers, and scraping bots all thank you for real tables.</p>',
  <<<'CF_E'
<h3>Menu</h3>
<ul>
  <li>Latte</li>
  <li>Tea</li>
</ul>

<table border="1">
  <tr><th>Item</th><th>Price</th><th>Stock</th></tr>
  <tr><td>Latte</td><td>$4.50</td><td>40</td></tr>
  <tr><td>Muffin</td><td>$3.25</td><td>12</td></tr>
</table>
CF_E,
  <<<'CF_O'
(renders: a bulleted 2-item list, then a bordered 3×3 table
with bold header row: Item / Price / Stock.)
CF_O,
  '',
  'shopping-cart-total'),
L('forms', 'Forms: how the web talks back', 8,
  '<p>A <code>&lt;form&gt;</code> bundles inputs and submits them wherever <code>action</code> points, using <code>method</code> (GET puts data in the URL, POST in the body). Give every control a <code>name</code> — that is the key on the server — and a <code>&lt;label for&gt;</code> so clicking the label focuses the field. Codeface&rsquo;s login page is exactly this.</p>',
  <<<'CF_E'
<form action="/order" method="post">
  <label for="item">Drink</label>
  <input id="item" name="item" type="text" required placeholder="latte">

  <label for="qty">Cups</label>
  <input id="qty" name="qty" type="number" min="1" value="1">

  <button type="submit">Place order</button>
</form>
CF_E,
  <<<'CF_O'
(renders: two labeled fields — "Drink" (required text, gray placeholder
"latte") and "Cups" (number, starts at 1) — and a "Place order" button.)
CF_O,
  '',
  'phone-normalizer'),
L('selectors', 'CSS selectors & the box model', 8,
  '<p>CSS = <em>selector</em> + <em>declarations</em>. <code>.class</code> (reusable), <code>#id</code> (one per page), <code>tag</code>, and combos like <code>.card p</code>. Every element is a box — content + <code>padding</code> + <code>border</code> + <code>margin</code> — and <code>box-sizing: border-box</code> makes widths behave the way you expect.</p>',
  <<<'CF_E'
<style>
  * { box-sizing: border-box; }
  .special {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
    padding: 8px 12px;
    margin: 12px 0;
  }
  .price { color: #16a34a; font-weight: bold; }
</style>

<div class="special">
  Today: oat latte <span class="price">$3.90</span>
</div>
CF_E,
  <<<'CF_O'
(renders: a pale-yellow banner with an amber left border containing
"Today: oat latte $3.90", the price in bold green.)
CF_O,
  '',
  'discount-tier'),
L('flexbox', 'Flexbox: one-dimensional layout', 9,
  '<p><code>display: flex</code> lines children up on one axis and solves the problems that used to require hacks: <code>justify-content</code> distributes along the row, <code>align-items</code> cross-wise, <code>gap</code> spaces children evenly, <code>flex: 1</code> makes one item absorb the slack. Navbars, card rows, centered modals — all flexbox.</p>',
  <<<'CF_E'
<style>
  .nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 12px 20px;
    background: #1e293b;
    color: white;
  }
  .nav a { color: #cbd5e1; text-decoration: none; }
</style>

<nav class="nav">
  <strong>Café Codeface</strong>
  <span><a href="/menu">Menu</a> · <a href="/about">About</a></span>
</nav>
CF_E,
  <<<'CF_O'
(renders: a dark slate bar; brand pinned left, the Menu and About
links pinned right, everything vertically centered.)
CF_O,
  '',
  'best-time-stock'),
L('grid', 'Grid: two-dimensional layout', 9,
  '<p>Flexbox lines items up; <strong>Grid</strong> designs the whole canvas: define columns (<code>grid-template-columns: 200px 1fr</code>) and rows, place children by line or name, and <code>grid-template-areas</code> lets you draw the layout in ASCII. Use Grid for the page frame, Flexbox inside each region.</p>',
  <<<'CF_E'
<style>
  .page {
    display: grid;
    grid-template-columns: 200px 1fr;
    grid-template-rows: 60px 1fr 40px;
    grid-template-areas:
      "header header"
      "side   main"
      "footer footer";
    min-height: 100vh;
  }
  header { grid-area: header; background: #6366f1; color: #fff; }
  aside  { grid-area: side;   background: #eef2ff; }
  main   { grid-area: main;   padding: 20px; }
  footer { grid-area: footer; background: #f1f5f9; }
</style>

<div class="page">
  <header>Café Codeface</header>
  <aside>menu · about · hours</aside>
  <main><h2>Today’s brews</h2></main>
  <footer>© 2026</footer>
</div>
CF_E,
  <<<'CF_O'
(renders: indigo header bar across the top, a 200px sidebar left of the
main content, and a footer pinned at the bottom.)
CF_O,
  '',
  'matrix-block-sum'),
L('mini-menu', 'Mini project: café menu page', 13,
  '<p>Everything together: semantic HTML, a styled table, flexbox header, responsive grid of cards. This is a complete, real page — the same techniques build every marketing site on the internet.</p>',
  <<<'CF_E'
<style>
  body { font-family: system-ui, sans-serif; margin: 0; }
  .nav { display: flex; justify-content: space-between;
         padding: 14px 24px; background: #1e293b; color: #fff; }
  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
           gap: 16px; padding: 24px; }
  .card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
  .price { color: #16a34a; font-weight: bold; }
</style>

<nav class="nav"><strong>☕ Café Codeface</strong><span>menu</span></nav>
<section class="cards">
  <div class="card"><h3>Latte</h3><p>Oat or dairy.</p><span class="price">$4.50</span></div>
  <div class="card"><h3>Tea</h3><p>Jasmine green.</p><span class="price">$2.75</span></div>
  <div class="card"><h3>Muffin</h3><p>Blueberry.</p><span class="price">$3.25</span></div>
</section>
CF_E,
  <<<'CF_O'
(renders: a dark navbar with the café brand, then a responsive row of
three rounded cards — each with a name, one-line description, and green
price. Shrink the window and the cards wrap into a single column.)
CF_O,
  '',
  'pick-route-distance'),
]],
];
