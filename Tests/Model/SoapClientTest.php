<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.12.13
 * Time: 14:27
 */

namespace PaymentSystem\QIWI\Tests\Model;

use Monolog\Handler\TestHandler;
use PaymentSystem\QIWI\Model\SoapClient;

class SoapClientTest extends \PHPUnit_Framework_TestCase
{

    public function testGetList()
    {
        $client = new SoapClient('223071', 'VK33BV48LBMZ026', new \Monolog\Logger('test'), [new TestHandler()]);
        $result = $client->getBillList(new \DateTime('2013-12-10'), new \DateTime('2013-12-10 00:02:00'));
        $this->assertEquals(array(218877084 => 161, 218877268 => 60, 218877324 => 161, 218877332 => 60,), $result);
    }

    public function testGetInfo()
    {
        $client = new SoapClient('223071', 'VK33BV48LBMZ026');
        $client->setLogger(new \Monolog\Logger('test'));

        $result = $client->checkBill(218877268);
        $this->assertEquals(585, $result->getAmount());
        $this->assertTrue($result->getPaid());

        $result = $client->checkBill(218877084);
        $this->assertEquals(150, $result->getAmount());
        $this->assertFalse($result->getPaid());

        $result = $client->checkBill(218877000);
        $this->assertEquals(-210, $result->getStatus());

    }

    public function testForTest()
    {
        $client = new SoapClient('6092753', 'ZxFJ4wVmgWCbwSpA7JpR', new \Monolog\Logger('test'));
        $result = $client->getBillList(new \DateTime('2013-08-01'), new \DateTime('2013-09-01'));
        print_r($result);
    }

    public function testCreate()
    {
        $client = new SoapClient('223071', 'VK33BV48LBMZ026');
        $client->setLogger(new \Monolog\Logger('test'));

        //$result = $client->createBill('905219485', 10, 'test', 1234, new \DateTime('+2day'));
        $result = $client->cancelBill(1234);
        print_r($result);
    }

}
 