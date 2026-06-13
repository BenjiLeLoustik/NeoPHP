<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Model;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ModelGenerator
{
    protected Container $container;
    private string $appName;
    private string $modelDir;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DatabaseException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->appName = $this->container->get('application');
        $this->modelDir = $this->container->get('modelPath');

        if (!is_dir($this->modelDir) && !mkdir($this->modelDir, 0777, true) && !is_dir($this->modelDir)) {
            throw new DatabaseException(
                title: 'Model Generator Error',
                message: sprintf("Unable to create the models directory '%s'.", $this->modelDir),
                code: 500
            );
        }
    }

    /**
     * @throws DatabaseException
     */
    public function generate(string $table, array $columns, bool $write = true): string
    {
        $className = $this->convertToClassName($table);
        $file = "{$this->modelDir}/$className.php";

        $modelData = [
            'table' => $table,
            'columns' => []
        ];

        $header = <<<PHP
<?php
declare(strict_types=1);

namespace Neo\\Src\\{$this->appName}\\Model;

use Neo\Core\Database\ORM\Model\AbstractModel;
PHP;

        $existingColumns = ['columns' => []];
        if (file_exists($file)) {
            [$header, $existingColumns] = $this->parseExistingFile(file_get_contents($file));
        }

        $columnsFromBDD = [];
        foreach ($columns as $col) {
            $name = $col['name'];
            $existing = ($existingColumns['columns'] ?? [])[$name] ?? null;
            $columnsFromBDD[$name] = $this->createColumnArray($col, $existing);
        }

        $customColumns = [];
        foreach ($existingColumns['columns'] as $name => $col) {
            if (isset($columnsFromBDD[$name])) continue;

            $isCustomVisibility = in_array($col['visibility'] ?? 'public', ['protected', 'private'], true);
            $hasAttribute = str_contains($col['docblock'], '#[');

            if ($isCustomVisibility || $hasAttribute) {
                $customColumns[$name] = $col;
            }
        }

        $modelData['columns'] = array_merge($columnsFromBDD, $customColumns);

        if (!$write) {
            return $className;
        }

        return $this->writeModelFile($file, $className, $modelData, $header);
    }

    private function createColumnArray(array $col, ?array $existing = null): array
    {
        $type = $this->convertColumnType($col['type']);
        $nullable = ($col['nullable'] || $type === '\\DateTime');

        if (
            ($col['key'] ?? '') === 'PRI'
            && str_contains(strtolower($col['extra'] ?? ''), 'auto_increment')
        ) {
            $nullable = true;
            $default = null;
        } else {
            $default = $col['default'] ?? null;
        }

        return [
            'docblock' => $existing['docblock'] ?? '',
            'type' => $type,
            'nullable' => $existing['nullable'] ?? $nullable,
            'default' => $default,
            'visibility' => $existing['visibility'] ?? 'public',
            'defaultValue' => $existing['defaultValue'] ?? '',
        ];
    }

    private function convertToClassName(string $table): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
    }

    private function convertColumnType(string $sqlType): string
    {
        $sqlType = strtolower($sqlType);
        if (str_contains($sqlType, 'enum')) {
            return 'string';
        }

        if (str_contains($sqlType, 'point')) {
            return 'string';
        }

        if (str_contains($sqlType, 'int')) {
            return 'int';
        }

        if (str_contains($sqlType, 'float') || str_contains($sqlType, 'double') || str_contains($sqlType, 'decimal')) {
            return 'float';
        }

        if (str_contains($sqlType, 'bool')) {
            return 'bool';
        }

        if (str_contains($sqlType, 'datetime') || str_contains($sqlType, 'timestamp') || str_contains($sqlType, 'date')) {
            return '\\DateTime';
        }

        return 'string';
    }

    private function parseExistingFile(string $content): array
    {
        $header = '';
        $contentArray = ['columns' => []];

        if (preg_match('/^(<\?php[\s\S]*?)class\s+\w+\s+extends\s+AbstractModel\s*{/', $content, $m)) {
            $header = trim($m[1]);
            $rest = str_replace($m[0], '', $content);
        } else {
            $rest = $content;
        }

        $lines = preg_split("/\r?\n/", $rest);
        $buffer = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || preg_match('/protected\s+static\s+\?string\s+\$table/', $trim)) {
                continue;
            }

            if (preg_match('/^\s*(\/\*\*[\s\S]*?\*\/|#\[[\w\\\\\(\):, ]+\]|#[\w\\\\\(\)]+)\s*$/', $trim)) {
                $buffer[] = $line;
                continue;
            }

            if (preg_match('/^\s*(public|protected|private)\s+([?\w\\\\]+)\s+\$(\w+)(\s*=\s*[^;]+)?/', $trim, $m2)) {
                $visibility = $m2[1];
                $rawType = $m2[2];
                $nullable = str_starts_with($rawType, '?');
                $type = ltrim($rawType, '?');
                $colName = $m2[3];
                $defaultValue = $m2[4] ?? '';

                $contentArray['columns'][$colName] = [
                    'docblock' => implode("\n", $buffer),
                    'type' => $type,
                    'nullable' => $nullable,
                    'visibility' => $visibility,
                    'defaultValue' => $defaultValue,
                ];

                $buffer = [];
            } else {
                if (!empty($trim)) {
                    $buffer[] = $line;
                }
            }
        }

        return [$header, $contentArray];
    }

    /**
     * @throws DatabaseException
     */
    private function writeModelFile(string $file, string $className, array $modelData, string $header = ''): string
    {
        $hasHidden = isset($modelData['columns']['hidden']);
        $hiddenLine = !$hasHidden ? "protected array \$hidden = [];" : '';

        $columns = $modelData['columns'];
        unset($columns['hidden']);

        $props = [];
        foreach ($columns as $colName => $col) {
            $lines = [];

            if (!empty($col['docblock'])) {
                $lines[] = $col['docblock'];
            }

            $nullableSign = ($col['nullable'] ?? false) ? '?' : '';
            $default = $col['defaultValue'] ?? '';

            if ($default === '') {
                if ($col['nullable'] ?? false) {
                    $default = ' = null';
                } elseif ($col['type'] === 'bool') {
                    $default = ' = ' . (($col['default'] ?? false) ? 'true' : 'false');
                } elseif ($col['type'] === 'int' || $col['type'] === 'float') {
                    $default = ' = ' . ($col['default'] ?? 0);
                } elseif (isset($col['default'])) {
                    $default = ' = ' . var_export($col['default'], true);
                }
            }

            $visibility = $col['visibility'] ?? 'public';
            $lines[] = "    {$visibility} {$nullableSign}{$col['type']} \${$colName}{$default};";
            $props[] = implode("\n", $lines);
        }

        $propsString = implode("\n\n", $props);

        if ($hasHidden) {
            $col = $modelData['columns']['hidden'];
            $default = trim($col['defaultValue'] ?? '= []');
            $hiddenLine = "protected array \$hidden {$default};";
        }

        $header = rtrim($header);
        $classAttributes = '';

        if (preg_match_all('/^\s*#\[.*\]\s*$/m', $header, $matches)) {
            $classAttributes = implode("\n", $matches[0]);
            $header = preg_replace('/^\s*#\[.*\]\s*$/m', '', $header);
            $header = rtrim($header);
        }

        $content = <<<PHP
$header
$classAttributes
class {$className} extends AbstractModel 
{
    protected static ?string \$table = '{$modelData['table']}';
    {$hiddenLine}
    
$propsString
}
PHP;

        if (file_put_contents($file, $content) === false) {
            throw new DatabaseException(
                title: 'Model Generator Error',
                message: sprintf("Unable to write the model file '%s'.", $file),
                code: 500
            );
        }

        return $className;
    }
}