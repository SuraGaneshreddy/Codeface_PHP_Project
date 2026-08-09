<?php
declare(strict_types=1);

/**
 * 🤖 AI practice generator — offline procedural content engine.
 *
 * When a user clears an entire section (all 526 problems / all 6 labs /
 * all 6 refactor challenges), this engine automatically builds a fresh set
 * of 10 items *for that user*. No external API calls — works offline on
 * XAMPP: expert-authored templates are parameterized by a seeded PRNG, and
 * every generated item carries its own oracle-built tests (machine-verified
 * by the /tmp/verify_ai.js harness in CI: reference solutions pass every
 * generated test, lab fixes turn every task green, refactor fixes raise the
 * cleanup score above 90).
 *
 * Determinism: everything derives from (user_id, section, batch). The same
 * user always regenerates byte-identical labs/refactors (zero extra tables),
 * while generated problems are materialized once into `problems` rows with
 * ai_user_id set (solver page, submissions, points and ratings reuse the
 * existing machinery untouched).
 */

require_once __DIR__ . '/emitters.php';

/* ============================ seeded PRNG ============================ */

/** glibc-style LCG — deterministic across platforms/versions (int64-safe). */
function cf_ai_rng(string $key): Closure {
    $s = crc32('codeface-ai.v1:' . $key) & 0x7fffffff;
    return function () use (&$s) {
        $s = ($s * 1103515245 + 12345) % 2147483648;
        return $s / 2147483648;
    };
}
function cf_ai_int(Closure $r, int $lo, int $hi): int {
    return $lo + (int)floor($r() * ($hi - $lo + 1));
}
function cf_ai_pick(Closure $r, array $xs) {
    return $xs[cf_ai_int($r, 0, count($xs) - 1)];
}
function cf_ai_shuffle(Closure $r, array $xs): array {
    for ($i = count($xs) - 1; $i > 0; $i--) {
        $j = cf_ai_int($r, 0, $i);
        [$xs[$i], $xs[$j]] = [$xs[$j], $xs[$i]];
    }
    return $xs;
}

/* ====== metrics port — EXACT parity with assets/js/metrics.js (analyzeFiles) ====== */

function cf_ai_strip_comments(string $code): string {
    $code = preg_replace('#/\*[\s\S]*?\*/#', '', $code);
    $code = preg_replace('#(^|[^:"\'`])//[^\n]*#', '$1', $code); // JS: no m-flag (^ = subject start)
    return $code;
}
function cf_ai_metrics(string $code): array {
    $c = cf_ai_strip_comments($code);
    $comp = 1;
    $comp += preg_match_all('/\bif\b/', $c);
    $comp += preg_match_all('/\bfor\b/', $c);
    $comp += preg_match_all('/\bwhile\b/', $c);
    $comp += preg_match_all('/\bcase\b/', $c);
    $comp += preg_match_all('/\bcatch\b/', $c);
    $comp += preg_match_all('/&&|\|\|/', $c);
    $comp += preg_match_all('/\?[^.:]/', $c);
    $loc = 0;
    foreach (preg_split('/\n/', $c) as $ln) if (trim($ln) !== '') $loc++;
    $s = preg_replace('/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|`(?:\\\\.|[^`\\\\])*`/', '""', $c);
    $d = 0; $max = 0;
    $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        if ($s[$i] === '{') { $d++; if ($d > $max) $max = $d; }
        elseif ($s[$i] === '}') { $d = max(0, $d - 1); }
    }
    $seen = []; $dup = 0; $total = 0;
    foreach (preg_split('/\n/', $c) as $ln) {
        $t = trim($ln);
        if (strlen($t) < 10 || $t === '}' || $t === '{}') continue;
        $total++;
        if (isset($seen[$t])) $dup++; else $seen[$t] = true;
    }
    $dupPct = $total ? (int)round($dup / $total * 100) : 0;
    $cryptic = 0;
    if (preg_match_all('/\b(?:var|let|const)\s+([a-z])\b/', $c, $mm)) {
        foreach ($mm[1] as $v) if (!in_array($v, ['i', 'j', 'k', 'x', 'y'], true)) $cryptic++;
    }
    // long functions (brace matching from each 'function' keyword)
    $long = 0; $idx = 0;
    while (($at = strpos($c, 'function', $idx)) !== false) {
        $open = strpos($c, '{', $at);
        if ($open === false) break;
        $depth = 0; $end = $open; $len = strlen($c);
        for ($j = $open; $j < $len; $j++) {
            if ($c[$j] === '{') $depth++;
            elseif ($c[$j] === '}') { $depth--; if ($depth === 0) { $end = $j; break; } }
        }
        $startLine = substr_count(substr($c, 0, $at), "\n") + 1;
        $endLine   = substr_count(substr($c, 0, $end), "\n") + 1;
        if ($endLine - $startLine + 1 > 25) $long++;
        $idx = $end + 1;
    }
    return ['loc' => $loc, 'comp' => $comp, 'depth' => $max, 'dup' => $dupPct, 'cryptic' => $cryptic, 'longFns' => $long];
}

/* ============================ PROBLEM TEMPLATES ============================ */
/* Each returns a pdef-compatible spec with 'category' => 'ai'.               */

/** Slug helper */
function cf_ai_pslug(int $uid, int $batch, int $n, string $key): string {
    return "aip{$uid}b{$batch}-{$n}-{$key}";
}

