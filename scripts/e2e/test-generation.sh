#!/usr/bin/env bash
# E2E tests for generation commands.
# Note: acs:generate requires a configured AI provider.
# Tests verify error handling and health check without a provider.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/_helpers.sh"

DRUSH="${DRUSH:-drush}"

section "acs:health"

# Without AI provider configured, health should return an error.
output=$($DRUSH acs:health 2>&1)
assert_has "health returns provider status" "success:" "$output"

section "acs:generate (no provider)"

output=$($DRUSH acs:generate -l http://localhost 2>&1 || true)
# Should fail gracefully without AI provider.
assert_has "generate without provider returns error" "Generation failed" "$output"

section "acs:generate:add (nonexistent category)"

output=$($DRUSH acs:generate:add nonexistent_cat -l http://localhost 2>&1 || true)
assert_has "generate:add nonexistent category returns error" "not found" "$output"

section "acs:generate:more (nonexistent card)"

output=$($DRUSH acs:generate:more content_gaps nonexistent-uuid -l http://localhost 2>&1 || true)
assert_has "generate:more nonexistent card returns error" "not found" "$output"

print_summary
