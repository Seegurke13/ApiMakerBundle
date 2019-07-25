<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Seegurke13\ApiMaker\Command;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Renderer\FormTypeRenderer;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Serializer\Serializer;

class MakeApiCommand extends AbstractMaker
{
    private $doctrineHelper;

    private $formTypeRenderer;

    public function __construct(DoctrineHelper $doctrineHelper, FormTypeRenderer $formTypeRenderer)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->formTypeRenderer = $formTypeRenderer;
    }

    public static function getCommandName(): string
    {
        return 'make:api';
    }

    /**
     * {@inheritdoc}
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        $command
            ->setDescription('Creates CRUD API for Doctrine entity class')
            ->addOption('angular', 'ng', InputOption::VALUE_NONE, 'generate angular service')
            ->addOption('angular-base', 'ng-base', InputOption::VALUE_NONE, 'generate angular service')
            ->addOption('interface', 'ts', InputOption::VALUE_NONE, 'generate typescript interface')
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('The class name of the entity to create CRUD (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())));

        $inputConfig->setArgumentAsNonInteractive('entity-class');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        if (null === $input->getArgument('entity-class')) {
            $argument = $command->getDefinition()->getArgument('entity-class');

            $entities = $this->doctrineHelper->getEntitiesForAutocomplete();

            $question = new Question($argument->getDescription());
            $question->setAutocompleterValues($entities);

            $value = $io->askQuestion($question);

            $input->setArgument('entity-class', $value);
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity-class'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );

        $entityDoctrineDetails = $this->doctrineHelper->createDoctrineDetails($entityClassDetails->getFullName());

        $repositoryVars = [];

        if (null !== $entityDoctrineDetails->getRepositoryClass()) {
            $repositoryClassDetails = $generator->createClassNameDetails(
                '\\' . $entityDoctrineDetails->getRepositoryClass(),
                'Repository\\',
                'Repository'
            );

            $repositoryVars = [
                'repository_full_class_name' => $repositoryClassDetails->getFullName(),
                'repository_class_name' => $repositoryClassDetails->getShortName(),
                'repository_var' => lcfirst(Inflector::singularize($repositoryClassDetails->getShortName())),
            ];
        }

        $controllerClassDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix() . 'Controller',
            'Controller\\',
            'Controller'
        );

        $iter = 0;
        do {
            $formClassDetails = $generator->createClassNameDetails(
                $entityClassDetails->getRelativeNameWithoutSuffix() . ($iter ?: '') . 'Type',
                'Form\\',
                'Type'
            );
            ++$iter;
        } while (class_exists($formClassDetails->getFullName()));

        $entityVarPlural = lcfirst(Inflector::pluralize($entityClassDetails->getShortName()));
        $entityVarSingular = lcfirst(Inflector::singularize($entityClassDetails->getShortName()));

        $routeName = Str::asRouteName($controllerClassDetails->getRelativeNameWithoutSuffix());
        $templatesPath = Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix());


        $generator->generateController(
            $controllerClassDetails->getFullName(),
            __DIR__ . '/../Resources/maker/api/controller/Controller.tpl.php',
            array_merge([
                    'repository_var' => $repositoryVars['repository_var'],
                    'entity_full_class_name' => $entityClassDetails->getFullName(),
                    'entity_class_name' => $entityClassDetails->getShortName(),
                    'form_full_class_name' => $formClassDetails->getFullName(),
                    'form_class_name' => $formClassDetails->getShortName(),
                    'route_path' => Str::asRoutePath($controllerClassDetails->getRelativeNameWithoutSuffix()),
                    'route_name' => $routeName,
                    'templates_path' => $templatesPath,
                    'entity_var_plural' => $entityVarPlural,
                    'entity_var_singular' => $entityVarSingular,
                    'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                ],
                $repositoryVars
            )
        );

        $this->formTypeRenderer->render(
            $formClassDetails,
            $entityDoctrineDetails->getFormFields(),
            $entityClassDetails
        );

        if ($input->getOption('interface') === true) {
            $members = [];
            $metaData = $this->doctrineHelper->getMetadata($entityClassDetails->getFullName());
            $fields = $metaData->fieldMappings;
            $mappingFields = $metaData->associationMappings;
            foreach ($mappingFields as $mappingField => $value) {
                $members[] = [
                    'name' => $mappingField,
                    'type' => $this->getDoctrineTypeFromMapping($generator, $value),
                ];
            }
            foreach ($fields as $field) {
                $members[] = [
                    'name' => $field['fieldName'],
                    'type' => $this->doctrineToTsType($field['type']),
                ];
            }
            $variables = [
                'members' => $members,
                'className' => $entityClassDetails->getShortName(),
            ];
            $generator->generateFile(
                'generated/interface/' . $entityVarSingular . '.interface.ts',
                __DIR__ . '/../Resources/maker/api/interface/interface.tpl.ts',
                $variables
            );
        }

        if ($input->getOption('angular') === true) {
            $variables = [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entityVarSingular' => $entityVarSingular,
            ];
            $generator->generateFile(
                'generated/angular/' . $entityVarSingular . '-data.service.ts',
                __DIR__ . '/../Resources/maker/api/angular/data.service.tpl.ts',
                $variables
            );

            if ($input->getOption('angular-base')) {
                $generator->generateFile(
                    'generated/angular/abstract-symfony-data.service.ts',
                    __DIR__ . '/../Resources/maker/api/angular/abstract-symfony-data.service.ts',
                    []
                );
            }
        }

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text(sprintf('Next: Check your new CRUD by going to <fg=yellow>%s/</>', Str::asRoutePath($controllerClassDetails->getRelativeNameWithoutSuffix())));
    }

    /**
     * {@inheritdoc}
     */
    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(
            Route::class,
            'router'
        );

        $dependencies->addClassDependency(
            AbstractType::class,
            'form'
        );

        $dependencies->addClassDependency(
            Validation::class,
            'validator'
        );

        $dependencies->addClassDependency(
            DoctrineBundle::class,
            'orm-pack'
        );

        $dependencies->addClassDependency(
            ParamConverter::class,
            'annotations'
        );

        $dependencies->addClassDependency(
            Serializer::class,
            'serializer'
        );
    }

    private function doctrineToTsType(string $type): string
    {
        switch ($type) {
            case 'integer':
            case 'double':
            case 'dezimal':
            case 'float':
                return 'number';

            case 'text':
            case 'string':
                return 'string';

            case 'Date':
            case 'Time':
            case 'TimeStamp':
            case 'datetime':
                return 'Date';

            default:
                return 'any';
        }
    }

    private function getDoctrineTypeFromMapping(Generator $generator, array $value): string
    {
        $entityClassName = substr(strrchr($value['targetEntity'], "\\"), 1);
        if ($value['isOwningSide'] === true) {
            return $entityClassName;
        } else {
            return $entityClassName.'[]';
        }
    }
}
