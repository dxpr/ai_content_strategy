#!/usr/bin/env bash
# E2E test runner for AI Content Strategy module.
# Sets up a fresh Drupal install with fixtures and runs all test scripts.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
MODULE_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
FILTER="${1:-}"

echo "AI Content Strategy E2E Tests"
echo "=============================="
echo ""

# Install PHP extensions required by Drupal.
if command -v apk &> /dev/null; then
  echo "Installing system dependencies..."
  apk add --no-cache bash yq libpng libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev > /dev/null 2>&1
  docker-php-ext-install gd > /dev/null 2>&1
fi

# Check for yq.
if ! command -v yq &> /dev/null; then
  echo "ERROR: yq is required. Install: https://github.com/mikefarah/yq"
  exit 1
fi

# Create a fresh Drupal install.
SITE_DIR=$(mktemp -d)
trap 'rm -rf "$SITE_DIR"' EXIT

echo "Setting up Drupal in $SITE_DIR..."

cd "$SITE_DIR"
composer create-project drupal/recommended-project:11.1.x-dev . --no-interaction --quiet

# Symlink our module.
mkdir -p web/modules/contrib
ln -s "$MODULE_DIR" web/modules/contrib/ai_content_strategy

# Install Drush and AI module dependency.
composer require drush/drush drupal/ai --quiet --no-interaction

# Install Drupal with SQLite.
./vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --site-name="ACS E2E Tests" \
  --site-mail="test@example.com" \
  --yes \
  --quiet

# Enable the module.
DRUSH="$SITE_DIR/vendor/bin/drush"
$DRUSH en ai_content_strategy --yes --quiet

# Rebuild cache after enabling module.
$DRUSH cr --quiet

# Start PHP built-in web server for acs:sitemap tests.
echo "Starting PHP web server on localhost:8888..."
php -S localhost:8888 -t "$SITE_DIR/web" > /dev/null 2>&1 &
PHP_SERVER_PID=$!
# Add to trap for cleanup.
trap 'kill $PHP_SERVER_PID 2>/dev/null; rm -rf "$SITE_DIR"' EXIT
# Wait for server to be ready.
for i in $(seq 1 10); do
  if curl -s -o /dev/null http://localhost:8888/ 2>/dev/null; then
    break
  fi
  sleep 0.5
done

echo "Creating test fixtures..."

# Create test fixtures: pre-populate recommendations via php:eval.
$DRUSH php:eval '
$storage = \Drupal::service("ai_content_strategy.recommendation_storage");

$test_card_uuid = "e2e-test-card-0001";
$test_idea_uuid = "e2e-test-idea-0001";
$test_idea2_uuid = "e2e-test-idea-0002";

$recommendations = [
  "content_gaps" => [
    [
      "uuid" => $test_card_uuid,
      "title" => "E2E Test Card",
      "description" => "A card created for E2E testing",
      "priority" => "high",
      "content_ideas" => [
        [
          "uuid" => $test_idea_uuid,
          "text" => "First test idea",
          "implemented" => false,
          "link" => "",
        ],
        [
          "uuid" => $test_idea2_uuid,
          "text" => "Second test idea",
          "implemented" => false,
          "link" => "",
        ],
      ],
    ],
  ],
  "authority_topics" => [
    [
      "uuid" => "e2e-test-card-0002",
      "title" => "Authority Test Card",
      "description" => "Testing authority topics",
      "priority" => "medium",
      "content_ideas" => [
        [
          "uuid" => "e2e-test-idea-0003",
          "text" => "Authority idea",
          "implemented" => false,
          "link" => "",
        ],
      ],
    ],
  ],
];

$storage->saveRecommendations($recommendations, 10);
echo "Fixtures created.\n";
'

echo "Drupal installed with fixtures. Running tests..."
echo ""

# Export fixture UUIDs for tests.
export TEST_CARD_UUID="e2e-test-card-0001"
export TEST_IDEA_UUID="e2e-test-idea-0001"
export TEST_IDEA2_UUID="e2e-test-idea-0002"

# Track test file results.
TESTS_RUN=0
FAILED_FILES=""

for test_file in "$SCRIPT_DIR"/test-*.sh; do
  test_name=$(basename "$test_file" .sh)

  # Apply filter if given.
  if [ -n "$FILTER" ] && [[ "$test_name" != *"$FILTER"* ]]; then
    continue
  fi

  echo "--- Running: $test_name ---"
  # Run test file in subshell; capture exit code without aborting.
  if DRUSH="$DRUSH" bash "$test_file"; then
    : # Test file passed.
  else
    FAILED_FILES="$FAILED_FILES $test_name"
  fi
  TESTS_RUN=$((TESTS_RUN + 1))
done

echo ""
echo "=============================="
echo "  Completed $TESTS_RUN test files."
if [ -n "$FAILED_FILES" ]; then
  echo "  FAILED:$FAILED_FILES"
  echo "=============================="
  exit 1
else
  echo "  All test files passed."
  echo "=============================="
fi
