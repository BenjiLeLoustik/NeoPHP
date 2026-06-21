#!/usr/bin/env bash
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHPUNIT_BIN="${ROOT_DIR}/vendor/bin/phpunit"
PHPSTAN_BIN="${ROOT_DIR}/vendor/bin/phpstan"
PHPSTAN_CONF="${ROOT_DIR}/phpstan.neon"
CORE_DIR="${ROOT_DIR}/neo/Core"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

TOTAL_SUITES=0
FAILED_SUITES=0
declare -a SUITE_NAMES
declare -a SUITE_RESULTS

PHPSTAN_STATUS=1
PHPSTAN_RAN=0

echo -e "${BOLD}== Neo Framework - runner_dev ==${NC}\n"

if [[ ! -x "${PHPUNIT_BIN}" ]]; then
    echo -e "${RED}phpunit introuvable: ${PHPUNIT_BIN}${NC}"
    echo "Veuillez lancer 'composer install' avant d'utiliser ce script."
    exit 1
fi

echo -e "${BOLD}-- 1. Tests unitaires (./neo/Core/*/Tests) --${NC}"

if [[ ! -d "${CORE_DIR}" ]]; then
    echo -e "${RED}Dossier introuvable: ${CORE_DIR}${NC}"
    exit 1
fi

while IFS= read -r -d '' config; do
    suite_dir="$(dirname "${config}")"
    suite_name="$(basename "$(dirname "${suite_dir}")")"

    TOTAL_SUITES=$((TOTAL_SUITES + 1))
    SUITE_NAMES+=("${suite_name}")

    echo -e "\n${YELLOW}> ${suite_name}${NC} (${config#$ROOT_DIR/})"

    if "${PHPUNIT_BIN}" -c "${config}" --testdox; then
        SUITE_RESULTS+=("OK")
    else
        SUITE_RESULTS+=("FAIL")
        FAILED_SUITES=$((FAILED_SUITES + 1))
    fi
done < <(find "${CORE_DIR}" -type f -name 'phpunit.xml' -print0 | sort -z)

if [[ ${TOTAL_SUITES} -eq 0 ]]; then
    echo -e "${YELLOW}Aucun phpunit.xml trouve sous ${CORE_DIR}.${NC}"
fi

echo -e "\n${BOLD}-- 2. Analyse statique (phpstan.neon) --${NC}"

if [[ ! -x "${PHPSTAN_BIN}" ]]; then
    echo -e "${RED}phpstan introuvable: ${PHPSTAN_BIN}${NC}"
elif [[ ! -f "${PHPSTAN_CONF}" ]]; then
    echo -e "${RED}Config introuvable: ${PHPSTAN_CONF}${NC}"
else
    PHPSTAN_RAN=1
    if "${PHPSTAN_BIN}" analyse -c "${PHPSTAN_CONF}" --no-progress; then
        PHPSTAN_STATUS=0
    else
        PHPSTAN_STATUS=1
    fi
fi

echo -e "\n${BOLD}-- 3. Recapitulatif --${NC}\n"

printf "%-30s %s\n" "Suite" "Resultat"
printf "%-30s %s\n" "-----" "--------"
for i in "${!SUITE_NAMES[@]}"; do
    name="${SUITE_NAMES[$i]}"
    result="${SUITE_RESULTS[$i]}"
    if [[ "${result}" == "OK" ]]; then
        printf "%-30s ${GREEN}%s${NC}\n" "${name}" "OK"
    else
        printf "%-30s ${RED}%s${NC}\n" "${name}" "FAIL"
    fi
done

echo ""
printf "%-30s %s\n" "Total suites" "${TOTAL_SUITES}"
printf "%-30s %s\n" "Suites en echec" "${FAILED_SUITES}"

if [[ ${PHPSTAN_RAN} -eq 1 ]]; then
    if [[ ${PHPSTAN_STATUS} -eq 0 ]]; then
        printf "%-30s ${GREEN}%s${NC}\n" "PHPStan" "OK"
    else
        printf "%-30s ${RED}%s${NC}\n" "PHPStan" "FAIL"
    fi
else
    printf "%-30s ${YELLOW}%s${NC}\n" "PHPStan" "NON EXECUTE"
fi

echo -e "\n${BOLD}-- 4. Decision PR --${NC}\n"

if [[ ${FAILED_SUITES} -eq 0 && ${TOTAL_SUITES} -gt 0 && ${PHPSTAN_RAN} -eq 1 && ${PHPSTAN_STATUS} -eq 0 ]]; then
    echo -e "${GREEN}${BOLD}Oui, vous pouvez ouvrir la PR.${NC}"
    exit 0
fi

echo -e "${RED}${BOLD}Non, vous ne pouvez pas ouvrir la PR pour le moment :${NC}"

if [[ ${TOTAL_SUITES} -eq 0 ]]; then
    echo -e "${RED}  - Aucune suite de tests n'a ete trouvee sous ${CORE_DIR}.${NC}"
fi

if [[ ${FAILED_SUITES} -gt 0 ]]; then
    echo -e "${RED}  - Non, vos tests ne sont pas valides : ${FAILED_SUITES} suite(s) sur ${TOTAL_SUITES} echouent. Corrigez-les avant de continuer.${NC}"
fi

if [[ ${PHPSTAN_RAN} -eq 0 ]]; then
    echo -e "${RED}  - PHPStan n'a pas pu etre execute, verifiez votre installation et la presence de phpstan.neon.${NC}"
elif [[ ${PHPSTAN_STATUS} -ne 0 ]]; then
    echo -e "${RED}  - Non, votre code ne respecte pas les regles definies dans phpstan.neon. Corrigez les erreurs remontees ci-dessus avant de continuer.${NC}"
fi

exit 1