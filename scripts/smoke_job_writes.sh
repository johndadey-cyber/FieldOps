#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${FIELDOPS_BASE_URL:-http://127.0.0.1:8010}"
COOKIES="$(mktemp -t fieldops_cookies.XXXXXX)"
CSRF_JSON="$(mktemp -t fieldops_csrf.XXXXXX)"
ADD_JSON="$(mktemp -t fieldops_add.XXXXXX)"
JOB_JSON="$(mktemp -t fieldops_job.XXXXXX)"
UPD_JSON="$(mktemp -t fieldops_upd.XXXXXX)"
DEL_JSON="$(mktemp -t fieldops_del.XXXXXX)"

php_json() { php -r "$1"; }  # run small PHP one‑liners for robust JSON parsing

cleanup() { rm -f "$COOKIES" "$CSRF_JSON" "$ADD_JSON" "$JOB_JSON" "$UPD_JSON" "$DEL_JSON"; }
trap cleanup EXIT

echo "▶ Smoke: using BASE_URL=$BASE_URL"

# 0) Start a built-in server if we detect nothing listening on :8010
if ! lsof -i :8010 >/dev/null 2>&1; then
  echo "Server not detected on :8010 — starting one now…"
  (php -S 127.0.0.1:8010 -t public >/dev/null 2>&1 & echo $! > /tmp/fieldops_server.pid)
  sleep 1
fi

# 1) Seed dispatcher role
echo "→ Setting dispatcher role"
curl -sS -c "$COOKIES" -b "$COOKIES" "$BASE_URL/test_auth.php?role=dispatcher" >/dev/null

# 2) CSRF
echo "→ Fetching CSRF token"
curl -sS -c "$COOKIES" -b "$COOKIES" "$BASE_URL/test_csrf.php" | tee "$CSRF_JSON" >/dev/null
TOKEN="$(php_json '$j=json_decode(file_get_contents("'"$CSRF_JSON"'"),true); echo $j["token"]??"";')"
echo "  CSRF: $TOKEN"
test -n "$TOKEN" || { echo "✖ No CSRF token"; exit 1; }

# 3) Create customer
EMAIL="smoke$(date +%s)@example.com"
PHONE="555-02$((RANDOM%90+10))0"
echo "→ Creating customer"
curl -sS -c "$COOKIES" -b "$COOKIES" -H "Accept: application/json" \
  --data-urlencode "csrf_token=$TOKEN" \
  --data-urlencode "first_name=Smoke" \
  --data-urlencode "last_name=Test" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "phone=$PHONE" \
  "$BASE_URL/add_customer.php?__return=json" | tee "$ADD_JSON" >/dev/null

ADD_OK="$(php_json '$j=json_decode(file_get_contents("'"$ADD_JSON"'"),true); echo (isset($j["ok"])&&$j["ok"])?1:0;')"
CID="$(php_json '$j=json_decode(file_get_contents("'"$ADD_JSON"'"),true); echo $j["id"]??"";')"
if [ "$ADD_OK" != "1" ] || [ -z "$CID" ]; then
  echo "✖ Customer not created"
  cat "$ADD_JSON"
  exit 1
fi
echo "  Created customer id: $CID"

# 4) Create job
echo "→ Creating job"
JOBDATE="$(php -r 'echo (new DateTimeImmutable("tomorrow"))->format("Y-m-d");')"
curl -sS -c "$COOKIES" -b "$COOKIES" -H "Accept: application/json" \
  -d "csrf_token=$TOKEN" \
  -d "customer_id=$CID" \
  -d "description=Smoke run job" \
  -d "scheduled_date=$JOBDATE" \
  -d "scheduled_time=10:00:00" \
  -d "duration_minutes=60" \
  -d "status=scheduled" \
  "$BASE_URL/job_save.php?__return=json" | tee "$JOB_JSON" >/dev/null

JOB_OK="$(php_json '$j=json_decode(file_get_contents("'"$JOB_JSON"'"),true); echo (isset($j["ok"])&&$j["ok"])?1:0;')"
JID="$(php_json '$j=json_decode(file_get_contents("'"$JOB_JSON"'"),true); echo $j["id"]??"";')"
if [ "$JOB_OK" != "1" ] || [ -z "$JID" ]; then
  echo "✖ Job not created"
  cat "$JOB_JSON"
  exit 1
fi
echo "  Created job id: $JID"

# 5) Update job (change description) — expect ok:true
echo "→ Updating job"
curl -sS -c "$COOKIES" -b "$COOKIES" -H "Accept: application/json" \
  -d "csrf_token=$TOKEN" \
  -d "job_id=$JID" \
  -d "customer_id=$CID" \
  -d "description=Smoke run job (updated)" \
  -d "scheduled_date=$JOBDATE" \
  -d "scheduled_time=10:00:00" \
  -d "duration_minutes=60" \
  -d "status=scheduled" \
  "$BASE_URL/job_save.php?__return=json" | tee "$UPD_JSON" >/dev/null

UPD_OK="$(php_json '$j=json_decode(file_get_contents("'"$UPD_JSON"'"),true); echo (isset($j["ok"])&&$j["ok"])?1:0;')"
if [ "$UPD_OK" != "1" ]; then
  echo "✖ Job not updated"
  cat "$UPD_JSON"
  exit 1
fi
echo "  Job updated"

# 6) Delete job — expect ok:true
echo "→ Deleting job"
curl -sS -c "$COOKIES" -b "$COOKIES" -H "Accept: application/json" \
  -d "csrf_token=$TOKEN" \
  -d "job_id=$JID" \
  "$BASE_URL/job_delete.php?__return=json" | tee "$DEL_JSON" >/dev/null

DEL_OK="$(php_json '$j=json_decode(file_get_contents("'"$DEL_JSON"'"),true); echo (isset($j["ok"])&&$j["ok"])?1:0;')"
if [ "$DEL_OK" != "1" ]; then
  echo "✖ Job not deleted"
  cat "$DEL_JSON"
  exit 1
fi
echo "✔ Smoke OK"
