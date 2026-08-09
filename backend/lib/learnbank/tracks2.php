<?php
// Learn tracks 2/2: csharp, go, ruby, php, kotlin, rust
return [
'csharp' => ['lessons' => [
L('values', 'Strong types, gentle syntax', 6,
  '<p>C# feels familiar if you have seen Java or JavaScript. <code>var</code> lets the compiler infer obvious types; behind it, everything is still strongly typed. Interpolated strings (<code>$"..."</code>) make output readable.</p>',
  <<<'CF_E'
double pricePerLatte = 4.50;
var cupsSold = 6;

double revenue = pricePerLatte * cupsSold;
bool soldOut = cupsSold >= 50;
System.Console.WriteLine($"revenue: {revenue:0.00}");
System.Console.WriteLine($"sold out? {soldOut}");
CF_E,
  <<<'CF_O'
revenue: 27.00
sold out? False
CF_O,
  '', 'sum-even'),
L('methods', 'Small machines (methods)', 7,
  '<p>Methods declare exactly what goes in and comes out. C# naming favors PascalCase for methods. Expression-bodied methods (<code>=&gt;</code>) keep one-liners tidy.</p>',
  <<<'CF_E'
static double ApplyDiscount(double price, int percentOff)
    => price * (1 - percentOff / 100.0);

static string FormatMoney(double amount)
    => $"${amount:0.00}";

System.Console.WriteLine(FormatMoney(ApplyDiscount(59.99, 20)));
CF_E,
  <<<'CF_O'
$47.99
CF_O,
  '', 'best-time-stock'),
L('collections', 'List & Dictionary + LINQ', 8,
  '<p><code>List&lt;T&gt;</code> and <code>Dictionary&lt;K,V&gt;</code> are the workhorses, and LINQ turns aggregation into one-liners: <code>Sum</code>, <code>Where</code>, <code>GroupBy</code>. Reading LINQ fluently is a C# superpower.</p>',
  <<<'CF_E'
using System.Collections.Generic;
using System.Linq;

var basket = new List<string> { "apple", "milk", "apple" };
var prices = new Dictionary<string, double> { ["apple"] = 0.50, ["milk"] = 2.25 };

var total = basket.Sum(item => prices.GetValueOrDefault(item, 0));
System.Console.WriteLine($"total: {total:0.00}");
CF_E,
  <<<'CF_O'
total: 3.25
CF_O,
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p><code>Trim</code>, <code>Split</code>, <code>ToLower</code>, <code>string.Join</code> — the standard text toolbox. Strings are immutable; a <code>StringBuilder</code> helps in heavy loops.</p>',
  <<<'CF_E'
static string ToUsername(string displayName)
{
    var parts = displayName.Trim().ToLower().Split(' ');
    return parts[0][0] + parts[^1];
}

System.Console.WriteLine(ToUsername("Ada Lovelace"));
System.Console.WriteLine(ToUsername("Jean Van Damme"));
CF_E,
  <<<'CF_O'
alovelace
jvandamme
CF_O,
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>Records model the rows; one LINQ GroupBy builds the whole summary. This is the density professional C# aims for.</p>',
  <<<'CF_E'
record Expense(string Date, string Category, double Amount);

var rows = new[] {
    new Expense("07-01", "food", 12.50),
    new Expense("07-03", "books", 30.00),
};

foreach (var g in rows.GroupBy(e => e.Category))
    System.Console.WriteLine($"{g.Key}: {g.Sum(e => e.Amount):0.00}");
CF_E,
  <<<'CF_O'
food: 12.50
books: 30.00
CF_O,
  '', 'sales-summary'),
]],
'go' => ['lessons' => [
L('values', 'Small language, clear values', 6,
  '<p>Go has <em>few</em> ways to do things — on purpose. <code>:=</code> declares and assigns; <code>var</code> declares. Types come after names (<code>n int</code>). Unused variables are compile errors: no dead weight allowed.</p>',
  "package main\n\nimport \"fmt\"\n\nfunc main() {\n    pricePerLatte := 4.50\n    cupsSold := 6\n\n    revenue := pricePerLatte * float64(cupsSold)\n    fmt.Printf(\"revenue: %.2f\\n\", revenue)\n    fmt.Println(\"sold out?\", cupsSold >= 50)\n}",
  "revenue: 27.00\nsold out? false",
  '', 'sum-even'),
L('functions', 'Functions that return everything', 7,
  '<p>Go functions can return <strong>multiple values</strong> — the famous <code>(result, error)</code> pair. You check errors right where they happen; there are no exceptions.</p>',
  "package main\n\nimport \"fmt\"\n\nfunc applyDiscount(price float64, percentOff int) float64 {\n    return price * (1 - float64(percentOff)/100)\n}\n\nfunc main() {\n    fmt.Printf(\"%.2f\\n\", applyDiscount(59.99, 20))\n}",
  "47.992",
  '', 'best-time-stock'),
L('collections', 'Slices and maps', 8,
  '<p>A <code>slice</code> (<code>[]string</code>) is your dynamic list; a <code>map[string]float64</code> is the lookup. The comma-ok idiom safely asks "was that key there?"</p>',
  "package main\n\nimport \"fmt\"\n\nfunc main() {\n    basket := []string{\"apple\", \"milk\", \"apple\"}\n    prices := map[string]float64{\"apple\": 0.50, \"milk\": 2.25}\n\n    total := 0.0\n    for _, item := range basket {\n        total += prices[item] // missing keys give the zero value: 0\n    }\n    fmt.Printf(\"total: %.2f\\n\", total)\n}",
  "total: 3.25",
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines (strings package)', 7,
  '<p>The <code>strings</code> package covers daily text work: <code>TrimSpace</code>, <code>ToLower</code>, <code>Split</code>, <code>Join</code>. Ranges over strings walk characters, not bytes.</p>',
  "package main\n\nimport (\n    \"fmt\"\n    \"strings\"\n)\n\nfunc toUsername(displayName string) string {\n    parts := strings.Fields(strings.ToLower(displayName))\n    return parts[0][:1] + parts[len(parts)-1]\n}\n\nfunc main() {\n    fmt.Println(toUsername(\"  Ada Lovelace \"))\n}",
  "alovelace",
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>Structs model the data, maps aggregate it, Printf formats it. Boring, predictable — that is Go\'s entire sales pitch.</p>',
  "package main\n\nimport \"fmt\"\n\ntype Expense struct {\n    Date, Category string\n    Amount         float64\n}\n\nfunc main() {\n    rows := []Expense{{\"07-01\", \"food\", 12.50}, {\"07-03\", \"books\", 30.0}}\n    byCategory := map[string]float64{}\n    for _, e := range rows {\n        byCategory[e.Category] += e.Amount\n    }\n    fmt.Println(byCategory)\n}",
  "map[books:30 food:12.5]",
  '', 'sales-summary'),
]],
'ruby' => ['lessons' => [
L('values', 'Everything is an object', 6,
  '<p>In Ruby, even numbers are objects with methods (<code>4.5.round</code>). Variables are just names pointing at objects. Code reads almost like prose — that is by design.</p>',
  "price_per_latte = 4.50\ncups_sold = 6\n\nrevenue = price_per_latte * cups_sold\nsold_out = cups_sold >= 50\n\nputs \"revenue: #{revenue}\"\nputs \"sold out? #{sold_out}\"",
  "revenue: 27.0\nsold out? false",
  '', 'sum-even'),
L('methods', 'Small machines (methods)', 7,
  '<p>Methods start with <code>def</code> and return their last expression automatically — no <code>return</code> needed. Parentheses are optional; readability decides.</p>',
  "def apply_discount(price, percent_off)\n  price * (1 - percent_off / 100.0)\nend\n\ndef format_money(amount)\n  \"$#{'%.2f' % amount}\"\nend\n\nputs format_money(apply_discount(59.99, 20))",
  "$47.99",
  '', 'best-time-stock'),
L('collections', 'Arrays, hashes, and blocks', 8,
  '<p>Ruby blocks (<code>each</code>, <code>map</code>, <code>select</code>, <code>sum</code>) turn loops into sentences. Hashes use the modern <code>{ key: value }</code> style with symbol keys.</p>',
  "basket = ['apple', 'milk', 'apple']\nprices = { 'apple' => 0.50, 'milk' => 2.25 }\n\ntotal = basket.sum { |item| prices.fetch(item, 0) }\nputs \"total: #{total.round(2)}\"\nputs \"unique: #{basket.uniq.size}\"",
  "total: 3.25\nunique: 2",
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p>Ruby strings chain beautifully: <code>s.strip.downcase.split</code>. String interpolation <code>\"Hello #{name}\"</code> keeps building text pleasant.</p>',
  "def to_username(display_name)\n  parts = display_name.strip.downcase.split\n  parts[0][0] + parts[-1]\nend\n\nputs to_username('Ada Lovelace')\nputs to_username('  Grace Hopper ')",
  "alovelace\nghopper",
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p><code>each_with_object</code> and <code>group_by</code> make aggregation read like the business rule it is. This is Rails-grade idiomatic Ruby.</p>',
  "rows = [\n  ['2026-07-01', 'food', 12.50],\n  ['2026-07-03', 'books', 30.0],\n]\n\nby_category = rows.group_by { |_, cat, _| cat }\n                  .transform_values { |items| items.sum { |row| row[2] } }\nby_category.each { |cat, sum| puts \"#{cat}: #{sum.round(2)}\" }",
  "food: 12.5\nbooks: 30.0",
  '', 'sales-summary'),
]],
'php' => ['lessons' => [
L('values', 'The web’s original server language', 6,
  '<p>PHP variables start with <code>$</code>, strings concatenate with a dot (<code>.</code>), and code runs inside <code>&lt;?php</code> tags. This whole platform — pages, APIs, rooms — is exactly this language.</p>',
  <<<'CF_E'
<?php
$pricePerLatte = 4.50;
$cupsSold = 6;

$revenue = $pricePerLatte * $cupsSold;
$soldOut = $cupsSold >= 50 ? 'yes' : 'no';

echo "revenue: {$revenue}\n";
echo "sold out? {$soldOut}\n";
CF_E,
  <<<'CF_O'
revenue: 27
sold out? no
CF_O,
  '', 'sum-even'),
L('functions', 'Small machines (functions)', 7,
  '<p>Functions take typed parameters and a return type in modern PHP. <code>number_format</code> handles money display. Vanilla PHP got much nicer over the years.</p>',
  <<<'CF_E'
<?php
function applyDiscount(float $price, int $percentOff): float {
    return $price * (1 - $percentOff / 100);
}

echo number_format(applyDiscount(59.99, 20), 2) . "\n";
echo number_format(applyDiscount(200, 15), 2) . "\n";
CF_E,
  <<<'CF_O'
47.99
170.00
CF_O,
  '', 'best-time-stock'),
L('collections', 'Arrays that do everything', 8,
  '<p>PHP&rsquo;s array is list AND dictionary in one: <code>[\'apple\', \'milk\']</code> is a list, <code>[\'apple\' =&gt; 0.5]</code> is a map. <code>foreach ... as</code> walks it; <code>??</code> provides defaults. You use it so often it becomes muscle memory.</p>',
  <<<'CF_E'
<?php
$basket = ['apple', 'milk', 'apple'];
$prices = ['apple' => 0.50, 'milk' => 2.25];

$total = 0;
foreach ($basket as $item) {
    $total += $prices[$item] ?? 0;
}
echo "total: {$total}\n";
echo 'unique: ' . count(array_unique($basket)) . "\n";
CF_E,
  <<<'CF_O'
total: 3.25
unique: 2
CF_O,
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p><code>trim</code>, <code>strtolower</code>, <code>explode</code>, <code>implode</code> — PHP ships a huge text toolbox. The username pipeline needs exactly four of them.</p>',
  <<<'CF_E'
<?php
function toUsername(string $displayName): string {
    $parts = explode(' ', strtolower(trim($displayName)));
    return $parts[0][0] . end($parts);
}

echo toUsername('Ada Lovelace') . "\n";
echo toUsername('  Grace Hopper ') . "\n";
CF_E,
  <<<'CF_O'
alovelace
ghopper
CF_O,
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>This is real server-side work: rows from a query, aggregate with an array map, render a line per category — the exact pattern powering the pages you are looking at.</p>',
  <<<'CF_E'
<?php
$rows = [
    ['2026-07-01', 'food', 12.50],
    ['2026-07-03', 'books', 30.00],
];

$byCategory = [];
foreach ($rows as [$date, $cat, $amount]) {
    $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $amount;
}
foreach ($byCategory as $cat => $sum) {
    echo "{$cat}: " . number_format($sum, 2) . "\n";
}
CF_E,
  <<<'CF_O'
food: 12.50
books: 30.00
CF_O,
  '', 'sales-summary'),
]],
'kotlin' => ['lessons' => [
L('values', 'val and var', 6,
  '<p>Kotlin splits declarations into <code>val</code> (read-only, preferred) and <code>var</code> (changeable). Types come after the name (<code>name: String</code>) and usually infer away. String templates put code straight inside quotes.</p>',
  "fun main() {\n    val pricePerLatte = 4.50\n    var cupsSold = 0\n\n    cupsSold += 6\n    val revenue = pricePerLatte * cupsSold\n    println(\"revenue: \$revenue\")\n    println(\"sold out? \${cupsSold >= 50}\")\n}",
  "revenue: 27.0\nsold out? false",
  '', 'sum-even'),
L('functions', 'Small machines (fun)', 7,
  '<p><code>fun</code> declares functions; single-expression functions drop the braces entirely. Nullable types (<code>String?</code>) force you to handle "missing" explicitly — a whole bug class gone.</p>',
  "fun applyDiscount(price: Double, percentOff: Int) =\n    price * (1 - percentOff / 100.0)\n\nfun formatMoney(amount: Double) = \"$%.2f\".format(amount)\n\nfun main() {\n    println(formatMoney(applyDiscount(59.99, 20)))\n}",
  "$47.99",
  '', 'best-time-stock'),
L('collections', 'listOf and mapOf', 8,
  '<p>Immutable by default: <code>listOf</code> and <code>mapOf</code> cover daily needs. Chained operations (<code>filter</code>, <code>map</code>, <code>sumOf</code>) read like the rules themselves.</p>',
  "fun main() {\n    val basket = listOf(\"apple\", \"milk\", \"apple\")\n    val prices = mapOf(\"apple\" to 0.50, \"milk\" to 2.25)\n\n    val total = basket.sumOf { prices[it] ?: 0.0 }\n    println(\"total: \$total\")\n}",
  "total: 3.25",
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p>Kotlin strings chain like Ruby\'s: <code>trim().lowercase().split(\" \")</code>. The scope function <code>let</code> and safe calls (<code>?.</code>) keep pipelines null-safe.</p>',
  "fun toUsername(displayName: String): String {\n    val parts = displayName.trim().lowercase().split(\" \")\n    return parts[0].take(1) + parts.last()\n}\n\nfun main() {\n    println(toUsername(\"Ada Lovelace\"))\n    println(toUsername(\"Jean Van Damme\"))\n}",
  "alovelace\njvandamme",
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>A <code>data class</code> for the record, <code>groupBy</code> + <code>sumOf</code> for the aggregation — production Kotlin is refreshingly compact.</p>',
  "data class Expense(val date: String, val category: String, val amount: Double)\n\nfun main() {\n    val rows = listOf(\n        Expense(\"07-01\", \"food\", 12.50),\n        Expense(\"07-03\", \"books\", 30.0),\n    )\n    rows.groupBy { it.category }\n        .forEach { (cat, list) -> println(\"\$cat: \${list.sumOf { it.amount }}\") }\n}",
  "food: 12.5\nbooks: 30.0",
  '', 'sales-summary'),
]],
'rust' => ['lessons' => [
L('values', 'Ownership, briefly', 6,
  '<p>Rust bindings are immutable by default (<code>let x = 5</code>), mutable only when you ask (<code>let mut</code>). Values have one <em>owner</em> — the borrow checker then guarantees no dangling references at compile time.</p>',
  "fn main() {\n    let price_per_latte = 4.50;\n    let mut cups_sold = 0;\n\n    cups_sold += 6;\n    let revenue = price_per_latte * cups_sold as f64;\n    println!(\"revenue: {:.2}\", revenue);\n    println!(\"sold out? {}\", cups_sold >= 50);\n}",
  "revenue: 27.00\nsold out? false",
  '', 'sum-even'),
L('functions', 'Small machines (fn)', 7,
  '<p>Functions declare parameter and return types; the last expression without a semicolon is the return value. Types are explicit at function boundaries — inference stays inside.</p>',
  "fn apply_discount(price: f64, percent_off: i32) -> f64 {\n    price * (1.0 - percent_off as f64 / 100.0)\n}\n\nfn main() {\n    println!(\"{:.2}\", apply_discount(59.99, 20));\n}",
  "47.99",
  '', 'best-time-stock'),
L('collections', 'Vec and HashMap', 8,
  '<p><code>vec!</code> builds growable lists; <code>HashMap</code> joins via the standard collections. Iterators (<code>.iter().map().sum()</code>) feel modern while compiling to zero-cost loops.</p>',
  "use std::collections::HashMap;\n\nfn main() {\n    let basket = vec![\"apple\", \"milk\", \"apple\"];\n    let prices = HashMap::from([(\"apple\", 0.50), (\"milk\", 2.25)]);\n\n    let total: f64 = basket.iter().map(|i| prices.get(i).unwrap_or(&0.0)).sum();\n    println!(\"total: {:.2}\", total);\n}",
  "total: 3.25",
  '', 'shopping-cart-total'),
L('strings-loops', 'Text pipelines', 7,
  '<p>Text arrives as <code>&str</code>; <code>split_whitespace</code>, <code>to_lowercase</code>, and <code>collect</code> do the pipeline work. Rust makes the string\'s memory story visible — you always know who owns it.</p>',
  "fn to_username(display_name: &str) -> String {\n    let parts: Vec<&str> = display_name.split_whitespace().collect();\n    let first = parts[0].chars().next().unwrap();\n    format!(\"{}{}\", first.to_lowercase(), parts[parts.len() - 1].to_lowercase())\n}\n\nfn main() {\n    println!(\"{}\", to_username(\"Ada Lovelace\"));\n}",
  "alovelace",
  '', 'username-gen'),
L('mini-expenses', 'Mini project: expense report', 12,
  '<p>A struct, a Vec, a HashMap fold — and the compiler proves the whole thing memory-safe. This is why people stick with Rust after the fight.</p>',
  "use std::collections::HashMap;\n\nstruct Expense { category: String, amount: f64 }\n\nfn main() {\n    let rows = vec![\n        Expense { category: \"food\".into(), amount: 12.5 },\n        Expense { category: \"books\".into(), amount: 30.0 },\n    ];\n    let mut by_category: HashMap<String, f64> = HashMap::new();\n    for e in rows {\n        *by_category.entry(e.category).or_insert(0.0) += e.amount;\n    }\n    println!(\"{:?}\", by_category);\n}",
  "{\"books\": 30.0, \"food\": 12.5}",
  '', 'sales-summary'),
]],
];
