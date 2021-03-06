<?php

/*
 * This file is part of the FOSJsRoutingBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\JsRoutingBundle\Extractor;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use JMS\I18nRoutingBundle\Router\I18nLoader;

/**
 * @author      William DURAND <william.durand1@gmail.com>
 */
class ExposedRoutesExtractor implements ExposedRoutesExtractorInterface
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * Base cache directory
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * @var array
     */
    protected $bundles;

    /**
     * Default constructor.
     *
     * @param RouterInterface $router         The router.
     * @param array           $routesToExpose Some route names to expose.
     * @param string          $cacheDir
     * @param array           $bundles        list of loaded bundles to check when generating the prefix
     */
    public function __construct(RouterInterface $router, array $routesToExpose = array(), $cacheDir, $bundles = array())
    {
        $this->router = $router;
        $this->routesToExpose = $routesToExpose;
        $this->cacheDir = $cacheDir;
        $this->bundles = $bundles;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutes()
    {
        $exposedRoutes = array();
        /** @var $route Route */
        foreach ($this->getExposedRoutes() as $name => $route) {
            // Maybe there is a better way to do that...
            $compiledRoute = $route->compile();
            $defaults = array_intersect_key(
                $route->getDefaults(),
                array_fill_keys($compiledRoute->getVariables(), null)
            );
            $requirements = $route->getRequirements();
            $host = method_exists($route, 'getHost') ? $route->getHost() : '';
            $exposedRoutes[$name] = new ExtractedRoute(
                $compiledRoute->getTokens(),
                $defaults,
                $requirements,
                $host
            );
        }

        return $exposedRoutes;
    }

    /**
     * {@inheritDoc}
     */
    public function getExposedRoutes()
    {
        $routes     = array();
        $collection = $this->router->getRouteCollection();
        $pattern    = $this->buildPattern();

        foreach ($collection->all() as $name => $route) {
            if (false === $route->getOption('expose')) {
                continue;
            }

            if (($route->getOption('expose') && (true === $route->getOption('expose') || 'true' === $route->getOption('expose')))
                || ('' !== $pattern && preg_match('#' . $pattern . '#', $name))) {
                $routes[$name] = $route;
            }
        }

        return $routes;
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseUrl()
    {
        return $this->router->getContext()->getBaseUrl() ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrefix($locale)
    {
        if (isset($this->bundles['JMSI18nRoutingBundle'])) {
            return $locale . I18nLoader::ROUTING_PREFIX;
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getHost()
    {
        return $this->router->getContext()->getHost();
    }

    /**
     * {@inheritDoc}
     */
    public function getScheme()
    {
        return $this->router->getContext()->getScheme();
    }

    /**
     * {@inheritDoc}
     */
    public function getCachePath($locale)
    {
        $cachePath = $this->cacheDir . DIRECTORY_SEPARATOR . 'fosJsRouting';
        if (!file_exists($cachePath)) {
            mkdir($cachePath);
        }

        if (isset($this->bundles['JMSI18nRoutingBundle'])) {
            $cachePath = $cachePath . DIRECTORY_SEPARATOR . 'data.' . $locale . '.json';
        } else {
            $cachePath = $cachePath . DIRECTORY_SEPARATOR . 'data.json';
        }

        return $cachePath;
    }

    /**
     * {@inheritDoc}
     */
    public function getResources()
    {
        return $this->router->getRouteCollection()->getResources();
    }

    /**
     * Convert the routesToExpose array in a regular expression pattern.
     *
     * @return string
     */
    protected function buildPattern()
    {
        $patterns = array();
        foreach ($this->routesToExpose as $toExpose) {
            $patterns[] = '(' . $toExpose . ')';
        }

        return implode($patterns, '|');
    }
}
