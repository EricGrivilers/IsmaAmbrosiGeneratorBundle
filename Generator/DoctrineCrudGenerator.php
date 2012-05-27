<?php

namespace IsmaAmbrosi\Bundle\GeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadataInfo;

class DoctrineCrudGenerator extends Generator
{

    private $filesystem;

    private $skeletonDir;

    private $routePrefix;

    private $routeNamePrefix;

    private $bundle;

    private $document;

    private $controller;

    private $metadata;

    private $format;

    private $actions;

    /**
     * Constructor.
     *
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem  A Filesystem instance
     * @param string                                   $skeletonDir Path to the skeleton directory
     */
    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
    }

    /**
     * Generate the CRUD controller.
     *
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle           A bundle object
     * @param string                                               $document         The document relative class name
     * @param string                                               $controller       The controller name
     * @param \Doctrine\ODM\MongoDB\Mapping\ClassMetadataInfo      $metadata         The document class metadata
     * @param string                                               $format
     * @param string                                               $routePrefix
     * @param boolean                                              $needWriteActions
     *
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, $document, $controller, ClassMetadataInfo $metadata, $format, $routePrefix, $needWriteActions)
    {
        $this->routePrefix = $routePrefix;
        $this->routeNamePrefix = preg_replace('/[^\w]+/', '_', $routePrefix);
        $this->actions = $needWriteActions ? array('index', 'show', 'new', 'edit', 'delete') : array('index', 'show');

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The CRUD generator does not support document classes with multiple primary keys.');
        }

        if ((is_array($metadata->identifier) && !in_array('id', $metadata->identifier)) || 'id' != $metadata->identifier) {
            throw new \RuntimeException('The CRUD generator expects the document object has a primary key field named "id" with a getId() method.');
        }

        $this->document = $document;
        $this->bundle = $bundle;
        $this->metadata = $metadata;
        $this->setFormat($format);

        $this->controller = $controller;

        $this->generateControllerClass();

        $dir = sprintf('%s/Resources/views/%s', $this->bundle->getPath(), str_replace('\\', DIRECTORY_SEPARATOR, $this->controller));

        if (!file_exists($dir)) {
            $this->filesystem->mkdir($dir, 0777);
        }

        $this->generateIndexView($dir);

        if (in_array('show', $this->actions)) {
            $this->generateShowView($dir);
        }

        if (in_array('new', $this->actions)) {
            $this->generateNewView($dir);
        }

        if (in_array('edit', $this->actions)) {
            $this->generateEditView($dir);
        }

        $this->generateTestClass();
        $this->generateConfiguration();
    }

    /**
     * Sets the configuration format.
     *
     * @param string $format The configuration format
     */
    private function setFormat($format)
    {
        switch ($format) {
            case 'yml':
            case 'xml':
            case 'php':
            case 'annotation':
                $this->format = $format;
                break;
            default:
                $this->format = 'yml';
                break;
        }
    }

    /**
     * Generates the routing configuration.
     *
     */
    private function generateConfiguration()
    {
        if (!in_array($this->format, array('yml', 'xml', 'php'))) {
            return;
        }

        $target = sprintf(
            '%s/Resources/config/routing/%s.%s',
            $this->bundle->getPath(),
            strtolower(str_replace('\\', '_', $this->document)),
            $this->format
        );

        $this->renderFile($this->skeletonDir, 'config/routing.'.$this->format, $target, array(
            'actions'             => $this->actions,
            'route_prefix'        => $this->routePrefix,
            'route_name_prefix'   => $this->routeNamePrefix,
            'bundle'              => $this->bundle->getName(),
            'document'            => $this->document,
        ));
    }

    /**
     * Generates the controller class only.
     *
     */
    private function generateControllerClass()
    {
        $dir = $this->bundle->getPath();

        $documentParts = explode('\\', $this->document);
        $documentClass = array_pop($documentParts);

        $controllerParts = explode('\\', $this->controller);
        $controllerClass = array_pop($controllerParts);
        if (false !== strpos($this->controller, '\\')) {
            $namespace = implode('\\', $controllerParts);
        } else {
            $namespace = implode('\\', $documentParts);
        }

        $target = sprintf(
            '%s/Controller/%s/%sController.php',
            $dir,
            str_replace('\\', '/', $namespace),
            $controllerClass
        );

        if (file_exists($target)) {
            throw new \RuntimeException('Unable to generate the controller as it already exists.');
        }

        $this->renderFile($this->skeletonDir, 'controller.php', $target, array(
            'actions'              => $this->actions,
            'route_prefix'         => $this->routePrefix,
            'route_name_prefix'    => $this->routeNamePrefix,
            'dir'                  => $this->skeletonDir,
            'bundle'               => $this->bundle->getName(),
            'document'             => $this->document,
            'document_class'       => $documentClass,
            'controller_name'      => $controllerClass,
            'namespace'            => $this->bundle->getNamespace(),
            'controller_namespace' => $namespace,
            'format'               => $this->format,
        ));
    }

    /**
     * Generates the functional test class only.
     *
     */
    private function generateTestClass()
    {
        $documentParts = explode('\\', $this->document);
        $documentClass = array_pop($documentParts);

        $controllerParts = explode('\\', $this->controller);
        $controllerClass = array_pop($controllerParts);
        if (false !== strpos($this->controller, '\\')) {
            $namespace = implode('\\', $controllerParts);
        } else {
            $namespace = implode('\\', $documentParts);
        }

        $dir = $this->bundle->getPath().'/Tests/Controller';
        $target = $dir.'/'.str_replace('\\', '/', $namespace).'/'.$controllerClass.'ControllerTest.php';

        $this->renderFile($this->skeletonDir, 'tests/test.php', $target, array(
            'route_prefix'         => $this->routePrefix,
            'route_name_prefix'    => $this->routeNamePrefix,
            'document'             => $this->document,
            'document_class'       => $documentClass,
            'namespace'            => $this->bundle->getNamespace(),
            'controller_class'     => $controllerClass,
            'controller_namespace' => $namespace,
            'actions'              => $this->actions,
            'dir'                  => $this->skeletonDir,
        ));
    }

    /**
     * Generates the index.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    private function generateIndexView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/index.html.twig', $dir.'/index.html.twig', array(
            'dir'                 => $this->skeletonDir,
            'document'            => $this->document,
            'fields'              => $this->metadata->fieldMappings,
            'actions'             => $this->actions,
            'record_actions'      => $this->getRecordActions(),
            'route_prefix'        => $this->routePrefix,
            'route_name_prefix'   => $this->routeNamePrefix,
        ));
    }

    /**
     * Generates the show.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    private function generateShowView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/show.html.twig', $dir.'/show.html.twig', array(
            'dir'                 => $this->skeletonDir,
            'document'            => $this->document,
            'fields'              => $this->metadata->fieldMappings,
            'actions'             => $this->actions,
            'route_prefix'        => $this->routePrefix,
            'route_name_prefix'   => $this->routeNamePrefix,
        ));
    }

    /**
     * Generates the new.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    private function generateNewView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/new.html.twig', $dir.'/new.html.twig', array(
            'dir'                 => $this->skeletonDir,
            'route_prefix'        => $this->routePrefix,
            'route_name_prefix'   => $this->routeNamePrefix,
            'document'            => $this->document,
            'actions'             => $this->actions,
        ));
    }

    /**
     * Generates the edit.html.twig template in the final bundle.
     *
     * @param string $dir The path to the folder that hosts templates in the bundle
     */
    private function generateEditView($dir)
    {
        $this->renderFile($this->skeletonDir, 'views/edit.html.twig', $dir.'/edit.html.twig', array(
            'dir'                 => $this->skeletonDir,
            'route_prefix'        => $this->routePrefix,
            'route_name_prefix'   => $this->routeNamePrefix,
            'document'            => $this->document,
            'actions'             => $this->actions,
        ));
    }

    /**
     * Returns an array of record actions to generate (edit, show).
     *
     * @return array
     */
    private function getRecordActions()
    {
        return array_filter($this->actions, function($item) {
            return in_array($item, array('show', 'edit'));
        });
    }
}
