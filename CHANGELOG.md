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