# Utilities

The `Utils` module brings together the framework's cross-cutting utilities: cache, configuration, logging, multi-channel notifications, and the PHP attribute/file scanners.

---

## Module Structure

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
    ├── Result/     AttributeScanResult, FileScanResult
    ├── ScannerAttributeManager.php
    ├── ScannerFileManager.php
    └── ScannerModule.php
```

---

## Documentation by Component

| Component | Description | README |
|-----------|-------------|--------|
| `Cache` | File / Redis / memory cache, `remember` pattern | [Cache/README.md](Cache/README.md) |
| `Config` | Loading of `*.config.php`, dot-notation access, test override | [Config/README.md](Config/README.md) |
| `Logger` | RFC 5424 levels, channels, daily / size rotation, ZIP archiving | [Logger/README.md](Logger/README.md) |
| `Notification` | Email (PHPMailer), Slack (Webhook), SMS (Twilio/Vonage/Log) | [Notification/README.md](Notification/README.md) |
| `Scanner` | PHP 8 reflection on class, method, property, and parameter attributes, plus directory-based class discovery | [Scanner/README.md](Scanner/README.md) |

---

## Controller Extensions

| Method | Component |
|---------|-----------|
| `getCache()` | Cache |
| `getConfig()` | Config |
| `getLogger()` | Logger |
| `getScanner(string $className)` | Scanner (attribute scanning) |
| `getFileScanner()` | Scanner (directory-based class discovery) |

---

## CLI Commands

| Command | Component | Description |
|----------|-----------|-------------|
| `cache:clear` | Cache | Clears a project's cache |
| `config:generate` | Config | Generates the default config files |
| `make:config` | Config | Creates a custom configuration file |