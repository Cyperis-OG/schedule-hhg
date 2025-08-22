#!/bin/bash
# List JS and PHP files that are not referenced by path from source files (excluding docs)
find . \( -path './node_modules' -o -path './vendor' -o -path './docs' \) -prune -o -type f \( -name '*.js' -o -name '*.php' \) -print |
  while read -r f; do
    path="${f#./}"
    refs=$(rg -F "$path" -l --glob '!docs/**' . || true)
    refs_dots=$(rg -F "./$path" -l --glob '!docs/**' . || true)
    refs_combined=$(printf "%s\n%s" "$refs" "$refs_dots" | sed '/^$/d' | sort -u | grep -v "^$f$" || true)
    if [ -z "$refs_combined" ]; then
      echo "$path"
    fi
  done