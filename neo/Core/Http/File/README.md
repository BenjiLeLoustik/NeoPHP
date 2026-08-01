# Upload

The `File` submodule handles secure file uploads. It relies on `UploaderManager` to validate, move, and name uploaded files.

---

## Summary

1. [Structure](#structure)
2. [UploaderManager](#uploadermanager)
3. [UploadedFile](#uploadedfile)
4. [Security](#security)
5. [Exceptions](#exceptions)
6. [Controller Extension](#controller-extension)

---

## Structure

```
File/
├── UploaderManager.php                  # Upload handling
├── UploaderModule.php                   # DI registration
├── Model/
│   └── UploadedFile.php                 # Representation of an uploaded file
├── Exception/
│   ├── UploadException.php              # File-related error
│   └── UploaderException.php            # Upload process error
└── Extension/
    └── UploaderControllerExtension.php  # Injects upload() into controllers
```

---

## UploaderManager

**File:** `UploaderManager.php`

```php
$uploader = $container->get(UploaderManager::class);

$file = $request->file('avatar'); // UploadedFile

$finalName = $uploader->upload(
    file: $file,
    name: 'avatar_' . $userId,            // Desired name (without extension)
    allowedExtensions: ['jpg', 'png', 'webp'],
    directory: 'uploads/avatars'           // Relative to assetsPath
);
// Returns: 'avatar_42.jpg'
// Or, in case of a collision: 'avatar_42_1722172800.jpg'
```

Files are moved to `src/<Project>/Assets/{directory}/`.

---

## UploadedFile

**File:** `Model/UploadedFile.php`

Encapsulates the `$_FILES` data for a given field.

```php
$file = $request->file('document'); // ?UploadedFile

if ($file) {
    $file->getName();         // Original file name
    $file->getExtension();    // Lowercase extension ('pdf', 'jpg', ...)
    $file->getMimeType();     // Declared MIME type
    $file->getSize();         // Size in bytes
    $file->getTmpPath();      // Temporary path ($_FILES['tmp_name'])
    $file->isValid();         // true if UPLOAD_ERR_OK
}
```

---

## Security

The following extensions are **always forbidden**, regardless of the `allowedExtensions` list passed to `upload()`:

```
php, phtml, exe, sh, js
```

Verification order:
1. The file is valid (`$file->isValid()` → `UPLOAD_ERR_OK`)
2. The extension is not in the blacklist
3. The extension is in the whitelist (if not empty)

In case of a name collision in the destination folder, a `_<timestamp>` suffix is automatically added.

---

## Exceptions

| Class | Title | Cause |
|--------|-------|-------|
| `UploadException` | `Invalid File` | `isValid()` returns `false` |
| `UploadException` | `Forbidden File Type` | Extension is in the blacklist |
| `UploadException` | `Extension Not Allowed` | Extension is absent from the whitelist |
| `UploaderException` | `Upload Failed` | `move_uploaded_file()` failed |

---

## Controller Extension

**File:** `Extension/UploaderControllerExtension.php`

Automatically injects `upload()` into every controller. Accesses the file directly from the current `Request`.

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

If the file field is missing from the request, an `AbstractControllerException` (code 400) is thrown.