<?php

/**
 * Copyright (c) 2010-2016 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Loader;

/**
 * Class to manage autoload for classes / interfaces - PSR-4 compliant.
 * Inspired from http://www.php-fig.org/psr/psr-4/examples/
 *
 * @author  Romain Cottard
 * @see     http://www.php-fig.org/psr/psr-4/
 */
class Loader
{
    /**
     * @var array $cache Static cache list of class files
     */
    protected static $cache = array();

    /**
     * @var array $namespaces Array of files with namespace prefixes as key.
     */
    protected static $namespaces = array();

    /**
     * Class constructor.
     * Load the cache data for classes files / namespace\cache $cache
     */
    public function __construct()
    {
        if (empty(static::$cache)) {

            $cacheFile = __DIR__ . DIRECTORY_SEPARATOR . 'cache.php';
            $cache     = array();

            //~ Include file if exists (if not, Exception already loggued in Loader::writeCacheFile).
            if (is_readable($cacheFile)) {

                $cache = include $cacheFile;

                //~ If $cache is not an array, maybe the file is corrupted.
                if (!is_array($cache)) {
                    //~ Log this case, but not throw the exception.
                    throw new \Exception('Bad cache file content for Loader!');
                }
            }

            static::$cache = $cache;
        }
    }

    /**
     * Load class or interface specified.
     *
     * @param  string $class
     * @return string|boolean
     * @throws \Exception
     */
    public function autoload($class)
    {
        //~ Add \ in namespace for core classes.
        $class     = (false !== strpos($class, '\\') ? $class : '\\' . $class);
        $classPath = $class;

        if (!empty(static::$cache[$class])) {
            return $this->requireFile(static::$cache[$class]);
        }

        $requiredFile = '';

        while (false !== $position = strrpos($classPath, '\\')) {

            $classPath     = substr($classPath, 0, $position + 1);
            $classRelative = substr($class, $position + 1);

            $requiredFile = $this->getRequiredFile($classPath, $classRelative);

            if (!empty($requiredFile)) {
                break;
            }

            $classPath = rtrim($classPath, '\\');
        }

        if (!empty($requiredFile)) {
            static::$cache[$class] = $requiredFile;

            return $this->requireFile($requiredFile);
        }

        return false;
    }

    /**
     * Try to find file mapped to current namespace.
     *
     * @param string $classPath
     * @param string $relativePath
     * @return null|string
     */
    protected function getRequiredFile($classPath, $relativePath)
    {
        //~ Check for class path in namespace list
        if (!isset(static::$namespaces[$classPath])) {
            return null;
        }

        //~ Namespace found: search in list of directories
        foreach (static::$namespaces[$classPath] as $path) {

            //~ Search for file.
            $file = $path . str_replace('\\', '/', $relativePath) . '.php';

            if (file_exists($file)) {
                return $file;
            }
        }

        //~ Class not found
        return null;
    }

    /**
     * Require once the specified file.
     *
     * @param  string $file
     * @return string
     */
    protected function requireFile($file)
    {
        require_once $file;

        return $file;
    }

    /**
     * Add namespace to loader.
     *
     * @param  string $namespace
     * @param  string $path
     * @return self
     *
     */
    public function addNamespace($namespace, $path)
    {
        $namespace = trim(trim($namespace), '\\') . '\\';
        $path      = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!isset(static::$namespaces[$namespace])) {
            static::$namespaces[$namespace] = array();
        }

        static::$namespaces[$namespace][] = $path;

        return $this;
    }

    /**
     * Add namespaces from config data
     *
     * @param  array $config
     * @return self
     */
    public function addFromConfig($config)
    {
        foreach ($config as $data) {
            $this->addNamespace($data['namespace'], $data['path']);
        }

        return $this;
    }

    /**
     * Get list of defined namespaces
     *
     * @return array
     */
    public function getNamespaces()
    {
        return static::$namespaces;
    }

    /**
     * Register autoload class method.
     *
     * @param  bool $throw
     * @param  bool $prepend
     * @return bool
     */
    public function register($throw = true, $prepend = false)
    {
        return spl_autoload_register(array($this, 'autoload'), $throw, $prepend);
    }

    /**
     * Unregister autoload class method.
     *
     * @return bool
     */
    public function unregister()
    {
        return spl_autoload_unregister(array($this, 'autoload'));
    }
}
