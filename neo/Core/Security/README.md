# Security

Le module `Security` regroupe trois sous-systèmes complémentaires qui sécurisent les applications NeoPHP :

- **Auth** — authentification par session ou JWT, gestion des rôles, hachage de mots de passe.
- **CSRF** — protection contre les attaques Cross-Site Request Forgery via tokens de session.
- **Middleware** — pipeline d'autorisation déclaratif par attributs PHP 8, avec middlewares intégrés et support de middlewares personnalisés.

---

## Structure du module

```
Security/
├── Auth/
│   ├── AuthManager.php                    Point d'entrée de l'authentification
│   ├── AuthModule.php
│   ├── JwtManager.php                     JWT HMAC-SHA256 sans dépendance
│   ├── PasswordManager.php                Hachage bcrypt (cost 12)
│   ├── Collector/  AuthCollector
│   ├── Exception/  AuthException, JwtException
│   ├── Extension/  AuthControllerExtension, AuthViewExtension
│   └── Guard/      SessionGuard, TokenGuard
├── Csrf/
│   ├── CsrfManager.php                    Token unique par session
│   ├── CsrfTokenManager.php               Tokens nommés avec expiration
│   ├── CsrfModule.php
│   ├── Exception/  CsrfException
│   ├── Extension/  CsrfViewExtension
│   └── Token/      CsrfToken
└── Middleware/
    ├── MiddlewareManager.php              Orchestrateur du pipeline
    ├── MiddlewareModule.php
    ├── Interface/  MiddlewareInterface
    ├── Attribute/  Middleware, IsGranted
    ├── Default/    Auth, Guest, IsGranted, Role, Csrf, RateLimit, AuthRateLimit
    ├── Exception/  MiddlewareException
    ├── Extension/  MiddlewareControllerExtension
    └── Commands/   MakeMiddlewareCommand
```

---

## Documentation par composant

| Composant | Description | README |
|-----------|-------------|--------|
| `Auth` | Session/JWT, rôles, bcrypt, JwtManager | [Auth/README.md](Auth/README.md) |
| `Csrf` | Token session, tokens nommés, Twig `csrf_token()` | [Csrf/README.md](Csrf/README.md) |
| `Middleware` | Pipeline attributs, middlewares intégrés, personnalisés | [Middleware/README.md](Middleware/README.md) |

---

## Extensions contrôleur

| Méthode | Composant |
|---------|-----------|
| `auth()` | Auth — accès à `AuthManager` |
| `getPasswordManager()` | Auth — accès à `PasswordManager` |
| `getMiddleware()` | Middleware — accès à `MiddlewareManager` |

## Extensions Twig

| Fonction | Composant | Description |
|----------|-----------|-------------|
| `auth_check()` | Auth | `true` si l'utilisateur est connecté |
| `auth_user()` | Auth | Objet utilisateur courant |
| `auth_has_role(role)` | Auth | `true` si l'utilisateur a le rôle donné |
| `csrf_token(id?)` | Csrf | Token CSRF pour les formulaires |
