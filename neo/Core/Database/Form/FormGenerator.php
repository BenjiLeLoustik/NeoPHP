<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;

class FormGenerator
{
    protected Container $container;
    private string $formDir;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->formDir   = $this->container->get('formPath');

        if (!is_dir($this->formDir) && !mkdir($this->formDir, 0777, true) && !is_dir($this->formDir)) {
            throw new DatabaseException(
                title: 'Form Generator Error',
                message: sprintf("Unable to create the forms directory '%s'.", $this->formDir),
                code: 500
            );
        }
    }

    public function generate(string $modelClass): string
    {
        $modelParts = explode('\\', $modelClass);
        $modelName = array_pop($modelParts);
        $modelNamespace = implode('\\', $modelParts);
        $formClassName = $modelName . 'Form';
        $namespaceForm = $this->container->get('formNamespace');
        $file = "{$this->formDir}/$formClassName.php";

        if (file_exists($file)) {
            return $formClassName;
        }

        $paramModelName = lcfirst($modelName);

        $code = <<<PHP
<?php
declare(strict_types=1);

namespace $namespaceForm;

use Neo\Core\DI\Container;
use Neo\Core\Database\Builder\FormBuilder;
use Neo\Core\Database\Form\Form;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Http\Request;
use Neo\Core\Database\Form\Type\SubmitType;
use $modelNamespace\\$modelName;
use Neo\Core\Translation\TranslationManager;


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

    public function build(?AbstractModel \${$paramModelName} = null): Form
    {
        \$form = (new FormBuilder(\${$paramModelName} ?? \$this->{$paramModelName}))
            ->auto()
            ->add('submit', SubmitType::class, ['label' => 'Submit'])
            ->generate();

        \$form->addCsrfField();
        
        \$form->handleRequest(\$this->request);
        \$form->setData(\${$paramModelName} ?? \$this->{$paramModelName});
        \$form->populateData();
        
        return \$form;
    }
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