#!/usr/bin/env bash
# E2E tests for card commands.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:card:edit (not found)"

output=$($DRUSH acs:card:edit content_gaps nonexistent --title="Test" 2>&1)
assert_has "edit nonexistent card returns error" "not found" "$output"

section "acs:card:edit (no changes)"

output=$($DRUSH acs:card:edit content_gaps nonexistent 2>&1)
assert_has "edit no changes returns error" "No changes" "$output"

section "acs:card:delete (not found)"

output=$($DRUSH acs:card:delete content_gaps nonexistent 2>&1)
assert_has "delete nonexistent card returns error" "not found" "$output"

output=$($DRUSH acs:card:delete content_gaps nonexistent --dry-run 2>&1)
assert_has "delete dry-run nonexistent returns error" "not found" "$output"

print_summary
