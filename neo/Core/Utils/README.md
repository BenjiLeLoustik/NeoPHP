# Module Utils — NeoPHP

Ce module regroupe les utilitaires transversaux du framework NeoPHP : cache, configuration, journalisation (logger), notifications multi-canaux et scanner d'attributs PHP.

---

## Table des matières

1. [Architecture générale](#architecture-générale)
2. [Cache](#cache)
   - [CacheManager](#cachemanager)
   - [Drivers disponibles](#drivers-disponibles)
   - [Interface CacheDriverInterface](#interface-cachedriverinterface)
   - [Extension contrôleur](#extension-contrôleur-cache)
   - [Commande CLI](#commande-cli-cache)
3. [Config](#config)
   - [ConfigManager](#configmanager)
   - [ConfigModule](#configmodule)
   - [ConfigTemplateWriter](#configtemplatewriter)
   - [Extension contrôleur](#extension-contrôleur-config)
4. [Logger](#logger)
   - [LoggerManager](#loggermanager)
   - [Niveaux de log](#niveaux-de-log)
   - [Canaux et rotation](#canaux-et-rotation)
   - [Extension contrôleur](#extension-contrôleur-logger)
5. [Notification](#notification)
   - [NotificationManager](#notificationmanager)
   - [EmailChannel](#emailchannel)
   - [SlackChannel](#slackchannel)
   - [SmsChannel](#smschannel)
   - [NotificationEnum](#notificationenum)
6. [Scanner](#scanner)
   - [ScannerAttributeManager](#scannerattributemanager)
7. [Points techniques importants](#points-techniques-importants)

---

## Architecture générale

```
Utils/
├── Cache/
│   ├── Driver/
│   │   ├── Interface/          # CacheDriverInterface
│   │   ├── FileDriver.php      # Cache fichiers
│   │   ├── RedisDriver.php     # Cache Redis (Predis)
│   │   └── ArrayDriver.php     # Cache en mémoire (tests)
│   ├── Commands/               # CacheClearCommand
│   ├── Extension/              # CacheControllerExtension
│   ├── CacheManager.php
│   └── CacheModule.php
├── Config/
│   ├── Extension/              # ConfigControllerExtension
│   ├── Templates/              # Générateurs de fichiers config
│   ├── Writer/                 # ConfigTemplateWriter
│   ├── ConfigManager.php
│   └── ConfigModule.php
├── Logger/
│   ├── Extension/              # LoggerControllerExtension
│   ├── LoggerManager.php
│   └── LoggerModule.php
├── Notification/
│   ├── Channel/
│   │   ├── Email/              # EmailChannel (PHPMailer)
│   │   ├── Slack/              # SlackChannel (webhook)
│   │   └── Sms/                # SmsChannel
│   │       └── Driver/         # TwilioDriver, VonageDriver, LogDriver
│   ├── Enum/                   # NotificationEnum
│   ├── NotificationManager.php
│   └── NotificationModule.php
└── Scanner/
    ├── ScannerAttributeManager.php
    └── ScannerModule.php
```

---

## Cache

### CacheManager

**Fichier :** `Cache/CacheManager.php`

Point d'entrée unique pour le cache. Il instancie le driver approprié selon la configuration et délègue toutes les opérations.

```php
$cache = $container->get(CacheManager::class);

// Stocker une valeur (TTL en secondes, optionnel)
$cache->set('user:1', $userData, 3600);

// Lire une valeur (retourne $default si absente ou expirée)
$user = $cache->get('user:1', null);

// Vérifier l'existence
if ($cache->has('user:1')) {
    // ...
}

// Supprimer une entrée
$cache->delete('user:1');

// Vider tout le cache
$cache->clear();

// Méthode "remember" : charge depuis le cache ou exécute le callback
$users = $cache->remember('all_users', 600, function () use ($em) {
    return $em->getRepository(User::class)->findAll();
});
```

### Drivers disponibles

#### FileDriver

**Fichier :** `Cache/Driver/FileDriver.php`

Stocke les données dans des fichiers sérialisés sur le disque. Chaque clé est hachée en SHA-256 pour le nom de fichier.

- Format du fichier : `serialize(['key' => ..., 'expires_at' => ..., 'content' => ...])`
- Chemin : `{storagePath}/cache/`
- Expiration vérifiée à chaque lecture (`time() > $data['expires_at']`)

```php
// Configuration dans cache.config.php
return [
    'driver' => 'files',
    'ttl'    => 3600,
    'drivers' => [
        'files' => [
            'path' => 'cache',  // Relatif au storagePath
        ],
    ],
];
```

#### RedisDriver

**Fichier :** `Cache/Driver/RedisDriver.php`

Utilise la bibliothèque **Predis** pour la connexion Redis. Les données sont sérialisées.

- Les clés sont préfixées si `prefix` est défini dans la configuration.
- `clear()` avec un préfixe supprime uniquement les clés correspondantes. Sans préfixe, exécute `FLUSHDB`.
- Le TTL est géré par Redis nativement via `SETEX`.

```php
// Configuration dans cache.config.php
return [
    'driver' => 'redis',
    'ttl'    => 3600,
    'drivers' => [
        'redis' => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'password' => null,
            'database' => 0,
            'prefix'   => 'myapp:',
        ],
    ],
];
```

#### ArrayDriver

**Fichier :** `Cache/Driver/ArrayDriver.php`

Cache en mémoire (processus courant uniquement). Idéal pour les tests unitaires. Les données sont perdues à la fin de la requête.

```php
return [
    'driver' => 'array',
    'ttl'    => 3600,
];
```

### Interface CacheDriverInterface

**Fichier :** `Cache/Driver/Interface/CacheDriverInterface.php`

Contrat que tout driver de cache doit implémenter :

```php
interface CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): void;
    public function delete(string $key): void;
    public function clear(): void;
    public function has(string $key): bool;
}
```

**Créer un driver personnalisé :**

```php
use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;

final class MemcachedDriver implements CacheDriverInterface
{
    public function __construct(private \Memcached $client) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($key);
        return $value === false ? $default : $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->client->set($key, $value, $ttl ?? 3600);
    }

    public function delete(string $key): void
    {
        $this->client->delete($key);
    }

    public function clear(): void
    {
        $this->client->flush();
    }

    public function has(string $key): bool
    {
        $this->client->get($key);
        return $this->client->getResultCode() !== \Memcached::RES_NOTFOUND;
    }
}
```

### Extension contrôleur Cache

**Fichier :** `Cache/Extension/CacheControllerExtension.php`

L'extension est enregistrée automatiquement et ajoute la méthode `getCache()` à tous les contrôleurs.

```php
// Dans un contrôleur
class ProductController extends AbstractController
{
    public function index(): Response
    {
        $products = $this->getCache()->remember('products:all', 300, function () {
            return $this->getRepository(Product::class)->findAll();
        });

        return $this->render('products/index.html.twig', ['products' => $products]);
    }
}
```

### Commande CLI Cache

| Commande | Description |
|----------|-------------|
| `cache:clear` | Vide le répertoire de cache d'un projet |

```bash
php neo cache:clear --project=Blog
# Supprime tous les fichiers dans src/Blog/Storage/var/cache/
```

---

## Config

### ConfigManager

**Fichier :** `Config/ConfigManager.php`

Charge et expose tous les fichiers de configuration d'un projet. Les fichiers doivent suivre le nommage `{nom}.config.php` et retourner un tableau PHP.

**Fonctionnement :**

1. Au démarrage, charge tous les fichiers `*.config.php` du répertoire `configsPath`.
2. Si un répertoire de tests est configuré (`testConfigsPath`), charge les fichiers `*.config.test.php` et les fusionne profondément (`deepMerge`) sur les configs existantes.
3. L'accès se fait via un patron fluide `from(key)->get(path)`.

```php
$config = $container->get(ConfigManager::class);

// Lire une valeur simple
$dbName = $config->from('database')->get('connections.default.dbname');

// Valeur par défaut si absente
$debug = $config->from('app')->get('debug', false);

// Lire toute la configuration d'un fichier
$allDbConfig = $config->from('database')->all();

// Modifier une valeur à l'exécution
$config->from('app')->set('locale', 'fr');
```

**Structure des fichiers de configuration :**

```php
// src/MyProject/Config/app.config.php
return [
    'name'   => 'Mon Application',
    'debug'  => false,
    'locale' => 'fr',
    'features' => [
        'registration' => true,
        'api'          => false,
    ],
];
```

```php
// src/MyProject/Config/app.config.test.php
// Valeurs qui écrasent celles de app.config.php dans les tests
return [
    'debug'  => true,
    'features' => [
        'api' => true,
    ],
];
```

**Accès imbriqué par point :**

```php
// app.config.php retourne ['features' => ['registration' => true]]
$config->from('app')->get('features.registration'); // true
$config->from('app')->get('features.unknown', 'default'); // 'default'
```

### ConfigModule

**Fichier :** `Config/ConfigModule.php`

Module d'enregistrement dans le conteneur DI. Enregistre le `ConfigManager` comme service singleton.

### ConfigTemplateWriter

**Fichier :** `Config/Writer/ConfigTemplateWriter.php`

Utilisé par les commandes de scaffolding pour écrire des fichiers de configuration à partir de templates.

```php
ConfigTemplateWriter::write(
    templates: [new DatabaseConfigTemplate()],
    configPath: '/path/to/Config/',
    projectName: 'Blog',
    context: ['dbname' => 'blog_db'],
    askOverwrite: true,
);
```

Chaque template implémente `ConfigTemplateInterface` et définit `filename()` et `render(string $project, array $context): string`.

### Extension contrôleur Config

**Fichier :** `Config/Extension/ConfigControllerExtension.php`

Ajoute la méthode `getConfig()` à tous les contrôleurs.

```php
class AppController extends AbstractController
{
    public function settings(): Response
    {
        $appName = $this->getConfig()->from('app')->get('name');
        $features = $this->getConfig()->from('app')->get('features');

        return $this->render('settings.html.twig', [
            'appName'  => $appName,
            'features' => $features,
        ]);
    }
}
```

---

## Logger

### LoggerManager

**Fichier :** `Logger/LoggerManager.php`

Système de journalisation structuré avec support des niveaux RFC 5424, des canaux nommés, de la rotation des fichiers et de l'archivage automatique.

### Niveaux de log

| Niveau | Code | Description |
|--------|------|-------------|
| `DEBUG` | 100 | Informations de débogage détaillées |
| `INFO` | 200 | Événements normaux du système |
| `NOTICE` | 250 | Événements normaux mais significatifs |
| `WARNING` | 300 | Situations anormales non critiques |
| `ERROR` | 400 | Erreurs applicatives |
| `CRITICAL` | 500 | Erreurs critiques |
| `ALERT` | 550 | Action immédiate requise |
| `EMERGENCY` | 600 | Système inutilisable |

### Canaux et rotation

**Configuration `logger.config.php` :**

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
        'type'          => 'daily',  // 'daily' ou 'size'
        'max_file_size' => 10485760, // 10 Mo (pour type 'size')
    ],

    'archive' => [
        'enabled'   => true,
        'extension' => 'zip',
    ],
];
```

**Placeholders du format :**

| Placeholder | Description |
|-------------|-------------|
| `{%datetime%}` | Date et heure (`Y-m-d H:i:s`) |
| `{%level_name%}` | Niveau en majuscules |
| `{%level_code%}` | Code numérique du niveau |
| `{%origin%}` | Origine (paramètre `$origin`) |
| `{%message%}` | Message de log |
| `{%context%}` | Contexte JSON encodé |

### Utilisation

```php
$logger = $container->get(LoggerManager::class);

// Méthodes de convenance par niveau
$logger->debug('Requête SQL exécutée', ['sql' => $sql, 'time' => $ms]);
$logger->info('Utilisateur connecté', ['user_id' => 42], 'auth');
$logger->warning('Tentative de connexion échouée', ['ip' => $_SERVER['REMOTE_ADDR']]);
$logger->error('Paiement refusé', ['order_id' => 123, 'reason' => $msg], 'payment');
$logger->critical('Base de données inaccessible');
$logger->alert('Disque presque plein', ['usage' => '95%']);
$logger->emergency('Système en panne');

// Méthode générique
$logger->log('WARNING', 'Message personnalisé', ['key' => 'value'], 'origin');

// Canal spécifique
$logger->channel('security')->error('Accès non autorisé', ['path' => '/admin']);

// Canal via paramètre optionnel
$logger->info('Événement de sécurité', [], 'system', 'security');
```

**Rotation quotidienne :** Les fichiers sont nommés `app-2026-07-28.log`. Les anciens fichiers sont automatiquement archivés dans `{storagePath}/logs/archives/2026/07/2026-07-28.zip`.

**Rotation par taille :** Le fichier courant est renommé `app.log.{timestamp}` dès qu'il dépasse `max_file_size`.

### Extension contrôleur Logger

**Fichier :** `Logger/Extension/LoggerControllerExtension.php`

Ajoute la méthode `getLogger()` à tous les contrôleurs.

```php
class OrderController extends AbstractController
{
    public function checkout(int $orderId): Response
    {
        try {
            // ... traitement du paiement
            $this->getLogger()->info('Commande validée', ['order_id' => $orderId], 'payment');
        } catch (\Throwable $e) {
            $this->getLogger()->error('Échec paiement', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ], 'payment');
            throw $e;
        }

        return $this->redirect('/orders/' . $orderId);
    }
}
```

---

## Notification

### NotificationManager

**Fichier :** `Notification/NotificationManager.php`

Système de notifications multi-canaux avec patron **builder fluide**. Supporte l'email, Slack et SMS. Intègre le profiler automatiquement.

```php
$notif = $container->get(NotificationManager::class);

// Envoi d'un email
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
    ->setParams([
        'to'     => ['+33612345678', '+33687654321'],
        'driver' => 'twilio',  // Optionnel, utilise le driver par défaut sinon
    ])
    ->setTemplate('sms/code.html.twig', ['code' => '123456'])
    ->doSend();

// Résultat
if ($result === NotificationEnum::SUCCESS) {
    // Envoi réussi
} elseif ($result === NotificationEnum::PARTIAL) {
    // Envoi partiel (certains destinataires SMS ont échoué)
}
```

**Flux d'exécution de `doSend()` :**

1. Vérification qu'un canal est sélectionné.
2. Rendu du template via le moteur de vue du projet.
3. Appel de `channel->send()`.
4. Enregistrement dans le profiler (si actif).
5. Réinitialisation du builder pour la prochaine utilisation.

### EmailChannel

**Fichier :** `Notification/Channel/Email/EmailChannel.php`

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
// Envoi avec plusieurs destinataires et CC
$notif
    ->channel(EmailChannel::class)
    ->setParams([
        'to'      => ['alice@example.com', 'bob@example.com'],
        'cc'      => 'manager@example.com',
        'bcc'     => 'archive@example.com',
        'subject' => 'Rapport mensuel',
        'driver'  => 'smtp',
    ])
    ->setTemplate('emails/report.html.twig', ['data' => $reportData])
    ->doSend();
```

### SlackChannel

**Fichier :** `Notification/Channel/Slack/SlackChannel.php`

Envoie des messages via l'API Webhook Slack (Incoming Webhooks).

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

### SmsChannel

**Fichier :** `Notification/Channel/Sms/SmsChannel.php`

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
    ->setParams(['to' => ['+33600000001', '+33600000002', '+33600000003']])
    ->setTemplate('sms/promo.html.twig', ['discount' => '20%'])
    ->doSend();

match ($result) {
    NotificationEnum::SUCCESS => $logger->info('Tous les SMS envoyés'),
    NotificationEnum::PARTIAL => $logger->warning('Certains SMS ont échoué'),
    default => null,
};
```

### NotificationEnum

**Fichier :** `Notification/Enum/NotificationEnum.php`

```php
enum NotificationEnum: string
{
    case SUCCESS = 'success';   // Envoi entièrement réussi
    case FAILED  = 'failed';    // Envoi complètement échoué
    case PARTIAL = 'partial';   // Envoi partiellement réussi (SMS multi-destinataires)
    case SKIPPED = 'skipped';   // Envoi ignoré (canal désactivé)
}
```

---

## Scanner

### ScannerAttributeManager

**Fichier :** `Scanner/ScannerAttributeManager.php`

Outil de réflexion pour découvrir et lire les attributs PHP 8 sur une classe, ses méthodes, ses propriétés et les paramètres de ses méthodes.

```php
use Neo\Core\Utils\Scanner\ScannerAttributeManager;

$scanner = new ScannerAttributeManager(MyController::class);

// Configurer ce qu'on veut scanner
$results = $scanner
    ->onClass()           // Scanner la classe elle-même
    ->onMethods()         // Scanner les méthodes
    ->onProperties()      // Scanner les propriétés
    ->onParameters()      // Scanner les paramètres de méthodes
    ->withAttribute(Route::class) // Filtrer par attribut spécifique
    ->scan();

// Résultat : liste d'entrées
foreach ($results as $entry) {
    echo $entry['type'];       // 'class', 'method', 'property', 'parameter'
    echo $entry['target'];     // ex: 'MyController::index()'
    $attr = $entry['attribute']; // Instance de l'attribut (ex: Route)
    $args = $entry['arguments']; // Arguments bruts du constructeur
    $refl = $entry['reflection']; // ReflectionClass|ReflectionMethod|...
}
```

**Scanner tous les attributs sans filtre :**

```php
$results = (new ScannerAttributeManager(MyClass::class))
    ->onAll()
    ->withAllAttributes()
    ->scan();
```

**Scanner uniquement les méthodes publiques :**

```php
use ReflectionMethod;

$results = (new ScannerAttributeManager(MyClass::class))
    ->onMethods(ReflectionMethod::IS_PUBLIC)
    ->withAttribute(Route::class)
    ->scan();
```

**Cas d'usage : découverte de routes**

```php
// Le framework utilise ScannerAttributeManager pour découvrir les routes
$scanner = new ScannerAttributeManager(HomeController::class);
$routes  = $scanner
    ->onMethods(ReflectionMethod::IS_PUBLIC)
    ->withAttribute(Route::class)
    ->scan();

foreach ($routes as $entry) {
    /** @var Route $route */
    $route  = $entry['attribute'];
    $method = $entry['reflection']; // ReflectionMethod

    echo sprintf(
        '%s %s → %s::%s',
        $route->method,
        $route->path,
        HomeController::class,
        $method->getName()
    );
}
```

**Cas d'usage : injection par attribut**

```php
// Exemple d'un système d'injection de dépendances via attributs
$scanner = new ScannerAttributeManager(MyService::class);
$injects = $scanner
    ->onProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED)
    ->withAttribute(Inject::class)
    ->scan();

foreach ($injects as $entry) {
    /** @var ReflectionProperty $prop */
    $prop    = $entry['reflection'];
    $inject  = $entry['attribute'];  // Instance de Inject
    $service = $container->get($inject->type);
    $prop->setValue($instance, $service);
}
```

**Structure d'un résultat `scan()` :**

```php
[
    'target'     => 'MyController::index()',    // Étiquette lisible
    'attribute'  => /* instance de l'attribut */,
    'arguments'  => ['/home', 'GET'],           // Arguments bruts du constructeur
    'type'       => 'method',                   // 'class'|'method'|'property'|'parameter'
    'reflection' => /* ReflectionMethod */,     // Objet de réflexion
]
```

---

## Points techniques importants

### Système de modules

Chaque composant de Utils est encapsulé dans un `Module` (ex: `CacheModule`, `ConfigModule`, `LoggerModule`). Ces modules sont enregistrés dans le conteneur DI via `register()` et initialisés via `init()`. Les extensions de contrôleur sont des `ControllerExtensionInterface` avec l'attribut `#[Extension(type: ExtensionTypeEnum::CONTROLLER)]`.

### Extensions de contrôleur

Les classes `*ControllerExtension` injectent des méthodes dynamiques dans `AbstractController` via `registerMethod()`. La signature PHP Doc (`@method`) assure la complétion IDE :

```php
/** @method \Neo\Core\Utils\Cache\CacheManager getCache() */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class CacheControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getCache', fn() => $container->get(CacheManager::class));
    }
}
```

### Profiler intégré

Le `LoggerManager` et le `NotificationManager` s'intègrent au profiler de NeoPHP lorsque `NEO_PROFILER_ENABLED` est défini. Les logs et les envois de notifications sont automatiquement collectés et affichables dans le panneau de débogage.

### Cache "remember"

La méthode `CacheManager::remember()` implémente le patron **cache-aside** en une seule ligne :

```php
// Sans remember (verbeux)
$key = 'stats:daily';
if (!$cache->has($key)) {
    $value = computeExpensiveStats();
    $cache->set($key, $value, 900);
} else {
    $value = $cache->get($key);
}

// Avec remember (concis)
$value = $cache->remember('stats:daily', 900, fn() => computeExpensiveStats());
```

### Notifications et templates

Le `NotificationManager` utilise le moteur de vue du projet (injecté comme `notification.viewModule`) pour rendre les templates avant envoi. Cela permet d'utiliser Twig ou tout autre moteur pour les corps des notifications.

### Logs et archivage automatique

Le `LoggerManager` détecte automatiquement les anciens fichiers (non actifs) et les archive en ZIP organisé par `{année}/{mois}/{date}.zip`. L'archivage se produit de manière transparente à chaque appel de `log()`.

### Sécurité Redis

Le `RedisDriver` utilise `unserialize($raw, ['allowed_classes' => true])` contrairement au `FileDriver` qui désactive les classes (`['allowed_classes' => false]`). En environnement Redis partagé, il est recommandé de définir un `prefix` unique par application pour éviter les collisions de clés.

### Scanner et performance

`ScannerAttributeManager` crée des instances d'attributs via `ReflectionAttribute::newInstance()`. Pour les scans fréquents (ex: découverte de routes au démarrage), il est recommandé de mettre les résultats en cache dans le `CacheManager` (driver `array` ou `files`) pour éviter le coût de la réflexion à chaque requête.