/** The generator for one template instance. $r = rng stream. */
function cf_ai_gen_spec(string $key, Closure $r): array {
    switch ($key) {

    case 'coupon-final': {
        $m = cf_ai_pick($r, [399, 499, 599, 799, 999]);
        $pts = cf_ai_pick($r, [5, 10, 15, 20]);
        $flt = cf_ai_pick($r, [40, 50, 75, 100, 125]);
        $mkSave = function (int $base, int $pct): int { return $base - intdiv($base * $pct, 100); };
        $solve = "function finalPrice(base, coupon) {
  var m = coupon.match(/^SAVE(\\d+)\$/);
  if (m) return base - Math.floor(base * Number(m[1]) / 100);
  var f = coupon.match(/^FLAT(\\d+)\$/);
  if (f) return base >= {$m} ? base - Number(f[1]) : base;
  return base;
}";
        $oracle = function (int $base, string $coupon) use ($m) {
            if (preg_match('/^SAVE(\d+)$/', $coupon, $mm)) return $base - intdiv($base * (int)$mm[1], 100);
            if (preg_match('/^FLAT(\d+)$/', $coupon, $mm)) return $base >= $m ? $base - (int)$mm[1] : $base;
            return $base;
        };
        $mHigh = max($m, 200) + 500;
        $cases = [
            [$mHigh, "SAVE{$pts}"],
            [$m + 100, "FLAT{$flt}"],
            [$m, "FLAT{$flt}"],
            [$m - 1, "FLAT{$flt}"],
            [200, "FLAT{$flt}"],
            [cf_ai_int($r, 100, 900), 'xyz'.cf_ai_int($r, 1, 9)],
            [cf_ai_int($r, 600, 1500), 'SAVE'.cf_ai_pick($r, [0, 1])], // SAVE0/SAVE1 edges
            [$m + cf_ai_int($r, 0, 400), ''],
        ];
        $tests = [];
        foreach ($cases as $i => [$b, $c]) $tests[] = t([$b, $c], $oracle($b, $c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'coupon-final', 'title' => 'Coupon Final Price', 'difficulty' => 'easy',
            'fn' => 'finalPrice', 'sig' => sig(['base' => 'int', 'coupon' => 'str'], 'int'),
            'blurb' => "A store applies at most one coupon: <code>SAVE&lt;p&gt;</code> takes <em>p%</em> off (floored); <code>FLAT&lt;f&gt;</code> takes <em>₹f</em> off but only when the base is ₹{$m} or more; anything else changes nothing.",
            'constraints' => ['0 ≤ base ≤ 10^6', 'coupon matches /^(SAVE|FLAT)\d+$/ or is empty/garbage', 'return value is an integer number of rupees'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Extend so FLAT coupons never push the price below ₹1.',
        ];
    }

    case 'make-slug': {
        $suffixes = ['', 'v2', 'draft', 'in'];
        $solve = "function makeSlug(title, suffix) {
  var s = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+\$/g, '');
  return suffix ? s + '-' + suffix : s;
}";
        $oracle = function (string $title, string $suffix): string {
            $s = strtolower($title);
            $s = preg_replace('/[^a-z0-9]+/', '-', $s);
            $s = preg_replace('/^-+|-+$/', '', $s);
            return $suffix !== '' ? $s . '-' . $suffix : $s;
        };
        $titles = [
            'My First Blog Post', '  Hello   World ', 'GST Filing: 2026 Guide!',
            '---Crazy--Dashes---', 'A1 Status Report', 'launch (beta) notes',
            'Weekly Sync: Mondays @ 9', 'Price of Mango per Kg',
        ];
        $tests = [];
        foreach ($titles as $i => $ti) {
            $suf = $i < 4 ? $suffixes[$i % 4] : cf_ai_pick($r, $suffixes);
            $tests[] = t([$ti, $suf], $oracle($ti, $suf), $i < 3 ? 1 : 0);
        }
        return [
            'slug' => '', 'key' => 'make-slug', 'title' => 'URL Slug Maker', 'difficulty' => 'easy',
            'fn' => 'makeSlug', 'sig' => sig(['title' => 'str', 'suffix' => 'str'], 'str'),
            'blurb' => 'CMS routes want clean slugs: lowercase, every run of non-alphanumeric characters collapsed to one dash, no leading/trailing dashes, then the suffix appended with a dash when it is not empty.',
            'constraints' => ['title is ASCII (letters, digits, spaces, punctuation)', 'suffix ∈ {"", "v2", "draft", "in"}', 'no consecutive dashes in the result'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Also strip a trailing stop word such as "the".',
        ];
    }

    case 'split-bill': {
        $solve = "function splitBill(totalCents, heads, tipPct) {
  var tip = Math.floor(totalCents * tipPct / 100 + 0.5);
  var grand = totalCents + tip;
  var base = Math.floor(grand / heads);
  var rem = grand - base * heads;
  var out = [];
  for (var i = 0; i < heads; i++) out.push(base + (i < rem ? 1 : 0));
  return out;
}";
        $oracle = function (int $total, int $heads, int $tip): array {
            $tipC = (int)floor($total * $tip / 100 + 0.5);
            $grand = $total + $tipC;
            $base = intdiv($grand, $heads);
            $rem = $grand - $base * $heads;
            $out = [];
            for ($i = 0; $i < $heads; $i++) $out[] = $base + ($i < $rem ? 1 : 0);
            return $out;
        };
        $cases = [
            [10000, 4, 10], [9999, 3, 5], [500, 1, 20], [25000, 6, 0],
            [123456, 7, 8], [80, 5, 0], [10000, 2, 15], [cf_ai_int($r, 100, 5000), 3, 12],
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'split-bill', 'title' => 'Split the Bill (with tip)', 'difficulty' => 'medium',
            'fn' => 'splitBill', 'sig' => sig(['totalCents' => 'int', 'heads' => 'int', 'tipPct' => 'int'], 'int[]'),
            'blurb' => 'Dinner apps split in whole cents: first apply the tip (rounded to the nearest cent), then split equally — the leftover cents go to the first people, one cent each.',
            'constraints' => ['1 ≤ heads ≤ 12', '0 ≤ tipPct ≤ 30', 'tip is rounded: floor(total*pct/100 + 0.5)', 'the returned array sums to total + tip exactly'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Return per-person amounts in rupees with 2 decimals instead.',
        ];
    }

    case 'log-levels': {
        $solve = "function countLevels(lines) {
  var n = { ERROR: 0, WARN: 0, INFO: 0, OTHER: 0 };
  for (var i = 0; i < lines.length; i++) {
    var l = lines[i];
    if (l.indexOf('[ERROR]') === 0) n.ERROR++;
    else if (l.indexOf('[WARN]') === 0) n.WARN++;
    else if (l.indexOf('[INFO]') === 0) n.INFO++;
    else n.OTHER++;
  }
  return n;
}";
        $oracle = function (array $lines): array {
            $n = ['ERROR' => 0, 'WARN' => 0, 'INFO' => 0, 'OTHER' => 0];
            foreach ($lines as $l) {
                if (strpos($l, '[ERROR]') === 0) $n['ERROR']++;
                elseif (strpos($l, '[WARN]') === 0) $n['WARN']++;
                elseif (strpos($l, '[INFO]') === 0) $n['INFO']++;
                else $n['OTHER']++;
            }
            return $n;
        };
        $mkLines = function () use ($r): array {
            $pool = ['[INFO] boot ok', '[WARN] disk 82%', '[ERROR] payment failed',
                     '[INFO] cron tick', 'plain text line', '[DEBUG] trace', '[ERROR] db timeout',
                     ' leading space [ERROR]', '[WARN] retry 1/3', '[INFO] user login'];
            $k = count($pool);
            $out = [];
            for ($i = 0; $i < 7; $i++) $out[] = $pool[cf_ai_int($r, 0, $k - 1)];
            return $out;
        };
        $tests = [];
        $fixed = [['[ERROR] a', '[INFO] b', '[WARN] c', 'x'], ['[INFO] only'], ['[OTHER] here', '[DEBUG] d', '']];
        for ($i = 0; $i < 8; $i++) {
            $lines = $i < 3 ? $fixed[$i] : $mkLines();
            $tests[] = t([$lines], $oracle($lines), $i < 3 ? 1 : 0);
        }
        return [
            'slug' => '', 'key' => 'log-levels', 'title' => 'Log Level Counter', 'difficulty' => 'medium',
            'fn' => 'countLevels', 'sig' => sig(['lines' => 'str[]'], 'map'),
            'blurb' => 'Alerting dashboards bucket raw log lines. A line counts as ERROR/WARN/INFO only when it <em>starts with</em> the tag; everything else is OTHER (even "[DEBUG]" or a stray [ERROR] mid-line).',
            'constraints' => ['return exactly the keys {ERROR, WARN, INFO, OTHER}', 'a leading space before the tag makes it OTHER', '0 ≤ lines.length ≤ 50'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Also return the first ERROR line index (or -1).',
        ];
    }

    case 'peak-window': {
        $solve = "function peakWindow(nums, k) {
  var best = 0;
  for (var i = 0; i < k; i++) best += nums[i];
  var cur = best;
  for (var j = k; j < nums.length; j++) {
    cur += nums[j] - nums[j - k];
    if (cur > best) best = cur;
  }
  return best;
}";
        $oracle = function (array $nums, int $k): int {
            $best = PHP_INT_MIN; $n = count($nums);
            for ($i = 0; $i + $k <= $n; $i++) {
                $s = 0;
                for ($j = 0; $j < $k; $j++) $s += $nums[$i + $j];
                if ($s > $best) $best = $s;
            }
            return $best;
        };
        $mk = function (int $n, int $k) use ($r): array {
            $nums = [];
            for ($i = 0; $i < $n; $i++) $nums[] = cf_ai_int($r, 1, 50);
            return [$nums, $k];
        };
        $cases = [
            [[5, 2, 8, 1, 9, 3], 3], [[1, 1, 1, 1], 2], [[10, 20, 30, 40, 50], 1],
            [[7, 3], 2], $mk(8, 3), $mk(10, 4), $mk(12, 5), $mk(6, 6),
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'peak-window', 'title' => 'Peak K-Window Sales', 'difficulty' => 'medium',
            'fn' => 'peakWindow', 'sig' => sig(['nums' => 'int[]', 'k' => 'int'], 'int'),
            'blurb' => 'Daily sales figures: find the highest total of any <em>k</em> consecutive days (the marketing "peak streak"). Slide the window — recompute from scratch and the dashboard hangs on year-long logs.',
            'constraints' => ['1 ≤ k ≤ nums.length ≤ 10^5', '1 ≤ nums[i] ≤ 10^4', 'return the maximum sum as an integer'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Also return the window’s starting index.',
        ];
    }

    case 'refund-quote': {
        $dayRate = cf_ai_pick($r, [3, 5, 7, 9]);
        $solve = "function refundQuote(paid, daysUsed, feePct) {
  var fee = Math.ceil(paid * feePct / 100);
  var refund = paid - fee - daysUsed * {$dayRate};
  return refund > 0 ? refund : 0;
}";
        $oracle = function (int $paid, int $days, int $fee) use ($dayRate): int {
            $r = $paid - (int)ceil($paid * $fee / 100) - $days * $dayRate;
            return max(0, $r);
        };
        $cases = [
            [1000, 30, 10], [1000, 0, 10], [999, 100, 12], [500, 200, 5],
            [4999, 45, 8], [100, 0, 99], [25000, 90, 15], [cf_ai_int($r, 200, 2000), cf_ai_int($r, 0, 100), 10],
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'refund-quote', 'title' => 'Refund Calculator', 'difficulty' => 'medium',
            'fn' => 'refundQuote', 'sig' => sig(['paid' => 'int', 'daysUsed' => 'int', 'feePct' => 'int'], 'int'),
            'blurb' => "Self-serve refunds: subtract the cancellation fee (<em>ceiling</em> of paid × feePct%) and ₹{$dayRate} for every day used — but never report a negative refund.",
            'constraints' => ['fee = ceil(paid × feePct / 100)', "day charge = ₹{$dayRate} per used day", 'result is floored at 0'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Cap used-day charges at 50% of the amount paid.',
        ];
    }

    case 'band-of': {
        $themes = [
            [['Standard', 'Express', 'Priority', 'Same-day'], [40, 70, 90]],
            [['Cold', 'Warm', 'Hot'], ['' . cf_ai_int($r, 25, 40), '' . cf_ai_int($r, 60, 80)]],
            [['Bronze', 'Silver', 'Gold', 'Platinum'], [100, 250, 500]],
            [['Low', 'Medium', 'High'], ['' . cf_ai_int($r, 30, 50), '' . cf_ai_int($r, 70, 90)]],
        ];
        [$names, $cuts0] = cf_ai_pick($r, $themes);
        $cuts = array_map('intval', $cuts0);
        sort($cuts);
        $solve = "function bandOf(score, cutoffs, names) {
  for (var i = 0; i < cutoffs.length; i++) if (score < cutoffs[i]) return names[i];
  return names[names.length - 1];
}";
        $oracle = function (int $score, array $cuts, array $names): string {
            foreach ($cuts as $i => $c) if ($score < $c) return $names[$i];
            return $names[count($names) - 1];
        };
        $bounds = [$cuts[0] - 1, $cuts[0], $cuts[0] + 1, $cuts[count($cuts)-1] - 1, $cuts[count($cuts)-1], $cuts[count($cuts)-1] + 100];
        while (count($bounds) < 8) $bounds[] = cf_ai_int($r, 0, (int)($cuts[count($cuts)-1]) + 150);
        $tests = [];
        foreach ($bounds as $i => $sc) $tests[] = t([$sc, $cuts, $names], $oracle($sc, $cuts, $names), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'band-of', 'title' => 'Tier Resolver', 'difficulty' => 'easy',
            'fn' => 'bandOf', 'sig' => sig(['score' => 'int', 'cutoffs' => 'int[]', 'names' => 'str[]'], 'str'),
            'blurb' => 'Sorting into tiers: names[0] covers scores below cutoffs[0], names[1] up to cutoffs[1], and so on — the last name catches everything at or above the final cutoff. Cutoffs come sorted.',
            'constraints' => ['names.length === cutoffs.length + 1', 'boundary: score === cutoffs[i] falls through to the NEXT band', 'cutoffs strictly increasing'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Binary-search instead of scanning when cutoffs can be thousands long.',
        ];
    }

    case 'ship-cost': {
        $ra = [cf_ai_int($r, 30, 50), cf_ai_int($r, 20, 30)];
        $rb = [cf_ai_int($r, 55, 70), cf_ai_int($r, 30, 45)];
        $rc = [cf_ai_int($r, 80, 110), cf_ai_int($r, 50, 70)];
        $solve = "function shipCost(grams, zone) {
  var R = { A: [{$ra[0]}, {$ra[1]}], B: [{$rb[0]}, {$rb[1]}], C: [{$rc[0]}, {$rc[1]}] }[zone];
  if (!R) return -1;
  return R[0] + Math.ceil(grams / 1000) * R[1];
}";
        $oracle = function (int $g, string $z) use ($ra, $rb, $rc) {
            $map = ['A' => $ra, 'B' => $rb, 'C' => $rc];
            if (!isset($map[$z])) return -1;
            [$base, $per] = $map[$z];
            return $base + (int)ceil($g / 1000) * $per;
        };
        $cases = [
            [500, 'A'], [1000, 'A'], [1001, 'B'], [2500, 'C'],
            [0, 'B'], [9999, 'C'], [1500, 'Z'], [cf_ai_int($r, 100, 900), cf_ai_pick($r, ['A', 'B', 'C'])],
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'ship-cost', 'title' => 'Shipping Quote', 'difficulty' => 'medium',
            'fn' => 'shipCost', 'sig' => sig(['grams' => 'int', 'zone' => 'str'], 'int'),
            'blurb' => "Per-kg courier pricing: zone A charges ₹{$ra[0]} + {$ra[1]}×kg, zone B ₹{$rb[0]} + {$rb[1]}×kg, zone C ₹{$rc[0]} + {$rc[1]}×kg — weight always rounds <em>up</em> to the next whole kg. Unknown zones return -1.",
            'constraints' => ['ceil(grams/1000) — 1001 g counts as 2 kg', 'zones are only "A", "B", "C"', 'grams ≥ 0'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Add free shipping above ₹2000 of item value.',
        ];
    }

    case 'busy-count': {
        $solve = "function busyCount(slots, start, end) {
  function mins(t) { var p = t.split(':'); return Number(p[0]) * 60 + Number(p[1]); }
  var s = mins(start), e = mins(end), n = 0;
  for (var i = 0; i < slots.length; i++) {
    var ab = slots[i].split('-');
    if (mins(ab[0]) < e && mins(ab[1]) > s) n++;
  }
  return n;
}";
        $oracle = function (array $slots, string $start, string $end): int {
            $mins = function (string $t): int { [$h, $m] = explode(':', $t); return (int)$h * 60 + (int)$m; };
            $s = $mins($start); $e = $mins($end); $n = 0;
            foreach ($slots as $sl) {
                [$a, $b] = explode('-', $sl);
                if ($mins($a) < $e && $mins($b) > $s) $n++;
            }
            return $n;
        };
        $slotPool = ['09:00-10:00', '10:00-11:00', '11:30-12:30', '13:00-14:00',
                     '09:30-10:30', '15:00-16:00', '18:00-18:30', '07:00-08:00'];
        $mk = function () use ($r, $slotPool): array {
            $k = cf_ai_int($r, 3, 6);
            $out = [];
            for ($i = 0; $i < $k; $i++) $out[] = cf_ai_pick($r, $slotPool);
            return $out;
        };
        $cases = [
            [$slotPool, '09:30', '10:30'], [$slotPool, '10:00', '11:00'],
            [['09:00-10:00'], '10:00', '11:00'], [['09:00-10:00', '11:00-12:00'], '17:00', '18:00'],
            [$mk(), '09:00', '10:00'], [$mk(), '12:00', '13:00'], [$mk(), '08:00', '09:00'], [$mk(), '14:00', '15:30'],
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'busy-count', 'title' => 'Calendar Busy Counter', 'difficulty' => 'medium',
            'fn' => 'busyCount', 'sig' => sig(['slots' => 'str[]', 'start' => 'str', 'end' => 'str'], 'int'),
            'blurb' => 'Free/busy APIs count overlapping meetings in "HH:MM-HH:MM" form. A slot is busy during [start, end) when it <em>strictly</em> overlaps — back-to-back meetings (10:00 end, 10:00 start) do not clash.',
            'constraints' => ['times are "HH:MM" 24-hour strings', 'overlap test: slotStart < end && slotEnd > start', 'start < end always'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Merge the busy slots into maximal blocks instead of counting.',
        ];
    }

    case 'sku-qty': {
        $solve = "function skuQty(lines, sku) {
  var n = 0;
  for (var i = 0; i < lines.length; i++) {
    var parts = lines[i].split(':');
    if (parts[0].trim() === sku) n += parseInt(parts[1], 10);
  }
  return n;
}";
        $oracle = function (array $lines, string $sku): int {
            $n = 0;
            foreach ($lines as $l) {
                [$k, $q] = explode(':', $l) + [1 => '0'];
                if (trim($k) === $sku) $n += (int)$q;
            }
            return $n;
        };
        $skus = ['W-1', 'W-2', 'M-9', 'X-4'];
        $mk = function () use ($r, $skus): array {
            $out = [];
            $n = cf_ai_int($r, 4, 7);
            for ($i = 0; $i < $n; $i++) {
                $s = cf_ai_pick($r, $skus);
                $pad = cf_ai_pick($r, ['', ' ', '  ']);
                $out[] = $s . ':' . $pad . cf_ai_int($r, 1, 12);
            }
            return $out;
        };
        $cases = [
            [['W-1:3', 'W-2:1', 'W-1:2'], 'W-1'],
            [['M-9:5'], 'W-1'],
            [['X-4: 7', 'X-4:  3'], 'X-4'],
            [['W-2:1', 'W-2:1', 'W-2:1'], 'W-2'],
            [$mk(), $skus[0]], [$mk(), $skus[1]], [$mk(), $skus[2]], [$mk(), $skus[3]],
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'sku-qty', 'title' => 'SKU Total from Lines', 'difficulty' => 'easy',
            'fn' => 'skuQty', 'sig' => sig(['lines' => 'str[]', 'sku' => 'str'], 'int'),
            'blurb' => 'Warehouse CSV exports look like "W-1: 5". Total the quantity for one SKU — the SKU key must match exactly after trimming; quantities may be space-padded.',
            'constraints' => ['line format "SKU: qty"', 'compare the trimmed key, exact case', 'absent SKU → 0'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Tolerate malformed lines (missing colon) by skipping them.',
        ];
    }

    case 'longest-streak': {
        $solve = "function longestStreak(flags) {
  var best = 0, cur = 0;
  for (var i = 0; i < flags.length; i++) {
    cur = flags[i] ? cur + 1 : 0;
    if (cur > best) best = cur;
  }
  return best;
}";
        $oracle = function (array $flags): int {
            $best = 0; $cur = 0;
            foreach ($flags as $f) { $cur = $f ? $cur + 1 : 0; if ($cur > $best) $best = $cur; }
            return $best;
        };
        $mk = function (int $n) use ($r): array {
            $out = [];
            for ($i = 0; $i < $n; $i++) $out[] = cf_ai_int($r, 0, 2) > 0;
            return $out;
        };
        $cases = [
            [[true, true, false, true, true, true]], [[false, false, false]], [[true]],
            [[true, false, true, false]], [$mk(8)], [$mk(10)], [$mk(12)], [$mk(15)],
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'longest-streak', 'title' => 'Longest Login Streak', 'difficulty' => 'easy',
            'fn' => 'longestStreak', 'sig' => sig(['flags' => 'bool[]'], 'int'),
            'blurb' => 'Gamification dashboards track daily-login streaks: given booleans per day, find the longest run of <code>true</code>. A streak dies at the first false.',
            'constraints' => ['0 ≤ flags.length ≤ 10^5', 'empty input → 0'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Return the streak’s end-day index too.',
        ];
    }

    case 'curve-grades': {
        $solve = "function curveGrades(scores, bonus, cap) {
  var out = [];
  for (var i = 0; i < scores.length; i++) out.push(Math.min(cap, scores[i] + bonus));
  return out;
}";
        $oracle = function (array $scores, int $bonus, int $cap): array {
            return array_map(function ($s) use ($bonus, $cap) { return min($cap, $s + $bonus); }, $scores);
        };
        $cases = [
            [[55, 88, 91], 5, 95], [[10, 20], 0, 100], [[90, 92, 99], 10, 95],
            [[0, 1], 2, 3], [[77, 77, 77], 3, 80],
            [[cf_ai_int($r, 0, 100), cf_ai_int($r, 0, 100), cf_ai_int($r, 0, 100), cf_ai_int($r, 0, 100)], 7, 100],
            [[40, 60, 80], 21, 100], [[33], 66, 99],
        ];
        $tests = [];
        foreach ($cases as $i => $c) $tests[] = t($c, $oracle(...$c), $i < 3 ? 1 : 0);
        return [
            'slug' => '', 'key' => 'curve-grades', 'title' => 'Grade Curve with Cap', 'difficulty' => 'easy',
            'fn' => 'curveGrades', 'sig' => sig(['scores' => 'int[]', 'bonus' => 'int', 'cap' => 'int'], 'int[]'),
            'blurb' => 'A professor adds a flat bonus to every score, but nobody may exceed the cap. Return the curved list in the original order — element-wise map, no sorting.',
            'constraints' => ['result[i] = min(cap, scores[i] + bonus)', '0 ≤ scores[i] ≤ 100', 'length preserved'],
            'tests' => $tests, 'solution_js' => $solve,
            'follow' => 'Give the bonus only to students below the median.',
        ];
    }

    }
    throw new RuntimeException("unknown ai problem template: $key");
}

