#!/usr/bin/env bash
# E2E tests for settings commands.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:settings:get"

output=$($DRUSH acs:settings:get 2>&1)
assert_has "get returns success" "success: true" "$output"
assert_has "get has system_prompt" "system_prompt" "$output"

section "acs:settings:set"

# No changes.
output=$($DRUSH acs:settings:set 2>&1)
assert_has "set no changes returns error" "No changes" "$output"

# Dry run.
output=$($DRUSH acs:settings:set --system-prompt="Test prompt" --dry-run 2>&1)
assert_has "set dry-run returns success" "success: true" "$output"
assert_has "set dry-run mentions dry run" "Dry run" "$output"

# Actual set.
output=$($DRUSH acs:settings:set --system-prompt="E2E test prompt" 2>&1)
assert_has "set returns success" "success: true" "$output"

# Verify.
output=$($DRUSH acs:settings:get 2>&1)
assert_has "get after set shows update" "success: true" "$output"

print_summary
