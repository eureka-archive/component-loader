<?php

/**
 * Copyright (c) 2010-2016 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Loader;

use Eureka\Eurekon;
use Eureka\Eurekon\Style;
use Eureka\Eurekon\Out;

/**
 * Loader cache generator.
 *
 * @author  Romain Cottard
 * @version 2.0.0
 */
class Generator extends Eurekon\Console
{
    /**
     * Set to true to set class as an executable script
     *
     * @var boolean $executable
     */
    protected $executable = true;

    /**
     * Console script description.
     *
     * @var boolean $executable
     */
    protected $description = 'Loader cache generator';

    /**
     * Help method.
     * Must be overridden.
     *
     * @return void
     */
    public function help()
    {
        $style = new Style(' *** RUN - HELP ***');
        Out::std($style->color('fg', Style::COLOR_GREEN)->get());
        Out::std('');

        $help = new Eurekon\Help('...', true);
        $help->addArgument('p', 'password', 'Password to hash. If empty, generate on', true, false);
        $help->addArgument('l', 'length', 'Length for password generated (default 16 chars)', true, false);

        $help->display();
    }

    /**
     * Run method.
     * Must be overridden.
     *
     * @return void
     */
    public function run()
    {
        $root = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(EKA_ROOT));
        ClassMapGenerator::dump($root, __DIR__ . '/cache.php');
    }
}