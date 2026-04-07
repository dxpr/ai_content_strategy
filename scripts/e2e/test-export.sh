#!/usr/bin/env bash
# E2E tests for export command.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:export (no data)"

output=$($DRUSH acs:export 2>&1)
assert_has "export with no data returns error" "No recommendations" "$output"

output=$($DRUSH acs:export --format=json 2>&1)
assert_has "export json with no data returns error" "No recommendations" "$output"

output=$($DRUSH acs:export --format=csv 2>&1)
assert_has "export csv with no data returns error" "No recommendations" "$output"

print_summary
