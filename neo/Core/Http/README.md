# Module Http — NeoPHP

Le module `Http` couvre l'intégralité du cycle requête/réponse de NeoPHP : abstraction de la requête entrante, types de réponses HTTP, gestion de la session, messages flash, cookies et upload de fichiers.

---

## Structure du module

```
Http/
├── Request/
│   ├── Request.php                          Requête HTTP entrante (immuable)
│   └── Collector/RequestCollector.php       Collecteur Profiler
├── Response/
│   ├── ResponseManager.php                  Fabrique de réponses
│   ├── ResponseModule.php
│   ├── Types/
│   │   ├── Response.php                     Réponse HTTP générique
│   │   ├── JsonResponse.php                 Réponse JSON
│   │   └── RedirectResponse.php             Réponse de redirection
│   └── Extension/ResponseControllerExtension.php
├── Client/
│   ├── ClientManager.php
│   ├── ClientModule.php
│   ├── Session/
│   │   ├── Session.php                      Session PHP native
│   │   └── Extension/SessionControllerExtension.php
│   ├── Flash/
│   │   ├── Flash.php                        Messages flash éphémères
│   │   ├── Extension/FlashControllerExtension.php
│   │   └── Extension/FlashViewExtension.php
│   └── Cookie/
│       ├── Cookie.php                       Cookies avec préfixage automatique
│       └── Extension/CookieControllerExtension.php
└── File/
    ├── UploaderManager.php                  Upload sécurisé de fichiers
    ├── UploaderModule.php
    ├── Model/UploadedFile.php
    ├── Exception/
    └── Extension/UploaderControllerExtension.php
```

---

## Documentation par composant

| Composant | Description | README |
|-----------|-------------|--------|
| `Request` | Requête HTTP entrante, lecture des données, fichiers, IP | [Request/README.md](Request/README.md) |
| `Response` | Réponses HTTP, JSON, redirection, ResponseManager | [Response/README.md](Response/README.md) |
| `Session` | Session PHP native, configuration, méthodes, no-op CLI | [Client/Session/README.md](Client/Session/README.md) |
| `Flash` | Messages flash session, rendu HTML, Twig | [Client/Flash/README.md](Client/Flash/README.md) |
| `Cookie` | Cookies avec préfixage, configuration, lecture/écriture | [Client/Cookie/README.md](Client/Cookie/README.md) |
| `File` | Upload sécurisé, validation, liste noire d'extensions | [File/README.md](File/README.md) |

---

## Extensions contrôleur

Chaque composant injecte automatiquement ses méthodes dans `AbstractController` :

| Méthode | Composant |
|---------|-----------|
| `getSession()` | Session |
| `getFlash()` | Flash |
| `getCookie()` | Cookie |
| `json()` / `jsonSuccess()` / `jsonError()` | Response |
| `upload()` | File |

## Fonction Twig

| Fonction | Composant | Description |
|----------|-----------|-------------|
| `flashes()` | Flash | Rendu HTML de tous les messages flash en attente |
