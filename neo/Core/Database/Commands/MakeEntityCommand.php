<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:entity',
    description: 'Create a Data Mapper entity (POPO with mapping attributes)',
    category: 'Database',
)]
final class MakeEntityCommand extends AbstractCommand
{
    private const array SCALAR_TYPES = [
        'string', 'text', 'integer', 'bigint', 'smallint',
        'boolean', 'float', 'decimal', 'datetime', 'date', 'time', 'json',
    ];

    private const array RELATION_TYPES = [
        'onetoone' => 'OneToOne',
        'manytoone' => 'ManyToOne',
        'onetomany' => 'OneToMany',
        'manytomany' => 'ManyToMany',
    ];

    public function configure(): void
    {
        $this->addArgument(
            name: 'entity',
            description: 'Entity name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite the file if it exists',
        );

        $this->addOption(
            name: 'no-repository',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Do not generate a repository class for the entity',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $entity = $input->getArgument('entity') ?? Input::ask('Entity name ?');
        if (!$entity) {
            Output::error('Entity name is required.');
            return ExitCode::INVALID;
        }

        $project = $input->getOption('project');
        $force = (bool) $input->getOption('force');

        $entity = Fs::pascalCase($entity);
        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        $entityDir = "$basePath/Database/Entity";
        Fs::ensureDir($entityDir);
        $path = "$entityDir/$entity.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Entity '$entity' exists. Overwrite ?", false)) {
                Output::warning('Aborted.');
                return ExitCode::SUCCESS;
            }
        }

        Output::title("New entity: $entity");

        $fields = $this->collectFields();

        $withRepository = !(bool) $input->getOption('no-repository');
        $repositoryName = $withRepository ? $entity . 'Repository' : null;
        $repositoryFqcn = $withRepository ? "Neo\\Src\\$project\\Database\\Repository\\$repositoryName" : null;

        $namespace = "Neo\\Src\\$project\\Database\\Entity";
        $code = $this->render($namespace, $entity, $fields, $repositoryName, $repositoryFqcn);

        file_put_contents($path, $code);
        Output::success("Entity '$entity' generated at $path");

        if ($withRepository) {
            $repositoryDir = "$basePath/Database/Repository";
            Fs::ensureDir($repositoryDir);
            $repositoryPath = "$repositoryDir/$repositoryName.php";

            if (file_exists($repositoryPath)) {
                Output::muted("Repository already exists, kept: $repositoryPath");
            } else {
                file_put_contents(
                    $repositoryPath,
                    $this->renderRepository(
                        "Neo\\Src\\$project\\Database\\Repository",
                        $repositoryName,
                        $namespace,
                        $entity
                    )
                );
                Output::success("Repository '$repositoryName' generated at $repositoryPath");
            }
        }

        Output::muted("Run your schema diff command to create/update the '" . $this->tableName($entity) . "' table.");

        return ExitCode::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectFields(): array
    {
        $fields = [];

        while (true) {
            Output::newLine();

            $name = 'New property name (press <return> to stop adding fields)'
                    |> Input::ask(...)
                    |> trim(...);

            if ($name === '') {
                break;
            }

            $name = $name
                    |> Fs::pascalCase(...)
                    |> lcfirst(...);

            $type = $this->askType();

            $fields[] = match ($type) {
                'OneToOne' => $this->askOneToOne($name),
                'ManyToOne' => $this->askManyToOne($name),
                'OneToMany' => $this->askOneToMany($name),
                'ManyToMany' => $this->askManyToMany($name),
                default => $this->askScalar($name, $type),
            };
        }

        return $fields;
    }

    private function askType(): string
    {
        while (true) {
            Output::newLine();
            $type = $this->askWithDefault('Field type (enter ? to see all types) [string]:', 'string');

            if ($type === '?') {
                $this->printTypes();
                continue;
            }

            $canonical = $this->normalizeType($type);
            if ($canonical !== null) {
                return $canonical;
            }

            Output::error(sprintf('Invalid type "%s". Enter ? to see all available types.', $type));
        }
    }

    private function printTypes(): void
    {
        Output::newLine();
        Output::label('Main', 'string, text, boolean, integer, bigint, smallint, float, decimal');
        Output::label('Date/Time', 'datetime, date, time');
        Output::label('Other', 'json');
        Output::label('Relation', 'OneToOne, ManyToOne, OneToMany, ManyToMany');
    }

    private function normalizeType(string $type): ?string
    {
        $lower = strtolower($type);

        if (in_array($lower, self::SCALAR_TYPES, true)) {
            return $lower;
        }

        return self::RELATION_TYPES[$lower] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function askScalar(string $name, string $type): array
    {
        $length = null;
        if ($type === 'string') {
            Output::newLine();
            $length = (int) $this->askWithDefault('Field length [255]:', '255');
        }

        Output::newLine();
        $nullable = $this->askBool('Can this field be null in the database (nullable)', false);

        return [
            'kind' => 'scalar',
            'name' => $name,
            'type' => $type,
            'nullable' => $nullable,
            'length' => $length
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function askManyToOne(string $name): array
    {
        $target = $this->askTarget();

        Output::newLine();
        $nullable = $this->askBool("Is the '$name' relation nullable", true);

        Output::newLine();
        $inversedBy = $this->askOptionalField(
            sprintf('Field name on %s that maps back (press <return> to skip):', $target)
        );

        return [
            'kind' => 'manyToOne',
            'name' => $name,
            'target' => $target,
            'nullable' => $nullable,
            'inversedBy' => $inversedBy
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function askOneToMany(string $name): array
    {
        $target = $this->askTarget();

        Output::newLine();
        $mappedBy = $this->askRequiredField(
            sprintf('Field on %s that owns the relation (the ManyToOne side):', $target)
        );

        return [
            'kind' => 'oneToMany',
            'name' => $name,
            'target' => $target,
            'mappedBy' => $mappedBy
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function askOneToOne(string $name): array
    {
        $target = $this->askTarget();

        Output::newLine();
        $owning = $this->askBool('Is this the owning side (this entity holds the foreign key)', true);

        if ($owning) {
            Output::newLine();
            $nullable = $this->askBool("Is the '$name' relation nullable", true);

            Output::newLine();
            $inversedBy = $this->askOptionalField(
                sprintf('Field name on %s that maps back (press <return> to skip):', $target)
            );

            return [
                'kind' => 'oneToOne',
                'name' => $name,
                'target' => $target,
                'owning' => true,
                'nullable' => $nullable,
                'inversedBy' => $inversedBy,
                'mappedBy' => null
            ];
        }

        Output::newLine();
        $mappedBy = $this->askRequiredField(
            sprintf('Field on %s that owns the relation:', $target)
        );

        return [
            'kind' => 'oneToOne',
            'name' => $name,
            'target' => $target,
            'owning' => false,
            'nullable' => true,
            'inversedBy' => null,
            'mappedBy' => $mappedBy
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function askManyToMany(string $name): array
    {
        $target = $this->askTarget();

        Output::newLine();
        $owning = $this->askBool('Is this the owning side (this entity manages the join table)', true);

        if ($owning) {
            Output::newLine();
            $inversedBy = $this->askOptionalField(
                sprintf('Field name on %s that maps back (press <return> to skip):', $target)
            );

            Output::newLine();
            $joinTable = $this->askOptionalRaw('Join table name (press <return> for the default):');

            return [
                'kind' => 'manyToMany',
                'name' => $name,
                'target' => $target,
                'owning' => true,
                'inversedBy' => $inversedBy,
                'mappedBy' => null,
                'joinTable' => $joinTable
            ];
        }

        Output::newLine();
        $mappedBy = $this->askRequiredField(
            sprintf('Field on %s that owns the relation:', $target)
        );

        return [
            'kind' => 'manyToMany',
            'name' => $name,
            'target' => $target,
            'owning' => false,
            'inversedBy' => null,
            'mappedBy' => $mappedBy,
            'joinTable' => null
        ];
    }

    private function askTarget(): string
    {
        Output::newLine();
        return Fs::pascalCase($this->askRequiredRaw('Related entity (target) name:'));
    }

    private function askWithDefault(string $question, string $default): string
    {
        $answer = $question
                |> Input::ask(...)
                |> trim(...);

        return $answer === '' ? $default : $answer;
    }

    private function askBool(string $label, bool $default): bool
    {
        $def = $default ? 'yes' : 'no';
        $answer = sprintf('%s (yes/no) [%s]:', $label, $def)
                |> (fn($x) => $this->askWithDefault($x, $def))
                |> strtolower(...);

        return in_array($answer, ['y', 'yes', '1', 'true', 'o', 'oui'], true);
    }

    private function askRequiredRaw(string $question): string
    {
        while (true) {
            $answer = $question
                    |> Input::ask(...)
                    |> trim(...);

            if ($answer !== '') {
                return $answer;
            }
            Output::error('This value is required.');
        }
    }

    private function askRequiredField(string $question): string
    {
        return $question
                |> $this->askRequiredRaw(...)
                |> Fs::pascalCase(...)
                |> lcfirst(...);
    }

    private function askOptionalRaw(string $question): ?string
    {
        $answer = $question
                |> Input::ask(...)
                |> trim(...);

        return $answer === '' ? null : $answer;
    }

    private function askOptionalField(string $question): ?string
    {
        $raw = $this->askOptionalRaw($question);

        return $raw === null
            ? null
            : ($raw |> Fs::pascalCase(...) |> lcfirst(...));
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private function render(
        string $namespace,
        string $entity,
        array $fields,
        ?string $repositoryName = null,
        ?string $repositoryFqcn = null
    ): string {
        $uses = [
            'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\Column;',
            'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\Entity;',
            'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\GeneratedValue;',
            'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\Id;',
            'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\Table;',
        ];

        if ($repositoryFqcn !== null) {
            $uses[] = "use $repositoryFqcn;";
        }

        $needs = [
            'ManyToOne' => false,
            'OneToMany' => false,
            'OneToOne' => false,
            'ManyToMany' => false,
            'JoinColumn' => false,
            'JoinTable' => false,
            'Collection' => false,
        ];

        $props = [];
        $methods = [];
        $ctorLines = [];

        $props[] = "    #[Id]\n    #[GeneratedValue]\n    #[Column(type: 'integer', unsigned: true)]\n    private ?int \$id = null;";
        $methods[] = "    public function getId(): ?int\n    {\n        return \$this->id;\n    }";

        foreach ($fields as $field) {
            switch ($field['kind']) {
                case 'scalar':
                    $props[] = $this->renderScalarProp($field);
                    $methods[] = $this->renderScalarAccessors($field);
                    break;

                case 'manyToOne':
                    $needs['ManyToOne'] = true;
                    $needs['JoinColumn'] = true;
                    $props[] = $this->renderManyToOneProp($field);
                    $methods[] = $this->renderToOneAccessors($field);
                    break;

                case 'oneToOne':
                    $needs['OneToOne'] = true;
                    if ($field['owning']) {
                        $needs['JoinColumn'] = true;
                    }
                    $props[] = $this->renderOneToOneProp($field);
                    $methods[] = $this->renderToOneAccessors($field);
                    break;

                case 'oneToMany':
                    $needs['OneToMany'] = true;
                    $needs['Collection'] = true;
                    $props[] = $this->renderOneToManyProp($field);
                    $methods[] = $this->renderCollectionAccessors($field);
                    $ctorLines[] = "        \$this->{$field['name']} = new Collection();";
                    break;

                case 'manyToMany':
                    $needs['ManyToMany'] = true;
                    $needs['Collection'] = true;
                    if ($field['owning']) {
                        $needs['JoinTable'] = true;
                    }
                    $props[] = $this->renderManyToManyProp($field);
                    $methods[] = $this->renderCollectionAccessors($field);
                    $ctorLines[] = "        \$this->{$field['name']} = new Collection();";
                    break;
            }
        }

        $attributeMap = [
            'ManyToOne' => 'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\ManyToOne;',
            'OneToMany' => 'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\OneToMany;',
            'OneToOne' => 'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\OneToOne;',
            'ManyToMany' => 'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\ManyToMany;',
            'JoinColumn' => 'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\JoinColumn;',
            'JoinTable' => 'use Neo\\Core\\Database\\ORM\\Mapping\\Attribute\\JoinTable;',
            'Collection' => 'use Neo\\Core\\Database\\ORM\\Collection\\Collection;',
        ];
        foreach ($attributeMap as $key => $useLine) {
            if ($needs[$key]) {
                $uses[] = $useLine;
            }
        }
        sort($uses);

        $ctor = '';
        if ($ctorLines !== []) {
            $ctorBody = implode("\n", $ctorLines);
            $ctor = "\n    public function __construct()\n    {\n$ctorBody\n    }\n";
        }

        $table = $this->tableName($entity);
        $entityAttr = $repositoryName !== null
            ? "#[Entity(repositoryClass: {$repositoryName}::class)]"
            : '#[Entity]';
        $usesBlock = implode("\n", $uses);
        $propsBlock = implode("\n\n", $props);
        $methodsBlock = implode("\n\n", $methods);

        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

$usesBlock

$entityAttr
#[Table(name: '$table')]
final class $entity
{
$propsBlock
$ctor
$methodsBlock
}

PHP;
    }

    private function renderRepository(
        string $repositoryNamespace,
        string $repositoryName,
        string $entityNamespace,
        string $entity
    ): string {
        return <<<PHP
<?php
declare(strict_types=1);

namespace $repositoryNamespace;

use Neo\\Core\\Database\\ORM\\Persistence\\EntityRepository;
use $entityNamespace\\$entity;

/**
 * @extends EntityRepository<$entity>
 */
final class $repositoryName extends EntityRepository
{
}

PHP;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderScalarProp(array $field): string
    {
        $phpType = $this->phpType($field['type']);
        $nullable = $field['nullable'] ? '?' : '';
        $default = $field['nullable'] ? ' = null' : '';

        $args = ["type: '{$field['type']}'"];
        if ($field['type'] === 'string' && $field['length'] !== null) {
            $args[] = "length: {$field['length']}";
        }
        if ($field['nullable']) {
            $args[] = 'nullable: true';
        }
        $argList = implode(', ', $args);

        return "    #[Column($argList)]\n    private {$nullable}{$phpType} \${$field['name']}{$default};";
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderScalarAccessors(array $field): string
    {
        $phpType = $this->phpType($field['type']);
        $nullable = $field['nullable'] ? '?' : '';
        $studly = ucfirst($field['name']);
        $name = $field['name'];

        return "    public function get$studly(): {$nullable}{$phpType}\n    {\n        return \$this->{$name};\n    }\n\n"
            . "    public function set$studly({$nullable}{$phpType} \${$name}): static\n    {\n        \$this->{$name} = \${$name};\n\n        return \$this;\n    }";
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderManyToOneProp(array $field): string
    {
        $nullable = $field['nullable'] ? '?' : '';
        $default = $field['nullable'] ? ' = null' : '';
        $jcNullable = $field['nullable'] ? 'true' : 'false';

        $args = "targetEntity: {$field['target']}::class";
        if (!empty($field['inversedBy'])) {
            $args .= ", inversedBy: '{$field['inversedBy']}'";
        }

        return "    #[ManyToOne($args)]\n"
            . "    #[JoinColumn(name: '{$this->foreignKeyColumn($field['name'])}', nullable: $jcNullable)]\n"
            . "    private {$nullable}{$field['target']} \${$field['name']}{$default};";
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderOneToOneProp(array $field): string
    {
        if ($field['owning']) {
            $nullable = $field['nullable'] ? '?' : '';
            $default = $field['nullable'] ? ' = null' : '';
            $jcNullable = $field['nullable'] ? 'true' : 'false';

            $args = "targetEntity: {$field['target']}::class";
            if (!empty($field['inversedBy'])) {
                $args .= ", inversedBy: '{$field['inversedBy']}'";
            }

            return "    #[OneToOne($args)]\n"
                . "    #[JoinColumn(name: '{$this->foreignKeyColumn($field['name'])}', nullable: $jcNullable, unique: true)]\n"
                . "    private {$nullable}{$field['target']} \${$field['name']}{$default};";
        }

        return "    #[OneToOne(targetEntity: {$field['target']}::class, mappedBy: '{$field['mappedBy']}')]\n"
            . "    private ?{$field['target']} \${$field['name']} = null;";
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderOneToManyProp(array $field): string
    {
        return "    /** @var Collection<{$field['target']}> */\n"
            . "    #[OneToMany(targetEntity: {$field['target']}::class, mappedBy: '{$field['mappedBy']}')]\n"
            . "    private Collection \${$field['name']};";
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderManyToManyProp(array $field): string
    {
        $doc = "    /** @var Collection<{$field['target']}> */\n";

        if ($field['owning']) {
            $args = "targetEntity: {$field['target']}::class";
            if (!empty($field['inversedBy'])) {
                $args .= ", inversedBy: '{$field['inversedBy']}'";
            }

            $prop = $doc . "    #[ManyToMany($args)]\n";
            if ($field['joinTable'] !== null) {
                $prop .= "    #[JoinTable(name: '{$field['joinTable']}')]\n";
            } else {
                $prop .= "    #[JoinTable]\n";
            }

            return $prop . "    private Collection \${$field['name']};";
        }

        return $doc
            . "    #[ManyToMany(targetEntity: {$field['target']}::class, mappedBy: '{$field['mappedBy']}')]\n"
            . "    private Collection \${$field['name']};";
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderToOneAccessors(array $field): string
    {
        $nullable = $field['nullable'] ? '?' : '';
        $studly = ucfirst($field['name']);
        $name = $field['name'];
        $target = $field['target'];

        return "    public function get$studly(): {$nullable}$target\n    {\n        return \$this->{$name};\n    }\n\n"
            . "    public function set$studly({$nullable}$target \${$name}): static\n    {\n        \$this->{$name} = \${$name};\n\n        return \$this;\n    }";
    }

    /**
     * @param array<string, mixed> $field
     */
    private function renderCollectionAccessors(array $field): string
    {
        $studly = ucfirst($field['name']);
        $name = $field['name'];
        $target = $field['target'];
        $singular = $this->singularize($studly);

        $get = "    /** @return Collection<$target> */\n    public function get$studly(): Collection\n    {\n        return \$this->{$name};\n    }";

        if ($field['kind'] === 'oneToMany') {
            $setter = 'set' . ucfirst($field['mappedBy']);
            $getter = 'get' . ucfirst($field['mappedBy']);

            $add = "    public function add$singular($target \$item): static\n    {\n"
                . "        if (!\$this->{$name}->contains(\$item)) {\n"
                . "            \$this->{$name}->add(\$item);\n            \$item->{$setter}(\$this);\n        }\n\n        return \$this;\n    }";

            $remove = "    public function remove$singular($target \$item): static\n    {\n"
                . "        if (\$this->{$name}->removeElement(\$item) && \$item->{$getter}() === \$this) {\n"
                . "            \$item->{$setter}(null);\n        }\n\n        return \$this;\n    }";

            return "$get\n\n$add\n\n$remove";
        }

        $add = "    public function add$singular($target \$item): static\n    {\n"
            . "        if (!\$this->{$name}->contains(\$item)) {\n            \$this->{$name}->add(\$item);\n        }\n\n        return \$this;\n    }";

        $remove = "    public function remove$singular($target \$item): static\n    {\n"
            . "        \$this->{$name}->removeElement(\$item);\n\n        return \$this;\n    }";

        return "$get\n\n$add\n\n$remove";
    }

    private function singularize(string $studly): string
    {
        if (str_ends_with($studly, 'ies')) {
            return substr($studly, 0, -3) . 'y';
        }
        if (str_ends_with($studly, 's')) {
            return substr($studly, 0, -1);
        }
        return $studly;
    }

    private function phpType(string $type): string
    {
        return match ($type) {
            'integer', 'smallint', 'bigint' => 'int',
            'boolean' => 'bool',
            'float' => 'float',
            'decimal' => 'string',
            'datetime', 'date', 'time' => '\\DateTime',
            'json' => 'array',
            default => 'string',
        };
    }

    private function foreignKeyColumn(string $property): string
    {
        $snake = $property
                |> (fn (string $p): string => (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $p))
                |> strtolower(...);

        $snake = $snake
                |> (fn (string $s): string => (string) preg_replace('/_+/', '_', $s))
                |> (fn (string $s): string => trim($s, '_'));

        if (str_ends_with($snake, '_id')) {
            return $snake;
        }
        if (str_ends_with($snake, 'id')) {
            return substr($snake, 0, -2) . '_id';
        }

        return $snake . '_id';
    }

    private function tableName(string $entity): string
    {
        $snake = $entity
                |> (fn (string $e): string => (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $e))
                |> strtolower(...);

        return $snake . 's';
    }

    protected function getAvailableProjects(): array
    {
        return glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR)
                |> (fn (array $d): array => array_map(basename(...), $d));
    }
}