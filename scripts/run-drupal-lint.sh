#!/bin/bash
source scripts/prepare-drupal-lint.sh

EXIT_CODE=0

echo "---- Checking with Drupal standard... ----"
phpcs --standard=Drupal \
  --warning-severity=0 \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_content_strategy/vendor,.github,vendor \
  -v \
  .
status=$?
if [ $status -ne 0 ]; then
  EXIT_CODE=$status
fi

echo "---- Checking with DrupalPractice standard... ----"
phpcs --standard=DrupalPractice \
  --warning-severity=0 \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_content_strategy/vendor,.github,vendor \
  -v \
  .

status=$?
if [ $status -ne 0 ]; then
  EXIT_CODE=$status
fi

# Exit with failure if any of the checks failed
exit $EXIT_CODE
