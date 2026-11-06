#!/bin/sh
# Raise repo-root VERSION by one from HEAD, then stage it.
# --restore puts VERSION back to HEAD after a failed build.
set -eu

if [ "${1:-}" = "--restore" ]; then
  if git cat-file -e HEAD:VERSION 2>/dev/null; then
    git checkout HEAD -- VERSION
  else
    rm -f VERSION
    git rm -f --cached --ignore-unmatch -- VERSION >/dev/null 2>&1 || true
  fi
  exit 0
fi

if git rev-parse --verify HEAD >/dev/null 2>&1 && git cat-file -e HEAD:VERSION 2>/dev/null; then
  base=$(git show HEAD:VERSION | tr -d '[:space:]')
else
  base=0
fi

case "$base" in
  ''|*[!0-9]*)
    echo "VERSION in HEAD is not a positive integer." >&2
    exit 1
    ;;
esac

next=$((base + 1))
if [ "$next" -lt 1 ]; then
  echo "VERSION must stay a positive integer." >&2
  exit 1
fi

printf '%s\n' "$next" > VERSION
git add -- VERSION
echo "Package version: $base -> $next"
