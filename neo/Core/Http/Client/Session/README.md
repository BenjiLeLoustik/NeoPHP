# Session

`Neo\Core\Http\Client\Session\Session` encapsule la session PHP native avec une configuration centralisée et un comportement no-op silencieux en CLI.

---

## Sommaire

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Utilisation](#utilisation)
4. [Extension contrôleur](#extension-contrôleur)
5. [Comportement CLI](#comportement-cli)

---

## Structure

```
Client/Session/
├── Session.php                          # Gestion de la session PHP
└── Extension/
    └── SessionControllerExtension.php   # Injecte getSession() dans les contrôleurs
```

---

## Configuration

La session est configurée depuis `session.config.php`, clé `session` :

```php
return [
    'session' => [
        'enabled'   => true,
        'name'      => 'neo_session',
        'lifetime'  => 7200,       // Durée en secondes
        'secure'    => true,       // Cookie HTTPS uniquement
        'http_only' => true,       // Cookie inaccessible en JavaScript
        'same_site' => 'Lax',     // Politique SameSite
        'storage'   => [
            'enabled' => true,
            'handler' => 'files',  // Stockage fichier
        ],
    ],
];
```

Les fichiers de session sont stockés dans `src/<Projet>/Storage/var/cache/session/`.

---

## Utilisation

```php
$session = $container->get(Session::class);

// Écrire
$session->set('user_id', 42);

// Lire
$session->get('user_id');           // 42
$session->get('missing', 'défaut'); // 'défaut'

// Vérifier l'existence
$session->has('user_id');           // true

// Supprimer une clé
$session->remove('user_id');

// Accéder à toute la session
$session->all();   // array complet de $_SESSION

// Vider la session
$session->clear(); // vide $_SESSION sans la détruire

// Regénérer l'ID (à appeler après un login)
$session->regenerate();

// Détruire la session
$session->destroy();
```

---

## Extension contrôleur

**Fichier :** `Extension/SessionControllerExtension.php`

Injecte automatiquement `getSession()` dans tous les contrôleurs.

```php
class AuthController extends AbstractController
{
    #[Route('/login', 'POST')]
    public function login(): Response
    {
        // ... vérification des credentials

        $this->getSession()->set('user_id', $user->getId());
        $this->getSession()->regenerate();

        return $this->redirect('/dashboard');
    }

    #[Route('/logout', 'POST')]
    public function logout(): Response
    {
        $this->getSession()->destroy();
        return $this->redirect('/login');
    }
}
```

---

## Comportement CLI

En contexte CLI (`PHP_SAPI === 'cli'`), le constructeur détecte l'environnement et toutes les méthodes (`set`, `get`, `has`, `remove`, `all`, `clear`, `regenerate`, `destroy`) deviennent des no-ops silencieux. Aucune session PHP n'est démarrée.
