#!/bin/bash
set -e

echo 'PHP Lint'
find . \( -path './node_modules' -o -path './vendor' -o -path './docs' \) -prune -o -name '*.php' -print |
  while read -r f; do
    php -l "$f"
  done

echo 'JS Syntax'
find assets -name '*.js' -print |
  while read -r f; do
    node --check "$f" >/dev/null && echo "$f: OK"
  done