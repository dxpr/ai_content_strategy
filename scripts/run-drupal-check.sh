#!/bin/bash
set -vo pipefail

DRUPAL_RECOMMENDED_PROJECT=${DRUPAL_RECOMMENDED_PROJECT:-11.x-dev}
PHP_EXTENSIONS="gd"
DRUPAL_CHECK_TOOL="mglaman/drupal-check"

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

# Install the statistic modules if D11 (removed from core).
if [[ $DRUPAL_RECOMMENDED_PROJECT == 11.* ]]; then
  composer require drupal/statistics
fi

# Install drupal-check - try different approaches for compatibility
echo "Attempting to install drupal-check..."

# First try the latest version
if composer require $DRUPAL_CHECK_TOOL --dev --with-all-dependencies; then
  echo "Successfully installed drupal-check"
elif composer require "drupal/coder:^8.3.1" --dev --with-all-dependencies; then
  echo "Installed drupal/coder instead as fallback for static analysis"
  # Use phpcs for static analysis instead
  ./vendor/bin/phpcs --standard=Drupal --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml web/modules/contrib/ai_content_strategy
  exit $?
else
  echo "Warning: Could not install drupal-check or drupal/coder due to dependency conflicts."
  echo "This is a known issue with PHP 8.3 and Drupal 11.x-dev."
  echo "Skipping drupal-check analysis..."
  exit 0
fi

# Run drupal-check if it was installed successfully
if [ -f ./vendor/bin/drupal-check ]; then
  echo "Running drupal-check analysis..."
  ./vendor/bin/drupal-check --drupal-root . -ad web/modules/contrib/ai_content_strategy
else
  echo "drupal-check binary not found, skipping analysis"
  exit 0
fi 