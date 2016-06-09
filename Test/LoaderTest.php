<?php

/**
 * =====================================================
 * Licensed under creative common:
 * - Attribution-ShareAlike 3.0 Unported (CC BY-SA 3.0)
 *
 * See the LICENCE file for more informations
 * =====================================================
 *
 * @author Romain Cottard
 * @version 2.1.0
 */
namespace Eureka\Component\Loader;

require_once __DIR__ . '/../Loader.php';

/**
 * Test Loader class.
 *
 * @author Romain Cottard
 * @version 2.1.0
 */
class LoaderTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Main test method.
     *
     * @return void
     * @covers Loader::__construct
     * @covers Loader::extentions
     * @covers Loader::register
     * @covers Loader::unregister
     * @covers Loader::autoload
     * @covers Loader::addNamespace
     * @covers Loader::getNamespaces
     * @covers Loader::getRequiredFile
     * @covers Loader::requireFile
     *
     */
    public function testLoader()
    {
        $loader = new Loader();
        $loader->extensions();
        $loader->register();

        $eurekaPath = realpath(__DIR__ . '/../../../../../');

        $loader->addNamespace('Eureka\Component\Loader', $eurekaPath . '/src/Eureka/Component/Loader/Test');
        $loader->addNamespace('Eureka\Component\Acl',    $eurekaPath . '/src/Eureka/Component/Acl');
        $loader->addNamespace('Eureka\Component\Acl',    $eurekaPath . '/src/Eureka/Component/Acl/Test');
        $loader->addNamespace('Eureka\Component\Kernel', $eurekaPath . '/src/Eureka/Component/Kernel');

        $acl = new \Eureka\Component\Acl\Acl();
        $this->assertTrue(true);

        $myObject = new MyClass();
    }

    /**
     * Main test method.
     *
     * @return void
     * @covers Loader::__construct
     * @covers Loader::register
     * @covers Loader::autoload
     * @covers Loader::getRequiredFile
     *
     * @expectedException \Exception
     */
    public function testLoaderException()
    {
        $loader = new Loader();
        $loader->register();

        $someFakeObject = new \Eureka\Component\Loader\LoaderNotExists();
    }
}
