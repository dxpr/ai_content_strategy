#!/bin/bash

source scripts/prepare-drupal-lint.sh

phpcbf --standard=Drupal \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=node_modules,ai_content_strategy/vendor,.github,vendor \
  .
# phpcbf exit codes: 0=no errors, 1=errors were fixed, 2=nothing to fix, 3=processing error
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

# phpcbf exit 0 (clean) and 2 (nothing to fix) are both success.
exit 0
