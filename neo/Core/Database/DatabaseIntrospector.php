<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use PDO;
use PDOException;

class DatabaseIntrospector
{
    private PDO $pdo;

    public function __construct(Container $container)
    {
        $this->pdo = DatabaseConnection::getPdo();
    }

    public function getTables(): array
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES");

            if ($stmt === false) {
                throw new FrameworkException(
                    title: 'Database Introspection Error',
                    message: "Impossible de récupérer la liste des tables.",
                    code: 500
                );
            }

            $tables = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            return $tables;
        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Database Introspection Error',
                message: "Erreur lors de la récupération des tables : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }

    public function getColumns(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare("DESCRIBE `{$table}`");
            $stmt->execute();

            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = [
                    'name'     => $row['Field'],
                    'type'     => $row['Type'],
                    'nullable' => $row['Null'] === 'YES',
                    'default'  => $row['Default'],
                    'key'      => $row['Key'],
                    'extra'    => $row['Extra'],
                ];
            }

            return $columns;
        } catch (PDOException $e) {
            throw new FrameworkException(
                title: 'Database Introspection Error',
                message: "Erreur lors de la récupération des colonnes de '{$table}' : " . $e->getMessage(),
                code: 500,
                previous: $e
            );
        }
    }
}