# Notification — NeoPHP

Le sous-module `Notification` fournit un système d'envoi multi-canaux (Email, Slack, SMS) avec un patron builder fluide et intégration automatique du Profiler.

---

## Sommaire

1. [Structure](#structure)
2. [NotificationManager](#notificationmanager)
3. [EmailChannel](#emailchannel)
4. [SlackChannel](#slackchannel)
5. [SmsChannel](#smschannel)
6. [NotificationEnum](#notificationenum)
7. [Intégration Profiler](#intégration-profiler)

---

## Structure

```
Notification/
├── NotificationManager.php             # Builder fluide multi-canaux
├── NotificationModule.php              # Enregistrement DI
├── Channel/
│   ├── ChannelInterface.php
│   ├── Email/
│   │   └── EmailChannel.php            # Envoi SMTP via PHPMailer
│   ├── Slack/
│   │   └── SlackChannel.php            # Envoi via Incoming Webhook
│   └── Sms/
│       ├── SmsChannel.php              # Abstraction SMS multi-drivers
│       └── Driver/
│           ├── DriverInterface.php
│           ├── TwilioDriver.php
│           ├── VonageDriver.php
│           └── LogDriver.php           # Driver de développement (log sans envoi)
├── Collector/
│   └── NotificationCollector.php      # Collecteur Profiler
├── Enum/
│   └── NotificationEnum.php
└── Exception/
    ├── NotificationException.php
    └── ChannelException.php
```

---

## NotificationManager

**Fichier :** `NotificationManager.php`

Builder fluide pour envoyer des notifications via n'importe quel canal.

```php
$notif = $container->get(NotificationManager::class);

// Email
$result = $notif
    ->channel(EmailChannel::class)
    ->setParams([
        'to'      => 'alice@example.com',
        'subject' => 'Bienvenue !',
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

**Flux d'exécution de `doSend()` :**

1. Vérification qu'un canal est sélectionné.
2. Rendu du template via le moteur de vue du projet.
3. Appel de `channel->send()`.
4. Enregistrement dans le Profiler (si actif).
5. Réinitialisation du builder pour la prochaine utilisation.

---

## EmailChannel

**Fichier :** `Channel/Email/EmailChannel.php`

Utilise **PHPMailer** pour l'envoi SMTP. Supporte plusieurs drivers SMTP configurables.

**Configuration `api.config.php` :**

```php
return [
    'mailer' => [
        'enabled' => true,
        'default' => 'smtp',
        'from' => [
            'address' => 'noreply@myapp.com',
            'name'    => 'Mon Application',
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

**Paramètres acceptés :**

| Clé | Type | Description |
|-----|------|-------------|
| `to` | `string\|array` | Destinataire(s) |
| `cc` | `string\|array` | Copie(s) |
| `bcc` | `string\|array` | Copie(s) cachée(s) |
| `subject` | `string` | Objet de l'email |
| `driver` | `string` | Driver SMTP à utiliser (optionnel) |

```php
$notif
    ->channel(EmailChannel::class)
    ->setParams([
        'to'      => ['alice@example.com', 'bob@example.com'],
        'cc'      => 'manager@example.com',
        'subject' => 'Rapport mensuel',
        'driver'  => 'smtp',
    ])
    ->setTemplate('emails/report.html.twig', ['data' => $reportData])
    ->doSend();
```

---

## SlackChannel

**Fichier :** `Channel/Slack/SlackChannel.php`

Envoie des messages via l'API Incoming Webhooks Slack.

**Configuration `api.config.php` :**

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

**Paramètres acceptés :**

| Clé | Type | Description |
|-----|------|-------------|
| `channel` | `string` | Canal Slack (`#channel` ou `@user`) |
| `username` | `string` | Nom d'affichage du bot |
| `icon` | `string` | Emoji de l'icône |

```php
$notif
    ->channel(SlackChannel::class)
    ->setParams(['channel' => '#alerts', 'icon' => ':warning:'])
    ->setTemplate('slack/alert.html.twig', ['message' => 'CPU > 90%'])
    ->doSend();
```

---

## SmsChannel

**Fichier :** `Channel/Sms/SmsChannel.php`

Abstraction SMS multi-drivers. Supporte l'envoi vers plusieurs destinataires et gère les échecs partiels.

**Drivers disponibles :**

| Driver | Classe | Description |
|--------|--------|-------------|
| `twilio` | `TwilioDriver` | API Twilio REST |
| `vonage` | `VonageDriver` | API Vonage (ex-Nexmo) |
| `log` | `LogDriver` | Journalise sans envoyer (développement) |

**Configuration `api.config.php` :**

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

**Comportement partiel :** Si l'envoi réussit pour certains destinataires et échoue pour d'autres, `SmsChannel` retourne `NotificationEnum::PARTIAL`. Si tous échouent, une `ChannelException` est levée.

```php
$result = $notif
    ->channel(SmsChannel::class)
    ->setParams(['to' => ['+33600000001', '+33600000002']])
    ->setTemplate('sms/promo.html.twig', ['discount' => '20%'])
    ->doSend();

match ($result) {
    NotificationEnum::SUCCESS => $logger->info('Tous les SMS envoyés'),
    NotificationEnum::PARTIAL => $logger->warning('Certains SMS ont échoué'),
    default => null,
};
```

---

## NotificationEnum

**Fichier :** `Enum/NotificationEnum.php`

```php
enum NotificationEnum: string
{
    case SUCCESS = 'success';  // Envoi entièrement réussi
    case FAILED  = 'failed';   // Envoi complètement échoué
    case PARTIAL = 'partial';  // Envoi partiellement réussi (SMS multi-destinataires)
    case SKIPPED = 'skipped';  // Canal désactivé
}
```

---

## Intégration Profiler

`NotificationManager` s'intègre au Profiler de NeoPHP lorsque `NEO_PROFILER_ENABLED` est défini. Chaque envoi est collecté via `NotificationCollector` et visible dans le panneau de débogage.

Le rendu des templates utilise le moteur de vue du projet (injecté comme `notification.viewModule`), ce qui permet d'utiliser Twig pour les corps des notifications.
