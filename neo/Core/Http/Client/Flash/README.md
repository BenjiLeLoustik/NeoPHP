# Flash — NeoPHP

`Neo\Core\Http\Client\Flash\Flash` gère les messages éphémères stockés en session et consommés à la prochaine lecture (pattern flash message).

---

## Sommaire

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Ajouter un message](#ajouter-un-message)
4. [Lire les messages](#lire-les-messages)
5. [Rendu HTML](#rendu-html)
6. [Extension contrôleur](#extension-contrôleur)
7. [Fonction Twig](#fonction-twig)

---

## Structure

```
Client/Flash/
├── Flash.php                           # Messages flash
└── Extension/
    ├── FlashControllerExtension.php    # Injecte getFlash() dans les contrôleurs
    └── FlashViewExtension.php          # Expose flashes() dans Twig
```

---

## Configuration

Configuré depuis `session.config.php`, clé `flash` :

```php
return [
    'flash' => [
        'session_key' => '_flash',
        'auto_expire' => true,       // Vide les messages après lecture
        'types'       => ['success', 'error', 'warning', 'info'],
    ],
];
```

---

## Ajouter un message

```php
$flash = $container->get(Flash::class);

$flash->add('success', 'Votre profil a été mis à jour.');
$flash->add('error', 'Une erreur est survenue.');
$flash->add('warning', 'Votre session expire bientôt.');
$flash->add('info', 'Mise à jour disponible.');
```

Le type doit être déclaré dans la configuration (`types`). Sinon, une `FrameworkException` est levée.

---

## Lire les messages

```php
// Récupère tous les messages sous forme de tableau
// Si auto_expire = true, les messages sont supprimés après cette lecture
$messages = $flash->getAll();
// [
//   ['type' => 'success', 'message' => 'Votre profil a été mis à jour.'],
//   ['type' => 'error',   'message' => 'Une erreur est survenue.'],
// ]

// Vérifier s'il y a des messages en attente
if ($flash->has()) {
    // ...
}
```

---

## Rendu HTML

```php
echo $flash->render();
// <span class='flash-message success'>Votre profil a été mis à jour.</span>
// <span class='flash-message error'>Une erreur est survenue.</span>
```

Les valeurs sont passées par `htmlspecialchars()` pour prévenir les XSS.

---

## Extension contrôleur

**Fichier :** `Extension/FlashControllerExtension.php`

Injecte automatiquement `getFlash()` dans tous les contrôleurs.

```php
class UserController extends AbstractController
{
    #[Route('/profile', 'POST')]
    public function update(): Response
    {
        // ... traitement

        $this->getFlash()->add('success', 'Profil mis à jour.');
        return $this->redirect('/profile');
    }
}
```

---

## Fonction Twig

**Fichier :** `Extension/FlashViewExtension.php`

Expose la fonction `flashes()` dans tous les templates Twig. Le résultat est marqué `is_safe: html`.

```twig
{# Dans un layout ou un partial #}
{{ flashes() }}
```

Génère le rendu HTML de tous les messages flash en attente (équivalent à `Flash::render()`).
