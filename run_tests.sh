#!/usr/bin/env bash

ROOT="$(cd "$(dirname "$0")" && pwd)"

resolve_php() {
    if [ -n "${PHP_BIN:-}" ]; then
        echo "$PHP_BIN"
        return
    fi

    for candidate in php8.5 php8 php; do
        if command -v "$candidate" &>/dev/null; then
            version=$("$candidate" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
            major=$(echo "$version" | cut -d. -f1)
            minor=$(echo "$version" | cut -d. -f2)
            if [ "$major" -gt 8 ] || { [ "$major" -eq 8 ] && [ "$minor" -ge 5 ]; }; then
                echo "$candidate"
                return
            fi
        fi
    done

    for win_path in \
        "/c/php/php.exe" \
        "/c/php8.5/php.exe" \
        "/c/laragon/bin/php/php-8.5"*/php.exe \
        "/c/xampp/php/php.exe" \
        "/c/Program Files/php/php.exe"
    do
        for p in $win_path; do
            if [ -x "$p" ]; then
                version=$("$p" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
                major=$(echo "$version" | cut -d. -f1)
                minor=$(echo "$version" | cut -d. -f2)
                if [ "$major" -gt 8 ] || { [ "$major" -eq 8 ] && [ "$minor" -ge 5 ]; }; then
                    echo "$p"
                    return
                fi
            fi
        done
    done

    echo ""
}

PHP_BIN=$(resolve_php)

if [ -z "$PHP_BIN" ]; then
    echo -e "\033[31m\033[1m✘ PHP 8.5+ not found.\033[0m"
    echo -e "\033[2mSet it manually: PHP_BIN=/path/to/php ./run_tests.sh\033[0m"
    exit 1
fi


PHPUNIT_CMD=("$PHP_BIN" "$ROOT/vendor/bin/phpunit")
PHPSTAN_CMD=("$PHP_BIN" "$ROOT/vendor/bin/phpstan")
PHPSTAN_CONFIG="$ROOT/phpstan.neon"

MODULES=(
    "Asset"
    "Application"
    "Console"
    "Controller"
    "Cron"
    "DI"
)

RESET="\033[0m"
BOLD="\033[1m"
DIM="\033[2m"
GREEN="\033[32m"
RED="\033[31m"
YELLOW="\033[33m"
CYAN="\033[36m"

bold() { echo -e "${BOLD}$1${RESET}"; }
green() { echo -e "${GREEN}$1${RESET}"; }
red() { echo -e "${RED}$1${RESET}"; }
dim() { echo -e "${DIM}$1${RESET}"; }

separator() { echo -e "${DIM}$(printf '─%.0s' {1..60})${RESET}"; }
separator_double() { echo -e "${DIM}$(printf '═%.0s' {1..60})${RESET}"; }

badge_ok() { echo -e "${GREEN}${BOLD} PASS ${RESET}"; }
badge_fail() { echo -e "${RED}${BOLD} FAIL ${RESET}"; }
badge_skip() { echo -e "${YELLOW}${BOLD} SKIP ${RESET}"; }

title() {
    echo
    echo -e "${BOLD}  $1${RESET}"
    separator
}

PASSED=0
FAILED=0
SKIPPED=0

declare -a RESULT_LINES=()

record() {
    local name="$1" status="$2" summary="$3"
    case "$status" in
        pass) RESULT_LINES+=("$(green '✔')  $(printf '%-30s' "$name") ${DIM}${summary}${RESET}") ; ((PASSED++)) ;;
        fail) RESULT_LINES+=("$(red   '✘')  $(printf '%-30s' "$name") ${DIM}${summary}${RESET}") ; ((FAILED++)) ;;
        skip) RESULT_LINES+=("$(echo -e "${YELLOW}–${RESET}")  $(printf '%-30s' "$name") ${DIM}${summary}${RESET}") ; ((SKIPPED++)) ;;
    esac
}

title "PHPUnit — Module Test Suites"

for MODULE in "${MODULES[@]}"; do
    CONFIG="$ROOT/neo/Core/$MODULE/Tests/phpunit.xml"

    if [ ! -f "$CONFIG" ]; then
        echo -e "  $(badge_skip)  ${BOLD}${MODULE}${RESET}  ${DIM}phpunit.xml not found${RESET}"
        record "$MODULE (phpunit)" skip "phpunit.xml not found"
        continue
    fi

    echo -ne "  ${DIM}›${RESET} Running ${CYAN}${MODULE}${RESET}..."

    OUTPUT=$("${PHPUNIT_CMD[@]}" -c "$CONFIG" --colors=never 2>&1)
    CODE=$?

    SUMMARY=$(echo "$OUTPUT" | grep -E '^Tests:|^OK \(' | tail -1)

    if [ $CODE -eq 0 ]; then
        echo -e "\r  $(badge_ok)  ${BOLD}$(printf '%-20s' "$MODULE")${RESET}  ${DIM}${SUMMARY}${RESET}"
        record "$MODULE (phpunit)" pass "$SUMMARY"
    else
        echo -e "\r  $(badge_fail)  ${BOLD}$(printf '%-20s' "$MODULE")${RESET}  ${DIM}${SUMMARY}${RESET}"
        echo "$OUTPUT" | grep -E '(FAIL|Error|There (was|were))' | while read -r line; do
            echo -e "         ${DIM}${line}${RESET}"
        done
        record "$MODULE (phpunit)" fail "$SUMMARY"
    fi
done

title "PHPStan — Static Analysis"

if [ ! -f "$PHPSTAN_CONFIG" ]; then
    echo -e "  $(badge_skip)  ${DIM}phpstan.neon not found${RESET}"
    record "PHPStan" skip "phpstan.neon not found"
else
    echo -ne "  ${DIM}›${RESET} Running ${CYAN}PHPStan${RESET}..."

    OUTPUT=$("${PHPSTAN_CMD[@]}" analyse --no-progress --no-interaction 2>&1)
    CODE=$?

    SUMMARY=$(echo "$OUTPUT" | grep -iE '(No errors|error)' | tail -1 | xargs)

    if [ $CODE -eq 0 ]; then
        echo -e "\r  $(badge_ok)  ${BOLD}$(printf '%-20s' 'PHPStan')${RESET}  ${DIM}${SUMMARY}${RESET}"
        record "PHPStan" pass "$SUMMARY"
    else
        echo -e "\r  $(badge_fail)  ${BOLD}$(printf '%-20s' 'PHPStan')${RESET}  ${DIM}${SUMMARY}${RESET}"
        echo "$OUTPUT" | grep -v '^$' | grep -v 'Note:' | while read -r line; do
            echo -e "         ${DIM}${line}${RESET}"
        done
        record "PHPStan" fail "$SUMMARY"
    fi
fi

echo
separator_double
echo -e "  ${BOLD}SUMMARY${RESET}"
separator_double

for line in "${RESULT_LINES[@]}"; do
    echo -e "  $line"
done

echo
TOTAL=$((PASSED + FAILED))
echo -ne "  ${BOLD}Total:${RESET} $TOTAL  "
echo -ne "${GREEN}Passed: $PASSED${RESET}  "

if [ $FAILED -gt 0 ]; then
    echo -ne "${RED}Failed: $FAILED${RESET}"
else
    echo -ne "${DIM}Failed: $FAILED${RESET}"
fi

if [ $SKIPPED -gt 0 ]; then
    echo -ne "  ${YELLOW}Skipped: $SKIPPED${RESET}"
fi

echo
separator
echo

exit $((FAILED > 0 ? 1 : 0))