/** 12 keys — batch b uses a rotated window of 10 with fresh PRNG constants. */
function cf_ai_problem_keys(): array {
    return ['coupon-final', 'make-slug', 'split-bill', 'log-levels', 'peak-window', 'refund-quote',
            'band-of', 'ship-cost', 'busy-count', 'sku-qty', 'longest-streak', 'curve-grades'];
}

/**
 * Build batch $batch (1-based) of 10 problem specs for user $uid.
 * Deterministic. Specs carry slug/title/category/tags + pdef fields.
 */
function cf_ai_problems_specs(int $uid, int $batch): array {
    $keys = cf_ai_problem_keys();
    $out = [];
    for ($i = 0; $i < 10; $i++) {
        $key = $keys[($batch * 10 + $i) % count($keys)];
        $r = cf_ai_rng("p:{$uid}:{$batch}:{$i}:{$key}");
        $spec = cf_ai_gen_spec($key, $r);
        $spec['slug'] = cf_ai_pslug($uid, $batch, $i + 1, $key);
        $spec['title'] = $spec['title'] . " · 🤖 set {$batch}";
        $spec['category'] = 'ai';
        $out[] = $spec;
    }
    return $out;
}

/**
 * Clear-board trigger for Practice. If the user solved every canonical
 * problem AND every problem of their latest AI batch, generate batch+1.
 * Returns ['batch'=>N,'count'=>10] when new problems were created, else null.
 */
