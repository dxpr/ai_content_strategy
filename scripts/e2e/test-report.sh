#!/usr/bin/env bash
# E2E tests for report commands.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:report:status"

output=$($DRUSH acs:report:status 2>&1)
assert_has "status returns success" "success: true" "$output"
assert_has "status has active_categories" "active_categories" "$output"
assert_has "status has has_report" "has_report" "$output"

section "acs:report (no data)"

output=$($DRUSH acs:report 2>&1)
assert_has "report with no data returns error" "No report generated" "$output"

section "acs:report:card (not found)"

output=$($DRUSH acs:report:card content_gaps nonexistent-uuid 2>&1)
assert_has "card not found returns error" "not found" "$output"

section "acs:sitemap"

output=$($DRUSH acs:sitemap -l http://localhost 2>&1)
assert_has "sitemap returns success" "success: true" "$output"
assert_has "sitemap has total_urls" "total_urls" "$output"
assert_has "sitemap has content_types" "content_types" "$output"

print_summary
