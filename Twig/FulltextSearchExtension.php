<?php

namespace Symbio\FulltextSearchBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;

class FulltextSearchExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Constructor
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        $container = $this->container;
        return array(
            new \Twig_SimpleFunction('get_parameter', function($name) use ($container) {
                return $container->hasParameter($name) ? $container->getParameter($name) : '';
            }),
        );
    }

    public function getName()
    {
        return 'symbio_fulltext_search_extension';
    }
}