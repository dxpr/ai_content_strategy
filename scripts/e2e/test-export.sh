#!/usr/bin/env bash
# E2E tests for export command.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:export (yaml default)"

output=$($DRUSH acs:export 2>&1)
assert_has "export yaml contains content_gaps" "content_gaps" "$output"
assert_has "export yaml contains card data" "priority:" "$output"

section "acs:export --format=json"

output=$($DRUSH acs:export --format=json 2>&1)
assert_has "export json has categories key" "categories" "$output"
assert_has "export json has content_gaps" "content_gaps" "$output"

section "acs:export --format=csv"

output=$($DRUSH acs:export --format=csv 2>&1)
assert_has "csv has header" "Category" "$output"
assert_has "csv has card data" "Content Gaps" "$output"

section "acs:export --category filter"

output=$($DRUSH acs:export --category=content_gaps 2>&1)
assert_has "export filtered contains content_gaps" "content_gaps" "$output"

output=$($DRUSH acs:export --category=nonexistent 2>&1 || true)
assert_has "export nonexistent category returns error" "No recommendations" "$output"

section "acs:export --file"

TMPFILE=$(mktemp /tmp/acs-export-XXXXXX.yml)
output=$($DRUSH acs:export --file="$TMPFILE" 2>&1)
assert_has "export to file returns success" "success: true" "$output"
assert_has "export to file mentions path" "$TMPFILE" "$output"
assert_not_empty "file has content" "$(cat "$TMPFILE")"
rm -f "$TMPFILE"

section "acs:export --file (bad path)"

output=$($DRUSH acs:export --file="/nonexistent/dir/file.yml" 2>&1 || true)
assert_has "export bad path returns error" "Cannot write" "$output"

print_summary
