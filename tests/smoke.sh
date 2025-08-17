#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP:-php}"

# ---- pick a free port
PORT=""
for p in 8030 8031 8032 8033 8034 8035 8036 8037 8038 8039; do
  if ! lsof -iTCP:$p -sTCP:LISTEN >/dev/null 2>&1; then PORT="$p"; break; fi
done
[ -n "$PORT" ] || { echo "No free port found"; exit 1; }

# ---- start server
LOG="/tmp/fo_server_${PORT}.log"
APP_ENV=${APP_ENV:-test} $PHP -S 127.0.0.1:$PORT -t "$ROOT/public" >"$LOG" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID >/dev/null 2>&1 || true' EXIT

# ---- wait for server
for i in {1..50}; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/availability_manager.php" || true)
  [ "$code" = "200" ] && break
  sleep 0.1
done
[ "$code" = "200" ] || { echo "Server not responding. Log:"; tail -n +200 "$LOG"; exit 1; }

# ---- get CSRF (manager, fallback to simple form)
COOK="/tmp/fo_cookies_${PORT}.txt"
HTML=$(curl -s -c "$COOK" "http://127.0.0.1:$PORT/availability_manager.php")
TOKEN=$(printf '%s' "$HTML" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)
if [ -z "${TOKEN:-}" ]; then
  HTML=$(curl -s -c "$COOK" "http://127.0.0.1:$PORT/availability_form.php")
  TOKEN=$(printf '%s' "$HTML" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)
fi
[ -n "$TOKEN" ] || { echo "Could not extract CSRF token"; exit 1; }

