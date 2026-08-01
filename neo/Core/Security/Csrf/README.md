# CSRF

Le sous-module `Csrf` protège les formulaires et les requêtes HTTP contre les attaques Cross-Site Request Forgery via des tokens de session.

---

## Sommaire

1. [Structure](#structure)
2. [CsrfManager](#csrfmanager)
3. [CsrfTokenManager](#csrftokenmanager)
4. [CsrfToken](#csrftoken)
5. [Extension Twig](#extension-twig)
6. [CsrfMiddleware](#csrfmiddleware)

---

## Structure

```
Csrf/
├── CsrfManager.php                     # Token unique par session
├── CsrfTokenManager.php                # Tokens nommés avec expiration
├── CsrfModule.php                      # Enregistrement DI
├── Exception/
│   └── CsrfException.php
├── Extension/
│   └── CsrfViewExtension.php           # Fonction Twig csrf_token()
└── Token/
    └── CsrfToken.php                   # Value object token
```

---

## CsrfManager

**Fichier :** `CsrfManager.php`

Gère un **token unique par session**, stocké sous la clé `_csrf_token`. C'est le composant utilisé par `CsrfMiddleware`.

```php
$csrf = $container->get(CsrfManager::class);

// Générer ou récupérer le token de la session courante
$token = $csrf->generate();

// Lire le token sans le créer
$token = $csrf->token();

// Valider le token envoyé dans la requête
$isValid = $csrf->validate();

// Forcer la régénération du token
$csrf->refresh();
```

**Sources du token dans la requête (dans l'ordre) :**

1. `body('_csrf_token')` — champ caché dans un formulaire HTML.
2. `header('X-CSRF-Token')` — header HTTP (pour les requêtes AJAX).

**Comparaison sécurisée :** `hash_equals()` est utilisé pour prévenir les attaques temporelles.

**Exemple dans un contrôleur :**

```php
#[Route('/profile/edit', 'POST')]
public function edit(): Response
{
    // Le CsrfMiddleware valide automatiquement si configuré.
    // Sinon, validation manuelle :
    if (!$this->csrfManager->validate()) {
        throw new \RuntimeException('Token CSRF invalide.');
    }
    // ...
}
```

---

## CsrfTokenManager

**Fichier :** `CsrfTokenManager.php`

Alternative avancée au `CsrfManager`. Gère des **tokens nommés avec expiration individuelle**, un par formulaire, en parallèle.

```php
$manager = $container->get(CsrfTokenManager::class);

// Générer un token pour un formulaire spécifique (expire dans 3600s)
$token = $manager->generateToken('contact_form', expiry: 3600);
$tokenValue = $token->getValue(); // Chaîne hex de 64 caractères

// Récupérer un token existant
$token = $manager->getToken('contact_form'); // CsrfToken|null

// Valider et consommer le token (invalidate: true = suppression après validation)
$isValid = $manager->validateToken('contact_form', $submittedValue, invalidate: true);
```

**Stockage :** `$_SESSION['_csrf_tokens']['<id>']`

**Token expiré :** si expiré lors de la validation, il est supprimé de la session et la méthode retourne `false`.

**Comportement CLI :** en contexte CLI (`PHP_SAPI === 'cli'`), toutes les opérations sont des no-ops silencieux.

---

## CsrfToken

**Fichier :** `Token/CsrfToken.php`

Value object représentant un token nommé.

```php
$token->getId();       // 'contact_form'
$token->getValue();    // Chaîne hexadécimale de 64 caractères (32 octets)
$token->getExpiry();   // Timestamp Unix d'expiration
$token->isExpired();   // true si time() > expiry
```

---

## Extension Twig

**Fichier :** `Extension/CsrfViewExtension.php`

Expose la fonction `csrf_token()` dans tous les templates Twig. Si le token n'existe pas encore en session, il est créé automatiquement.

```twig
{# Token par défaut (identifiant 'default') #}
<form method="POST" action="{{ path('profile.edit') }}">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
    {# ... champs du formulaire ... #}
    <button type="submit">Enregistrer</button>
</form>

{# Token nommé pour un formulaire spécifique #}
<input type="hidden" name="_csrf_token" value="{{ csrf_token('contact_form') }}">
```

---

## CsrfMiddleware

**Fichier :** `../Middleware/Default/CsrfMiddleware.php`

Valide automatiquement le token CSRF pour toutes les requêtes non-sûres. Les méthodes `GET`, `HEAD` et `OPTIONS` sont ignorées.

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use Neo\Core\Security\Middleware\Default\CsrfMiddleware;

// Sur un contrôleur entier
#[Middleware(use: CsrfMiddleware::class, message: 'Token CSRF manquant ou invalide.')]
class MonController extends AbstractController { ... }

// Sur une méthode spécifique
#[Middleware(use: CsrfMiddleware::class)]
#[Route('/settings', 'POST')]
public function update(): Response { ... }
```

Voir [Middleware/README.md](../Middleware/README.md) pour la configuration complète du pipeline.
