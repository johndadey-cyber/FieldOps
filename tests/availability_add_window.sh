#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP:-php}"

PORT=""
for p in 8050 8051 8052 8053 8054 8055 8056 8057 8058 8059; do
  if ! lsof -iTCP:$p -sTCP:LISTEN >/dev/null 2>&1; then PORT="$p"; break; fi
done
[ -n "$PORT" ] || { echo "No free port found"; exit 1; }

LOG="/tmp/fo_server_${PORT}.log"
$PHP -S 127.0.0.1:$PORT -t "$ROOT/public" >"$LOG" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID >/dev/null 2>&1 || true' EXIT

for i in {1..50}; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/dev_login.php" || true)
  [ "$code" = "200" ] && break
  sleep 0.1
done
[ "$code" = "200" ] || { echo "Server not responding. Log:"; tail -n +200 "$LOG"; exit 1; }

COOK="/tmp/fo_cookies_${PORT}.txt"
LOGIN_JSON=$(curl -s -c "$COOK" "http://127.0.0.1:$PORT/dev_login.php?role=dispatcher")
TOKEN=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["token"] ?? "";' "$LOGIN_JSON")
[ -n "$TOKEN" ] || { echo "Could not get CSRF token"; exit 1; }

EMP_ID=$($PHP -r '
require $argv[1];
$pdo = getPDO();
echo (int)$pdo->query("SELECT e.id FROM employees e JOIN people p ON p.id=e.person_id WHERE e.is_active=1 ORDER BY e.id ASC LIMIT 1")->fetchColumn();
' "$ROOT/config/database.php") || { echo "Could not determine employee id"; exit 1; }
[ -n "$EMP_ID" ] && [ "$EMP_ID" != "0" ] || { echo "Could not determine employee id"; exit 1; }

find_slot() {
  local emp="$1"
  $PHP -r '
    $emp=(int)$argv[1];
    require $argv[2];
    $pdo=getPDO();
    $days=["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
    foreach($days as $d){
      $st=$pdo->prepare("SELECT TIME_TO_SEC(start_time) s, TIME_TO_SEC(end_time) e FROM employee_availability WHERE employee_id=? AND day_of_week=? ORDER BY start_time");
      $st->execute([$emp,$d]);
      $busy=$st->fetchAll(PDO::FETCH_ASSOC);
      for($t=21600;$t<=75600-1800;$t+=1800){
        $s=$t; $e=$t+1800;
        $ok=true;
        foreach($busy as $b){ $bs=(int)$b["s"]; $be=(int)$b["e"]; if($s < $be && $bs < $e){ $ok=false; break; } }
        if($ok){ printf("{\"day\":\"%s\",\"start\":\"%02d:%02d\",\"end\":\"%02d:%02d\"}\n", $d, intdiv($s,3600), intdiv($s%3600,60), intdiv($e,3600), intdiv($e%3600,60)); exit(0); }
      }
    }
    fwrite(STDERR,"no_free_slot\n"); exit(2);
  ' "$emp" "$ROOT/config/database.php"
}

SLOT_JSON=$(find_slot "$EMP_ID")
[ -n "$SLOT_JSON" ] || { echo "No free slot"; exit 1; }
DAY=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["day"];' "$SLOT_JSON")
START=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["start"];' "$SLOT_JSON")
END=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo $d["end"];' "$SLOT_JSON")

CREATE_JSON=$(curl -s -b "$COOK" -H "Content-Type: application/json" -d "{\"csrf_token\":\"$TOKEN\",\"employee_id\":$EMP_ID,\"day_of_week\":\"$DAY\",\"start_time\":\"$START\",\"end_time\":\"$END\"}" "http://127.0.0.1:$PORT/api/availability/create.php")
echo "CREATE: $CREATE_JSON"
echo "$CREATE_JSON" | grep -q '"ok":true' || { echo "Create failed"; exit 1; }
NEW_ID=$($PHP -r '$j=$argv[1]; $d=json_decode($j,true); echo (int)($d["id"]??0);' "$CREATE_JSON")
[ "$NEW_ID" != "0" ] || { echo "Could not resolve new id"; exit 1; }

WEEK_START=$($PHP -r 'echo (new DateTimeImmutable("monday this week"))->format("Y-m-d");')
INDEX_JSON=$(curl -s -b "$COOK" "http://127.0.0.1:$PORT/api/availability/index.php?employee_id=$EMP_ID&week_start=$WEEK_START")
echo "$INDEX_JSON" | grep -q '"ok":true' || { echo "Index call failed"; exit 1; }
FOUND=$($PHP -r '$j=$argv[1]; $id=$argv[2]; $d=json_decode($j,true); foreach($d["availability"]??[] as $a){ if((int)$a["id"]==$id){ echo 1; exit; } } echo 0;' "$INDEX_JSON" "$NEW_ID")
[ "$FOUND" = "1" ] || { echo "New window not found in index"; exit 1; }

DELETE_JSON=$(curl -s -b "$COOK" -X POST "http://127.0.0.1:$PORT/availability_delete.php" -H "Accept: application/json" --data-urlencode "csrf_token=$TOKEN" --data-urlencode "id=$NEW_ID")
echo "DELETE: $DELETE_JSON"
echo "$DELETE_JSON" | grep -q '"ok":true' || { echo "Delete failed"; exit 1; }

echo "✅ availability_add_window passed on port $PORT"
