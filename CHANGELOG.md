v4.23.1
- fix: add /packages/ to gitignore (9a4de93)

v4.23.0
- feat: expose RoleConfig from AuthManager for third-party role resolution (7a73995)

v4.22.1
- docs: fix reduce text (1e46ee8)

v4.22.0
- docs: document package assets convention and PackageAssetController (b7ba716)
- feat: add PackageAssetController to serve package static assets (aea1c5e)
- feat: add getAssetsPath to PackageInterface and AbstractPackage (a38650a)
- docs: replace dangling hello-package reference with a full inline example in Package README (849d6f8)
- build: allow wikimedia/composer-merge-plugin in composer.json (0c891fb)
- feat: seed composer.local.json from template if missing in ProjectCreateCommand (aa0b720)
- build: add composer.local.json.dist template (43e9dbc)
- feat: register new projects in composer.local.json instead of composer.json (b54cf45)
- chore: ignore composer.local.json (768fb1f)
- build: add wikimedia/composer-merge-plugin to merge composer.local.json (6cdcb36)

v4.21.0
- fix: move @var docblock above the assignment instead of the foreach to satisfy PHPStan (4e91110)
- fix: set default composer (a253d63)
- feat: add package:require command to install packages and detect NeoPHP PackageInterface classes (0fc84ce)
- feat: add withExcludedSegment to ScannerFileManager to prune directories during scan (5a3b588)
- feat: add packages key to generated app.config.php template (f9161d5)
- build: register hello-package as a local path repository (2c25128)
- feat: load package config files from Config/Packages with project priority (ae27401)
- feat: report migration status across project and package paths in DatabaseMigrationStatusCommand (baa9d12)
- feat: pass project and package paths to MigrationRunner rollback in DatabaseMigrationRollbackCommand (267ac2e)
- feat: run migrations from project and package paths in DatabaseMigrationMigrateCommand (5c743ff)
- feat: search all project and package paths before rollback in MigrationRunner (2b34815)
- feat: scan project and package cron paths in CronRunCommand (2e117f5)
- feat: scan project and package cron paths in CronListCommand (89962ca)
- refactor: make CronScanner stateless and accept multiple paths (452917f)
- feat: add PackageModule dependency to ConsoleModule (1969973)
- refactor: split static and instance command scanning in ConsoleManager (01dc91c)
- feat: add PackageModule dependency to ExtensionModule (c849a53)
- refactor: use ScannerFileManager in ExtensionManager and append package paths (c128a03)
- feat: add PackageModule dependency to EventModule (bc32832)
- refactor: use ScannerFileManager in EventManager and append package listener paths (80171fa)
- feat: add PackageModule dependency to ViewModule (e5c0832)
- refactor: use ScannerFileManager in ViewManager and register package view namespaces (9a28b36)
- feat: add PackageModule dependency to RouterModule (e042554)
- refactor: use ScannerFileManager in RouterManager and append package controller paths (b5f207a)
- docs: add Package module README (31ce696)
- feat: add PackageModule to register and wire declared packages (26cb527)
- feat: add PackageManager config copy helper (0129803)
- feat: add AbstractPackage with convention-based path resolution (347bf12)
- feat: add PackageException (b636c2d)
- feat: add PackageInterface contract (6010fbd)

v4.20.8
- fix: resolve PHPStan errors (4b217de)
- docs: document ScannerFileManager and getFileScanner in Utils README (1960d60)
- docs: document ScannerFileManager and FileScanResult in Scanner README (6ed3b84)
- refactor: use ScannerFileManager in CronScanner::scan (a4a95c0)
- refactor: use ScannerFileManager in ConsoleManager command discovery (45aec32)
- refactor: use ScannerFileManager in ExtensionManager::discover (3e24410)
- refactor: use ScannerFileManager in EventManager::scanListeners (961bbed)
- refactor: use ScannerFileManager in RouterManager::scanControllers (8a0de60)
- refactor: use ScannerFileManager in ModuleManager::discover (4e63d34)
- refactor: rename getScanner to getFileScanner in ScannerControllerExtension PHPDoc (607978d)

v4.20.7
- fix: use symlink instead of mirror for project path repositories (ec55f18)

v4.20.6
- fix: DatabaseConnection should not fail when database.config.php is missing (3ecda78)

v4.20.5
- fix: remove auth from gitignore (b01b8fd)

v4.20.4
- fix: use AttributeScanResult getters instead of array access in ConsoleManager (2938884)

v4.20.3
- fix: replace "Table of Contents" to "Summary" (38290dc)

v4.20.2
- doc: update STARTUP (8234539)

v4.20.1
- docs: translate all README, STARTUP and CONTRIBUTION files (3bc4bf8)

v4.20.0
- docs: update ROADMAP (4939cb5)
- docs: document Paginator, QueryBuilder::paginate() and paginator_links in README (5605142)
- feat: add paginator_links Twig extension for rendering pagination navigation (7e930b1)
- feat: add EntityRepository::paginate() for entity-based pagination (6cde3ea)
- feat: add QueryBuilder::paginate() using a cloned builder for the count query (4a23c22)
- feat: add standalone Paginator with pagination metadata and link windowing (8d08f47)

v4.19.0
- docs: update ROADMAP (5669e0b)
- docs: document persistent connections and transaction leak warning (2a9481c)
- feat: add persistan option key (4ffd28c)
- feat: support persistent PDO connections via config option (2182fdf)

v4.18.2
- docs: update ROADMAP (d2a2275)

v4.18.1
- fix: buffer output during dispatch to prevent premature flush before headers are sent (7070295)

v4.18.0
- fix: remove redundant isAbstract() check and use AttributeScanResult getters in ConsoleManager (761732f)
- docs: update ROADMAP (d3c04c6)
- docs: document DatabaseIntrospector and its metadata DTOs in README (7343a07)
- refactor: convert ColumnMetadata to array in MigrationGenerator::generate() (daa18da)
- refactor: convert ColumnMetadata to array at JSON snapshot boundary in MigrationSchemaSnapshot (d32c9fa)
- refactor: return metadata DTOs from DatabaseIntrospector (a39184e)
- feat: add IndexMetadata DTO (d5666fd)
- feat: add ForeignKeyMetadata DTO (abc80d1)
- feat: add ColumnMetadata DTO (4779cd8)

v4.17.1
- fix: migrate remaining ScannerAttributeManager consumers to AttributeScanResult getters (79e1c54)

v4.17.0
- docs: update ROADMAP (7e1e058)
- docs: document AttributeScanResult DTO in Scanner README (844b312)
- refactor: consume AttributeScanResult in RouterManager, CronScanner, TestScanner and MiddlewareManager (a878659)
- refactor: return AttributeScanResult instead of array shape in ScannerAttributeManager (bf1ccde)
- feat: add AttributeScanResult DTO (7e52e7d)

v4.16.1
- fix: accumulate changelog entries instead of overwriting on each release (1aef400)

- docs: update ROADMAP (d9acce7)
- docs: document RoleConfig DTO in Auth README (cec95a5)
- refactor: build RoleConfig from config array in AuthManager (df9022e)
- refactor: use RoleConfig instead of raw array in TokenGuard (ec60677)
- refactor: use RoleConfig instead of raw array in SessionGuard (dfdebc3)
- feat: add RoleConfig DTO shared between auth guards (7d41c9c)