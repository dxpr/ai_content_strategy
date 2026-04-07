#!/usr/bin/env bash
# E2E tests for idea commands.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:idea:edit (not found)"

output=$($DRUSH acs:idea:edit content_gaps nonexistent nonexistent --text="Test" 2>&1)
assert_has "edit nonexistent card returns error" "not found" "$output"

section "acs:idea:edit (no changes)"

output=$($DRUSH acs:idea:edit content_gaps nonexistent nonexistent 2>&1)
assert_has "edit no text returns error" "No changes" "$output"

section "acs:idea:implement (not found)"

output=$($DRUSH acs:idea:implement content_gaps nonexistent nonexistent 2>&1)
assert_has "implement nonexistent card returns error" "not found" "$output"

section "acs:idea:delete (not found)"

output=$($DRUSH acs:idea:delete content_gaps nonexistent nonexistent 2>&1)
assert_has "delete nonexistent card returns error" "not found" "$output"

output=$($DRUSH acs:idea:delete content_gaps nonexistent nonexistent --dry-run 2>&1)
assert_has "delete dry-run nonexistent returns error" "not found" "$output"

print_summary
