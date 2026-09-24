#!/usr/bin/env bash
# cc-assistant test runner. Usage: tests/run.sh
# PHP binary: $CC_TEST_PHP if set, else the Local Flywheel bundle, else `php`.
# The warehouse/outcome tests need the sqlite3 extension (loaded via -d).
set -u
cd "$(dirname "$0")"

PHP="${CC_TEST_PHP:-}"
# Local Flywheel has shipped PHP under two different roots; probe both. The
# extension dir is derived from whichever binary wins, so a CC_TEST_PHP
# override also gets sqlite3 — passing one used to report the three
# sqlite-backed test files as "extension not loaded".
if [ -z "$PHP" ]; then
  for c in /c/Users/*/AppData/Local/Programs/Local/resources/extraResources/lightning-services/php-8*/bin/win64/php.exe \
           /c/Users/*/AppData/Roaming/Local/lightning-services/php-8*/bin/win64/php.exe; do
    [ -x "$c" ] && PHP="$c" && break
  done
fi
[ -n "$PHP" ] || PHP="php"
EXT_DIR="$(dirname "$PHP")/ext"
if [ -d "$EXT_DIR" ]; then EXT_ARGS=(-d "extension_dir=$EXT_DIR")
else EXT_ARGS=(); fi
for cc_ext in sqlite3 mbstring; do
  if ! "$PHP" -r "exit(extension_loaded('$cc_ext') ? 0 : 1);"; then EXT_ARGS+=(-d "extension=$cc_ext"); fi
done
echo "PHP: $PHP"

rm -rf .tmp-warehouse .tmp-outcome .tmp-commodity .tmp-kw .tmp-wcag outcome-test-data
FAILED=0
# Auto-discover instead of a hand-kept list. The hardcoded list had silently
# stopped running asset-references-test.php and render-health-test.php — the
# suite still printed "ALL TEST FILES PASS" while skipping a third of itself,
# which is worse than having no runner. Extension args are harmless on the
# files that do not need them.
for t in *-test.php; do
  echo "== $t"
  if ! "$PHP" "${EXT_ARGS[@]}" "$t"; then FAILED=1; fi
  echo
done
rm -rf .tmp-warehouse .tmp-outcome .tmp-commodity .tmp-kw .tmp-wcag
if [ "$FAILED" -eq 0 ]; then echo "ALL TEST FILES PASS"; else echo "TEST FAILURES — see above"; fi
exit $FAILED
