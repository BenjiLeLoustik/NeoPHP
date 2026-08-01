# Cookie

`Neo\Core\Http\Client\Cookie\Cookie` encapsule la gestion des cookies PHP avec préfixage automatique et configuration centralisée.

---

## Sommaire

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Utilisation](#utilisation)
4. [Extension contrôleur](#extension-contrôleur)

---

## Structure

```
Client/Cookie/
├── Cookie.php                          # Gestion des cookies
└── Extension/
    └── CookieControllerExtension.php   # Injecte getCookie() dans les contrôleurs
```

---

## Configuration

Configuré depuis `session.config.php`, clé `cookie` :

```php
return [
    'cookie' => [
        'prefix'    => 'neo_',
        'lifetime'  => 2592000,  // 30 jours (en secondes)
        'path'      => '/',
        'domain'    => '',
        'secure'    => true,     // Cookie HTTPS uniquement
        'http_only' => true,     // Cookie inaccessible en JavaScript
        'same_site' => 'Lax',
    ],
];
```

Tous les noms de cookies sont automatiquement préfixés (ex. : `user_theme` → `neo_user_theme`). Les méthodes `get`, `has` et `remove` appliquent le même préfixe de façon transparente.

---

## Utilisation

```php
$cookie = $container->get(Cookie::class);

// Écrire un cookie (valeurs de config par défaut)
$cookie->set('user_theme', 'dark');

// Avec paramètres personnalisés
$cookie->set(
    name: 'remember_token',
    value: $token,
    expire: time() + 86400,   // expire dans 1 jour
    path: '/',
    domain: 'example.com',
    secure: true,
    httpOnly: true
);

// Lire
$theme = $cookie->get('user_theme', 'light'); // valeur ou défaut

// Vérifier l'existence
$cookie->has('user_theme'); // true/false

// Supprimer (expire dans le passé)
$cookie->remove('user_theme');

// Tous les cookies bruts ($_COOKIE complet, non filtrés par préfixe)
$cookie->all();
```

---

## Extension contrôleur

**Fichier :** `Extension/CookieControllerExtension.php`

Injecte automatiquement `getCookie()` dans tous les contrôleurs.

```php
class PreferenceController extends AbstractController
{
    #[Route('/theme', 'POST')]
    public function setTheme(): Response
    {
        $theme = $this->getRequest()->body('theme', 'light');
        $this->getCookie()->set('user_theme', $theme);

        return $this->redirect('/');
    }

    #[Route('/theme', 'GET')]
    public function getTheme(): JsonResponse
    {
        $theme = $this->getCookie()->get('user_theme', 'light');
        return $this->jsonSuccess(['theme' => $theme]);
    }
}
```
