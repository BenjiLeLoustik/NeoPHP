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