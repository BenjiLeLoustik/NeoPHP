# Module Validator

Le module Validator fournit un système de validation basé sur les **attributs PHP 8** (`#[Attribute]`). Les contraintes se posent directement sur les propriétés des modèles ou objets. Le `ValidatorManager` inspecte ces attributs par réflexion et retourne un tableau d'erreurs par champ.

---

## Sommaire

1. [Structure du module](#structure-du-module)
2. [Fonctionnement général](#fonctionnement-général)
3. [ValidatorManager](#validatormanager)
4. [AbstractConstraint](#abstractconstraint)
5. [Contraintes disponibles](#contraintes-disponibles)
   - [NotBlank](#notblank)
   - [Length](#length)
   - [Email](#email)
   - [Regex](#regex)
   - [Choice](#choice)
   - [Range](#range)
   - [Date](#date)
   - [Url](#url)
   - [Unique](#unique)
   - [EqualToField](#equaltofield)
6. [Intégration avec les formulaires (Form)](#intégration-avec-les-formulaires-form)
7. [Créer une contrainte personnalisée](#créer-une-contrainte-personnalisée)
8. [Exemple complet](#exemple-complet)

---

## Structure du module

```
Validator/
├── ValidatorManager.php            # Orchestrateur de la validation
├── ValidatorModule.php             # Enregistrement dans le conteneur DI
├── Abstract/
│   └── AbstractConstraint.php      # Classe abstraite de base pour toutes les contraintes
└── Assert/
    ├── NotBlank.php                # Champ requis (non vide)
    ├── Length.php                  # Longueur de chaîne (min, max, exactly)
    ├── Email.php                   # Format e-mail valide
    ├── Regex.php                   # Expression régulière
    ├── Choice.php                  # Valeur parmi une liste autorisée
    ├── Range.php                   # Valeur numérique dans une plage
    ├── Date.php                    # Date valide (format et bornes optionnelles)
    ├── Url.php                     # URL valide
    ├── Unique.php                  # Unicité en base de données
    └── EqualToField.php            # Égalité avec un autre champ
```

---

## Fonctionnement général

1. Les contraintes sont déclarées comme **attributs PHP 8** sur les propriétés d'une classe.
2. `ValidatorManager::validate()` inspecte la classe par réflexion et parcourt toutes les propriétés.
3. Chaque contrainte est instanciée et sa méthode `validate()` est appelée avec la valeur de la propriété.
4. Le résultat est un tableau `['nomChamp' => ['message erreur 1', ...]]`.
5. Si le tableau est vide, la validation est réussie.

**Comportement clé : les contraintes autres que `NotBlank` et `EqualToField` sont ignorées si la valeur est `null` ou `''`.** Cela permet de combiner `NotBlank` avec d'autres contraintes pour rendre un champ obligatoire.

---

## ValidatorManager

```php
use Neo\Core\Validator\ValidatorManager;

$validator = new ValidatorManager();
// ou via le conteneur :
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
public function validate(object $model, ?Form $form = null): array<string, array<int, string>>
```

| Paramètre | Description |
|---|---|
| `$model` | L'objet à valider (instancié avec les valeurs à contrôler) |
| `$form` | Instance `Form` optionnelle pour les contraintes ajoutées dynamiquement |

---

## AbstractConstraint

Toutes les contraintes étendent `Neo\Core\Validator\Abstract\AbstractConstraint`.

```php
abstract class AbstractConstraint
{
    public string $message;

    public function __construct(string $message = '') { ... }

    public function setPropertyName(string $name): void { ... }

    abstract public function validate(mixed $value, ?object $object = null): bool;
}
```

Le `ValidatorManager` appelle `setPropertyName()` avant `validate()` pour permettre aux contraintes d'accéder au nom de la propriété en cours de validation (utile notamment pour `Unique`).

---

## Contraintes disponibles

### NotBlank

Vérifie que la valeur n'est pas vide. C'est la seule contrainte qui s'applique même quand la valeur est `null` ou `''`. Si `NotBlank` échoue, les autres contraintes du même champ ne sont pas évaluées.

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

    #[Length(min: 10, message: 'La description doit faire au moins {%min%} caractères.')]
    public ?string $description = null;
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

La contrainte passe si la valeur est `null` ou `''` (utiliser `NotBlank` pour rendre le champ obligatoire).

---

### Regex

Valide la valeur contre une expression régulière.

```php
use Neo\Core\Validator\Assert\Regex;

class Produit
{
    #[Regex(pattern: '/^[A-Z]{2}-\d{4}$/', message: 'Format de référence invalide (ex: AB-1234).')]
    public string $reference;

    #[Regex(pattern: '/^\d{10}$/', message: 'Le numéro de téléphone doit contenir 10 chiffres.')]
    public ?string $telephone = null;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `pattern` | `string` | Expression régulière complète (délimiteurs inclus) |

---

### Choice

Vérifie que la valeur fait partie d'un tableau de choix autorisés. Supporte aussi bien les tableaux indexés que les tableaux associatifs (la valeur peut être une clé ou une valeur du tableau).

```php
use Neo\Core\Validator\Assert\Choice;

class Commande
{
    #[Choice(
        choices: ['en_attente', 'expediee', 'livree', 'annulee'],
        message: 'Statut invalide.'
    )]
    public string $statut;

    #[Choice(
        choices: ['fr' => 'Français', 'en' => 'English', 'es' => 'Español'],
        message: 'Langue non supportée.'
    )]
    public string $langue;
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

    #[Range(min: 1, message: 'La quantité doit être au moins 1.')]
    public int $quantite;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `min` | `?float` | Valeur minimale incluse |
| `max` | `?float` | Valeur maximale incluse |

Retourne `false` si la valeur n'est pas numérique.

---

### Date

Vérifie qu'une valeur est une date valide selon le format spécifié, avec des bornes optionnelles.

```php
use Neo\Core\Validator\Assert\Date;

class Evenement
{
    #[Date(format: 'Y-m-d', message: 'Format de date invalide (AAAA-MM-JJ).')]
    public string $dateDebut;

    #[Date(
        format: 'Y-m-d',
        min: '2020-01-01',
        max: '2030-12-31',
        message: 'La date doit être entre 2020 et 2030.'
    )]
    public ?string $dateFin = null;

    // Relative : la date doit être dans le futur
    #[Date(format: 'Y-m-d', min: 'now', message: 'La date doit être dans le futur.')]
    public ?string $dateExpiration = null;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `format` | `string` | Format PHP de date (`'Y-m-d'` par défaut) |
| `min` | `string\|DateTimeInterface\|null` | Borne inférieure (valeur relative acceptée : `'+1 day'`, `'now'`, `'-1 year'`) |
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

    #[Url(message: "L'URL de l'avatar est invalide.")]
    public ?string $avatar = null;
}
```

---

### Unique

Vérifie l'unicité d'une valeur directement en base de données via une requête `SELECT COUNT(*)`. Nécessite une connexion PDO active (`DatabaseConnection`).

```php
use Neo\Core\Validator\Assert\Unique;
use Neo\Core\Validator\Assert\NotBlank;
use Neo\Core\Validator\Assert\Email;

class Utilisateur
{
    public string $table = 'users';

    #[NotBlank(message: "L'e-mail est obligatoire.")]
    #[Email(message: "Format d'e-mail invalide.")]
    #[Unique(message: 'Cet e-mail est déjà utilisé.')]
    public string $email;

    // Avec table et colonne explicites
    #[Unique(
        message: 'Ce pseudo est déjà pris.',
        table: 'users',
        column: 'username'
    )]
    public string $pseudo;

    // Exclure l'ID en cours (pour les mises à jour)
    #[Unique(
        message: 'Cet e-mail est déjà utilisé.',
        excludedId: 42  // ID à ignorer dans la vérification
    )]
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

### EqualToField

Vérifie que la valeur de la propriété est identique à celle d'un autre champ du même objet. Utilisée typiquement pour la confirmation de mot de passe.

```php
use Neo\Core\Validator\Assert\NotBlank;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\EqualToField;

class FormulaireInscription
{
    #[NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Length(min: 8, message: 'Le mot de passe doit faire au moins {%min%} caractères.')]
    public string $motDePasse;

    #[NotBlank(message: 'La confirmation est obligatoire.')]
    #[EqualToField(field: 'motDePasse', message: 'Les mots de passe ne correspondent pas.')]
    public string $confirmation;
}
```

| Paramètre | Type | Description |
|---|---|---|
| `field` | `string` | Nom de la propriété à comparer |

Contrairement aux autres contraintes, `EqualToField` s'applique même si la valeur est vide (pour garantir que la confirmation est bien présente).

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

Pour ajouter une contrainte spécifique au projet, il suffit d'étendre `AbstractConstraint` :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MonProjet\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MinAge extends AbstractConstraint
{
    public function __construct(
        public int $minAge = 18,
        string $message = 'Vous devez avoir au moins :age ans.'
    ) {
        parent::__construct(str_replace(':age', (string) $minAge, $message));
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        if (!$date) {
            return false;
        }

        $age = $date->diff(new \DateTimeImmutable())->y;
        return $age >= $this->minAge;
    }
}
```

Usage :

```php
use Neo\Src\MonProjet\Validator\Assert\MinAge;

class Membre
{
    #[MinAge(minAge: 18, message: 'Vous devez avoir au moins :age ans.')]
    public ?string $dateNaissance = null;
}
```

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
