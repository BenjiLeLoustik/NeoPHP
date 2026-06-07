# Contributing to NeoPHP

Ce document concerne les contributions sur le coeur du framework, en particulier tout changement dans `neo/Core/`.

## Périmètre

Si vous modifiez le coeur de NeoPHP :

- respectez l'organisation déjà en place dans `neo/Core/`
- gardez les mêmes conventions de nommage, de découpage et de responsabilité que les features existantes
- ajoutez votre code dans le module concerné au lieu d'introduire une structure parallèle
- gardez les changements ciblés et cohérents avec l'architecture actuelle du framework

En pratique, une nouvelle feature du coeur doit ressembler au reste du dépôt, autant dans la structure que dans le style d'implémentation.

## Tests obligatoires

Les tests sont obligatoires pour toute modification du coeur.

Attentes minimales :

- toute nouvelle feature doit être couverte par des tests
- toute correction de bug doit inclure au moins un test de non-régression
- si un comportement existant change, les tests concernés doivent être mis à jour

Quand c'est pertinent, ajoutez les tests dans le module concerné, par exemple :

- `neo/Core/Application/Tests/`
- `neo/Core/Asset/Tests/`
- `neo/Core/Console/Tests/`

Si vous introduisez un nouveau sous-système du coeur, fournissez la structure de test associée dès la PR.

## Qualité statique

Le code doit respecter **PHPStan level 5**.

Référence du projet :

- configuration : `phpstan.neon`
- niveau attendu : `level: 5`

Une contribution n'est pas prête tant que l'analyse statique échoue.

## Validation avant envoi

Avant d'envoyer une contribution, lancez le script de validation à la racine :

```bash
./run_tests.sh
```

Ce script sert de point de contrôle avant mise en ligne. Il exécute automatiquement :

- les suites PHPUnit configurées dans le dépôt
- PHPStan

Vous devez considérer la contribution comme prête à être envoyée uniquement quand `run_tests.sh` passe entièrement.

Prérequis utiles :

- PHP `>= 8.5`
- dépendances installées via Composer

Si besoin, le binaire PHP peut être forcé ainsi :

```bash
PHP_BIN=/path/to/php ./run_tests.sh
```

## Pull Requests

Une PR doit être lisible, vérifiable et limitée à un sujet clair.

### Ce qu'une PR doit contenir

- un titre explicite
- un résumé court du problème traité
- la solution retenue
- les zones impactées dans `neo/Core/`
- les tests ajoutés ou mis à jour
- le résultat de la validation locale avec `run_tests.sh`

### Format attendu

Utilisez une description de PR simple, par exemple :

```text
## Objectif
- Corrige / ajoute ...

## Changements
- ...

## Tests
- Ajout de ...
- Exécution de ./run_tests.sh : OK

## Impact
- BC break : oui/non
- Modules concernés : ...
```

### Règles de fond

- une PR = un sujet
- pas de refactor non lié mélangé à une feature ou à un fix
- pas de régression connue laissée volontairement dans la PR
- pas de PR sans tests
- pas de PR avec PHPStan en échec

## En résumé

Pour contribuer proprement au coeur de NeoPHP :

1. respectez les conventions déjà présentes dans `neo/Core/`
2. ajoutez les tests nécessaires
3. faites passer PHPStan level 5
4. exécutez `./run_tests.sh`
5. ouvrez une PR claire avec contexte, changements et validation
