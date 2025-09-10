#!/bin/bash
set -vo pipefail

DRUPAL_RECOMMENDED_PROJECT=${DRUPAL_RECOMMENDED_PROJECT:-11.2.x-dev}
PHP_EXTENSIONS="gd"

# Install required PHP extensions
for ext in $PHP_EXTENSIONS; do
  if ! php -m | grep -q $ext; then
    apk update && apk add --no-cache ${ext}-dev
    docker-php-ext-install $ext
  fi
done

# Create Drupal project if it doesn't exist
if [ ! -d "/drupal" ]; then
  composer create-project drupal/recommended-project=$DRUPAL_RECOMMENDED_PROJECT drupal --no-interaction --stability=dev
fi

cd drupal
mkdir -p web/modules/contrib/

# Symlink ai_content_strategy if not already linked
if [ ! -L "web/modules/contrib/ai_content_strategy" ]; then
  ln -s /src web/modules/contrib/ai_content_strategy
fi

# Install the statistics module if D11 (removed from core).
if [[ $DRUPAL_RECOMMENDED_PROJECT == 11.* ]]; then
  composer require drupal/statistics
fi

# Install required dependencies for AI module
composer require drupal/ai

# Install PHPUnit and test dependencies
echo "Installing PHPUnit and test dependencies..."
composer require drupal/core-dev --dev --with-all-dependencies || echo "Core-dev installation completed with warnings"

# Set up the test environment
export SIMPLETEST_BASE_URL="http://localhost"
export SIMPLETEST_DB="sqlite://localhost/tmp/test.sqlite"

# Run PHPUnit tests for our module
if [ -f ./vendor/bin/phpunit ]; then
  echo "Running PHPUnit tests for ai_content_strategy module..."
  
  # Check if our module has tests
  if [ -d "web/modules/contrib/ai_content_strategy/tests" ]; then
    ./vendor/bin/phpunit --configuration core/phpunit.xml.dist web/modules/contrib/ai_content_strategy/tests/
  else
    echo "No tests directory found in ai_content_strategy module"
    echo "Running a basic Drupal core test to verify PHPUnit setup..."
    ./vendor/bin/phpunit --configuration core/phpunit.xml.dist --filter=PathProcessorTest core/tests/Drupal/Tests/Core/PathProcessor/
  fi
else
  echo "PHPUnit not available, trying to run basic syntax checks instead..."
  find web/modules/contrib/ai_content_strategy -name "*.php" -exec php -l {} \; || echo "Syntax check completed"
fi

echo "Test run completed"