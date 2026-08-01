# Logger

The `Logger` submodule provides a structured logging system with support for RFC 5424 levels, named channels, file rotation, and automatic archiving.

---

## Summary

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Log Levels](#log-levels)
4. [Usage](#usage)
5. [Channels](#channels)
6. [Rotation and Archiving](#rotation-and-archiving)
7. [Controller Extension](#controller-extension)
8. [Profiler Integration](#profiler-integration)

---

## Structure

```
Logger/
├── LoggerManager.php                   # Main manager
├── LoggerModule.php                    # DI registration
├── Collector/
│   └── LogCollector.php               # Profiler collector
└── Extension/
    └── LoggerControllerExtension.php  # Injects getLogger() into controllers
```

---

## Configuration

**File:** `src/<Project>/Config/logger.config.php`

```php
return [
    'enabled'    => true,
    'min_level'  => 'DEBUG',
    'log_format' => '[{%datetime%}][{%level_name%}][{%origin%}] {%message%} {%context%}',

    'channels' => [
        'app' => [
            'name'      => 'app',
            'extension' => 'log',
            'enabled'   => true,
        ],
        'security' => [
            'name'      => 'security',
            'extension' => 'log',
            'enabled'   => true,
        ],
    ],

    'rotation' => [
        'enabled'       => true,
        'type'          => 'daily',   // 'daily' or 'size'
        'max_file_size' => 10485760,  // 10 MB (for type 'size')
    ],

    'archive' => [
        'enabled'   => true,
        'extension' => 'zip',
    ],
];
```

**Format Placeholders:**

| Placeholder | Description |
|-------------|-------------|
| `{%datetime%}` | Date and time (`Y-m-d H:i:s`) |
| `{%level_name%}` | Level in uppercase |
| `{%level_code%}` | Numeric level code |
| `{%origin%}` | `$origin` parameter passed to the method |
| `{%message%}` | Log message |
| `{%context%}` | JSON-encoded context |

---

## Log Levels

| Level | Code | Description |
|--------|------|-------------|
| `DEBUG` | 100 | Detailed debugging information |
| `INFO` | 200 | Normal system events |
| `NOTICE` | 250 | Normal but significant events |
| `WARNING` | 300 | Non-critical abnormal situations |
| `ERROR` | 400 | Application errors |
| `CRITICAL` | 500 | Critical errors |
| `ALERT` | 550 | Immediate action required |
| `EMERGENCY` | 600 | System unusable |

---

## Usage

```php
$logger = $container->get(LoggerManager::class);

// Convenience methods by level
$logger->debug('SQL query executed', ['sql' => $sql, 'time' => $ms]);
$logger->info('User logged in', ['user_id' => 42], 'auth');
$logger->notice('Login attempt', ['ip' => $_SERVER['REMOTE_ADDR']]);
$logger->warning('Token nearing expiration', ['user_id' => 42]);
$logger->error('Payment declined', ['order_id' => 123, 'reason' => $msg], 'payment');
$logger->critical('Database unreachable');
$logger->alert('Disk almost full', ['usage' => '95%']);
$logger->emergency('System down');

// Generic method
$logger->log('WARNING', 'Custom message', ['key' => 'value'], 'origin');
```

---

## Channels

```php
// Specific channel via chained method
$logger->channel('security')->error('Unauthorized access', ['path' => '/admin']);

// Channel via optional parameter (4th argument)
$logger->info('Security event', [], 'system', 'security');
```

Log files are stored in `src/<Project>/Storage/logs/`.

---

## Rotation and Archiving

**Daily rotation** (`type: 'daily'`): files are named `app-2026-07-30.log`. On each call to `log()`, older (non-active) files are automatically archived.

**Size-based rotation** (`type: 'size'`): the current file is renamed `app.log.{timestamp}` as soon as it exceeds `max_file_size`.

**Archiving**: older files are compressed into ZIP archives under `{storagePath}/logs/archives/{year}/{month}/{date}.zip`.

---

## Controller Extension

**File:** `Extension/LoggerControllerExtension.php`

Automatically injects `getLogger()` into all controllers.

```php
class OrderController extends AbstractController
{
    #[Route('/orders/{id}/checkout', 'POST')]
    public function checkout(int $id): Response
    {
        try {
            // ... payment processing
            $this->getLogger()->info('Order validated', ['order_id' => $id], 'payment');
        } catch (\Throwable $e) {
            $this->getLogger()->error('Payment failed', [
                'order_id' => $id,
                'error'    => $e->getMessage(),
            ], 'payment');
            throw $e;
        }

        return $this->redirect('/orders/' . $id);
    }
}
```

---

## Profiler Integration

`LoggerManager` integrates with the NeoPHP profiler when `NEO_PROFILER_ENABLED` is set. Logs are automatically collected via `LogCollector` and displayable in the debug panel.