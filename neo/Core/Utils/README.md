# Utilitaires

Le module `Utils` regroupe les utilitaires transversaux du framework : cache, configuration, journalisation, notifications multi-canaux et scanner d'attributs PHP.

---

## Structure du module

```
Utils/
├── Cache/
│   ├── Driver/ (FileDriver, RedisDriver, ArrayDriver)
│   ├── Commands/   CacheClearCommand
│   ├── Extension/  CacheControllerExtension
│   ├── CacheManager.php
│   └── CacheModule.php
├── Config/
│   ├── Templates/  (ApiConfig, AppConfig, AuthConfig, CacheConfig, DatabaseConfig, …)
│   ├── Writer/     ConfigTemplateWriter
│   ├── Commands/   GenerateDefaultConfigCommand, MakeConfigCommand
│   ├── Extension/  ConfigControllerExtension
│   ├── ConfigManager.php
│   └── ConfigModule.php
├── Logger/
│   ├── Collector/  LogCollector
│   ├── Extension/  LoggerControllerExtension
│   ├── LoggerManager.php
│   └── LoggerModule.php
├── Notification/
│   ├── Channel/    Email/, Slack/, Sms/ (Twilio, Vonage, Log)
│   ├── Collector/  NotificationCollector
│   ├── Enum/       NotificationEnum
│   ├── NotificationManager.php
│   └── NotificationModule.php
└── Scanner/
    ├── Extension/  ScannerControllerExtension
    ├── ScannerAttributeManager.php
    └── ScannerModule.php
```

---

## Documentation par composant

| Composant | Description | README |
|-----------|-------------|--------|
| `Cache` | Cache fichiers / Redis / mémoire, pattern `remember` | [Cache/README.md](Cache/README.md) |
| `Config` | Chargement des `*.config.php`, accès par point, surcharge test | [Config/README.md](Config/README.md) |
| `Logger` | Niveaux RFC 5424, canaux, rotation quotidienne / taille, archivage ZIP | [Logger/README.md](Logger/README.md) |
| `Notification` | Email (PHPMailer), Slack (Webhook), SMS (Twilio/Vonage/Log) | [Notification/README.md](Notification/README.md) |
| `Scanner` | Réflexion PHP 8 sur attributs de classe, méthodes, propriétés, paramètres | [Scanner/README.md](Scanner/README.md) |

---

## Extensions contrôleur

| Méthode | Composant |
|---------|-----------|
| `getCache()` | Cache |
| `getConfig()` | Config |
| `getLogger()` | Logger |

---

## Commandes CLI

| Commande | Composant | Description |
|----------|-----------|-------------|
| `cache:clear` | Cache | Vide le cache d'un projet |
| `config:generate` | Config | Génère les fichiers de config par défaut |
| `make:config` | Config | Crée un fichier de configuration personnalisé |
