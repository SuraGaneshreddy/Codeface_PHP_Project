#!/bin/bash
# Codeface end-to-end HTTP integration tests (developer tool).
# Covers: AI treadmills (problems/labs/refactor), ownership & unlock gates,
#         the 10-problem practice gate, guest behavior, live rooms (create/pads/
#         sync/409/chat/SSE/heartbeat), matchmaking, email-OTP password reset
#         (section H runs when ITEST_SMTPBOX points at a fake-smtp capture file),
#         email existence guard (MX + typo suggestions), two-step verified signup
#         (section J, mailbox-existence gate), API validation.
#
# Prereqs: the app reachable (e.g. `php -S 127.0.0.1:8093 -t .`), fresh seeded DB
#          (delete database/data/codeface.sqlite and hit any page), demo accounts present.
# Usage:
#   bash tools/integration-test.sh                      # sqlite dev server on :8093
#   ITEST_BASE=http://127.0.0.1:8094 \
#   ITEST_DSN="mysql:host=127.0.0.1;port=3306;dbname=codeface" \
#   ITEST_DSN_USER=root ITEST_DSN_PASS= bash tools/integration-test.sh
set -u
BASE="${ITEST_BASE:-http://127.0.0.1:8093}"
APP="$BASE/frontend"      # all browser pages live here
API="$BASE/backend/api"   # all JSON endpoints live here
DSN="${ITEST_DSN:-sqlite:/home/user/codeface/database/data/codeface.sqlite}"
DU="${ITEST_DSN_USER:-}"
DP="${ITEST_DSN_PASS:-}"
PHPSQL() { DSN="$DSN" DU="$DU" DP="$DP" php -r '$p=new PDO(getenv("DSN"),getenv("DU")?getenv("DU"):null,getenv("DP")?getenv("DP"):null);$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);eval($argv[1]);' "$1"; }
PASS=0; FAIL=0
ok()  { PASS=$((PASS+1)); echo "  ✓ $1"; }
bad() { FAIL=$((FAIL+1)); echo "  ✗✗✗ $1"; }
ck()  { if [ "${2:-1}" -eq 0 ]; then ok "$1"; else bad "$1 — ${3:-?}"; fi; }
login() { local jar=/tmp/cf_jar_$1.txt; rm -f "$jar"
  local c=$(curl -s -c "$jar" $APP/login.php | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$jar" -c "$jar" -X POST $APP/login.php --data-urlencode "csrf=$c" --data-urlencode "identity=$1" --data-urlencode "password=password123" -o /dev/null; }
jar() { echo /tmp/cf_jar_$1.txt; }
csrf_of() { curl -s -b "$(jar $1)" "$APP/index.php" | grep -o '<meta name="csrf" content="[^"]*"' | head -1 | sed 's/.*content="//;s/"//'; }
# Two-step verified signup (sections H–J): with SMTP configured, register.php first
# emails a code and only creates the account after it comes back. Handles both modes.
register_verified() { # $1 jar $2 username $3 email $4 password → prints final HTTP code
  local jar=$1 c code otp
  c=$(curl -s -c "$jar" "$APP/register.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  code=$(curl -s -o /tmp/cf_reg_s1.html -w "%{http_code}" -c "$jar" -b "$jar" -X POST "$APP/register.php" \
    --data-urlencode "csrf=$c" --data-urlencode "username=$2" --data-urlencode "email=$3" \
    --data-urlencode "password=$4" --data-urlencode "confirm=$4")
  [ "$code" = "302" ] && { echo 302; return; }                          # one-step mode (no SMTP)
  grep -q 'name="otp"' /tmp/cf_reg_s1.html || { echo "$code"; return; } # validation error page
  otp=$(awk '/^==== /{b=""} {b=b $0 ORS} END{printf "%s", b}' "$ITEST_SMTPBOX" | grep -oE '[0-9]{6}' | tail -1)
  curl -s -o /dev/null -w "%{http_code}" -c "$jar" -b "$jar" -X POST "$APP/register.php" \
    --data-urlencode "csrf=$c" --data-urlencode "action=verify" --data-urlencode "otp=$otp"
}
http() { curl -s -b "$1" -o "${3:-/dev/null}" -w "%{http_code}" "$APP/$2"; }

# idempotent insert helper (driver-agnostic via NOT EXISTS)
sweep_problems() { # $1 uid
  PHPSQL '$p->exec("INSERT INTO submissions (user_id, problem_id, status, code, passed, total, created_at)
    SELECT '$1', p.id, \"pass\", \"--itest\", 1, 1, CURRENT_TIMESTAMP FROM problems p
    WHERE p.ai_user_id IS NULL AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.user_id='$1' AND s.problem_id=p.id AND s.status=\"pass\")");'
}
sweep_ai_problems() { # $1 uid
  PHPSQL '$p->exec("INSERT INTO submissions (user_id, problem_id, status, code, passed, total, created_at)
    SELECT '$1', p.id, \"pass\", \"--itest\", 1, 1, CURRENT_TIMESTAMP FROM problems p
    WHERE p.ai_user_id='$1' AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.user_id='$1' AND s.problem_id=p.id AND s.status=\"pass\")");'
}
sweep_labs() { # $1 uid: canonical done
  PHPSQL 'require "/home/user/codeface/backend/lib/labsbank.php";
    foreach (cf_labs() as $l) { $st=$p->prepare("SELECT 1 FROM lab_progress WHERE user_id=? AND lab_slug=?");
      $st->execute(["'"$1"'",$l["slug"]]); if(!$st->fetch()){
      $p->prepare("INSERT INTO lab_progress (user_id,lab_slug,completed_at) VALUES (?,?,CURRENT_TIMESTAMP)")->execute(["'"$1"'",$l["slug"]]); } }'
}
sweep_ai_labs() { # $1 uid $2 batch — regenerate slugs server-side, complete them (idempotent)
  PHPSQL 'require "/home/user/codeface/backend/lib/emitters.php";require "/home/user/codeface/backend/lib/db.php";require "/home/user/codeface/backend/lib/helpers.php";require "/home/user/codeface/backend/lib/aibank.php";
    foreach (cf_ai_labs_for(intval("'"$1"'"), intval("'"$2"'")) as $l) { $st=$p->prepare("SELECT 1 FROM lab_progress WHERE user_id=? AND lab_slug=?");
      $st->execute([intval("'"$1"'"),$l["slug"]]); if ($st->fetch()) continue;
      $p->prepare("INSERT INTO lab_progress (user_id,lab_slug,completed_at) VALUES (?,?,CURRENT_TIMESTAMP)")->execute([intval("'"$1"'"),$l["slug"]]); }'
}
sweep_refactors() { # $1 uid: canonical green
  PHPSQL 'require "/home/user/codeface/backend/lib/refactorbank.php";
    $st=$p->prepare("INSERT INTO refactor_runs (user_id,challenge_slug,score,tests_passed,tests_total,metrics,created_at) VALUES (?,?,?,?,?,'"'"'{}'"'"',CURRENT_TIMESTAMP)");
    foreach (cf_refactors() as $c) { $t=count($c["checks"]); $st->execute(["'"$1"'",$c["slug"],92,$t,$t]); }'
}
sweep_ai_refactors() { # $1 uid $2 batch
  PHPSQL 'require "/home/user/codeface/backend/lib/emitters.php";require "/home/user/codeface/backend/lib/refactorbank.php";require "/home/user/codeface/backend/lib/db.php";require "/home/user/codeface/backend/lib/helpers.php";require "/home/user/codeface/backend/lib/aibank.php";
    $st=$p->prepare("INSERT INTO refactor_runs (user_id,challenge_slug,score,tests_passed,tests_total,metrics,created_at) VALUES (?,?,?,?,?,'"'"'{}'"'"',CURRENT_TIMESTAMP)");
    foreach (cf_ai_refactors_for(intval("'"$1"'"), intval("'"$2"'")) as $c) { $t=count($c["checks"]); $st->execute([intval("'"$1"'"),$c["slug"],95,$t,$t]); }'
}
db_has() { PHPSQL "echo (int)(\$p->query(\"SELECT COUNT(*) c FROM users WHERE username='probe_'\")->fetch()['c'] ?? 0);"; }

echo "############ 0. logins"
login carol; login dev_mike; login alice; login bob
TA2=$(csrf_of alice); TB2=$(csrf_of bob); TC2=$(csrf_of carol)
echo "############ A. PRACTICE GATE (10 solves unlock Labs & Refactor)"
PHPSQL '$p->exec("DELETE FROM submissions WHERE user_id=4");
  $p->exec("INSERT INTO submissions (user_id, problem_id, status, code, passed, total, created_at)
            SELECT 4, id, \"pass\", \"--itest\", 1, 1, CURRENT_TIMESTAMP FROM problems ORDER BY id LIMIT 3");' 
CODE=$(http "$(jar dev_mike)" labs.php); [ "$CODE" = "403" ]; ck "dev_mike (3/10): labs.php 403" $? "got $CODE"
curl -s -b "$(jar dev_mike)" $APP/labs.php > /tmp/cf_g.html
grep -q "complete 10 problems in the Practice section" /tmp/cf_g.html; ck "wall explains the rule" $?
grep -q "<strong>3/10</strong>" /tmp/cf_g.html; ck "wall shows 3/10 + 7 to go" $?
CODE=$(http "$(jar dev_mike)" "lab.php?slug=legacy-cart-bug"); [ "$CODE" = "403" ]; ck "lab.php direct 403" $? "got $CODE"
CODE=$(http "$(jar dev_mike)" refactor.php); [ "$CODE" = "403" ]; ck "refactor.php 403" $? "got $CODE"
CODE=$(http "$(jar dev_mike)" "refactor-challenge.php?slug=god-function-checkout"); [ "$CODE" = "403" ]; ck "challenge direct 403" $? "got $CODE"
T=$(csrf_of dev_mike)
R=$(curl -s -b "$(jar dev_mike)" -X POST $API/labs/complete.php -H "Content-Type: application/json" -H "X-CSRF-Token: $T" -d '{"slug":"legacy-cart-bug"}')
echo "$R" | grep -q "unlock after 10"; ck "labs API 403" $? "got $R"
R=$(curl -s -b "$(jar dev_mike)" -X POST $API/refactor/submit.php -H "Content-Type: application/json" -H "X-CSRF-Token: $T" -d '{"slug":"god-function-checkout","score":90,"tests_passed":5,"tests_total":5}')
echo "$R" | grep -q "unlocks after 10"; ck "refactor API 403" $? "got $R"
CODE=$(curl -s -o /tmp/cf_gg.html -w "%{http_code}" $APP/labs.php); [ "$CODE" = "403" ]; ck "guest wall too" $? "got $CODE"
grep -q "Log in to start counting" /tmp/cf_gg.html; ck "guest CTA = login" $?
CODE=$(http "$(jar carol)" labs.php); [ "$CODE" = "200" ]; ck "carol (10/10): labs 200 (boundary)" $? "got $CODE"
CODE=$(http "$(jar carol)" refactor.php); [ "$CODE" = "200" ]; ck "carol: refactor 200" $? "got $CODE"

echo "############ B. PROBLEMS TREADMILL (alice)"
# idempotent: wipe alice's previous AI batches (submissions first — FK)
PHPSQL '$p->exec("DELETE FROM submissions WHERE user_id=1 AND problem_id IN (SELECT id FROM problems WHERE ai_user_id=1)");
        $p->exec("DELETE FROM problems WHERE ai_user_id=1");'
AI0=$(PHPSQL 'echo $p->query("SELECT COUNT(*) c FROM problems WHERE ai_user_id IS NOT NULL")->fetch()["c"];')
sweep_problems 1
curl -s -b "$(jar alice)" $APP/problems.php > /tmp/cf_p1.html
grep -q "You finished every problem!" /tmp/cf_p1.html; ck "batch-1 banner" $?
N=$(PHPSQL 'echo $p->query("SELECT COUNT(*) c FROM problems WHERE ai_user_id=1")->fetch()["c"];')
[ "$N" = "10" ]; ck "10 AI rows inserted" $? "got $N (pre-existing: $AI0)"
CNT=$(grep -o 'aip1b1-[0-9]*-[a-z-]*' /tmp/cf_p1.html | sort -u | wc -l)
[ "$CNT" = "10" ]; ck "10 AI cards linked" $? "got $CNT"
curl -s -b "$(jar alice)" $APP/problems.php > /tmp/cf_p1b.html
grep -q "You finished every problem!" /tmp/cf_p1b.html && bad "banner is one-shot" || ok "banner is one-shot"
sweep_ai_problems 1
curl -s -b "$(jar alice)" $APP/problems.php > /tmp/cf_p2.html
N=$(PHPSQL 'echo $p->query("SELECT COUNT(*) c FROM problems WHERE ai_user_id=1")->fetch()["c"];')
[ "$N" = "20" ]; ck "batch 2 spawned (20 rows)" $? "got $N"
grep -q "set 1 · 10/10 🏆" /tmp/cf_p2.html; ck "set chips: 1 done, 2 fresh" $?
AISLUG=$(PHPSQL '$r=$p->query("SELECT slug FROM problems WHERE ai_user_id=1 LIMIT 1")->fetch();echo $r["slug"];')
AIID=$(PHPSQL '$r=$p->query("SELECT id FROM problems WHERE ai_user_id=1 LIMIT 1")->fetch();echo $r["id"];')
CODE=$(http "$(jar bob)" "problem.php?slug=$AISLUG"); [ "$CODE" = "404" ]; ck "bob 404s alice's AI problem" $? "got $CODE"
CODE=$(http "$(jar alice)" "problem.php?slug=$AISLUG" /tmp/cf_pp.html); [ "$CODE" = "200" ]; ck "alice 200s own AI problem" $? "got $CODE"
TC=$(csrf_of carol)
R=$(curl -s -b "$(jar carol)" -X POST $API/submissions.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TC" -d "{\"problem_id\":$AIID,\"code\":\"x\",\"passed\":1,\"total\":1}")
echo "$R" | grep -q "not found"; ck "carol cannot bank alice's AI problem" $? "got $R"

echo "############ C. LABS TREADMILL (alice)"
PHPSQL '$p->exec("DELETE FROM lab_progress WHERE user_id=1");'
sweep_labs 1
curl -s -b "$(jar alice)" $APP/labs.php > /tmp/cf_l1.html
grep -q "You cleared every lab!" /tmp/cf_l1.html; ck "labs set-1 banner" $?
CNT=$(grep -o 'ail1b1-[0-9]*-[a-z-]*' /tmp/cf_l1.html | sort -u | wc -l)
[ "$CNT" = "10" ]; ck "10 AI lab cards" $? "got $CNT"
AILAB=$(grep -o 'ail1b1-[0-9]*-[a-z-]*' /tmp/cf_l1.html | sort -u | head -1)
CODE=$(http "$(jar alice)" "lab.php?slug=$AILAB" /tmp/cf_lp.html); [ "$CODE" = "200" ]; ck "alice opens AI lab" $? "got $CODE"
grep -q "generated by the Codeface AI for you" /tmp/cf_lp.html; ck "ai-hero on lab page" $?
CODE=$(http "$(jar carol)" "lab.php?slug=$AILAB"); [ "$CODE" = "404" ]; ck "carol (unlocked) 404s alice AI lab" $? "got $CODE"
TA=$(csrf_of alice)
R=$(curl -s -b "$(jar alice)" -X POST $API/labs/complete.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TA" -d "{\"slug\":\"$AILAB\"}")
echo "$R" | grep -q '"ok":true'; ck "API completes AI lab" $? "got $R"
R=$(curl -s -b "$(jar alice)" -X POST $API/labs/complete.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TA" -d '{"slug":"ail1b3-1-legacy-pricing"}')
echo "$R" | grep -q "not found"; ck "locked batch-3 lab 404" $? "got $R"
sweep_ai_labs 1 1
curl -s -b "$(jar alice)" $APP/labs.php > /tmp/cf_l2.html
CNT=$(grep -o 'ail1b2-[0-9]*-[a-z-]*' /tmp/cf_l2.html | sort -u | wc -l)
[ "$CNT" = "10" ]; ck "set 2 spawned" $? "got $CNT"

echo "############ D. REFACTOR TREADMILL (alice)"
PHPSQL '$p->exec("DELETE FROM refactor_runs WHERE user_id=1");'
sweep_refactors 1
curl -s -b "$(jar alice)" $APP/refactor.php > /tmp/cf_r1.html
grep -q "You refactored the whole Gym!" /tmp/cf_r1.html; ck "refactor set-1 banner" $?
CNT=$(grep -o 'air1b1-[0-9]*-[a-z-]*' /tmp/cf_r1.html | sort -u | wc -l)
[ "$CNT" = "10" ]; ck "10 AI repo cards" $? "got $CNT"
AIRF=$(grep -o 'air1b1-[0-9]*-[a-z-]*' /tmp/cf_r1.html | sort -u | head -1)
CODE=$(http "$(jar alice)" "refactor-challenge.php?slug=$AIRF" /tmp/cf_rp.html); [ "$CODE" = "200" ]; ck "alice opens AI repo" $? "got $CODE"
grep -q '"fix"' /tmp/cf_rp.html && bad "fix never sent client-side" || ok "fix never sent client-side"
CODE=$(http "$(jar carol)" "refactor-challenge.php?slug=$AIRF"); [ "$CODE" = "404" ]; ck "carol (unlocked) 404s alice AI repo" $? "got $CODE"
NCHECKS=$(PHPSQL 'require "/home/user/codeface/backend/lib/emitters.php";require "/home/user/codeface/backend/lib/db.php";require "/home/user/codeface/backend/lib/helpers.php";require "/home/user/codeface/backend/lib/aibank.php";echo count(cf_ai_refactor(1, "'"$AIRF"'")["checks"]);')
R=$(curl -s -b "$(jar alice)" -X POST $API/refactor/submit.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TA" -d "{\"slug\":\"$AIRF\",\"score\":97,\"tests_passed\":$NCHECKS,\"tests_total\":$NCHECKS,\"metrics\":{}}")
echo "$R" | grep -q '"ok":true'; ck "API accepts valid AI submit" $? "got $R"
R=$(curl -s -b "$(jar alice)" -X POST $API/refactor/submit.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TA" -d "{\"slug\":\"$AIRF\",\"score\":97,\"tests_passed\":3,\"tests_total\":3}")
echo "$R" | grep -q "does not match"; ck "tampered tests_total rejected" $? "got $R"
sweep_ai_refactors 1 1
curl -s -b "$(jar alice)" $APP/refactor.php > /tmp/cf_r2.html
CNT=$(grep -o 'air1b2-[0-9]*-[a-z-]*' /tmp/cf_r2.html | sort -u | wc -l)
[ "$CNT" = "10" ]; ck "set 2 spawned" $? "got $CNT"

echo "############ E. PUBLIC GUESTS unaffected"
CODE=$(curl -s -o /tmp/cf_gp.html -w "%{http_code}" $APP/problems.php)
[ "$CODE" = "200" ]; ck "guest problems.php 200" $? "got $CODE"
grep -q "(526)" /tmp/cf_gp.html; ck "guest All chip = 526" $?
grep -q "AI Problem-Setter" /tmp/cf_gp.html && bad "guest sees AI panel" || ok "guest sees no AI panel"


echo "############ F. PROFILE (avatar edit, name edit, journey statuses)"
curl -s -b "$(jar dev_mike)" $APP/profile.php > /tmp/cf_pr_m.html
grep -q 'for="avatarPick"' /tmp/cf_pr_m.html; ck "own profile: avatar ✏️ badge" $?
grep -q 'id="btnEditName"' /tmp/cf_pr_m.html; ck "own profile: ✏️ edit name" $?
grep -q "📍 Your journey" /tmp/cf_pr_m.html; ck "journey card (own)" $?
ST=$(grep -o 'st-chip st-locked">🔒 locked' /tmp/cf_pr_m.html | wc -l)
[ "$ST" = "2" ]; ck "mike: labs+refactor 🔒 locked chips" $? "got $ST"
grep -q '>2/16<' /tmp/cf_pr_m.html; ck "mike: htmlcss 2/16 ongoing in Learn list" $?
grep -q 'st-chip st-todo">not started' /tmp/cf_pr_m.html; ck "not-started language rows" $?
curl -s -b "$(jar carol)" $APP/profile.php > /tmp/cf_pr_c.html
grep -q '>16/16<' /tmp/cf_pr_c.html && grep -q 'st-chip st-done">complete ✓' /tmp/cf_pr_c.html; ck "carol: JS track complete ✓" $?
! grep -q 'st-chip st-locked' /tmp/cf_pr_c.html; ck "carol: gates open, no lock chips" $?
curl -s -b "$(jar bob)" "$APP/profile.php?u=alice" > /tmp/cf_pr_pub.html
grep -q 'for="avatarPick"' /tmp/cf_pr_pub.html && bad "bob sees no edit badge on alice's profile" || ok "bob view = read-only journey"
grep -q '>6/16<' /tmp/cf_pr_pub.html; ck "alice JS 6/16 visible publicly" $?

python3 -c "import base64;open('/tmp/cf_px.png','wb').write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))"
TM=$(csrf_of dev_mike)
CODE=$(curl -s -b "$(jar dev_mike)" -o /dev/null -w "%{http_code}" -X POST $API/profile/avatar.php -F "csrf=$TM" -F "avatar=@/tmp/cf_px.png;type=image/png")
[ "$CODE" = "302" ]; ck "avatar upload → 302" $? "got $CODE"
AV=$(PHPSQL '$r=$p->query("SELECT avatar FROM users WHERE id=4")->fetch();echo $r["avatar"] ?? "";')
[ "$AV" = "u4.png" ]; ck "users.avatar = u4.png" $? "got $AV"
CODE=$(curl -s -o /dev/null -w "%{http_code}" "$APP/avatar.php?id=4")
[ "$CODE" = "200" ]; ck "avatar.php streams the photo" $? "got $CODE"
CODE=$(curl -s -o /dev/null -w "%{http_code}" "$APP/avatar.php?id=99")
[ "$CODE" = "404" ]; ck "no-avatar user → 404" $? "got $CODE"
echo "not an image" > /tmp/cf_fake.png
curl -s -b "$(jar dev_mike)" -X POST $API/profile/avatar.php -F "csrf=$TM" -F "avatar=@/tmp/cf_fake.png;type=image/png" -o /dev/null
AV=$(PHPSQL '$r=$p->query("SELECT avatar FROM users WHERE id=4")->fetch();echo $r["avatar"] ?? "";')
[ "$AV" = "u4.png" ]; ck "fake image rejected, photo kept" $? "got $AV"
CODE=$(curl -s -b "$(jar dev_mike)" -o /dev/null -w "%{http_code}" -X POST $API/profile/update.php --data-urlencode "csrf=$TM" --data-urlencode "display_name=Mike T." --data-urlencode "bio=Learning JS one bug at a time")
[ "$CODE" = "302" ]; ck "name/bio update → 302" $? "got $CODE"
curl -s -b "$(jar dev_mike)" $APP/profile.php > /tmp/cf_pr_m2.html
grep -q "<h1>Mike T." /tmp/cf_pr_m2.html; ck "new display name rendered" $?
grep -q "Learning JS one bug at a time" /tmp/cf_pr_m2.html; ck "new bio rendered" $?
CODE=$(curl -s -b "$(jar dev_mike)" -o /dev/null -w "%{http_code}" -X POST $API/profile/update.php --data-urlencode "display_name=Nope")
[ "$CODE" = "403" ]; ck "no-CSRF update → 403" $? "got $CODE"
PHPSQL '$p->exec("UPDATE users SET display_name='"'"'Mike T.'"'"', bio='"'"'Career switcher. JavaScript first, fear later.'"'"', avatar=NULL WHERE id=4");'
rm -f /home/user/codeface/database/data/avatars/u4.png 2>/dev/null || true

echo "############ G. LIVE ROOMS (lobby, create, pads, collab sync, chat, SSE, matchmaking)"
R=$(curl -s -b "$(jar alice)" -X POST $API/rooms/create.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TA2" -d '{"name":"Itest Room","language":"javascript","problem_id":1}')
echo "$R" | grep -q '"ok":true'; ck "create room → ok" $? "got $R"
RCODE=$(echo "$R" | grep -o '"code":"[A-Z0-9]*"' | cut -d'"' -f4)
[ -n "$RCODE" ]; ck "6-char room code issued" $? "got $RCODE"
CODE=$(http "$(jar alice)" "room.php?code=$RCODE"); [ "$CODE" = "200" ]; ck "owner opens room page" $? "got $CODE"
CODE=$(http "$(jar bob)" "room.php?code=$RCODE"); [ "$CODE" = "200" ]; ck "bob opens room page" $? "got $CODE"
R=$(curl -s -b "$(jar alice)" -X POST $API/rooms/push.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TA2" -d "{\"code\":\"$RCODE\",\"language\":\"javascript\",\"base_version\":0,\"content\":\"// itest hello\"}")
echo "$R" | grep -q '"version":1'; ck "pad push → v1" $? "got $R"
curl -s -b "$(jar bob)" "$API/rooms/state.php?code=$RCODE" > /tmp/cf_state.json
grep -q "itest hello" /tmp/cf_state.json; ck "bob's state shows alice's edit (sync)" $?
CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$(jar bob)" -X POST $API/rooms/push.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TB2" -d "{\"code\":\"$RCODE\",\"language\":\"javascript\",\"base_version\":0,\"content\":\"// stale\"}")
[ "$CODE" = "409" ]; ck "stale base_version → 409 conflict" $? "got $CODE"
R=$(curl -s -b "$(jar bob)" -X POST $API/rooms/chat.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TB2" -d "{\"code\":\"$RCODE\",\"body\":\"itest-chat\"}")
echo "$R" | grep -q "itest-chat"; ck "chat post ok" $? "got $R"
curl -s -b "$(jar alice)" "$API/rooms/state.php?code=$RCODE" | grep -q "itest-chat"; ck "chat visible in alice's state" $?
timeout 4 curl -sN -b "$(jar alice)" "$API/rooms/stream.php?code=$RCODE" > /tmp/cf_sse.txt 2>/dev/null || true
grep -q "event: snapshot" /tmp/cf_sse.txt; ck "SSE stream sends snapshot" $?
grep -q '"members"' /tmp/cf_sse.txt; ck "SSE snapshot carries presence" $?
CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$(jar alice)" "$API/rooms/state.php?code=ZZZZZZ"); [ "$CODE" = "404" ]; ck "bogus room code → 404" $? "got $CODE"
HTTP_GUEST=$(curl -s -o /dev/null -w "%{http_code}" "$API/rooms/state.php?code=$RCODE"); [ "$HTTP_GUEST" = "401" ] || [ "$HTTP_GUEST" = "403" ]; ck "guest blocked from room API (401/403)" $? "got $HTTP_GUEST"
R=$(curl -s -b "$(jar alice)" -X POST $API/rooms/heartbeat.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TA2" -d "{\"code\":\"$RCODE\"}")
echo "$R" | grep -q '"ok":true'; ck "heartbeat ok" $? "got $R"
R=$(curl -s -b "$(jar carol)" -X POST $API/matchmaking/join.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TC2" -d '{"language":"javascript","difficulty":"medium"}')
echo "$R" | grep -q '"ok":true'; ck "matchmaking join" $? "got $R"
R=$(curl -s -b "$(jar carol)" -X POST $API/matchmaking/cancel.php -H "Content-Type: application/json" -H "X-CSRF-Token: $TC2" -d '{}')
echo "$R" | grep -q '"ok":true'; ck "matchmaking cancel" $? "got $R"
PHPSQL '$p->exec("DELETE FROM room_members WHERE room_id IN (SELECT id FROM rooms WHERE name=\"Itest Room\")");$p->exec("DELETE FROM room_pads WHERE room_id IN (SELECT id FROM rooms WHERE name=\"Itest Room\")");$p->exec("DELETE FROM rooms WHERE name=\"Itest Room\"");' 2>/dev/null || true

echo "############ H. FORGOT-PASSWORD EMAIL OTP (real SMTP path, captured locally)"
if [ -z "${ITEST_SMTPBOX:-}" ]; then
  echo "  ⤷ skipped: start \`node tools/fake-smtp.js /tmp/smtpbox.log 2525\`, boot the app with CODEFACE_SMTP_* env, re-run with ITEST_SMTPBOX=/tmp/smtpbox.log"
else
  JG=/tmp/cf_jar_guest.txt; rm -f "$JG"; touch "$ITEST_SMTPBOX"
  # idempotent: wipe leftovers from any previous run before registering
  PHPSQL '$p->exec("DELETE FROM password_resets WHERE user_id IN (SELECT id FROM users WHERE username=\"otp_user\")");$p->exec("DELETE FROM users WHERE username=\"otp_user\"");' 
  GHDR=-H; TG() { curl -s -c "$JG" -b "$JG" "$APP/$1" | grep -o '<meta name="csrf" content="[^"]*"' | head -1 | sed 's/.*content="//;s/"//'; }
  OTPMAIL="otp.user@gmail.com"

  CODE=$(curl -s -o /tmp/cf_h.html -w "%{http_code}" -c "$JG" "$APP/forgot.php"); [ "$CODE" = "200" ]; ck "forgot.php renders" $? "got $CODE"
  grep -q "Forgot password" /tmp/cf_h.html; ck "forgot page content" $?

  RG=/tmp/cf_jar_reg.txt; rm -f "$RG"
  CODE=$(register_verified "$RG" otp_user "$OTPMAIL" password123)
  [ "$CODE" = "302" ]; ck "register otp_user (email-verified signup)" $? "got $CODE"

  N0=$(grep -c "^==== " "$ITEST_SMTPBOX" 2>/dev/null)
  C=$(TG forgot.php)
  curl -s -b "$JG" -X POST $APP/forgot.php --data-urlencode "csrf=$C" --data-urlencode "email=nobody@gmail.com" > /tmp/cf_h1.html
  grep -q "Check your inbox" /tmp/cf_h1.html; ck "unknown email → same neutral page (no enumeration)" $?
  N1=$(grep -c "^==== " "$ITEST_SMTPBOX"); [ "$N1" = "$N0" ]; ck "no mail goes out for unknown email" $? "box $N0→$N1"

  C=$(TG forgot.php)
  curl -s -b "$JG" -X POST $APP/forgot.php --data-urlencode "csrf=$C" --data-urlencode "email=$OTPMAIL" > /tmp/cf_h2.html
  grep -q "Check your inbox" /tmp/cf_h2.html; ck "known email → neutral page" $?
  N2=$(grep -c "^==== " "$ITEST_SMTPBOX"); [ "$N2" = "$((N0+1))" ]; ck "one mail left the server" $? "box $N0→$N2"
  awk "/^==== /{b=\"\"} {b=b \$0 ORS} END{printf \"%s\", b}" "$ITEST_SMTPBOX" > /tmp/cf_lastmail.txt
  grep -q "To: <$OTPMAIL>" /tmp/cf_lastmail.txt; ck "mail addressed to the account's Gmail" $?
  OTP=$(grep -oE '[0-9]{6}' /tmp/cf_lastmail.txt | head -1)
  [ -n "$OTP" ]; ck "6-digit OTP inside the email body" $? "got '$OTP'"
  V=$(OTPVAL="$OTP" PHPSQL '$r=$p->query("SELECT pr.otp_hash FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE u.email=\"otp.user@gmail.com\" ORDER BY pr.id DESC LIMIT 1")->fetch();echo password_verify(getenv("OTPVAL"), $r["otp_hash"]) ? "1" : "0";')
  [ "$V" = "1" ]; ck "DB stores only the HASH of the OTP" $? "verify=$V"

  # resend within 60s is throttled
  C=$(TG forgot.php); curl -s -b "$JG" -X POST $APP/forgot.php --data-urlencode "csrf=$C" --data-urlencode "email=$OTPMAIL" > /dev/null
  N3=$(grep -c "^==== " "$ITEST_SMTPBOX"); [ "$N3" = "$N2" ]; ck "resend <60s throttled (no second mail)" $? "box $N2→$N3"

  C=$(TG reset.php)
  curl -s -b "$JG" -X POST $APP/reset.php --data-urlencode "csrf=$C" --data-urlencode "email=$OTPMAIL" --data-urlencode "otp=000000" --data-urlencode "password=newpassword9" --data-urlencode "confirm=newpassword9" > /tmp/cf_h3.html
  grep -q "not valid or has expired" /tmp/cf_h3.html; ck "wrong code rejected" $?
  ATT=$(PHPSQL '$r=$p->query("SELECT attempts FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE u.email=\"otp.user@gmail.com\" ORDER BY pr.id DESC LIMIT 1")->fetch();echo $r["attempts"];')
  [ "$ATT" = "1" ]; ck "attempts counter = 1 (brute-force guard)" $? "got $ATT"

  C=$(TG reset.php)
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$JG" -X POST $APP/reset.php --data-urlencode "csrf=$C" --data-urlencode "email=$OTPMAIL" --data-urlencode "otp=$OTP" --data-urlencode "password=newpassword9" --data-urlencode "confirm=newpassword9")
  [ "$CODE" = "302" ]; ck "correct code + new password → 302 to login" $? "got $CODE"
  curl -s -b "$JG" "$APP/login.php" | grep -q "Password updated"; ck "login page shows success flash" $?
  U=$(PHPSQL '$r=$p->query("SELECT used_at FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE u.email=\"otp.user@gmail.com\" ORDER BY pr.id DESC LIMIT 1")->fetch();echo $r["used_at"] ? "1" : "0";')
  [ "$U" = "1" ]; ck "code marked used (one-time only)" $?

  LJ=/tmp/cf_jar_otp_login.txt; rm -f "$LJ"
  C=$(curl -s -c "$LJ" $APP/login.php | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$LJ" -X POST $APP/login.php --data-urlencode "csrf=$C" --data-urlencode "identity=otp_user" --data-urlencode "password=password123" > /tmp/cf_h4.html
  grep -q "No account matches" /tmp/cf_h4.html; ck "OLD password no longer works" $?
  C=$(curl -s -c "$LJ" $APP/login.php | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -b "$LJ" -c "$LJ" -X POST $APP/login.php --data-urlencode "csrf=$C" --data-urlencode "identity=otp_user" --data-urlencode "password=newpassword9")
  [ "$CODE" = "302" ]; ck "NEW password logs in" $? "got $CODE"

  C=$(TG reset.php)
  curl -s -b "$JG" -X POST $APP/reset.php --data-urlencode "csrf=$C" --data-urlencode "email=$OTPMAIL" --data-urlencode "otp=$OTP" --data-urlencode "password=anotherpass9" --data-urlencode "confirm=anotherpass9" > /tmp/cf_h5.html
  grep -q "not valid or has expired" /tmp/cf_h5.html; ck "used code cannot be replayed" $?

  PHPSQL '$r=$p->query("SELECT id FROM users WHERE email=\"otp.user@gmail.com\"")->fetch();$uid=$r["id"];
    $st=$p->prepare("INSERT INTO password_resets (user_id, otp_hash, expires_at, created_at) VALUES (?,?,?,CURRENT_TIMESTAMP)");
    $st->execute([$uid, password_hash("424242", PASSWORD_DEFAULT), "2000-01-01 00:00:00"]);'
  C=$(TG reset.php)
  curl -s -b "$JG" -X POST $APP/reset.php --data-urlencode "csrf=$C" --data-urlencode "email=$OTPMAIL" --data-urlencode "otp=424242" --data-urlencode "password=whatever99" --data-urlencode "confirm=whatever99" > /tmp/cf_h6.html
  grep -q "not valid or has expired" /tmp/cf_h6.html; ck "expired code rejected" $?

  PHPSQL '$p->exec("DELETE FROM password_resets WHERE user_id IN (SELECT id FROM users WHERE username=\"otp_user\")");$p->exec("DELETE FROM users WHERE username=\"otp_user\"");'
fi
echo "############ I. EMAIL EXISTENCE GUARD (format + MX records + typo suggestions)"
JI=/tmp/cf_jar_i.txt; rm -f "$JI"
R=$(curl -s -c "$JI" -b "$JI" -X POST $API/auth/check-email.php --data-urlencode "email=notanemail")
echo "$R" | grep -q '"format":false'; ck "API: garbage format flagged" $? "got $R"
R=$(curl -s -c "$JI" -b "$JI" -X POST $API/auth/check-email.php --data-urlencode "email=friend@gmial.com")
echo "$R" | grep -q '"suggestion":"gmail.com"'; ck "API: gmial.com → suggests gmail.com" $? "got $R"
echo "$R" | grep -q '"mx":false'; ck "API: gmial.com → no mail server" $?
R=$(curl -s -c "$JI" -b "$JI" -X POST $API/auth/check-email.php --data-urlencode "email=someone@gmail.com")
echo "$R" | grep -q '"mx":true'; ck "API: gmail.com → MX ok" $? "got $R"
R=$(curl -s -c "$JI" -b "$JI" -X POST $API/auth/check-email.php --data-urlencode "email=x@nope-xqwc-39817.com")
echo "$R" | grep -q '"mx":false'; ck "API: invented domain → no mail server" $? "got $R"

jcsrf() { curl -s -c "$JI" -b "$JI" "$APP/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
C=$(jcsrf register.php)
curl -s -b "$JI" -X POST $APP/register.php --data-urlencode "csrf=$C" --data-urlencode "username=mx_bad1" --data-urlencode "email=mx_bad@gmial.com" --data-urlencode "password=password123" --data-urlencode "confirm=password123" > /tmp/cf_i1.html
grep -q "Did you mean gmail.com" /tmp/cf_i1.html; ck "register @gmial.com → red “did you mean gmail.com?”" $?
grep -q 'alert alert-error' /tmp/cf_i1.html; ck "register @gmial.com → account NOT created" $?
C=$(jcsrf register.php)
curl -s -b "$JI" -X POST $APP/register.php --data-urlencode "csrf=$C" --data-urlencode "username=mx_bad2" --data-urlencode "email=x@nope-xqwc-39817.com" --data-urlencode "password=password123" --data-urlencode "confirm=password123" > /tmp/cf_i2.html
grep -q "has no mail server" /tmp/cf_i2.html; ck "register invented domain → red rejection" $?
JIX=/tmp/cf_jar_ix.txt; rm -f "$JIX"   # fresh anonymous jar (JI may be logged in by now)
curl -s -c "$JIX" -b "$JIX" $APP/login.php | grep -q "data-emailcheck"; ck "login form wired for live check" $?
curl -s -c "$JIX" -b "$JIX" $APP/register.php | grep -q "data-emailcheck"; ck "register form wired for live check" $?
CODE=$(register_verified "$JI" mx_user "mx.user@gmail.com" password123)
[ "$CODE" = "302" ]; ck "real gmail.com address registers fine" $? "got $CODE"
PHPSQL '$p->exec("DELETE FROM users WHERE username IN (\"mx_user\",\"mx_bad1\",\"mx_bad2\")");'
echo "############ J. SIGNUP EMAIL VERIFICATION GATE (mailbox must really exist)"
if [ -z "${ITEST_SMTPBOX:-}" ]; then
  echo "  ⤷ skipped (like section H: needs fake-smtp + SMTP env)"
else
  JV=/tmp/cf_jar_v.txt; rm -f "$JV"
  PHPSQL '$p->exec("DELETE FROM users WHERE username=\"vest_user\"");'
  C=$(curl -s -c "$JV" $APP/register.php | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  NB=$(grep -c "^==== " "$ITEST_SMTPBOX")
  CODE=$(curl -s -o /tmp/cf_j1.html -w "%{http_code}" -c "$JV" -b "$JV" -X POST $APP/register.php \
    --data-urlencode "csrf=$C" --data-urlencode "username=vest_user" --data-urlencode "email=vest.user@gmail.com" \
    --data-urlencode "password=password123" --data-urlencode "confirm=password123")
  [ "$CODE" = "200" ]; ck "step 1 parks the signup (no instant account)" $? "got $CODE"
  grep -q "Verify your email" /tmp/cf_j1.html; ck "step 2 asks for the emailed code" $?
  D=$(PHPSQL 'echo $p->query("SELECT COUNT(*) c FROM users WHERE username=\"vest_user\"")->fetch()["c"];')
  [ "$D" = "0" ]; ck "nothing in DB until the code comes back" $? "got $D"
  NB2=$(grep -c "^==== " "$ITEST_SMTPBOX"); [ "$NB2" = "$((NB+1))" ]; ck "verification mail left the server" $? "box $NB→$NB2"
  awk '/^==== /{b=""} {b=b $0 ORS} END{printf "%s", b}' "$ITEST_SMTPBOX" > /tmp/cf_jm.txt
  grep -q "To: <vest.user@gmail.com>" /tmp/cf_jm.txt; ck "mail addressed to the signup gmail" $?
  OTP2=$(grep -oE '[0-9]{6}' /tmp/cf_jm.txt | tail -1)
  WRONG=000000; [ "$OTP2" = "000000" ] && WRONG=111111
  curl -s -c "$JV" -b "$JV" -X POST $APP/register.php --data-urlencode "csrf=$C" --data-urlencode "action=verify" --data-urlencode "otp=$WRONG" > /tmp/cf_j2.html
  grep -q "match the email we sent" /tmp/cf_j2.html; ck "wrong code → red error" $?
  D=$(PHPSQL 'echo $p->query("SELECT COUNT(*) c FROM users WHERE username=\"vest_user\"")->fetch()["c"];')
  [ "$D" = "0" ]; ck "wrong code creates nothing" $?
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$JV" -b "$JV" -X POST $APP/register.php --data-urlencode "csrf=$C" --data-urlencode "action=verify" --data-urlencode "otp=$OTP2")
  [ "$CODE" = "302" ]; ck "correct code creates the account (302)" $? "got $CODE"
  H1=$(PHPSQL '$r=$p->query("SELECT password_hash h FROM users WHERE username=\"vest_user\"")->fetch();echo $r["h"] ?? "";')
  curl -s -o /dev/null -b "$JV" -X POST $APP/register.php --data-urlencode "csrf=$C" --data-urlencode "action=verify" --data-urlencode "otp=$OTP2"
  H2=$(PHPSQL '$r=$p->query("SELECT password_hash h FROM users WHERE username=\"vest_user\"")->fetch();echo $r["h"] ?? "";')
  [ -n "$H1" ] && [ "$H1" = "$H2" ]; ck "consumed code is single-use (no-op replay)" $?
  PHPSQL '$p->exec("DELETE FROM users WHERE username=\"vest_user\"");'
fi
echo; echo "════════ INTEGRATION SUMMARY: pass=$PASS fail=$FAIL ════════"
[ "$FAIL" = "0" ]
