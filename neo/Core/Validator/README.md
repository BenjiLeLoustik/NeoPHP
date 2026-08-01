# Validator

Le module Validator fournit un système de validation basé sur les **attributs PHP 8** (`#[Attribute]`). Les contraintes se posent directement sur les propriétés des modèles ou objets. Le `ValidatorManager` inspecte ces attributs par réflexion et retourne un tableau d'erreurs par champ.

Depuis la refactorisation, chaque contrainte est **scindée en deux fichiers** : un attribut PHP (dans `Assert/`) et un validator dédié (dans `Validator/`).

---

## Sommaire

1. [Structure du module](#structure-du-module)
2. [Interfaces](#interfaces)
3. [Fonctionnement général](#fonctionnement-général)
4. [ValidatorManager](#validatormanager)
5. [AbstractConstraint](#abstractconstraint)
6. [ValidationContext](#validationcontext)
7. [Contraintes disponibles](#contraintes-disponibles)
   - [NotBlank](#notblank)
   - [Length](#length)
   - [Email](#email)
   - [Regex](#regex)
   - [Choice](#choice)
   - [Range](#range)
   - [Date](#date)
   - [Url](#url)
   - [Unique](#unique)
   - [Exists](#exists)
   - [EqualToField](#equaltofield)
8. [Intégration avec les formulaires (Form)](#intégration-avec-les-formulaires-form)
9. [Créer une contrainte personnalisée](#créer-une-contrainte-personnalisée)
10. [Exemple complet](#exemple-complet)

---

## Structure du module

```
Validator/
├── ValidatorManager.php                # Orchestrateur de la validation
├── ValidatorModule.php                 # Enregistrement dans le conteneur DI
├── ValidationContext.php               # Contexte passé à chaque validator
├── Abstract/
│   └── AbstractConstraint.php         # Classe abstraite de base pour toutes les contraintes
├── Interface/
│   ├── ConstraintInterface.php        # Contrat d'une contrainte
│   └── ConstraintValidatorInterface.php # Contrat d'un validator
├── Assert/                            # Attributs PHP (déclaration sur les propriétés)
│   ├── NotBlank.php
│   ├── Length.php
│   ├── Email.php
│   ├── Regex.php
│   ├── Choice.php
│   ├── Range.php
│   ├── Date.php
│   ├── Url.php
│   ├── Unique.php
│   ├── Exists.php
│   └── EqualToField.php
└── Validator/                         # Logique de validation (un fichier par contrainte)
    ├── NotBlankValidator.php
    ├── LengthValidator.php
    ├── EmailValidator.php
    ├── RegexValidator.php
    ├── ChoiceValidator.php
    ├── RangeValidator.php
    ├── DateValidator.php
    ├── UrlValidator.php
    ├── UniqueValidator.php
    ├── ExistsValidator.php
    └── EqualToFieldValidator.php
```

**Principe du split :** L'attribut (dans `Assert/`) porte uniquement les paramètres de configuration et déclare le validator via `validatedBy()`. Le validator (dans `Validator/`) contient toute la logique de validation et reçoit la valeur, la contrainte et le `ValidationContext`.

---

## Interfaces

### ConstraintInterface

```php
namespace Neo\Core\Validator\Interface;

interface ConstraintInterface
{
    public function getMessage(): string;

    /** @return class-string<ConstraintValidatorInterface> */
    public function validatedBy(): string;

    public function runOnEmpty(): bool;
}
```

| Méthode | Description |
|---------|-------------|
| `getMessage()` | Retourne le message d'erreur |
| `validatedBy()` | Retourne le FQCN du validator associé |
| `runOnEmpty()` | `true` si la contrainte doit s'appliquer même quand la valeur est `null` ou `''` |

### ConstraintValidatorInterface

```php
namespace Neo\Core\Validator\Interface;

interface ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void;
}
```

Le validator ne retourne rien : il appelle `$context->addViolation(string $message)` pour signaler une erreur.

---

## Fonctionnement général

1. Les contraintes sont déclarées comme **attributs PHP 8** sur les propriétés d'une classe.
2. `ValidatorManager::validate()` inspecte la classe par réflexion et parcourt toutes les propriétés.
3. Pour chaque contrainte trouvée, le `ValidatorManager` résout le validator via le conteneur DI (`validatedBy()`).
4. Le validator reçoit la valeur, la contrainte et un `ValidationContext`. Il appelle `$context->addViolation()` si la valeur est invalide.
5. Le résultat est un tableau `['nomChamp' => ['message erreur 1', ...]]`.
6. Si le tableau est vide, la validation est réussie.

**Comportement clé : les contraintes autres que `NotBlank` et `EqualToField` sont ignorées si la valeur est `null` ou `''` et que `runOnEmpty()` retourne `false`.** Cela permet de combiner `NotBlank` avec d'autres contraintes pour rendre un champ obligatoire.

---

## ValidatorManager

```php
use Neo\Core\Validator\ValidatorManager;

// Via le conteneur (recommandé) :
$validator = $container->get(ValidatorManager::class);

$errors = $validator->validate($monObjet);

if (!empty($errors)) {
    foreach ($errors as $champ => $messages) {
        foreach ($messages as $message) {
            echo "$champ : $message\n";
        }
    }
}
```

**Signature :**

```php
public function validate(object $model, ?Form $form = null): array<string, list<string>>
```

| Paramètre | Description |
|---|---|
| `$model` | L'objet à valider (instancié avec les valeurs à contrôler) |
| `$form` | Instance `Form` optionnelle pour les contraintes ajoutées dynamiquement |

Le `ValidatorManager` est injecté avec un `Container` qui lui permet de résoudre les validators à la demande. Les instances de validators sont mises en cache pour la durée de la requête.

---

## AbstractConstraint

Toutes les contraintes étendent `Neo\Core\Validator\Abstract\AbstractConstraint`.

```php
abstract class AbstractConstraint implements ConstraintInterface
{
    public function __construct(
        public string $message = '',
    ) {}

    public function getMessage(): string { return $this->message; }

    public function runOnEmpty(): bool { return false; }

    abstract public function validatedBy(): string;
}
```

Pour créer une contrainte personnalisée, étendre `AbstractConstraint` et surcharger `validatedBy()` (et éventuellement `runOnEmpty()`).

---

## ValidationContext

Le `ValidationContext` est l'objet passé à chaque validator lors de la validation. Il donne accès au champ courant, au modèle global et au formulaire optionnel.

```php
final class ValidationContext
{
    public function getField(): string;
    public function getModel(): object;
    public function getForm(): ?Form;

    public function addViolation(string $message): void;
    public function getViolations(): list<string>;
    public function hasViolations(): bool;

    public function fieldExists(string $field): bool;
    public function getValue(string $field): mixed;
}
```

| Méthode | Description |
|---------|-------------|
| `getField()` | Nom de la propriété en cours de validation |
| `getModel()` | Objet complet (utile pour les validators cross-champs) |
| `getForm()` | Formulaire lié, si présent |
| `addViolation(string $message)` | Ajoute une erreur pour ce champ |
| `fieldExists(string $field)` | Vérifie si un champ existe sur le modèle ou le formulaire |
| `getValue(string $field)` | Lit la valeur d'un autre champ (utile pour `EqualToField`) |

---

## Contraintes disponibles

### NotBlank

Vérifie que la valeur n'est pas vide. C'est la seule contrainte qui s'applique même quand la valeur est `null` ou `''` (`runOnEmpty()` retourne `true`). Si `NotBlank` échoue, les autres contraintes du même champ ne sont pas évaluées.

```php
use Neo\Core\Validator\Assert\NotBlank;

class Utilisateur
{
    #[NotBlank(message: 'Le nom est obligatoire.')]
    public string $nom;
}
```

**Règles de validation :**
- `array` vide → invalide
- `string` vide ou composé uniquement d'espaces → invalide
- `null` → invalide
- Toute autre valeur non nulle → valide

---

### Length

Vérifie la longueur d'une chaîne de caractères (`mb_strlen`). Accepte `min`, `max`, ou `exactly`. Les placeholders `{%min%}` et `{%max%}` sont remplacés dans le message d'erreur.

```php
use Neo\Core\Validator\Assert\Length;

class Article
{
    #[Length(min: 3, max: 255, message: 'Le titre doit faire entre {%min%} et {%max%} caractères.')]
    public string $titre;

    #[Length(exactly: 6, message: 'Le code postal doit faire exactement {%min%} caractères.')]
    public string $codePostal;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `min` | `?int` | Longueur minimale |
| `max` | `?int` | Longueur maximale |
| `exactly` | `?int` | Longueur exacte (positionne `min` et `max` à la même valeur) |

---

### Email

Vérifie le format d'une adresse e-mail via `FILTER_VALIDATE_EMAIL` combiné à une regex stricte (`utilisateur@domaine.tld`).

```php
use Neo\Core\Validator\Assert\Email;
use Neo\Core\Validator\Assert\NotBlank;

class Contact
{
    #[NotBlank(message: "L'e-mail est obligatoire.")]
    #[Email(message: "Format d'e-mail invalide.")]
    public string $email;
}
```

---

### Regex

Valide la valeur contre une expression régulière.

```php
use Neo\Core\Validator\Assert\Regex;

class Produit
{
    #[Regex(pattern: '/^[A-Z]{2}-\d{4}$/', message: 'Format de référence invalide (ex: AB-1234).')]
    public string $reference;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `pattern` | `string` | Expression régulière complète (délimiteurs inclus) |

---

### Choice

Vérifie que la valeur fait partie d'un tableau de choix autorisés. Supporte les tableaux indexés et associatifs.

```php
use Neo\Core\Validator\Assert\Choice;

class Commande
{
    #[Choice(
        choices: ['en_attente', 'expediee', 'livree', 'annulee'],
        message: 'Statut invalide.'
    )]
    public string $statut;
}
```

---

### Range

Vérifie qu'une valeur numérique est comprise dans un intervalle.

```php
use Neo\Core\Validator\Assert\Range;

class Produit
{
    #[Range(min: 0.01, max: 99999.99, message: 'Le prix doit être entre 0.01 et 99999.99 €.')]
    public float $prix;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `min` | `?float` | Valeur minimale incluse |
| `max` | `?float` | Valeur maximale incluse |

---

### Date

Vérifie qu'une valeur est une date valide selon le format spécifié, avec des bornes optionnelles.

```php
use Neo\Core\Validator\Assert\Date;

class Evenement
{
    #[Date(format: 'Y-m-d', message: 'Format de date invalide (AAAA-MM-JJ).')]
    public string $dateDebut;

    #[Date(format: 'Y-m-d', min: 'now', message: 'La date doit être dans le futur.')]
    public ?string $dateExpiration = null;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `format` | `string` | Format PHP de date (`'Y-m-d'` par défaut) |
| `min` | `string\|DateTimeInterface\|null` | Borne inférieure (valeur relative acceptée : `'+1 day'`, `'now'`) |
| `max` | `string\|DateTimeInterface\|null` | Borne supérieure |

---

### Url

Vérifie que la valeur est une URL valide via `FILTER_VALIDATE_URL`.

```php
use Neo\Core\Validator\Assert\Url;

class Profil
{
    #[Url(message: "L'URL du site est invalide.")]
    public ?string $siteWeb = null;
}
```

---

### Unique

Vérifie l'unicité d'une valeur directement en base de données via une requête `SELECT COUNT(*)`. Nécessite une connexion PDO active (`DatabaseConnection`).

```php
use Neo\Core\Validator\Assert\Unique;

class Utilisateur
{
    public string $table = 'users';

    #[NotBlank]
    #[Email]
    #[Unique(message: 'Cet e-mail est déjà utilisé.')]
    public string $email;

    // Exclure l'ID en cours (pour les mises à jour)
    #[Unique(message: 'Cet e-mail est déjà utilisé.', excludedId: 42)]
    public string $emailEdition;
}
```

**Résolution automatique :**
- Si `table` n'est pas précisé, la contrainte lit la propriété `$table` de l'objet.
- Si `column` n'est pas précisé, le nom de la propriété annotée est utilisé.
- Si `excludedId` n'est pas précisé et que l'objet a une propriété `$id` non nulle, elle est utilisée automatiquement.

| Paramètre | Type | Description |
|---|---|---|
| `table` | `?string` | Nom de la table (auto-détecté si `null`) |
| `column` | `?string` | Nom de la colonne (auto-détecté si `null`) |
| `conditions` | `array` | Conditions supplémentaires `['col' => 'valeur']` |
| `excludedId` | `?int` | ID à exclure de la vérification d'unicité |

---

### Exists

Vérifie qu'une valeur **existe** en base de données (contrairement à `Unique` qui vérifie l'absence). Utile pour valider qu'une clé étrangère référence bien un enregistrement existant.

```php
use Neo\Core\Validator\Assert\Exists;

class Commande
{
    #[Exists(table: 'users', column: 'id', message: 'Cet utilisateur n\'existe pas.')]
    public int $userId;

    #[Exists(table: 'products', column: 'id', message: 'Ce produit n\'existe pas.')]
    public int $productId;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `table` | `string` | Nom de la table à interroger |
| `column` | `string` | Nom de la colonne à vérifier |
| `conditions` | `array` | Conditions supplémentaires `['col' => 'valeur']` |

---

### EqualToField

Vérifie que la valeur de la propriété est identique à celle d'un autre champ du même objet. Utilisée typiquement pour la confirmation de mot de passe. S'applique même si la valeur est vide (`runOnEmpty()` retourne `true`).

```php
use Neo\Core\Validator\Assert\EqualToField;

class FormulaireInscription
{
    #[NotBlank]
    #[Length(min: 8)]
    public string $motDePasse;

    #[NotBlank]
    #[EqualToField(field: 'motDePasse', message: 'Les mots de passe ne correspondent pas.')]
    public string $confirmation;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `field` | `string` | Nom de la propriété à comparer |

---

## Intégration avec les formulaires (Form)

Le `ValidatorManager` peut recevoir une instance `Form` pour combiner les contraintes déclarées sur les attributs avec des contraintes ajoutées dynamiquement au formulaire.

```php
$form = new Form();
$form->addConstraint('email', new NotBlank(message: 'E-mail requis.'));
$form->addConstraint('email', new Email(message: 'E-mail invalide.'));

$errors = $validator->validate($monObjet, $form);
```

Les champs présents dans le formulaire mais absents de la classe sont également validés.

---

## Créer une contrainte personnalisée

Depuis la refactorisation, une contrainte personnalisée se compose de **deux fichiers** :

**1. L'attribut** dans `Assert/` — porte les paramètres et pointe vers le validator :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MonProjet\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Src\MonProjet\Validator\Validator\MinAgeValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MinAge extends AbstractConstraint
{
    public function __construct(
        public int $minAge = 18,
        string $message = 'Vous devez avoir au moins :age ans.',
    ) {
        parent::__construct(str_replace(':age', (string) $minAge, $message));
    }

    public function validatedBy(): string
    {
        return MinAgeValidator::class;
    }
}
```

**2. Le validator** dans `Validator/` — contient toute la logique :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MonProjet\Validator\Validator;

use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class MinAgeValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if ($value === null || $value === '') {
            return; // Laisse NotBlank gérer les valeurs vides
        }

        /** @var \Neo\Src\MonProjet\Validator\Assert\MinAge $constraint */
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        if (!$date) {
            $context->addViolation('Date invalide.');
            return;
        }

        $age = $date->diff(new \DateTimeImmutable())->y;
        if ($age < $constraint->minAge) {
            $context->addViolation($constraint->getMessage());
        }
    }
}
```

Usage :

```php
use Neo\Src\MonProjet\Validator\Assert\MinAge;

class Membre
{
    #[MinAge(minAge: 18)]
    public ?string $dateNaissance = null;
}
```

Le validator est résolu par le conteneur DI : il peut donc recevoir des dépendances (par ex. `DatabaseManager`) dans son constructeur.

---

## Exemple complet

```php
use Neo\Core\Validator\Assert\NotBlank;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\Email;
use Neo\Core\Validator\Assert\Unique;
use Neo\Core\Validator\Assert\EqualToField;
use Neo\Core\Validator\Assert\Regex;
use Neo\Core\Validator\ValidatorManager;

class FormulaireInscription
{
    public string $table = 'users';

    #[NotBlank(message: 'Le prénom est obligatoire.')]
    #[Length(min: 2, max: 50, message: 'Le prénom doit faire entre {%min%} et {%max%} caractères.')]
    public string $prenom;

    #[NotBlank(message: 'Le nom est obligatoire.')]
    #[Length(min: 2, max: 50, message: 'Le nom doit faire entre {%min%} et {%max%} caractères.')]
    public string $nom;

    #[NotBlank(message: "L'e-mail est obligatoire.")]
    #[Email(message: "Format d'e-mail invalide.")]
    #[Unique(message: 'Cet e-mail est déjà utilisé.')]
    public string $email;

    #[Regex(pattern: '/^\d{10}$/', message: 'Numéro de téléphone invalide (10 chiffres).')]
    public ?string $telephone = null;

    #[NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Length(min: 8, message: 'Minimum {%min%} caractères.')]
    public string $motDePasse;

    #[NotBlank(message: 'La confirmation est obligatoire.')]
    #[EqualToField(field: 'motDePasse', message: 'Les mots de passe ne correspondent pas.')]
    public string $confirmation;
}

// Utilisation dans un contrôleur
$formulaire = new FormulaireInscription();
$formulaire->prenom = $request->body('prenom');
$formulaire->nom = $request->body('nom');
$formulaire->email = $request->body('email');
$formulaire->telephone = $request->body('telephone');
$formulaire->motDePasse = $request->body('mot_de_passe');
$formulaire->confirmation = $request->body('confirmation');

$validator = $container->get(ValidatorManager::class);
$errors = $validator->validate($formulaire);

if (!empty($errors)) {
    return $this->json(['errors' => $errors], 422);
}

// Traitement de l'inscription...
```
