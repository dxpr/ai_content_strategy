#!/bin/bash

source scripts/prepare-drupal-lint.sh

phpcbf --standard=Drupal \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_content_strategy/vendor,.github,vendor \
  .
# phpcbf exit codes: 0=clean, 1=fixed, 2=unfixable, 3=error
DRUPAL_STATUS=$?
if [ $DRUPAL_STATUS -eq 3 ]; then
  exit 1
fi

phpcbf --standard=DrupalPractice \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_content_strategy/vendor,.github,vendor \
  .
PRACTICE_STATUS=$?
if [ $PRACTICE_STATUS -eq 3 ]; then
  exit 1
fi
