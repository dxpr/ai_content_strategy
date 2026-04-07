#!/usr/bin/env bash
# E2E tests for generation commands.
# Note: acs:generate requires a configured AI provider for actual generation.
# Tests verify --dry-run (no AI call), error handling, and health check.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"
CARD_UUID="${TEST_CARD_UUID:-e2e-test-card-0001}"

section "acs:health"

# Without AI provider configured, health should report not ready.
output=$($DRUSH acs:health 2>&1 || true)
assert_has "health reports provider not ready" "success: false" "$output"
assert_has "health mentions provider" "provider" "$output"

section "acs:generate --dry-run"

# Dry-run does NOT call the AI — should always succeed.
output=$($DRUSH acs:generate --dry-run -l http://localhost:8888 2>&1)
assert_has "generate dry-run returns success" "success: true" "$output"
assert_dry_run "generate dry-run has flag" "$output"
assert_has "generate dry-run lists categories" "enabled_categories" "$output"
assert_has "generate dry-run warns about overwrite" "Existing recommendations will be replaced" "$output"

section "acs:generate --category --dry-run"

output=$($DRUSH acs:generate --category=content_gaps --dry-run -l http://localhost:8888 2>&1)
assert_has "generate category dry-run returns success" "success: true" "$output"
assert_dry_run "generate category dry-run has flag" "$output"
assert_has "generate category dry-run shows category" "content_gaps" "$output"
assert_has "generate category dry-run shows existing count" "existing_cards" "$output"

section "acs:generate:add --dry-run"

output=$($DRUSH acs:generate:add content_gaps --dry-run -l http://localhost:8888 2>&1)
assert_has "generate:add dry-run returns success" "success: true" "$output"
assert_dry_run "generate:add dry-run has flag" "$output"
assert_has "generate:add dry-run shows category" "content_gaps" "$output"

section "acs:generate:more --dry-run"

output=$($DRUSH acs:generate:more content_gaps "$CARD_UUID" --dry-run -l http://localhost:8888 2>&1)
assert_has "generate:more dry-run returns success" "success: true" "$output"
assert_dry_run "generate:more dry-run has flag" "$output"
assert_has "generate:more dry-run shows card uuid" "$CARD_UUID" "$output"
assert_has "generate:more dry-run shows existing ideas" "existing_ideas" "$output"

section "acs:generate (no provider — actual generation fails)"

output=$($DRUSH acs:generate -l http://localhost:8888 2>&1 || true)
assert_has "generate without provider returns error" "Generation failed" "$output"

section "acs:generate:add (nonexistent category)"

output=$($DRUSH acs:generate:add nonexistent_cat -l http://localhost:8888 2>&1 || true)
assert_has "generate:add nonexistent category returns error" "not found" "$output"

section "acs:generate:more (nonexistent card)"

output=$($DRUSH acs:generate:more content_gaps nonexistent-uuid -l http://localhost:8888 2>&1 || true)
assert_has "generate:more nonexistent card returns error" "not found" "$output"

section "acs:generate --category (nonexistent)"

output=$($DRUSH acs:generate --category=nonexistent -l http://localhost:8888 2>&1 || true)
assert_has "generate category nonexistent returns error" "not found" "$output"

print_summary
