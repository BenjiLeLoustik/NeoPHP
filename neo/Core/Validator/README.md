# Validator

The Validator module provides a validation system based on **PHP 8 attributes** (`#[Attribute]`). Constraints are placed directly on the properties of models or objects. The `ValidatorManager` inspects these attributes via reflection and returns an array of errors per field.

Since the refactoring, each constraint is **split into two files**: a PHP attribute (in `Assert/`) and a dedicated validator (in `Validator/`).

---

## Table of Contents

1. [Module Structure](#module-structure)
2. [Interfaces](#interfaces)
3. [General Behavior](#general-behavior)
4. [ValidatorManager](#validatormanager)
5. [AbstractConstraint](#abstractconstraint)
6. [ValidationContext](#validationcontext)
7. [Available Constraints](#available-constraints)
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
8. [Integration with Forms (Form)](#integration-with-forms-form)
9. [Creating a Custom Constraint](#creating-a-custom-constraint)
10. [Full Example](#full-example)

---

## Module Structure

```
Validator/
├── ValidatorManager.php                # Validation orchestrator
├── ValidatorModule.php                 # Registration in the DI container
├── ValidationContext.php               # Context passed to each validator
├── Abstract/
│   └── AbstractConstraint.php         # Base abstract class for all constraints
├── Interface/
│   ├── ConstraintInterface.php        # Contract for a constraint
│   └── ConstraintValidatorInterface.php # Contract for a validator
├── Assert/                            # PHP attributes (declared on properties)
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
└── Validator/                         # Validation logic (one file per constraint)
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

**Split principle:** The attribute (in `Assert/`) carries only the configuration parameters and declares the validator via `validatedBy()`. The validator (in `Validator/`) contains all the validation logic and receives the value, the constraint, and the `ValidationContext`.

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

| Method | Description |
|---------|-------------|
| `getMessage()` | Returns the error message |
| `validatedBy()` | Returns the FQCN of the associated validator |
| `runOnEmpty()` | `true` if the constraint should apply even when the value is `null` or `''` |

### ConstraintValidatorInterface

```php
namespace Neo\Core\Validator\Interface;

interface ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void;
}
```

The validator returns nothing: it calls `$context->addViolation(string $message)` to report an error.

---

## General Behavior

1. Constraints are declared as **PHP 8 attributes** on a class's properties.
2. `ValidatorManager::validate()` inspects the class via reflection and iterates over all properties.
3. For each constraint found, the `ValidatorManager` resolves the validator via the DI container (`validatedBy()`).
4. The validator receives the value, the constraint, and a `ValidationContext`. It calls `$context->addViolation()` if the value is invalid.
5. The result is an array `['fieldName' => ['error message 1', ...]]`.
6. If the array is empty, validation succeeded.

**Key behavior: constraints other than `NotBlank` and `EqualToField` are skipped if the value is `null` or `''` and `runOnEmpty()` returns `false`.** This allows combining `NotBlank` with other constraints to make a field mandatory.

---

## ValidatorManager

```php
use Neo\Core\Validator\ValidatorManager;

// Via the container (recommended):
$validator = $container->get(ValidatorManager::class);

$errors = $validator->validate($myObject);

if (!empty($errors)) {
    foreach ($errors as $field => $messages) {
        foreach ($messages as $message) {
            echo "$field: $message\n";
        }
    }
}
```

**Signature:**

```php
public function validate(object $model, ?Form $form = null): array<string, list<string>>
```

| Parameter | Description |
|---|---|
| `$model` | The object to validate (instantiated with the values to check) |
| `$form` | Optional `Form` instance for dynamically added constraints |

`ValidatorManager` is injected with a `Container` that allows it to resolve validators on demand. Validator instances are cached for the duration of the request.

---

## AbstractConstraint

All constraints extend `Neo\Core\Validator\Abstract\AbstractConstraint`.

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

To create a custom constraint, extend `AbstractConstraint` and override `validatedBy()` (and optionally `runOnEmpty()`).

---

## ValidationContext

`ValidationContext` is the object passed to each validator during validation. It gives access to the current field, the global model, and the optional form.

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

| Method | Description |
|---------|-------------|
| `getField()` | Name of the property currently being validated |
| `getModel()` | Full object (useful for cross-field validators) |
| `getForm()` | Linked form, if present |
| `addViolation(string $message)` | Adds an error for this field |
| `fieldExists(string $field)` | Checks whether a field exists on the model or the form |
| `getValue(string $field)` | Reads the value of another field (useful for `EqualToField`) |

---

## Available Constraints

### NotBlank

Checks that the value is not empty. This is the only constraint that applies even when the value is `null` or `''` (`runOnEmpty()` returns `true`). If `NotBlank` fails, the other constraints on the same field are not evaluated.

```php
use Neo\Core\Validator\Assert\NotBlank;

class User
{
    #[NotBlank(message: 'Name is required.')]
    public string $name;
}
```

**Validation Rules:**
- Empty `array` → invalid
- Empty `string` or one made up only of whitespace → invalid
- `null` → invalid
- Any other non-null value → valid

---

### Length

Checks the length of a string (`mb_strlen`). Accepts `min`, `max`, or `exactly`. The `{%min%}` and `{%max%}` placeholders are replaced in the error message.

```php
use Neo\Core\Validator\Assert\Length;

class Article
{
    #[Length(min: 3, max: 255, message: 'Title must be between {%min%} and {%max%} characters.')]
    public string $title;

    #[Length(exactly: 6, message: 'Postal code must be exactly {%min%} characters.')]
    public string $postalCode;
}
```

| Parameter | Type | Description |
|---|---|---|
| `min` | `?int` | Minimum length |
| `max` | `?int` | Maximum length |
| `exactly` | `?int` | Exact length (sets `min` and `max` to the same value) |

---

### Email

Checks the format of an email address via `FILTER_VALIDATE_EMAIL` combined with a strict regex (`user@domain.tld`).

```php
use Neo\Core\Validator\Assert\Email;
use Neo\Core\Validator\Assert\NotBlank;

class Contact
{
    #[NotBlank(message: 'Email is required.')]
    #[Email(message: 'Invalid email format.')]
    public string $email;
}
```

---

### Regex

Validates the value against a regular expression.

```php
use Neo\Core\Validator\Assert\Regex;

class Product
{
    #[Regex(pattern: '/^[A-Z]{2}-\d{4}$/', message: 'Invalid reference format (e.g. AB-1234).')]
    public string $reference;
}
```

| Parameter | Type | Description |
|---|---|---|
| `pattern` | `string` | Full regular expression (including delimiters) |

---

### Choice

Checks that the value is part of an array of allowed choices. Supports both indexed and associative arrays.

```php
use Neo\Core\Validator\Assert\Choice;

class Order
{
    #[Choice(
        choices: ['pending', 'shipped', 'delivered', 'cancelled'],
        message: 'Invalid status.'
    )]
    public string $status;
}
```

---

### Range

Checks that a numeric value falls within a range.

```php
use Neo\Core\Validator\Assert\Range;

class Product
{
    #[Range(min: 0.01, max: 99999.99, message: 'Price must be between 0.01 and 99999.99 €.')]
    public float $price;
}
```

| Parameter | Type | Description |
|---|---|---|
| `min` | `?float` | Minimum value (inclusive) |
| `max` | `?float` | Maximum value (inclusive) |

---

### Date

Checks that a value is a valid date according to the specified format, with optional bounds.

```php
use Neo\Core\Validator\Assert\Date;

class Event
{
    #[Date(format: 'Y-m-d', message: 'Invalid date format (YYYY-MM-DD).')]
    public string $startDate;

    #[Date(format: 'Y-m-d', min: 'now', message: 'The date must be in the future.')]
    public ?string $expirationDate = null;
}
```

| Parameter | Type | Description |
|---|---|---|
| `format` | `string` | PHP date format (`'Y-m-d'` by default) |
| `min` | `string\|DateTimeInterface\|null` | Lower bound (relative value accepted: `'+1 day'`, `'now'`) |
| `max` | `string\|DateTimeInterface\|null` | Upper bound |

---

### Url

Checks that the value is a valid URL via `FILTER_VALIDATE_URL`.

```php
use Neo\Core\Validator\Assert\Url;

class Profile
{
    #[Url(message: 'The website URL is invalid.')]
    public ?string $website = null;
}
```

---

### Unique

Checks the uniqueness of a value directly in the database via a `SELECT COUNT(*)` query. Requires an active PDO connection (`DatabaseConnection`).

```php
use Neo\Core\Validator\Assert\Unique;

class User
{
    public string $table = 'users';

    #[NotBlank]
    #[Email]
    #[Unique(message: 'This email is already in use.')]
    public string $email;

    // Exclude the current ID (for updates)
    #[Unique(message: 'This email is already in use.', excludedId: 42)]
    public string $emailEdit;
}
```

**Automatic Resolution:**
- If `table` is not specified, the constraint reads the object's `$table` property.
- If `column` is not specified, the annotated property's name is used.
- If `excludedId` is not specified and the object has a non-null `$id` property, it is used automatically.

| Parameter | Type | Description |
|---|---|---|
| `table` | `?string` | Table name (auto-detected if `null`) |
| `column` | `?string` | Column name (auto-detected if `null`) |
| `conditions` | `array` | Additional conditions `['col' => 'value']` |
| `excludedId` | `?int` | ID to exclude from the uniqueness check |

---

### Exists

Checks that a value **exists** in the database (unlike `Unique`, which checks for absence). Useful for validating that a foreign key correctly references an existing record.

```php
use Neo\Core\Validator\Assert\Exists;

class Order
{
    #[Exists(table: 'users', column: 'id', message: 'This user does not exist.')]
    public int $userId;

    #[Exists(table: 'products', column: 'id', message: 'This product does not exist.')]
    public int $productId;
}
```

| Parameter | Type | Description |
|---|---|---|
| `table` | `string` | Name of the table to query |
| `column` | `string` | Name of the column to check |
| `conditions` | `array` | Additional conditions `['col' => 'value']` |

---

### EqualToField

Checks that the property's value is identical to that of another field on the same object. Typically used for password confirmation. Applies even if the value is empty (`runOnEmpty()` returns `true`).

```php
use Neo\Core\Validator\Assert\EqualToField;

class RegistrationForm
{
    #[NotBlank]
    #[Length(min: 8)]
    public string $password;

    #[NotBlank]
    #[EqualToField(field: 'password', message: 'Passwords do not match.')]
    public string $confirmation;
}
```

| Parameter | Type | Description |
|---|---|---|
| `field` | `string` | Name of the property to compare against |

---

## Integration with Forms (Form)

`ValidatorManager` can receive a `Form` instance to combine the constraints declared on attributes with constraints dynamically added to the form.

```php
$form = new Form();
$form->addConstraint('email', new NotBlank(message: 'Email is required.'));
$form->addConstraint('email', new Email(message: 'Invalid email.'));

$errors = $validator->validate($myObject, $form);
```

Fields present in the form but absent from the class are also validated.

---

## Creating a Custom Constraint

Since the refactoring, a custom constraint consists of **two files**:

**1. The attribute** in `Assert/` — carries the parameters and points to the validator:

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MyProject\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Src\MyProject\Validator\Validator\MinAgeValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MinAge extends AbstractConstraint
{
    public function __construct(
        public int $minAge = 18,
        string $message = 'You must be at least :age years old.',
    ) {
        parent::__construct(str_replace(':age', (string) $minAge, $message));
    }

    public function validatedBy(): string
    {
        return MinAgeValidator::class;
    }
}
```

**2. The validator** in `Validator/` — contains all the logic:

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MyProject\Validator\Validator;

use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class MinAgeValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if ($value === null || $value === '') {
            return; // Let NotBlank handle empty values
        }

        /** @var \Neo\Src\MyProject\Validator\Assert\MinAge $constraint */
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        if (!$date) {
            $context->addViolation('Invalid date.');
            return;
        }

        $age = $date->diff(new \DateTimeImmutable())->y;
        if ($age < $constraint->minAge) {
            $context->addViolation($constraint->getMessage());
        }
    }
}
```

Usage:

```php
use Neo\Src\MyProject\Validator\Assert\MinAge;

class Member
{
    #[MinAge(minAge: 18)]
    public ?string $birthDate = null;
}
```

The validator is resolved by the DI container: it can therefore receive dependencies (e.g. `DatabaseManager`) in its constructor.

---

## Full Example

```php
use Neo\Core\Validator\Assert\NotBlank;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\Email;
use Neo\Core\Validator\Assert\Unique;
use Neo\Core\Validator\Assert\EqualToField;
use Neo\Core\Validator\Assert\Regex;
use Neo\Core\Validator\ValidatorManager;

class RegistrationForm
{
    public string $table = 'users';

    #[NotBlank(message: 'First name is required.')]
    #[Length(min: 2, max: 50, message: 'First name must be between {%min%} and {%max%} characters.')]
    public string $firstName;

    #[NotBlank(message: 'Last name is required.')]
    #[Length(min: 2, max: 50, message: 'Last name must be between {%min%} and {%max%} characters.')]
    public string $lastName;

    #[NotBlank(message: 'Email is required.')]
    #[Email(message: 'Invalid email format.')]
    #[Unique(message: 'This email is already in use.')]
    public string $email;

    #[Regex(pattern: '/^\d{10}$/', message: 'Invalid phone number (10 digits).')]
    public ?string $phone = null;

    #[NotBlank(message: 'Password is required.')]
    #[Length(min: 8, message: 'Minimum {%min%} characters.')]
    public string $password;

    #[NotBlank(message: 'Confirmation is required.')]
    #[EqualToField(field: 'password', message: 'Passwords do not match.')]
    public string $confirmation;
}

// Usage in a controller
$form = new RegistrationForm();
$form->firstName = $request->body('first_name');
$form->lastName = $request->body('last_name');
$form->email = $request->body('email');
$form->phone = $request->body('phone');
$form->password = $request->body('password');
$form->confirmation = $request->body('confirmation');

$validator = $container->get(ValidatorManager::class);
$errors = $validator->validate($form);

if (!empty($errors)) {
    return $this->json(['errors' => $errors], 422);
}

// Registration processing...
```