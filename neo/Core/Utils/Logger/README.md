# Logger — NeoPHP

Le sous-module `Logger` fournit un système de journalisation structuré avec support des niveaux RFC 5424, des canaux nommés, de la rotation des fichiers et de l'archivage automatique.

---

## Sommaire

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Niveaux de log](#niveaux-de-log)
4. [Utilisation](#utilisation)
5. [Canaux](#canaux)
6. [Rotation et archivage](#rotation-et-archivage)
7. [Extension contrôleur](#extension-contrôleur)
8. [Intégration Profiler](#intégration-profiler)

---

## Structure

```
Logger/
├── LoggerManager.php                   # Gestionnaire principal
├── LoggerModule.php                    # Enregistrement DI
├── Collector/
│   └── LogCollector.php               # Collecteur Profiler
└── Extension/
    └── LoggerControllerExtension.php  # Injecte getLogger() dans les contrôleurs
```

---

## Configuration

**Fichier :** `src/<Projet>/Config/logger.config.php`

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
        'type'          => 'daily',   // 'daily' ou 'size'
        'max_file_size' => 10485760,  // 10 Mo (pour type 'size')
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
| `{%origin%}` | Paramètre `$origin` passé à la méthode |
| `{%message%}` | Message de log |
| `{%context%}` | Contexte JSON encodé |

---

## Niveaux de log

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

---

## Utilisation

```php
$logger = $container->get(LoggerManager::class);

// Méthodes de convenance par niveau
$logger->debug('Requête SQL exécutée', ['sql' => $sql, 'time' => $ms]);
$logger->info('Utilisateur connecté', ['user_id' => 42], 'auth');
$logger->notice('Tentative de connexion', ['ip' => $_SERVER['REMOTE_ADDR']]);
$logger->warning('Token proche de l\'expiration', ['user_id' => 42]);
$logger->error('Paiement refusé', ['order_id' => 123, 'reason' => $msg], 'payment');
$logger->critical('Base de données inaccessible');
$logger->alert('Disque presque plein', ['usage' => '95%']);
$logger->emergency('Système en panne');

// Méthode générique
$logger->log('WARNING', 'Message personnalisé', ['key' => 'value'], 'origin');
```

---

## Canaux

```php
// Canal spécifique via méthode chaînée
$logger->channel('security')->error('Accès non autorisé', ['path' => '/admin']);

// Canal via paramètre optionnel (4e argument)
$logger->info('Événement de sécurité', [], 'system', 'security');
```

Les fichiers de log sont stockés dans `src/<Projet>/Storage/logs/`.

---

## Rotation et archivage

**Rotation quotidienne** (`type: 'daily'`) : les fichiers sont nommés `app-2026-07-30.log`. À chaque appel de `log()`, les anciens fichiers (non actifs) sont automatiquement archivés.

**Rotation par taille** (`type: 'size'`) : le fichier courant est renommé `app.log.{timestamp}` dès qu'il dépasse `max_file_size`.

**Archivage** : les anciens fichiers sont compressés en ZIP dans `{storagePath}/logs/archives/{année}/{mois}/{date}.zip`.

---

## Extension contrôleur

**Fichier :** `Extension/LoggerControllerExtension.php`

Injecte automatiquement `getLogger()` dans tous les contrôleurs.

```php
class OrderController extends AbstractController
{
    #[Route('/orders/{id}/checkout', 'POST')]
    public function checkout(int $id): Response
    {
        try {
            // ... traitement du paiement
            $this->getLogger()->info('Commande validée', ['order_id' => $id], 'payment');
        } catch (\Throwable $e) {
            $this->getLogger()->error('Échec paiement', [
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

## Intégration Profiler

`LoggerManager` s'intègre au profiler de NeoPHP lorsque `NEO_PROFILER_ENABLED` est défini. Les logs sont automatiquement collectés via `LogCollector` et affichables dans le panneau de débogage.
