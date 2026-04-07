#!/usr/bin/env bash
# E2E tests for category management commands.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:category:list"

output=$($DRUSH acs:category:list 2>&1)
assert_has "list returns success" "success: true" "$output"
assert_has "list contains content_gaps" "content_gaps" "$output"
assert_has "list contains authority_topics" "authority_topics" "$output"

section "acs:category:get"

output=$($DRUSH acs:category:get content_gaps 2>&1)
assert_has "get returns success" "success: true" "$output"
assert_has "get contains label" "label:" "$output"
assert_has "get contains instructions" "instructions:" "$output"

# Nonexistent category.
output=$($DRUSH acs:category:get nonexistent 2>&1)
assert_has "get nonexistent returns error" "not found" "$output"

section "acs:category:create"

# Dry run.
output=$($DRUSH acs:category:create test_e2e "E2E Test" --dry-run 2>&1)
assert_has "create dry-run returns success" "success: true" "$output"
assert_has "create dry-run mentions dry run" "Dry run" "$output"

# Actual create.
output=$($DRUSH acs:category:create test_e2e "E2E Test" --instructions="Test instructions" 2>&1)
assert_has "create returns success" "success: true" "$output"
assert_has "create returns id" "test_e2e" "$output"

# Duplicate.
output=$($DRUSH acs:category:create test_e2e "E2E Test" 2>&1)
assert_has "create duplicate returns error" "already exists" "$output"

# Invalid ID.
output=$($DRUSH acs:category:create 123invalid "Bad ID" 2>&1)
assert_has "create invalid ID returns error" "Invalid ID" "$output"

section "acs:category:update"

output=$($DRUSH acs:category:update test_e2e --label="Updated E2E" --dry-run 2>&1)
assert_has "update dry-run returns success" "success: true" "$output"

output=$($DRUSH acs:category:update test_e2e --label="Updated E2E" 2>&1)
assert_has "update returns success" "success: true" "$output"

# No changes.
output=$($DRUSH acs:category:update test_e2e 2>&1)
assert_has "update no changes returns error" "No changes" "$output"

# Nonexistent.
output=$($DRUSH acs:category:update nonexistent --label="X" 2>&1)
assert_has "update nonexistent returns error" "not found" "$output"

section "acs:category:delete"

output=$($DRUSH acs:category:delete test_e2e --dry-run 2>&1)
assert_has "delete dry-run returns success" "success: true" "$output"

output=$($DRUSH acs:category:delete test_e2e 2>&1)
assert_has "delete returns success" "success: true" "$output"

# Already deleted.
output=$($DRUSH acs:category:delete test_e2e 2>&1)
assert_has "delete nonexistent returns error" "not found" "$output"

print_summary
