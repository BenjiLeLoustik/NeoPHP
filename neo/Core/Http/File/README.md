# File (Upload) — NeoPHP

Le sous-module `File` gère l'upload sécurisé de fichiers. Il s'appuie sur `UploaderManager` pour valider, déplacer et nommer les fichiers uploadés.

---

## Sommaire

1. [Structure](#structure)
2. [UploaderManager](#uploadermanager)
3. [UploadedFile](#uploadedfile)
4. [Sécurité](#sécurité)
5. [Exceptions](#exceptions)
6. [Extension contrôleur](#extension-contrôleur)

---

## Structure

```
File/
├── UploaderManager.php                  # Gestion des uploads
├── UploaderModule.php                   # Enregistrement DI
├── Model/
│   └── UploadedFile.php                 # Représentation d'un fichier uploadé
├── Exception/
│   ├── UploadException.php              # Erreur sur le fichier
│   └── UploaderException.php            # Erreur sur le processus d'upload
└── Extension/
    └── UploaderControllerExtension.php  # Injecte upload() dans les contrôleurs
```

---

## UploaderManager

**Fichier :** `UploaderManager.php`

```php
$uploader = $container->get(UploaderManager::class);

$file = $request->file('avatar'); // UploadedFile

$finalName = $uploader->upload(
    file: $file,
    name: 'avatar_' . $userId,            // Nom souhaité (sans extension)
    allowedExtensions: ['jpg', 'png', 'webp'],
    directory: 'uploads/avatars'           // Relatif à assetsPath
);
// Retourne : 'avatar_42.jpg'
// Ou en cas de collision : 'avatar_42_1722172800.jpg'
```

Les fichiers sont déplacés dans `src/<Projet>/Assets/{directory}/`.

---

## UploadedFile

**Fichier :** `Model/UploadedFile.php`

Encapsule les données de `$_FILES` pour un champ donné.

```php
$file = $request->file('document'); // ?UploadedFile

if ($file) {
    $file->getName();         // Nom original du fichier
    $file->getExtension();    // Extension en minuscules ('pdf', 'jpg', ...)
    $file->getMimeType();     // MIME type déclaré
    $file->getSize();         // Taille en octets
    $file->getTmpPath();      // Chemin temporaire ($_FILES['tmp_name'])
    $file->isValid();         // true si UPLOAD_ERR_OK
}
```

---

## Sécurité

Les extensions suivantes sont **toujours interdites**, quelle que soit la liste `allowedExtensions` passée à `upload()` :

```
php, phtml, exe, sh, js
```

Ordre de vérification :
1. Le fichier est valide (`$file->isValid()` → `UPLOAD_ERR_OK`)
2. L'extension n'est pas dans la liste noire
3. L'extension est dans la liste blanche (si non vide)

En cas de collision de nom dans le dossier de destination, un suffixe `_<timestamp>` est ajouté automatiquement.

---

## Exceptions

| Classe | Titre | Cause |
|--------|-------|-------|
| `UploadException` | `Invalid File` | `isValid()` retourne `false` |
| `UploadException` | `Forbidden File Type` | Extension dans la liste noire |
| `UploadException` | `Extension Not Allowed` | Extension absente de la liste blanche |
| `UploaderException` | `Upload Failed` | `move_uploaded_file()` a échoué |

---

## Extension contrôleur

**Fichier :** `Extension/UploaderControllerExtension.php`

Injecte automatiquement `upload()` dans tous les contrôleurs. Accède au fichier directement depuis la `Request` courante.

```php
class ProfileController extends AbstractController
{
    #[Route('/avatar', 'POST')]
    public function uploadAvatar(): Response
    {
        $filename = $this->upload(
            field: 'avatar',
            name: 'avatar_' . $this->getUser()->getId(),
            extensions: ['jpg', 'jpeg', 'png', 'webp'],
            directory: 'uploads/avatars'
        );

        // $filename = 'avatar_42.jpg'
        $this->getUser()->setAvatar($filename);
        // ...

        return $this->jsonSuccess(['filename' => $filename]);
    }
}
```

Si le champ de fichier est absent de la requête, une `AbstractControllerException` (code 400) est levée.
