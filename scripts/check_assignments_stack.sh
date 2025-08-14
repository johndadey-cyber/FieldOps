#!/usr/bin/env bash
set -euo pipefail

ok() { printf "✅ %s\n" "$*"; }
bad(){ printf "❌ %s\n" "$*" >&2; exit 1; }

# -------- assignment_process.php
f1="public/assignment_process.php"
[ -f "$f1" ] || bad "$f1 missing"

# Must have the test-only CSRF injector
grep -Fq "function ap_maybe_inject_test_csrf" "$f1" || bad "ap_maybe_inject_test_csrf() not found in $f1"
grep -Fq "ap_maybe_inject_test_csrf();" "$f1" || bad "ap_maybe_inject_test_csrf() not called in $f1"

# Must guard CLI execution (flagged endpoints only)
grep -Fq "FIELDOPS_ALLOW_ENDPOINT_EXECUTION" "$f1" || bad "CLI guard (FIELDOPS_ALLOW_ENDPOINT_EXECUTION) not found in $f1"

# RBAC + CSRF protections
grep -Fq "require_role('dispatcher')" "$f1" || bad "require_role('dispatcher') missing in $f1"
grep -Fq "require_csrf" "$f1" || bad "require_csrf() missing in $f1"

# Actions present
grep -Fq "case 'assign'" "$f1" || bad "case 'assign' missing in $f1"
grep -Fq "case 'unassign'" "$f1" || bad "case 'unassign' missing in $f1"

ok "$f1 looks good"

# -------- EndpointHarness.php
f2="tests/TestHelpers/EndpointHarness.php"
[ -f "$f2" ] || bad "$f2 missing"

grep -Fq "final class EndpointHarness" "$f2" || bad "EndpointHarness class missing"
grep -Fq "public static function run" "$f2" || bad "run() method missing"
grep -Fq "FIELDOPS_ALLOW_ENDPOINT_EXECUTION" "$f2" || bad "Harness does not define FIELDOPS_ALLOW_ENDPOINT_EXECUTION"
grep -Fq "inject_csrf" "$f2" || bad "inject_csrf option missing in harness"
grep -Fq "csrf_token" "$f2" || bad "CSRF session seeding missing in harness"

ok "$f2 looks good"

# -------- AssignmentsApiTest.php
f3="tests/Integration/AssignmentsApiTest.php"
[ -f "$f3" ] || bad "$f3 missing"

grep -Fq "require_once __DIR__ . '/../TestHelpers/EndpointHarness.php'" "$f3" || bad "AssignmentsApiTest not using EndpointHarness"
grep -Fq "SET FOREIGN_KEY_CHECKS=0" "$f3" || bad "Employee self-seeding block missing in AssignmentsApiTest"
grep -Fq "assignment_process.php" "$f3" || bad "AssignmentsApiTest not calling assignment_process.php"
grep -Fq "'action'       => 'assign'" "$f3" || bad "Assign request missing in AssignmentsApiTest"
grep -Fq "'replace'      => 1" "$f3" || bad "Replace flag missing in AssignmentsApiTest"
grep -Fq "assigned" "$f3" || bad "Expected 'assigned' action not asserted in AssignmentsApiTest"

ok "$f3 looks good"

echo
ok "All signatures present. Next steps:"
echo "  rm -rf .phpunit.cache"
echo "  ./vendor/bin/phpunit --stop-on-failure --testdox tests/Integration"
