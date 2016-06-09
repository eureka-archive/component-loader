<?php

/**
 * Copyright (c) 2010-2016 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Loader;

/**
 * ClassMapGenerator
 *
 * @author Gyula Sallai <salla016@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Romain Cottard
 * @see    https://github.com/composer/composer/blob/master/src/Composer/Autoload/ClassMapGenerator.php
 */
class ClassMapGenerator
{
    /**
     * Generate a class map file
     *
     * @param \Traversable $dirs Directories or a single path to search in
     * @param string       $cacheFile The name of the class map file
     */
    public static function dump($dirs, $cacheFile)
    {
        $maps = array();

        foreach ($dirs as $file) {
            $maps = array_merge($maps, static::createMap($file));
        }

        $content = <<<'CACHE'
<?php

return %s;

CACHE;
        $content = sprintf($content, var_export($maps, true));
        $written  = (bool) file_put_contents($cacheFile, $content);

        if (!$written) {
            throw new \RuntimeException('No content has been written in cache class map file !');
        }
    }

    /**
     * Iterate over all files in the given directory searching for classes
     *
     * @param \SplFileInfo $file The path to search in or an iterator
     * @param string       $blacklist Regex that matches against the file path that exclude from the classmap.
     * @param string       $namespace Optional namespace prefix to filter by
     *
     * @throws \RuntimeException When the path is neither an existing file nor directory
     * @return array             A class map array
     */
    public static function createMap(\SplFileInfo $file, $blacklist = null, $namespace = null)
    {
        $map = array();

        $filePath = $file->getRealPath();

        if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), array('php', 'inc', 'hh'))) {
            return $map;
        }

        if ($blacklist && preg_match($blacklist, strtr($filePath, '\\', '/'))) {
            return $map;
        }

        $classes = self::findClasses($filePath);

        foreach ($classes as $class) {
            // skip classes not within the given namespace prefix
            if (null !== $namespace && 0 !== strpos($class, $namespace)) {
                continue;
            }

            if (!isset($map[$class])) {
                $map[$class] = $filePath;
            } else {
                if ($map[$class] !== $filePath && !preg_match('{/(test|fixture|example|stub)s?/}i', strtr($map[$class] . ' ' . $filePath, '\\', '/'))) {
                    echo 'Warning: Ambiguous class resolution, "' . $class . '"' . ' was found in both "' . $map[$class] . '" and "' . $filePath . '", the first will be used.';
                }
            }
        }

        return $map;
    }

    /**
     * Extract the classes in the given file
     *
     * @param  string $path The file to check
     * @throws \RuntimeException
     * @return array             The found classes
     */
    private static function findClasses($path)
    {
        $extraTypes = PHP_VERSION_ID < 50400 ? '' : '|trait';
        if (defined('HHVM_VERSION') && version_compare(HHVM_VERSION, '3.3', '>=')) {
            $extraTypes .= '|enum';
        }

        $contents = @php_strip_whitespace($path);
        if (!$contents) {
            if (!file_exists($path)) {
                $message = 'File at "%s" does not exist, check your classmap definitions';
            } else {
                if (!is_readable($path)) {
                    $message = 'File at "%s" is not readable, check its permissions';
                } else {
                    if ('' === trim(file_get_contents($path))) {
                        // The input file was really empty and thus contains no classes
                        return array();
                    } else {
                        $message = 'File at "%s" could not be parsed as PHP, it may be binary or corrupted';
                    }
                }
            }
            $error = error_get_last();
            if (isset($error['message'])) {
                $message .= PHP_EOL . 'The following message may be helpful:' . PHP_EOL . $error['message'];
            }
            throw new \RuntimeException(sprintf($message, $path));
        }

        // return early if there is no chance of matching anything in this file
        if (!preg_match('{\b(?:class|interface' . $extraTypes . ')\s}i', $contents)) {
            return array();
        }

        // strip heredocs/nowdocs
        $contents = preg_replace('{<<<\s*(\'?)(\w+)\\1(?:\r\n|\n|\r)(?:.*?)(?:\r\n|\n|\r)\\2(?=\r\n|\n|\r|;)}s', 'null', $contents);
        // strip strings
        $contents = preg_replace('{"[^"\\\\]*+(\\\\.[^"\\\\]*+)*+"|\'[^\'\\\\]*+(\\\\.[^\'\\\\]*+)*+\'}s', 'null', $contents);
        // strip leading non-php code if needed
        if (substr($contents, 0, 2) !== '<?') {
            $contents = preg_replace('{^.+?<\?}s', '<?', $contents, 1, $replacements);
            if ($replacements === 0) {
                return array();
            }
        }
        // strip non-php blocks in the file
        $contents = preg_replace('{\?>.+<\?}s', '?><?', $contents);
        // strip trailing non-php code if needed
        $pos = strrpos($contents, '?>');
        if (false !== $pos && false === strpos(substr($contents, $pos), '<?')) {
            $contents = substr($contents, 0, $pos);
        }

        preg_match_all(
            '{
            (?:
                 \b(?<![\$:>])(?P<type>class|interface' . $extraTypes . ') \s++ (?P<name>[a-zA-Z_\x7f-\xff:][a-zA-Z0-9_\x7f-\xff:\-]*+)
               | \b(?<![\$:>])(?P<ns>namespace) (?P<nsname>\s++[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+)? \s*+ [\{;]
            )
            }ix',
            $contents,
            $matches
        );

        $classes   = array();
        $namespace = '';

        for ($i = 0, $len = count($matches['type']); $i < $len; $i++) {
            if (!empty($matches['ns'][$i])) {
                $namespace = str_replace(array(' ', "\t", "\r", "\n"), '', $matches['nsname'][$i]) . '\\';
            } else {
                $name = $matches['name'][$i];
                if ($name[0] === ':') {
                    // This is an XHP class, https://github.com/facebook/xhp
                    $name = 'xhp' . substr(str_replace(array('-', ':'), array('_', '__'), $name), 1);
                } else {
                    if ($matches['type'][$i] === 'enum') {
                        // In Hack, something like:
                        //   enum Foo: int { HERP = '123'; }
                        // The regex above captures the colon, which isn't part of
                        // the class name.
                        $name = rtrim($name, ':');
                    }
                }
                $classes[] = ltrim($namespace . $name, '\\');
            }
        }

        return $classes;
    }
}