function cf_ai_problems_tick(PDO $pdo, int $uid): ?array {
    require_once __DIR__ . '/content_seed.php';
    $canonical = (int)(db_one('SELECT COUNT(*) AS c FROM problems WHERE ai_user_id IS NULL')['c'] ?? 0);
    if ($canonical === 0) return null;
    $solved = (int)(db_one(
        "SELECT COUNT(DISTINCT s.problem_id) AS c FROM submissions s
         JOIN problems p ON p.id = s.problem_id
         WHERE s.user_id = ? AND s.status = 'pass' AND p.ai_user_id IS NULL", [$uid]
    )['c'] ?? 0);
    if ($solved < $canonical) return null;

    // owned AI rows (revealed batches) → is the newest fully solved?
    $owned = db_all('SELECT id, slug FROM problems WHERE ai_user_id = ? ORDER BY id', [$uid]);
    $maxBatch = 0;
    $batchIds = [];
    foreach ($owned as $o) {
        if (preg_match('/^aip\d+b(\d+)-/', $o['slug'], $m)) {
            $b = (int)$m[1];
            if ($b > $maxBatch) { $maxBatch = $b; $batchIds = []; }
            if ($b === $maxBatch) $batchIds[] = (int)$o['id'];
        }
    }
    if ($batchIds) {
        $ph = implode(',', array_fill(0, count($batchIds), '?'));
        $doneBatch = (int)(db_one(
            "SELECT COUNT(DISTINCT s.problem_id) AS c FROM submissions s
             WHERE s.user_id = ? AND s.status = 'pass' AND s.problem_id IN ($ph)",
            array_merge([$uid], $batchIds)
        )['c'] ?? 0);
        if ($doneBatch < count($batchIds)) return null;
    }

    $batch = $maxBatch + 1;
    $specs = cf_ai_problems_specs($uid, $batch);
    $cols  = 'slug, title, difficulty, description, tags, function_name, starter_js, solution_js, tests_json, points, category, starters_json, ai_user_id';
    $pts   = ['easy' => 10, 'medium' => 20, 'hard' => 35];
    $ins = $pdo->prepare(
        'INSERT INTO problems (' . $cols . ') VALUES (' . rtrim(str_repeat('?,', 13), ',') . ')'
    );
    foreach ($specs as $spec) {
        $starters = cf_starters_all($spec['fn'], $spec['sig']);
        $desc = cf_build_description($spec['fn'], $spec['sig'], $spec['blurb'], $spec['constraints'], $spec['tests'], $spec['follow']);
        $ins->execute([
            $spec['slug'], $spec['title'], $spec['difficulty'], $desc, 'ai-generated',
            $spec['fn'], $starters['javascript'], $spec['solution_js'],
            json_encode($spec['tests'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $pts[$spec['difficulty']], 'ai',
            json_encode($starters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $uid,
        ]);
    }
    return ['batch' => $batch, 'count' => count($specs)];
}

/** Owned AI problem rows, newest batches last, with solved-state for $uid. */
function cf_ai_problems_owned(PDO $pdo, int $uid): array {
    return db_all(
        "SELECT p.id, p.slug, p.title, p.difficulty,
                (SELECT COUNT(*) FROM submissions s WHERE s.user_id = ? AND s.problem_id = p.id AND s.status = 'pass') > 0 AS solved
         FROM problems p WHERE p.ai_user_id = ? ORDER BY p.id",
        [$uid, $uid]
    );
}

/* ============================ LAB TEMPLATES ============================ */
/* Each generator returns a canonical lab payload + 'fix' map (filename →    */
/* corrected content — used by the verification harness, never sent client). */

/** Deterministic money: %.2f of a float, same binary64 JS toFixed formats. */
function cf_ai_m2(float $v): string { return sprintf('%.2f', $v); }

/* ---- L-A: legacy pricing quote (debug) ---- */
function cf_ai_lab_pricing(Closure $r, int $uid, int $batch, int $n): array {
    $T = cf_ai_pick($r, [800, 1000, 1200]);
    $D = cf_ai_pick($r, [50, 100, 150]);
    $R = cf_ai_pick($r, ['1.05', '1.12', '1.18']);
    $oracle = function (array $items) use ($T, $D, $R): string {
        $sub = 0.0;
        foreach ($items as $it) $sub += (float)$it[0] * $it[1];
        $disc = $sub >= $T ? $D : 0;
        return cf_ai_m2(($sub - $disc) * (float)$R);
    };
    $it = function (string $p, int $q): array { return [$p, $q]; };
    $cases = [
        [$it('899.50', 1)],
        [$it(cf_ai_m2($T / 2), 2)],                        // exact boundary → discount applies
        [$it('500.00', 3)],
        [$it('250.25', 4), $it('0.50', 2)],
        [$it('99.99', 3), $it('19.99', 1)],
    ];
    $tasks = [];
    foreach ($cases as $i => $cs) {
        $argJs = '[' . implode(',', array_map(function ($x) { return "{price:'{$x[0]}',qty:{$x[1]}}"; }, $cs)) . ']';
        $tasks[] = [
            'text' => 'orderQuote(' . implode(' + ', array_map(function ($x) { return "{$x[1]}×₹{$x[0]}"; }, $cs)) . ') → "' . $oracle($cs) . '"',
            'check' => "orderQuote({$argJs}).total === \"{$oracle($cs)}\"",
        ];
    }
    $money = "// money.js — legacy money helpers (2019). Touch with care.
function parseMoney(s) {
  // parse a rupee string like \"899.50\" into a number
  return parseInt(s, 10);
}
function formatMoney(v) {
  return v.toFixed(2);
}";
    $cart = "// cart.js — cart totals. SPEC: subtotal = Σ price×qty;
//   if subtotal >= {$T} → flat ₹{$D} discount, BEFORE tax
//   total = (subtotal − discount) × {$R}, formatted to 2 decimals.
function cartTotal(items) {
  var sub = 0;
  for (var i = 0; i < items.length; i++) sub += parseMoney(items[i].price) * items[i].qty;
  var total = sub * {$R};
  if (sub > {$T}) total -= {$D};   // support reports wrong quotes — two bugs around here
  return formatMoney(total);
}";
    $api = "// api.js — READONLY façade. The mobile app calls orderQuote() — do NOT change its shape.
function orderQuote(items) {
  return { total: cartTotal(items), currency: 'INR' };
}";
    $moneyFix = "// money.js — FIXED: fractional rupees parse correctly.
function parseMoney(s) {
  return parseFloat(s);
}
function formatMoney(v) {
  return v.toFixed(2);
}";
    $cartFix = "// cart.js — FIXED: discount at >= {$T}, applied BEFORE tax.
function cartTotal(items) {
  var sub = 0;
  for (var i = 0; i < items.length; i++) sub += parseMoney(items[i].price) * items[i].qty;
  var disc = sub >= {$T} ? {$D} : 0;
  return formatMoney((sub - disc) * {$R});
}";
    return [
        'slug' => '', 'title' => 'Legacy Pricing Quote', 'kind' => 'debug', 'difficulty' => 'medium',
        'minutes' => 25,
        'summary' => "A 3-file quoting service mis-prices orders. Spec: subtotal → flat ₹{$D} off at ₹{$T} or more → then ×{$R} tax, 2-decimal output.",
        'brief' => '<p>You inherited a pricing service. The spec says: <strong>subtotal → flat ₹' . $D . ' discount when subtotal is ₹' . $T . ' or more (discount BEFORE tax) → then ×' . $R . ' tax</strong>. Prices arrive as strings with decimals; output is formatted to 2 decimals. Support reports wrong totals. The bugs are in the editable files — do not touch <code>api.js</code>.</p>',
        'files' => [
            ['name' => 'money.js', 'readonly' => false, 'content' => $money],
            ['name' => 'cart.js', 'readonly' => false, 'content' => $cart],
            ['name' => 'api.js', 'readonly' => true, 'content' => $api],
        ],
        'tasks' => $tasks,
        'fix' => ['money.js' => $moneyFix, 'cart.js' => $cartFix],
    ];
}

/* ---- L-B: inventory restock (debug) ---- */
function cf_ai_lab_stock(Closure $r, int $uid, int $batch, int $n): array {
    // 5 items: exact-boundary, below, above, reserved-heavy, well-stocked
    $rp = [cf_ai_int($r, 4, 8), cf_ai_int($r, 5, 9), cf_ai_int($r, 6, 10), cf_ai_int($r, 3, 6), cf_ai_int($r, 8, 12)];
    $catA = [ // [sku, name, qty, reorder, reserved]
        ['W-1', 'Widget Base', 20, $rp[0], 2],
        ['W-2', 'Widget Pro', 6, $rp[1], 1],         // available 5 vs rp → restock (5 < rp1 6..9?) computed below
        ['W-3', 'Gadget Mini', 3, 2, 0],
        ['W-4', 'Gadget Max', 12, $rp[3], 8],
        ['W-5', 'Spare Bolt', 9, 9, 0],              // exact boundary: available 9 vs reorder 9 → NOT restock ("<" rule)
    ];
    $oracle = function (array $cat): array {
        $out = [];
        foreach ($cat as $it) {
            [$sku, , $qty, $rp, $res] = $it;
            if ($qty - $res < $rp) $out[] = $sku;
        }
        return $out;
    };
    // force one deterministic above-water example so the set isn't "everything": bump W-3 if needed
    $restock = $oracle($catA);
    if (count($restock) >= 4) $catA[2][2] = 40; // W-3 clearly fine
    $restock = $oracle($catA);
    $csv = implode(',', $restock);
    $cnt = count($restock);
    $rows = [];
    foreach ($catA as [$sku, $name, $qty, $rpI, $res]) {
        $rows[] = "  { sku: '{$sku}', name: '{$name}', qty: {$qty}, reorder: {$rpI}, reserved: {$res} }";
    }
    $catalog = "// catalog.js — READONLY vendor snapshot (do not edit).
var CATALOG = [\n" . implode(",\n", $rows) . "\n];";
    $stock = "// stock.js — restock logic. SPEC: available = qty − reserved.
// Restock when available is STRICTLY BELOW the reorder point.
function getRestock() {
  var out = [];
  for (var i = 0; i < CATALOG.length; i++) {
    var it = CATALOG[i];
    var available = it.qty + it.reserved;      // was written in a hurry…
    if (available <= it.reorder) out.push(it.sku);
  }
  return out;
}";
    $api = "// api.js — READONLY façade feeding the purchasing dashboard.
function restockCsv() {
  return getRestock().join(',');
}";
    $stockFix = "// stock.js — FIXED: available = qty − reserved; strictly below reorder.
function getRestock() {
  var out = [];
  for (var i = 0; i < CATALOG.length; i++) {
    var it = CATALOG[i];
    if (it.qty - it.reserved < it.reorder) out.push(it.sku);
  }
  return out;
}";
    return [
        'slug' => '', 'title' => 'Restock Report Misfire', 'kind' => 'debug', 'difficulty' => 'medium',
        'minutes' => 22,
        'summary' => 'Purchasing gets the wrong restock list: reserved units are counted as available, and boundary items sneak in.',
        'brief' => '<p>The purchasing dashboard consumes <code>restockCsv()</code>. Spec: <strong>available = qty − reserved</strong>, restock when available is <strong>strictly below</strong> the reorder point. Two bugs drifted in. Fix only <code>stock.js</code>.</p>',
        'files' => [
            ['name' => 'catalog.js', 'readonly' => true, 'content' => $catalog],
            ['name' => 'stock.js', 'readonly' => false, 'content' => $stock],
            ['name' => 'api.js', 'readonly' => true, 'content' => $api],
        ],
        'tasks' => [
            ['text' => "restockCsv() === \"{$csv}\" ({$cnt} SKUs, in catalog order)", 'check' => "restockCsv() === \"{$csv}\""],
            ['text' => "getRestock() returns exactly {$cnt} items", 'check' => "getRestock().length === {$cnt}"],
            ['text' => 'boundary SKU W-5 (available 9, reorder 9) is NOT restocked', 'check' => "getRestock().indexOf('W-5') === -1"],
        ],
        'fix' => ['stock.js' => $stockFix],
    ];
}

/* ---- L-C: delivery quote adapter (api integration) ---- */
function cf_ai_lab_delivery(Closure $r, int $uid, int $batch, int $n): array {
    $z1 = [cf_ai_int($r, 4500, 6000), cf_ai_int($r, 1200, 1600)];
    $z2 = [cf_ai_int($r, 7000, 9000), cf_ai_int($r, 1700, 2200)];
    $z3 = [cf_ai_int($r, 3000, 4500), cf_ai_int($r, 900, 1200)];
    $ins = cf_ai_int($r, 5, 18);
    $b1 = $z1[0]; $p1 = $z1[1]; $b2 = $z2[0]; $p2 = $z2[1]; $b3 = $z3[0]; $p3 = $z3[1];
    $vendor = function (string $pin, int $grams) use ($z1, $z2, $z3): int {
        $zones = ['1' => $z1, '2' => $z2, '3' => $z3];
        $z = $zones[substr($pin, 0, 1)] ?? $z3;
        $kg = intdiv($grams, 1000);
        if ($kg === 0) $kg = 1;
        return $z[0] + ($kg - 1) * $z[1];
    };
    $oracle = function (string $pin, int $grams) use ($vendor, $ins): int {
        return (int)ceil($vendor($pin, $grams) / 100) + $ins;
    };
    $cases = [['110001', 500], ['110001', 2500], ['200045', 999], ['200045', 4001], ['305610', 1200], ['411045', 800]];
    $tasks = [];
    foreach ($cases as $c) {
        [$pin, $g] = $c;
        $exp = $oracle($pin, $g);
        $tasks[] = ['text' => "quoteParcel('{$pin}', {$g}) === {$exp}", 'check' => "quoteParcel('{$pin}', {$g}) === {$exp}"];
    }
    $vendorJs = "// vendor.js — READONLY carrier API (published SDK — do not edit).
// Returns PAISE. First kg costs zone base; every extra whole kg adds the zone rate.
function vendorRate(pinPrefix, grams) {
  var zones = { '1': [{$b1}, {$p1}], '2': [{$b2}, {$p2}], '3': [{$b3}, {$p3}] };
  var z = zones[pinPrefix] || zones['3'];
  var kg = Math.floor(grams / 1000);
  if (kg === 0) kg = 1;
  return z[0] + (kg - 1) * z[1];
}";
    $adapterJs = "// adapter.js — our quoting layer on top of vendor.js (written by an intern).
// SPEC: quoteParcel returns WHOLE RUPEES rounded UP (ceil), plus flat ₹{$ins} insurance.
var INSURANCE = {$ins};
function quoteParcel(pin, grams) {
  var paise = vendorRate(pin[0], grams);
  var rupees = paise / 100 + INSURANCE;   // support: customers complain about fractional paise & missing round-up
  return rupees;
}";
    $adapterFix = "// adapter.js — FIXED: ceil the paise→rupee conversion, then add insurance.
var INSURANCE = {$ins};
function quoteParcel(pin, grams) {
  return Math.ceil(vendorRate(pin[0], grams) / 100) + INSURANCE;
}";
    return [
        'slug' => '', 'title' => 'Carrier Quote Adapter', 'kind' => 'api', 'difficulty' => 'medium',
        'minutes' => 20,
        'summary' => "Integrate a paise-denominated carrier API: quotes must be whole rupees rounded up, plus ₹{$ins} insurance.",
        'brief' => '<p>The vendor SDK (<code>vendor.js</code>) is <strong>readonly</strong>: it bills in paise, first kg at zone base + extra whole kgs at the zone rate. Your adapter must return <strong>whole rupees rounded up (Math.ceil)</strong> plus flat ₹' . $ins . ' insurance — <em>insurance is added after the ceil, never rounded itself</em>. Fix <code>adapter.js</code> only.</p>',
        'files' => [
            ['name' => 'vendor.js', 'readonly' => true, 'content' => $vendorJs],
            ['name' => 'adapter.js', 'readonly' => false, 'content' => $adapterJs],
        ],
        'tasks' => $tasks,
        'fix' => ['adapter.js' => $adapterFix],
    ];
}

/* ---- L-D: coupon engine (debug) ---- */
function cf_ai_lab_coupons(Closure $r, int $uid, int $batch, int $n): array {
    $p1 = cf_ai_pick($r, [10, 15, 20]);
    $m1 = cf_ai_pick($r, [299, 499]);
    $f1 = cf_ai_pick($r, [40, 50, 75]);
    $m2 = cf_ai_pick($r, [199, 399, 599]);
    $f2 = cf_ai_pick($r, [150, 200, 250]);
    $m3 = cf_ai_pick($r, [999, 1499]);
    $table = [
        ['code' => "SAVE{$p1}", 'type' => 'pct', 'v' => $p1, 'min' => 0],
        ['code' => "FLAT{$f1}", 'type' => 'flat', 'v' => $f1, 'min' => $m2],
        ['code' => "VIP{$p1}", 'type' => 'pct', 'v' => $p1, 'min' => $m1],
        ['code' => "FLAT{$f2}", 'type' => 'flat', 'v' => $f2, 'min' => $m3],
    ];
    $find = function (string $code) use ($table) { foreach ($table as $c) if ($c['code'] === $code) return $c; return null; };
    $oracle = function (int $sub, string $code) use ($find): int {
        $c = $find($code);
        if (!$c || $sub < $c['min']) return 0;
        $d = $c['type'] === 'pct' ? intdiv($sub * $c['v'], 100) : $c['v'];
        return min($d, $sub);
    };
    $cases = [
        [1000, "SAVE{$p1}"], [$m2, "FLAT{$f1}"], [$m2 - 1, "FLAT{$f1}"],
        [$m1, "VIP{$p1}"], [$m3, "FLAT{$f2}"], [100, "FLAT{$f2}"],
        [777, 'NOPE'], [$m3 * 2, "FLAT{$f2}"],
    ];
    $tasks = [];
    foreach ($cases as [$sub, $code]) {
        $e = $oracle($sub, $code);
        $tasks[] = ['text' => "bestDiscount({$sub}, '{$code}') === {$e}", 'check' => "bestDiscount({$sub}, '{$code}') === {$e}"];
    }
    $rows = [];
    foreach ($table as $c) $rows[] = "  { code: '{$c['code']}', type: '{$c['type']}', v: {$c['v']}, min: {$c['min']} }";
    $couponsJs = "// coupons.js — READONLY offers table (marketing owns this — do not edit).
var COUPONS = [\n" . implode(",\n", $rows) . "\n];";
    $engineJs = "// engine.js — discount engine. SPEC: coupon applies only when subtotal >= min.
// pct discounts are FLOORED (whole rupees). A discount can never exceed the subtotal.
function bestDiscount(subtotal, code) {
  for (var i = 0; i < COUPONS.length; i++) {
    var c = COUPONS[i];
    if (c.code !== code) continue;
    if (subtotal > c.min) {                                  // boundary bug
      var d = c.type === 'pct' ? Math.ceil(subtotal * c.v / 100) : c.v;
      return d;                                              // negative payables downstream!
    }
  }
  return 0;
}";
    $engineFix = "// engine.js — FIXED: >= min, floor pct, clamp at subtotal.
function bestDiscount(subtotal, code) {
  for (var i = 0; i < COUPONS.length; i++) {
    var c = COUPONS[i];
    if (c.code !== code) continue;
    if (subtotal >= c.min) {
      var d = c.type === 'pct' ? Math.floor(subtotal * c.v / 100) : c.v;
      return d > subtotal ? subtotal : d;
    }
  }
  return 0;
}";
    return [
        'slug' => '', 'title' => 'Coupon Engine Edge Cases', 'kind' => 'debug', 'difficulty' => 'medium',
        'minutes' => 18,
        'summary' => 'Three coupon bugs in one small engine: boundary mins, rounded pcts, and discounts that exceed the basket.',
        'brief' => '<p>Spec (<code>coupons.js</code> is truth): a coupon applies only when <code>subtotal &gt;= min</code>; <em>pct</em> discounts are <strong>floored</strong> to whole rupees; a discount never exceeds the subtotal. The engine violates all three. Fix <code>engine.js</code> only.</p>',
        'files' => [
            ['name' => 'coupons.js', 'readonly' => true, 'content' => $couponsJs],
            ['name' => 'engine.js', 'readonly' => false, 'content' => $engineJs],
        ],
        'tasks' => $tasks,
        'fix' => ['engine.js' => $engineFix],
    ];
}

/* ---- L-E: timesheet payroll (debug) ---- */
function cf_ai_lab_payroll(Closure $r, int $uid, int $batch, int $n): array {
    $base = cf_ai_pick($r, [220, 250, 300, 350]);
    $ratesJs = "// rates.js — READONLY HR rates (do not edit).
var RATES = { base: {$base}, nightBonusPct: 25 };";
    $payrollJs = "// payroll.js — weekly pay. SPEC: per entry [hours, nightFlag]:
//   pay = hours × RATES.base, and night shifts add 25% of that day pay, FLOORED to whole rupees.
// Support: night-shift staff are overpaid by a rupee here and there, and Monday's entry vanishes.
function weeklyPay(entries) {
  var total = 0;
  for (var i = 1; i < entries.length; i++) {
    var h = entries[i][0], night = entries[i][1];
    var day = h * RATES.base;
    if (night) day += Math.round(day * RATES.nightBonusPct / 100);
    total += day;
  }
  return total;
}";
    $payrollFix = "// payroll.js — FIXED: include entry 0; night bonus is floored.
function weeklyPay(entries) {
  var total = 0;
  for (var i = 0; i < entries.length; i++) {
    var h = entries[i][0], night = entries[i][1];
    var day = h * RATES.base;
    if (night) day += Math.floor(day * RATES.nightBonusPct / 100);
    total += day;
  }
  return total;
}";
    $oracle = function (array $entries) use ($base): int {
        $total = 0;
        foreach ($entries as [$h, $night]) {
            $day = $h * $base;
            if ($night) $day += intdiv($day * 25, 100);
            $total += $day;
        }
        return $total;
    };
    $cases = [
        [[8, 0], [7, 1]], [[3, 1]], [[9, 0], [9, 1], [4, 0]],
        [[5, 1], [5, 1]], [[8, 0]], [[6, 1], [2, 0], [3, 1], [8, 0]],
    ];
    $tasks = [];
    foreach ($cases as $cs) {
        $js = '[' . implode(',', array_map(function ($e) { return "[{$e[0]},{$e[1]}]"; }, $cs)) . ']';
        $e = $oracle($cs);
        $tasks[] = ['text' => "weeklyPay({$js}) === {$e}", 'check' => "weeklyPay({$js}) === {$e}"];
    }
    return [
        'slug' => '', 'title' => 'Night-Shift Payroll Bug', 'kind' => 'debug', 'difficulty' => 'medium',
        'minutes' => 15,
        'summary' => 'Payroll overpays nights by stray rupees and skips the first timesheet entry. Two one-line bugs.',
        'brief' => '<p>Spec: each entry is <code>[hours, night]</code> — pay is <code>hours × ' . $base . '</code>, night shifts add <strong>25% floored</strong> to whole rupees. Every entry counts, including the first. Fix <code>payroll.js</code> only.</p>',
        'files' => [
            ['name' => 'rates.js', 'readonly' => true, 'content' => $ratesJs],
            ['name' => 'payroll.js', 'readonly' => false, 'content' => $payrollJs],
        ],
        'tasks' => $tasks,
        'fix' => ['payroll.js' => $payrollFix],
    ];
}

/* ---- L-F: log window stats (debug) ---- */
function cf_ai_lab_logwin(Closure $r, int $uid, int $batch, $n): array {
    // deterministic 10-line timeline around 10:00..10:32
    $lines = ['10:00 [ERROR] boot crash', '10:03 [INFO] restart', '10:07 [WARN] queue 90%',
              '10:12 [ERROR] db timeout', '10:15 [INFO] healed', '10:20 [ERROR] payment fail',
              '10:25 [WARN] slow query', '10:30 [ERROR] retry ok', '10:32 [INFO] stable', '10:35 [ERROR] cache miss'];
    $samplesJs = "// samples.js — READONLY production excerpt (do not edit).\n"
        . 'var LINES = ' . json_encode($lines) . ';';
    $oracle = function (string $level, int $endMin, int $win) use ($lines): int {
        $n = 0;
        foreach ($lines as $l) {
            $t = (int)substr($l, 0, 2) * 60 + (int)substr($l, 3, 2);
            $tag = strtoupper(explode(' ', $l)[1]);
            if ($tag === '[' . strtoupper($level) . ']' && $t > $endMin - $win && $t <= $endMin) $n++;
        }
        return $n;
    };
    $cases = [['error', 630, 15], ['ERROR', 632, 35], ['warn', 630, 30], ['info', 632, 16], ['error', 615, 10], ['WARN', 638, 40]];
    $tasks = [];
    foreach ($cases as [$lvl, $e, $w]) {
        $x = $oracle($lvl, $e, $w);
        $tasks[] = [
            'text' => "countInWindow(LINES, '{$lvl}', {$e}, {$w}) === {$x}",
            'check' => "countInWindow(LINES, '{$lvl}', {$e}, {$w}) === {$x}",
        ];
    }
    $parserJs = "// parser.js — windowed log stats. SPEC (from observability docs):
//   count lines tagged [level] with timestamp t in (endMin − windowMin, endMin]  — LEFT-OPEN.
//   Level matching is case-insensitive ('error' matches [ERROR]).
function countInWindow(lines, level, endMin, windowMin) {
  var n = 0;
  for (var i = 0; i < lines.length; i++) {
    var l = lines[i];
    var t = Number(l.slice(0, 2)) * 60 + parseInt(l.slice(3, 5), 10);
    var tag = l.split(' ')[1];
    if (tag === '[' + level + ']' && t >= endMin - windowMin && t <= endMin) n++;
  }
  return n;
}";
    $parserFix = "// parser.js — FIXED: left-open window + case-insensitive tag compare.
function countInWindow(lines, level, endMin, windowMin) {
  var n = 0;
  var want = '[' + level.toUpperCase() + ']';
  for (var i = 0; i < lines.length; i++) {
    var l = lines[i];
    var t = Number(l.slice(0, 2)) * 60 + parseInt(l.slice(3, 5), 10);
    if (l.split(' ')[1].toUpperCase() === want && t > endMin - windowMin && t <= endMin) n++;
  }
  return n;
}";
    return [
        'slug' => '', 'title' => 'Log Window Stats', 'kind' => 'debug', 'difficulty' => 'hard',
        'minutes' => 20,
        'summary' => 'Observability boards disagree: window is left-open, levels are case-insensitive — the parser does neither.',
        'brief' => '<p>Spec: count lines tagged <code>[level]</code> with timestamps in <strong>(endMin − windowMin, endMin]</strong> — a line exactly on the left edge is OUT. Levels compare case-insensitively. Both details are lost in <code>parser.js</code>. Times are <code>HH:MM</code> at 6-character positions (line[:2] hours, line[3:5] minutes).</p>',
        'files' => [
            ['name' => 'samples.js', 'readonly' => true, 'content' => $samplesJs],
            ['name' => 'parser.js', 'readonly' => false, 'content' => $parserJs],
        ],
        'tasks' => $tasks,
        'fix' => ['parser.js' => $parserFix],
    ];
}

/* ---- lab registry: 6 generators, batch of 10 from rotation ---- */
function cf_ai_lab_gens(): array {
    return [
        'legacy-pricing' => 'cf_ai_lab_pricing',
        'restock-report' => 'cf_ai_lab_stock',
        'carrier-adapter' => 'cf_ai_lab_delivery',
        'coupon-engine' => 'cf_ai_lab_coupons',
        'night-payroll' => 'cf_ai_lab_payroll',
        'log-window' => 'cf_ai_lab_logwin',
    ];
}

/** Full batch of 10 AI labs for (uid, batch). Deterministic; includes 'fix' maps. */
function cf_ai_labs_for(int $uid, int $batch): array {
    $gens = array_values(cf_ai_lab_gens());
    $keys = array_keys(cf_ai_lab_gens());
    $out = [];
    for ($i = 0; $i < 10; $i++) {
        $t = ($batch * 6 + $i) % 6;
        $r = cf_ai_rng("l:{$uid}:{$batch}:{$i}:" . $keys[$t]);
        $lab = $gens[$t]($r, $uid, $batch, $i);
        $lab['slug'] = "ail{$uid}b{$batch}-" . ($i + 1) . '-' . $keys[$t];
        $lab['title'] = $lab['title'] . " · 🤖 set {$batch}";
        $out[] = $lab;
    }
    return $out;
}

/** Regenerate one owned AI lab from its slug, or null. */
function cf_ai_lab(int $uid, string $slug): ?array {
    if (!preg_match('/^ail(\d+)b(\d+)-(\d+)-([a-z-]+)$/', $slug, $m)) return null;
    if ((int)$m[1] !== $uid) return null;
    $batch = (int)$m[2]; $n = (int)$m[3] - 1;
    if ($batch < 1 || $n < 0 || $n > 9) return null;
    $labs = cf_ai_labs_for($uid, $batch);
    return $labs[$n]['slug'] === $slug ? $labs[$n] : null;
}

/**
 * Which AI lab batches are unlocked for this user?
 * Rule: canonical all-done → batch 1; all 10 of batch b done → batch b+1.
 * Returns [maxBatch, canonicalDone].
 */
function cf_ai_labs_unlock(PDO $pdo, int $uid): array {
    $canon = [];
    foreach (cf_labs() as $l) $canon[] = $l['slug'];
    $doneSlugs = array_column(db_all('SELECT lab_slug FROM lab_progress WHERE user_id = ?', [$uid]), 'lab_slug');
    $done = array_fill_keys($doneSlugs, true);
    $canonDone = 0;
    foreach ($canon as $s) if (isset($done[$s])) $canonDone++;
    $allCanon = $canonDone === count($canon);
    $b = 0;
    if ($allCanon) {
        $b = 1;
        while ($b < 50) {
            $all = true;
            foreach (cf_ai_labs_for($uid, $b) as $lab) {
                if (!isset($done[$lab['slug']])) { $all = false; break; }
            }
            if (!$all) break;
            $b++;
        }
    }
    return [$b, $allCanon];
}

/* ========================= REFACTOR TEMPLATES ========================= */
/* Messy-but-working repos; 'fix' = senior-grade version (harness only),  */
/* base = measured metrics of the messy code (comp + dup), like canonical. */

/* ---- R-A: pricing god-function ---- */
function cf_ai_rf_god(Closure $r, int $uid, int $batch, int $n): array {
    $fq = cf_ai_int($r, 3, 5);          // bulk qty threshold (electronics)
    $fd = cf_ai_pick($r, [3, 4, 5]);    // bulk discount %
    $cp = cf_ai_pick($r, [8, 10, 12]);  // coupon %
    $TR = ['food' => '0.05', 'electronics' => '0.18'];
    $line = function (array $items, string $coupon = 'NONE') use ($fq, $fd, $cp, $TR) {
        $total = 0.0; $parts = [];
        foreach ($items as [$name, $cat, $price, $qty]) {
            $base = $price * $qty;
            if ($cat === 'electronics' && $qty >= $fq) $base *= (1 - $fd / 100);
            $wt = $base * (1 + (float)$TR[$cat]);
            $total += $wt;
            $parts[] = "{$name} x{$qty} = " . cf_ai_m2($wt);
        }
        $disc = $coupon === 'SAVE' ? $total * $cp / 100 : 0.0;
        $total -= $disc;
        return ['total' => round($total, 2), 'receipt' => implode(' | ', $parts) . ' || TOTAL: ' . cf_ai_m2($total)];
    };
    $cart1 = [['Widget', 'electronics', 100, $fq], ['Apple', 'food', 40, 2]];
    $cart2 = [['Apple', 'food', 40, 2]];
    $cart3 = [['Widget', 'electronics', 100, 1]];
    $cart4 = [['Pen', 'food', 2, 1]];
    $cart5 = [['Widget', 'electronics', 100, $fq], ['Cable', 'electronics', 25, 2], ['Apple', 'food', 40, 3]];
    $o1 = $line($cart1); $o2 = $line($cart2); $o3 = $line($cart3);
    $o4 = $line($cart4, 'SAVE'); $o5 = $line($cart5, 'SAVE');
    $J = function (array $items): string {
        return '[' . implode(',', array_map(function ($i) { return "{name:\"{$i[0]}\",cat:\"{$i[1]}\",price:{$i[2]},qty:{$i[3]}}"; }, $items)) . ']';
    };
    $checks = [
        ['text' => "electronics bulk ({$fq}×) gets {$fd}% off + 18% tax", 'check' => "processOrder({$J($cart1)},\"NONE\").total === {$o1['total']}"],
        ['text' => 'food line: 2×40 → with 5% tax', 'check' => "processOrder({$J($cart2)},\"NONE\").total === {$o2['total']}"],
        ['text' => 'single electronics: no bulk discount', 'check' => "processOrder({$J($cart3)},\"NONE\").total === {$o3['total']}"],
        ['text' => "SAVE coupon takes {$cp}% off after tax — tiny orders can go negative-safe", 'check' => "processOrder({$J($cart4)},\"SAVE\").total === {$o4['total']}"],
        ['text' => 'receipt format preserved byte-for-byte', 'check' => "processOrder({$J($cart1)},\"NONE\").receipt === \"{$o1['receipt']}\""],
        ['text' => 'mixed cart with coupon', 'check' => "processOrder({$J($cart5)},\"SAVE\").total === {$o5['total']}"],
    ];
    $messy = "// legacy checkout — one function does everything (2019, do not touch the OUTPUT format)
function processOrder(items, coupon) {
  var total = 0;
  var receipt = '';
  for (var i = 0; i < items.length; i++) {
    var it = items[i];
    if (it.cat === 'electronics') {
      var x = it.price * it.qty;
      if (it.qty >= {$fq}) { x = x - x * {$fd} / 100; }
      var y = x + x * 0.18;
      total = total + y;
      if (receipt !== '') receipt = receipt + ' | ';
      receipt = receipt + it.name + ' x' + it.qty + ' = ' + y.toFixed(2);
    } else if (it.cat === 'food') {
      var x2 = it.price * it.qty;
      var y2 = x2 + x2 * 0.05;
      total = total + y2;
      if (receipt !== '') receipt = receipt + ' | ';
      receipt = receipt + it.name + ' x' + it.qty + ' = ' + y2.toFixed(2);
    }
  }
  if (coupon === 'SAVE') { total = total - total * {$cp} / 100; }
  receipt = receipt + ' || TOTAL: ' + total.toFixed(2);
  return { total: Math.round(total * 100) / 100, receipt: receipt };
}";
    $fix = "// cleaned: table-driven taxes/bulk, tiny named helpers — same bytes out.
var TAX = { food: 0.05, electronics: 0.18 };
function bulkRate(item) {
  return item.cat === 'electronics' && item.qty >= {$fq} ? {$fd} / 100 : 0;
}
function lineWithTax(item) {
  return item.price * item.qty * (1 - bulkRate(item)) * (1 + TAX[item.cat]);
}
function processOrder(items, coupon) {
  var rows = items.map(function (it) { return it.name + ' x' + it.qty + ' = ' + lineWithTax(it).toFixed(2); });
  var sub = items.reduce(function (s, it) { return s + lineWithTax(it); }, 0);
  var total = coupon === 'SAVE' ? sub * (1 - {$cp} / 100) : sub;
  return { total: Math.round(total * 100) / 100, receipt: rows.join(' | ') + ' || TOTAL: ' + total.toFixed(2) };
}";
    $m = cf_ai_metrics($messy);
    return [
        'slug' => '', 'title' => 'The 2019 Checkout Blob', 'summary' => "One angry function prices, bulk-discounts, taxes and formats receipts with hardcoded {$fd}%/18%/5% magic numbers.",
        'goal' => "Pull the tax rates, the {$fq}-qty bulk rule and the receipt formatter into named helpers + a lookup table. Output must stay byte-identical.",
        'base' => ['comp' => $m['comp'], 'dup' => $m['dup']],
        'checks' => $checks,
        'files' => [['name' => 'legacy-checkout.js', 'content' => $messy]],
        'fix' => $fix,
    ];
}

/* ---- R-B: stringy vendor report (duplication monster) ---- */
function cf_ai_rf_report(Closure $r, int $uid, int $batch, int $n): array {
    $names = ['Asha Traders', 'Bhavesh Textiles', 'Craftline Co', 'Desai Mills'];
    $skus = ['AT-44', 'BT-07', 'CL-19', 'DM-81'];
    $star = cf_ai_pick($r, [40000, 50000, 60000]);
    $vs = [];
    foreach ($names as $i => $nm) {
        $vs[] = [
            'name' => $nm, 'sku' => $skus[$i],
            'orders' => cf_ai_int($r, 4, 20),
            'revenue' => cf_ai_int($r, 2, 9) * 10000 + cf_ai_int($r, 100, 9000),
        ];
    }
    // guarantee the star rule shows both branches (deterministic draws from the same stream)
    $vs[1]['revenue'] = $star + cf_ai_int($r, 2, 40) * 1000;                        // a star vendor
    $vs[2]['revenue'] = $star - cf_ai_int($r, 1, 15) * 1000 - cf_ai_int($r, 1, 999); // below the bar
    $oracle = function (array $vs) use ($star): string {
        $out = "VENDOR REPORT\n";
        $totO = 0; $totR = 0;
        foreach ($vs as $v) {
            $avg = intdiv($v['revenue'], $v['orders']);
            $out .= "{$v['name']} ({$v['sku']}) — orders: {$v['orders']}, revenue: ₹{$v['revenue']}, avg: ₹{$avg}\n";
            if ($v['revenue'] > $star) $out .= "  ★ star vendor\n";
            $totO += $v['orders']; $totR += $v['revenue'];
        }
        return $out . 'TOTAL vendors: ' . count($vs) . ", orders: {$totO}, revenue: ₹{$totR}";
    };
    $expected = $oracle($vs);
    $checks = [
        ['text' => 'report matches expected output byte-for-byte', 'check' => 'buildReport() === ' . json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
        ['text' => 'starts with the header line', 'check' => 'buildReport().indexOf("VENDOR REPORT\\n") === 0'],
        ['text' => "★ line appears for vendors above ₹{$star}", 'check' => 'buildReport().indexOf("★ star vendor") > -1'],
        ['text' => 'ends with the TOTAL line (no trailing newline)', 'check' => 'buildReport().slice(-1) !== "\\n" && buildReport().indexOf("TOTAL vendors:") > -1'],
    ];
    // messy: the SAME block pasted once per vendor, re-assigning shared vars (so the
    // avg / out+= / if lines are literally identical — a true duplication monster).
    $decl = true;
    $blocks = [];
    foreach ($vs as $v) {
        $kw = $decl ? 'var ' : '';
        $decl = false;
        $blocks[] = "  {$kw}name = \"{$v['name']}\", sku = \"{$v['sku']}\", orders = {$v['orders']}, revenue = {$v['revenue']};
  var avg = revenue / orders | 0;
  out += name + \" (\" + sku + \") — orders: \" + orders + \", revenue: ₹\" + revenue + \", avg: ₹\" + avg + \"\\n\";
  if (revenue > {$star}) { out += \"  ★ star vendor\\n\"; }";
    }
    $totO = array_sum(array_column($vs, 'orders'));
    $totR = array_sum(array_column($vs, 'revenue'));
    $messy = "// monthly vendor report — same block pasted once per vendor ('just change the values')
function buildReport() {
  var out = \"VENDOR REPORT\\n\";
" . implode("\n", $blocks) . "
  out += \"TOTAL vendors: " . count($vs) . ", orders: {$totO}, revenue: ₹{$totR}\";
  return out;
}";
    $fix = "// cleaned: data table + one loop; output identical.
var VENDORS = " . json_encode($vs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";
var STAR = {$star};
function vendorRow(v) {
  var row = v.name + ' (' + v.sku + ') — orders: ' + v.orders + ', revenue: ₹' + v.revenue + ', avg: ₹' + (v.revenue / v.orders | 0);
  return v.revenue > STAR ? row + '\\n  ★ star vendor' : row;
}
function buildReport() {
  var rows = VENDORS.map(vendorRow);
  var totO = VENDORS.reduce(function (s, v) { return s + v.orders; }, 0);
  var totR = VENDORS.reduce(function (s, v) { return s + v.revenue; }, 0);
  rows.push('TOTAL vendors: ' + VENDORS.length + ', orders: ' + totO + ', revenue: ₹' + totR);
  return 'VENDOR REPORT\\n' + rows.join('\\n');
}";
    $m = cf_ai_metrics($messy);
    return [
        'slug' => '', 'title' => 'Copy-Paste Vendor Report', 'summary' => count($vs) . " vendor blocks pasted one under another — same 3 lines (avg / append / ★-if), only the values re-assigned. Plus a ₹{$star} star rule.",
        'goal' => 'Kill the copy-paste: vendor rows belong in a data table rendered by ONE loop. Keep every byte of the report identical, including the ★ lines and the TOTAL line.',
        'base' => ['comp' => $m['comp'], 'dup' => $m['dup']],
        'checks' => $checks,
        'files' => [['name' => 'vendor-report.js', 'content' => $messy]],
        'fix' => $fix,
    ];
}
/* ---- R-C: nested tier pyramid ---- */
function cf_ai_rf_pyramid(Closure $r, int $uid, int $batch, int $n): array {
    $c = [cf_ai_int($r, 2, 4) * 500, cf_ai_int($r, 5, 7) * 500, cf_ai_int($r, 8, 10) * 500, cf_ai_int($r, 11, 14) * 500];
    sort($c);
    $names = ['starter', 'growth', 'pro', 'scale', 'enterprise'];
    $tier = function (int $amt) use ($c, $names): string {
        foreach ($c as $i => $x) if ($amt < $x) return $names[$i];
        return $names[4];
    };
    $rank = function (int $amt) use ($c): int {
        foreach ($c as $i => $x) if ($amt < $x) return $i;
        return 4;
    };
    $bounds = [$c[0] - 1, $c[0], $c[1] - 1, $c[1], $c[2] - 1, $c[2], $c[3] - 1, $c[3], $c[3] + 2500];
    $checks = [];
    foreach ($bounds as $b) {
        $checks[] = ['text' => "tierLabel({$b}) → \"{$tier($b)}\"", 'check' => "tierLabel({$b}) === \"{$tier($b)}\""];
        $checks[] = ['text' => "tierRank({$b}) → {$rank($b)}", 'check' => "tierRank({$b}) === {$rank($b)}"];
    }
    // builds one nested-if pyramid: $var takes $vals[i] for tier i, `return $var;` at the end
    $mkPyramid = function (string $var, array $vals) use ($c): string {
        return "  var {$var} = '';
  if (amount >= {$c[3]}) {
    {$var} = {$vals[4]};
  } else {
    if (amount >= {$c[2]}) {
      {$var} = {$vals[3]};
    } else {
      if (amount >= {$c[1]}) {
        {$var} = {$vals[2]};
      } else {
        if (amount >= {$c[0]}) {
          {$var} = {$vals[1]};
        } else {
          {$var} = {$vals[0]};
        }
      }
    }
  }
  return {$var};";
    };
    $q = function (string $s): string { return "'{$s}'"; };
    $messy = "// tier pyramids — sales added tierRank() by copy-pasting tierLabel() (2021, do not touch)\nfunction tierLabel(amount) {\n"
        . $mkPyramid('label', array_map($q, $names))
        . "\n}\n\nfunction tierRank(amount) {\n"
        . $mkPyramid('rank', ['0', '1', '2', '3', '4'])
        . "\n}";
    $fix = "// cleaned: one scan drives both lookups.
var CUTS = [" . implode(', ', $c) . "];
var NAMES = ['" . implode("', '", $names) . "'];
function tierIndex(amount) {
  for (var i = 0; i < CUTS.length; i++) if (amount < CUTS[i]) return i;
  return CUTS.length;
}
function tierLabel(amount) { return NAMES[tierIndex(amount)]; }
function tierRank(amount) { return tierIndex(amount); }";
    $m = cf_ai_metrics($messy);
    return [
        'slug' => '', 'title' => 'The If-Pyramid Twins', 'summary' => "Two four-deep nested if/else ladders (billing tier name + rank) — the second was copy-pasted from the first. Cutoffs: " . implode(', ', $c) . '.',
        'goal' => 'Replace both pyramids with ONE cutoffs scan reused by both functions. tierLabel and tierRank answers must stay identical.',
        'base' => ['comp' => $m['comp'], 'dup' => $m['dup']],
        'checks' => $checks,
        'files' => [['name' => 'tiers.js', 'content' => $messy]],
        'fix' => $fix,
    ];
}
/* ---- R-D: copy-paste channel validators ---- */
function cf_ai_rf_validators(Closure $r, int $uid, int $batch, int $n): array {
    $chs = ['web', 'pos', 'app', 'ivr'];
    $ok = function (string $line): bool {
        $p = explode(':', $line);
        if (count($p) !== 4) return false;
        [$ch, $sku, $qty, $price] = $p;
        if (!in_array($ch, ['web', 'pos', 'app', 'ivr'], true)) return false;
        if (!preg_match('/^[A-Z]-\d{2}$/', $sku)) return false;
        return (int)$qty > 0 && (int)$price > 0;
    };
    $oracle = function (array $lines) use ($ok): array {
        $out = ['web' => 0, 'pos' => 0, 'app' => 0, 'ivr' => 0];
        foreach ($lines as $l) if ($ok($l)) $out[explode(':', $l)[0]]++;
        return $out;
    };
    $sets = [
        ['web:W-10:2:499', 'pos:P-11:1:99', 'app:A-09:0:50', 'ivr:I-77:3:150'],
        ['web:W-10:2:499', 'web:WW-10:2:499', 'pos:P-99:1:1'],
        ['x:W-10:2:499', 'app:A-01:5:25', 'ivr:I-02:1:40', 'web:W-10:2:499'],
        ['web:W-10:2:499', 'app:A-01:5:25'],
        ['ivr:I-77:3:150'],
        ['web:W-10:0:499', 'pos:P-11:1:0', 'app:x-01:2:10'],
        ['app:A-01:5:25', 'app:A-02:5:25', 'app:A-03:5:25'],
        ['web:W-10:2:499', 'ivr:I-77:3:150', 'pos:P-11:2:200'],
    ];
    $checks = [];
    foreach ($sets as $i => $lines) {
        $o = $oracle($lines);
        $js = json_encode($lines, JSON_UNESCAPED_SLASHES);
        // JS JSON.stringify key order = object literal order (web,pos,app,ivr); php json_encode keeps insertion order — same ✓
        $checks[] = [
            'text' => 'counts → ' . json_encode($o, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' for [' . implode(' ', $lines) . ']',
            'check' => "JSON.stringify(validateAll({$js})) === " . json_encode(json_encode($o, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
    }
    $body = function (string $ch): string {
        return "function validate_{$ch}(line) {
  var p = line.split(':');
  if (p.length !== 4) return false;
  if (p[0] !== '{$ch}') return false;
  if (!/^[A-Z]-\d{2}$/.test(p[1])) return false;
  if (parseInt(p[2], 10) <= 0) return false;
  if (parseInt(p[3], 10) <= 0) return false;
  return true;
}";
    };
    $messy = "// channel validators — one file per sprint, 'adjust the constant'
" . $body('web') . "
" . $body('pos') . "
" . $body('app') . "
" . $body('ivr') . "

function validateAll(lines) {
  var out = { web: 0, pos: 0, app: 0, ivr: 0 };
  for (var i = 0; i < lines.length; i++) {
    if (validate_web(lines[i])) out.web++;
    else if (validate_pos(lines[i])) out.pos++;
    else if (validate_app(lines[i])) out.app++;
    else if (validate_ivr(lines[i])) out.ivr++;
  }
  return out;
}";
    $fix = "// cleaned: one validator parameterized by channel.
function validateLine(line, ch) {
  var p = line.split(':');
  return p.length === 4 && p[0] === ch && /^[A-Z]-\d{2}$/.test(p[1])
      && parseInt(p[2], 10) > 0 && parseInt(p[3], 10) > 0;
}
function validateAll(lines) {
  var out = { web: 0, pos: 0, app: 0, ivr: 0 };
  for (var i = 0; i < lines.length; i++) {
    for (var ch in out) if (validateLine(lines[i], ch)) { out[ch]++; break; }
  }
  return out;
}";
    $m = cf_ai_metrics($messy);
    return [
        'slug' => '', 'title' => 'Four Validators, One Truth', 'summary' => 'web/pos/app/ivr each got a copy-pasted validator — the same 8 lines, one string different.',
        'goal' => 'Collapse to one parameterized validator + loop. Counts must stay identical.',
        'base' => ['comp' => $m['comp'], 'dup' => $m['dup']],
        'checks' => $checks,
        'files' => [['name' => 'validators.js', 'content' => $messy]],
        'fix' => $fix,
    ];
}

/* ---- refactor registry ---- */
function cf_ai_rf_gens(): array {
    return [
        'checkout-blob' => 'cf_ai_rf_god',
        'vendor-report' => 'cf_ai_rf_report',
        'tier-pyramid' => 'cf_ai_rf_pyramid',
        'copy-validators' => 'cf_ai_rf_validators',
    ];
}

/** Full batch of 10 AI refactor challenges. Deterministic; includes 'fix'. */
function cf_ai_refactors_for(int $uid, int $batch): array {
    $gens = array_values(cf_ai_rf_gens());
    $keys = array_keys(cf_ai_rf_gens());
    $out = [];
    for ($i = 0; $i < 10; $i++) {
        $t = ($batch * 4 + $i) % 4;
        $r = cf_ai_rng("r:{$uid}:{$batch}:{$i}:" . $keys[$t]);
        $c = $gens[$t]($r, $uid, $batch, $i);
        $c['slug'] = "air{$uid}b{$batch}-" . ($i + 1) . '-' . $keys[$t];
        $c['title'] = $c['title'] . " · 🤖 set {$batch}";
        $out[] = $c;
    }
    return $out;
}

/** Regenerate one owned AI refactor challenge from its slug, or null. */
function cf_ai_refactor(int $uid, string $slug): ?array {
    if (!preg_match('/^air(\d+)b(\d+)-(\d+)-([a-z-]+)$/', $slug, $m)) return null;
    if ((int)$m[1] !== $uid) return null;
    $batch = (int)$m[2]; $n = (int)$m[3] - 1;
    if ($batch < 1 || $n < 0 || $n > 9) return null;
    $all = cf_ai_refactors_for($uid, $batch);
    return $all[$n]['slug'] === $slug ? $all[$n] : null;
}

/** Unlock rule — same shape as labs. Done = any run with tests_passed = tests_total. */
function cf_ai_refactor_unlock(PDO $pdo, int $uid): array {
    $canon = [];
    foreach (cf_refactors() as $c) $canon[] = $c['slug'];
    $doneSlugs = db_all(
        'SELECT DISTINCT challenge_slug FROM refactor_runs WHERE user_id = ? AND tests_passed = tests_total AND tests_total > 0',
        [$uid]
    );
    $done = array_fill_keys(array_column($doneSlugs, 'challenge_slug'), true);
    $canonDone = 0;
    foreach ($canon as $s) if (isset($done[$s])) $canonDone++;
    $allCanon = $canonDone === count($canon);
    $b = 0;
    if ($allCanon) {
        $b = 1;
        while ($b < 50) {
            $all = true;
            foreach (cf_ai_refactors_for($uid, $b) as $c) {
                if (!isset($done[$c['slug']])) { $all = false; break; }
            }
            if (!$all) break;
            $b++;
        }
    }
    return [$b, $allCanon];
}