# ---- fetch an active employee id straight from DB
EMP_ID=$($PHP -r "
require '$ROOT/config/database.php';
\$pdo = getPDO();
\$stmt = \$pdo->query(\"SELECT e.id
                        FROM employees e
                        JOIN people p ON p.id=e.person_id
                        WHERE e.is_active=1
                        ORDER BY e.id ASC
                        LIMIT 1\");
echo (\$stmt? (int)\$stmt->fetchColumn() : 0);
" || true)

# ---- auto-seed via app if none found
if [ -z "${EMP_ID:-}" ] || [ "$EMP_ID" = "0" ]; then
  echo 'No active employee found; creating one for smoke test...'
  EHTML=$(curl -s -b "$COOK" -c "$COOK" "http://127.0.0.1:$PORT/employee_form.php")
  ETOKEN=$(printf '%s' "$EHTML" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)
  [ -n "$ETOKEN" ] || { echo "Could not get token for employee save"; exit 1; }

  ESAVE=$(curl -s -b "$COOK" -X POST "http://127.0.0.1:$PORT/employee_save.php" \
    -H "Accept: application/json" \
    --data-urlencode "csrf_token=$ETOKEN" \
    --data-urlencode "first_name=Test" \
    --data-urlencode "last_name=Employee" \
    --data-urlencode "email=test+smoke@local.test" \
    --data-urlencode "phone=" \
    --data-urlencode "is_active=1")
  echo "EMPLOYEE_SAVE: $ESAVE"
  echo "$ESAVE" | grep -q '"ok":true' || { echo "Employee save failed"; exit 1; }
  EMP_ID=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo isset($d["id"])?(int)$d["id"]:0;' "$ESAVE")
  if [ -z "$EMP_ID" ] || [ "$EMP_ID" = "0" ]; then
    EMP_ID=$($PHP -r "
      require '$ROOT/config/database.php';
      \$pdo=getPDO();
      echo (int)\$pdo->query(\"SELECT id FROM employees ORDER BY id DESC LIMIT 1\")->fetchColumn();
    ")
  fi
fi
[ -n "${EMP_ID:-}" ] || { echo "Could not determine employee id"; exit 1; }

# ---- helper: find a free 60-min slot (Mon..Sun, 06:00..21:00, step 30m)
find_slot() {
  local emp="$1"
  $PHP -r '
    $emp=(int)$argv[1];
    require "'"$ROOT"'/config/database.php";
    $pdo=getPDO();
    $days=["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
    foreach($days as $d){
      $st=$pdo->prepare("SELECT TIME_TO_SEC(start_time) s, TIME_TO_SEC(end_time) e
                         FROM employee_availability
                         WHERE employee_id=? AND day_of_week=?
                         ORDER BY start_time");
      $st->execute([$emp,$d]);
      $busy=$st->fetchAll(PDO::FETCH_ASSOC);
      // scan 06:00 (21600) to 21:00 (75600) in 30m steps (1800s)
      for($t=21600; $t<=75600-3600; $t+=1800){
        $s=$t; $e=$t+3600; $ok=true;
        foreach($busy as $b){
          $bs=(int)$b["s"]; $be=(int)$b["e"];
          if($s < $be && $bs < $e){ $ok=false; break; }
        }
        if($ok){
          printf("{\"day\":\"%s\",\"start\":\"%02d:%02d\",\"end\":\"%02d:%02d\"}\n",
                 $d, intdiv($s,3600), intdiv($s%3600,60), intdiv($e,3600), intdiv($e%3600,60));
          exit(0);
        }
      }
    }
    fwrite(STDERR, "no_free_slot\n"); exit(2);
  ' "$emp"
}

SLOT_JSON=$(find_slot "$EMP_ID")
if [ -z "$SLOT_JSON" ]; then echo "Could not find free slot"; exit 1; fi
DAY=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["day"];' "$SLOT_JSON")
START=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["start"];' "$SLOT_JSON")
END=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["end"];' "$SLOT_JSON")

# ---- CREATE using the free slot
CREATE_JSON=$(curl -s -b "$COOK" -X POST "http://127.0.0.1:$PORT/availability_save.php" \
  -H "Accept: application/json" \
  --data-urlencode "csrf_token=$TOKEN" \
  --data-urlencode "employee_id=$EMP_ID" \
  --data-urlencode "day_of_week=$DAY" \
  --data-urlencode "start_time=$START" \
  --data-urlencode "end_time=$END")
echo "CREATE: $CREATE_JSON"
echo "$CREATE_JSON" | grep -q '"ok":true' || { echo "Create failed"; exit 1; }

# ---- resolve id robustly (JSON → id; fallback to DB)
NEW_ID=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo isset($d["id"])?(int)$d["id"]:0;' "$CREATE_JSON")
if [ -z "$NEW_ID" ] || [ "$NEW_ID" = "0" ]; then
  NEW_ID=$($PHP -r "
    require '$ROOT/config/database.php';
    \$pdo=getPDO();
    \$st=\$pdo->prepare('SELECT id FROM employee_availability WHERE employee_id=? AND day_of_week=? AND start_time=? AND end_time=? ORDER BY id DESC LIMIT 1');
    \$st->execute([$EMP_ID, '$DAY', '$START', '$END']);
    echo (int)\$st->fetchColumn();
  ")
fi
[ -n "$NEW_ID" ] && [ "$NEW_ID" != "0" ] || { echo "Could not resolve availability id"; exit 1; }

# ---- compute a DIFFERENT free slot for UPDATE (re-query after create)
USLOT_JSON=$(find_slot "$EMP_ID")
if [ -z "$USLOT_JSON" ]; then echo "Could not find alternate free slot"; exit 1; fi
UDAY=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["day"];' "$USLOT_JSON")
USTART=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["start"];' "$USLOT_JSON")
UEND=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["end"];' "$USLOT_JSON")

# ---- UPDATE (send both id and availability_id to satisfy either handler)
UPDATE_JSON=$(curl -s -b "$COOK" -X POST "http://127.0.0.1:$PORT/availability_update.php" \
  -H "Accept: application/json" \
  --data-urlencode "csrf_token=$TOKEN" \
  --data-urlencode "id=$NEW_ID" \
  --data-urlencode "availability_id=$NEW_ID" \
  --data-urlencode "employee_id=$EMP_ID" \
  --data-urlencode "day_of_week=$UDAY" \
  --data-urlencode "start_time=$USTART" \
  --data-urlencode "end_time=$UEND")
echo "UPDATE: $UPDATE_JSON"
echo "$UPDATE_JSON" | grep -q '"ok":true' || { echo "Update failed"; exit 1; }

# ---- DELETE
DELETE_JSON=$(curl -s -b "$COOK" -X POST "http://127.0.0.1:$PORT/availability_delete.php" \
  -H "Accept: application/json" \
  --data-urlencode "csrf_token=$TOKEN" \
  --data-urlencode "id=$NEW_ID")
echo "DELETE: $DELETE_JSON"
echo "$DELETE_JSON" | grep -q '"ok":true' || { echo "Delete failed"; exit 1; }

echo "✅ Smoke OK on port $PORT"
