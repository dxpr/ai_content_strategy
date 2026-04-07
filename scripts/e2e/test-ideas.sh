#!/usr/bin/env bash
# E2E tests for idea commands.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"
CARD_UUID="${TEST_CARD_UUID:-e2e-test-card-0001}"
IDEA_UUID="${TEST_IDEA_UUID:-e2e-test-idea-0001}"
IDEA2_UUID="${TEST_IDEA2_UUID:-e2e-test-idea-0002}"

section "acs:idea:edit --dry-run"

output=$($DRUSH acs:idea:edit content_gaps "$CARD_UUID" "$IDEA_UUID" --text="Dry run text" --dry-run 2>&1)
assert_has "edit dry-run returns success" "success: true" "$output"
assert_dry_run "edit dry-run has flag" "$output"

section "acs:idea:edit (actual)"

output=$($DRUSH acs:idea:edit content_gaps "$CARD_UUID" "$IDEA_UUID" --text="Updated test idea" 2>&1)
assert_has "edit returns success" "success: true" "$output"
assert_has "edit shows updated text" "Updated test idea" "$output"

section "acs:idea:edit (no changes)"

output=$($DRUSH acs:idea:edit content_gaps "$CARD_UUID" "$IDEA_UUID" 2>&1)
assert_has "edit no text returns error" "No changes" "$output"

section "acs:idea:implement"

output=$($DRUSH acs:idea:implement content_gaps "$CARD_UUID" "$IDEA_UUID" --link=https://example.com/post 2>&1)
assert_has "implement returns success" "success: true" "$output"
assert_has "implement shows link" "https://example.com/post" "$output"

section "acs:idea:implement --undo"

output=$($DRUSH acs:idea:implement content_gaps "$CARD_UUID" "$IDEA_UUID" --undo 2>&1)
assert_has "undo returns success" "success: true" "$output"
assert_has "undo message" "not implemented" "$output"

section "acs:idea:implement --dry-run"

output=$($DRUSH acs:idea:implement content_gaps "$CARD_UUID" "$IDEA_UUID" --dry-run 2>&1)
assert_has "implement dry-run returns success" "success: true" "$output"
assert_dry_run "implement dry-run has flag" "$output"

section "acs:idea:delete --dry-run"

output=$($DRUSH acs:idea:delete content_gaps "$CARD_UUID" "$IDEA2_UUID" --dry-run 2>&1)
assert_has "delete dry-run returns success" "success: true" "$output"
assert_dry_run "delete dry-run has flag" "$output"

section "acs:idea:delete (actual)"

output=$($DRUSH acs:idea:delete content_gaps "$CARD_UUID" "$IDEA2_UUID" 2>&1)
assert_has "delete returns success" "success: true" "$output"
assert_has "delete shows deleted" "Idea deleted" "$output"

# Verify deletion.
output=$($DRUSH acs:idea:delete content_gaps "$CARD_UUID" "$IDEA2_UUID" 2>&1)
assert_has "delete again returns not found" "not found" "$output"

section "acs:idea:edit (not found)"

output=$($DRUSH acs:idea:edit content_gaps nonexistent nonexistent --text="Test" 2>&1)
assert_has "edit nonexistent card returns error" "not found" "$output"

print_summary
