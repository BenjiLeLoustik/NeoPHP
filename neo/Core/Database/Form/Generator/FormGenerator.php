<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Generator;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class FormGenerator
{
    protected Container $container;
    private string $formDir;
    private ?string $subNamespace = null;
    private string $baseFormDir;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws DatabaseException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->formDir   = $this->container->get('formPath');
        $this->baseFormDir = $this->container->get('formPath');
        $this->formDir = $this->baseFormDir;

        if (!is_dir($this->formDir) && !mkdir($this->formDir, 0777, true) && !is_dir($this->formDir)) {
            throw new DatabaseException(
                title: 'Form Generator Error',
                message: sprintf("Unable to create the forms directory '%s'.", $this->formDir),
                code: 500
            );
        }
    }

    public function setConnection(string $connection): void
    {
        $this->subNamespace = str_replace(' ', '', ucwords(str_replace('_', ' ', $connection)));
        $this->formDir = $this->baseFormDir . '/' . $this->subNamespace;

        if (!is_dir($this->formDir) && !mkdir($this->formDir, 0777, true) && !is_dir($this->formDir)) {
            throw new DatabaseException(
                title: 'Form Generator Error',
                message: sprintf("Unable to create the forms directory '%s'.", $this->formDir),
                code: 500
            );
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws DatabaseException
     */
    public function generate(string $modelClass): string
    {
        $modelParts = explode('\\', $modelClass);
        $modelName = array_pop($modelParts);

        $modelNamespace = $this->subNamespace !== null
            ? $this->container->get('modelNamespace') . '\\' . $this->subNamespace
            : implode('\\', $modelParts);

        $formClassName = $modelName . 'Form';

        $namespaceForm = $this->container->get('formNamespace')
            . ($this->subNamespace !== null ? '\\' . $this->subNamespace : '');

        $file = "{$this->formDir}/$formClassName.php";

        if (file_exists($file)) {
            return $formClassName;
        }

        $paramModelName = lcfirst($modelName);

        $code = <<<PHP
<?php
declare(strict_types=1);

namespace $namespaceForm;

use Neo\Core\Database\Form\Form;
use Neo\Core\Database\Builder\FormBuilder;
use Neo\Core\Database\Form\Type\SubmitType;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\DI\Container;
use Neo\Core\Http\Request\Request;
use Neo\Core\Translation\TranslationManager;
use $modelNamespace\\$modelName;


class $formClassName
{
    protected Request \$request;
    protected $modelName \${$paramModelName};
    protected TranslationManager \$translationManager;

    public function __construct(Container \$container)
    {
        \$this->request = \$container->get(Request::class);
        \$this->translationManager = \$container->get(TranslationManager::class);
        \$this->{$paramModelName} = new {$modelName}();
    }
    
    /**
     * public function myBuilder(?AbstractModel \$myModel): Form
     * {
     *      \$form = new FormBuilder(\$myModel ?? \$this->myModel)
     *                  ->auto()
     *                  ->generate();      
     * 
     *      \$form->addCsrfField(); 
     *      \$form->setData(\$myModel ?? \$this->myModel); 
     *      \$form->populateData();
     *      return \$form; 
     * } 
     */
}

PHP;

        if (file_put_contents($file, $code) === false) {
            throw new DatabaseException(
                title: 'Form Generator Error',
                message: sprintf("Unable to generate the form '%s' in '%s'.", $formClassName, $this->formDir),
                code: 500
            );
        }

        return $formClassName;
    }
}