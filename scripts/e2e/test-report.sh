#!/usr/bin/env bash
# E2E tests for report commands.
# NOTE: This file runs after test-cards and test-ideas which mutate
# fixture data. Assertions must not depend on original fixture values.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"
CARD_UUID="${TEST_CARD_UUID:-e2e-test-card-0001}"

section "acs:report:status"

output=$($DRUSH acs:report:status 2>&1)
assert_has "status returns success" "success: true" "$output"
assert_has "status has active_categories" "active_categories" "$output"
assert_has "status shows has_report true" "has_report: true" "$output"
assert_has "status shows pages_analyzed" "pages_analyzed: 10" "$output"

section "acs:report (with fixture data)"

output=$($DRUSH acs:report 2>&1)
assert_has "report returns success" "success: true" "$output"
assert_has "report contains content_gaps" "content_gaps" "$output"
assert_has "report contains card uuid" "$CARD_UUID" "$output"

section "acs:report --category filter"

output=$($DRUSH acs:report --category=content_gaps 2>&1)
assert_has "report filtered by category returns success" "success: true" "$output"
assert_has "filtered report contains content_gaps" "content_gaps" "$output"

output=$($DRUSH acs:report --category=nonexistent 2>&1 || true)
assert_has "report with nonexistent category returns error" "No recommendations" "$output"

section "acs:report --priority filter"

output=$($DRUSH acs:report --priority=high 2>&1)
assert_has "report filtered by high priority returns success" "success: true" "$output"
assert_has "high priority report contains card" "$CARD_UUID" "$output"

output=$($DRUSH acs:report --priority=low 2>&1 || true)
assert_has "report with no low priority returns error" "No recommendations" "$output"

section "acs:report:card (with fixture data)"

output=$($DRUSH acs:report:card content_gaps "$CARD_UUID" 2>&1)
assert_has "card returns success" "success: true" "$output"
assert_has "card contains uuid" "$CARD_UUID" "$output"
assert_has "card contains priority" "priority: high" "$output"

output=$($DRUSH acs:report:card content_gaps nonexistent-uuid 2>&1 || true)
assert_has "card not found returns error" "not found" "$output"

section "acs:sitemap"

output=$($DRUSH acs:sitemap -l http://localhost:8888 2>&1 || true)
assert_has "sitemap returns success or error gracefully" "success:" "$output"
assert_has "sitemap has content_types" "content_types" "$output"

print_summary
