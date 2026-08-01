# Notification

The `Notification` submodule provides a multi-channel sending system (Email, Slack, SMS) with a fluent builder pattern and automatic Profiler integration.

---

## Summary

1. [Structure](#structure)
2. [NotificationManager](#notificationmanager)
3. [EmailChannel](#emailchannel)
4. [SlackChannel](#slackchannel)
5. [SmsChannel](#smschannel)
6. [NotificationEnum](#notificationenum)
7. [Profiler Integration](#profiler-integration)

---

## Structure

```
Notification/
├── NotificationManager.php             # Fluent multi-channel builder
├── NotificationModule.php              # DI registration
├── Channel/
│   ├── ChannelInterface.php
│   ├── Email/
│   │   └── EmailChannel.php            # SMTP sending via PHPMailer
│   ├── Slack/
│   │   └── SlackChannel.php            # Sending via Incoming Webhook
│   └── Sms/
│       ├── SmsChannel.php              # Multi-driver SMS abstraction
│       └── Driver/
│           ├── DriverInterface.php
│           ├── TwilioDriver.php
│           ├── VonageDriver.php
│           └── LogDriver.php           # Development driver (logs without sending)
├── Collector/
│   └── NotificationCollector.php      # Profiler collector
├── Enum/
│   └── NotificationEnum.php
└── Exception/
    ├── NotificationException.php
    └── ChannelException.php
```

---

## NotificationManager

**File:** `NotificationManager.php`

Fluent builder for sending notifications through any channel.

```php
$notif = $container->get(NotificationManager::class);

// Email
$result = $notif
    ->channel(EmailChannel::class)
    ->setParams([
        'to'      => 'alice@example.com',
        'subject' => 'Welcome!',
    ])
    ->setTemplate('emails/welcome.html.twig', ['user' => $user])
    ->doSend();

// Slack
$result = $notif
    ->channel(SlackChannel::class)
    ->setParams(['channel' => '#deployments'])
    ->setTemplate('slack/deploy.html.twig', ['version' => '1.2.3'])
    ->doSend();

// SMS
$result = $notif
    ->channel(SmsChannel::class)
    ->setParams(['to' => ['+33612345678', '+33687654321']])
    ->setTemplate('sms/code.html.twig', ['code' => '123456'])
    ->doSend();
```

**`doSend()` Execution Flow:**

1. Checks that a channel has been selected.
2. Renders the template via the project's view engine.
3. Calls `channel->send()`.
4. Records into the Profiler (if active).
5. Resets the builder for the next use.

---

## EmailChannel

**File:** `Channel/Email/EmailChannel.php`

Uses **PHPMailer** for SMTP sending. Supports several configurable SMTP drivers.

**`api.config.php` Configuration:**

```php
return [
    'mailer' => [
        'enabled' => true,
        'default' => 'smtp',
        'from' => [
            'address' => 'noreply@myapp.com',
            'name'    => 'My Application',
        ],
        'drivers' => [
            'smtp' => [
                'host'       => 'smtp.mailgun.org',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'postmaster@myapp.com',
                'password'   => 'secret',
            ],
            'mailtrap' => [
                'host'       => 'smtp.mailtrap.io',
                'port'       => 2525,
                'encryption' => 'tls',
                'username'   => 'mailtrap_user',
                'password'   => 'mailtrap_pass',
            ],
        ],
    ],
];
```

**Accepted Parameters:**

| Key | Type | Description |
|-----|------|-------------|
| `to` | `string\|array` | Recipient(s) |
| `cc` | `string\|array` | Copy recipient(s) |
| `bcc` | `string\|array` | Blind copy recipient(s) |
| `subject` | `string` | Email subject |
| `driver` | `string` | SMTP driver to use (optional) |

```php
$notif
    ->channel(EmailChannel::class)
    ->setParams([
        'to'      => ['alice@example.com', 'bob@example.com'],
        'cc'      => 'manager@example.com',
        'subject' => 'Monthly Report',
        'driver'  => 'smtp',
    ])
    ->setTemplate('emails/report.html.twig', ['data' => $reportData])
    ->doSend();
```

---

## SlackChannel

**File:** `Channel/Slack/SlackChannel.php`

Sends messages via the Slack Incoming Webhooks API.

**`api.config.php` Configuration:**

```php
return [
    'slack' => [
        'enabled'     => true,
        'webhook_url' => 'https://hooks.slack.com/services/T00/B00/XXXX',
        'default' => [
            'channel'  => '#notifications',
            'username' => 'NeoPHP Bot',
            'icon'     => ':robot_face:',
        ],
    ],
];
```

**Accepted Parameters:**

| Key | Type | Description |
|-----|------|-------------|
| `channel` | `string` | Slack channel (`#channel` or `@user`) |
| `username` | `string` | Bot display name |
| `icon` | `string` | Icon emoji |

```php
$notif
    ->channel(SlackChannel::class)
    ->setParams(['channel' => '#alerts', 'icon' => ':warning:'])
    ->setTemplate('slack/alert.html.twig', ['message' => 'CPU > 90%'])
    ->doSend();
```

---

## SmsChannel

**File:** `Channel/Sms/SmsChannel.php`

Multi-driver SMS abstraction. Supports sending to multiple recipients and handles partial failures.

**Available Drivers:**

| Driver | Class | Description |
|--------|--------|-------------|
| `twilio` | `TwilioDriver` | Twilio REST API |
| `vonage` | `VonageDriver` | Vonage API (formerly Nexmo) |
| `log` | `LogDriver` | Logs without sending (development) |

**`api.config.php` Configuration:**

```php
return [
    'sms' => [
        'enabled' => true,
        'default' => 'twilio',
        'drivers' => [
            'twilio' => [
                'account_sid' => 'ACxxx',
                'auth_token'  => 'xxx',
                'from'        => '+15005550006',
            ],
            'vonage' => [
                'api_key'    => 'xxx',
                'api_secret' => 'xxx',
                'from'       => 'MyApp',
            ],
        ],
    ],
];
```

**Partial Behavior:** If sending succeeds for some recipients and fails for others, `SmsChannel` returns `NotificationEnum::PARTIAL`. If all fail, a `ChannelException` is thrown.

```php
$result = $notif
    ->channel(SmsChannel::class)
    ->setParams(['to' => ['+33600000001', '+33600000002']])
    ->setTemplate('sms/promo.html.twig', ['discount' => '20%'])
    ->doSend();

match ($result) {
    NotificationEnum::SUCCESS => $logger->info('All SMS sent'),
    NotificationEnum::PARTIAL => $logger->warning('Some SMS failed'),
    default => null,
};
```

---

## NotificationEnum

**File:** `Enum/NotificationEnum.php`

```php
enum NotificationEnum: string
{
    case SUCCESS = 'success';  // Fully successful send
    case FAILED  = 'failed';   // Completely failed send
    case PARTIAL = 'partial';  // Partially successful send (multi-recipient SMS)
    case SKIPPED = 'skipped';  // Channel disabled
}
```

---

## Profiler Integration

`NotificationManager` integrates with the NeoPHP Profiler when `NEO_PROFILER_ENABLED` is set. Each send is collected via `NotificationCollector` and visible in the debug panel.

Template rendering uses the project's view engine (injected as `notification.viewModule`), which allows Twig to be used for notification bodies.