#!/usr/bin/env bash
# E2E tests for card commands.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"
CARD_UUID="${TEST_CARD_UUID:-e2e-test-card-0001}"

section "acs:card:edit (no changes)"

output=$($DRUSH acs:card:edit content_gaps "$CARD_UUID" 2>&1)
assert_has "edit no changes returns error" "No changes" "$output"

section "acs:card:edit --dry-run"

output=$($DRUSH acs:card:edit content_gaps "$CARD_UUID" --title="Dry Run Title" --dry-run 2>&1)
assert_has "edit dry-run returns success" "success: true" "$output"
assert_dry_run "edit dry-run has flag" "$output"
assert_has "edit dry-run shows new title" "Dry Run Title" "$output"

section "acs:card:edit (actual)"

output=$($DRUSH acs:card:edit content_gaps "$CARD_UUID" --title="Updated E2E Card" 2>&1)
assert_has "edit returns success" "success: true" "$output"
assert_has "edit shows updated title" "Updated E2E Card" "$output"

# Verify the edit persisted.
output=$($DRUSH acs:report:card content_gaps "$CARD_UUID" 2>&1)
assert_has "card reflects edit" "Updated E2E Card" "$output"

section "acs:card:edit --description"

output=$($DRUSH acs:card:edit content_gaps "$CARD_UUID" --description="New description" 2>&1)
assert_has "edit description returns success" "success: true" "$output"

section "acs:card:edit (not found)"

output=$($DRUSH acs:card:edit content_gaps nonexistent --title="Test" 2>&1)
assert_has "edit nonexistent card returns error" "not found" "$output"

section "acs:card:delete --dry-run"

output=$($DRUSH acs:card:delete content_gaps "$CARD_UUID" --dry-run 2>&1)
assert_has "delete dry-run returns success" "success: true" "$output"
assert_dry_run "delete dry-run has flag" "$output"

# Verify card still exists after dry-run.
output=$($DRUSH acs:report:card content_gaps "$CARD_UUID" 2>&1)
assert_has "card still exists after dry-run" "success: true" "$output"

section "acs:card:delete (not found)"

output=$($DRUSH acs:card:delete content_gaps nonexistent 2>&1)
assert_has "delete nonexistent card returns error" "not found" "$output"

print_summary
