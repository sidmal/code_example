<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 18.12.13
 * Time: 14:58
 */

namespace PaymentSystem\QIWI\Tests\Model;


class SoapServerTest extends \PHPUnit_Framework_TestCase
{

    public function testSoap()
    {
        $client = new \SoapClient(__DIR__ . '/../../Resources/wsdl/ishopServer.wsdl', ['location' => "http://localhost:8000/pay/qiwi"]);
        $params = new \StdClass();
        $params->login = '123';
        $params->password = '123';
        $params->txn = '123';
        $params->status = '60';
        $res = $client->updateBill($params);
        print_r($res);

    }

}
 