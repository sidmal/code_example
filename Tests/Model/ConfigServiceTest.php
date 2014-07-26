<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 16.12.13
 * Time: 17:39
 */

namespace PaymentSystem\QIWI\Tests\Model;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ConfigServiceTest extends WebTestCase
{
    public function testGetConfig()
    {
        $kernel = static::createKernel();
        $kernel->boot();

        $config = $kernel->getContainer()->get('payment_system.qiwi.config_service')->getConfig(2);
        print_r($config);
    }
}
 