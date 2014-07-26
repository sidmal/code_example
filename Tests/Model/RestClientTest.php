<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.12.13
 * Time: 16:40
 */

namespace PaymentSystem\QIWI\Tests\Model;


use PaymentSystem\QIWI\Model\RestClient;

class RestClientTest extends \PHPUnit_Framework_TestCase
{
    public function testGetInfo()
    {
        $client = new RestClient(239517, 'HVRHCHD68IC26JL', '239517');
        $client->setLogger(new \Monolog\Logger('test'));
        $result = $client->checkBill(219969180);
        //print_r($result);
        $this->assertTrue($result->getPaid());
        $this->assertEquals(219969180, $result->getTranId());

    }

    public function testForTest()
    {
        $client = new RestClient(5682085, '3qcoBkMJgRPygYy4Nprz', 216969);
        $client->setLogger(new \Monolog\Logger('test'));
        //$result = $client->checkBill(11234);
        //$result = $client->checkBill(223960520);
        $result = $client->createBill('+79533626059', 1, 'test for отмена', 223960520, new \DateTime('+2day'), 'Infinuum');
        print_r($result);
    }

    public function testCoins()
    {
        // 233562 523e57wuT08g3PB  доступ   Новый пароль: 4I1KyJlJBeuGHtwvdYnp
        // 6091643 80hPHadM3WkoGAylTmCo
        // дентификатор пользователя: 6092753      Пароль: ZxFJ4wVmgWCbwSpA7JpR

        $client = new RestClient(6091643, '80hPHadM3WkoGAylTmCo', 233562);
        $client->setLogger(new \Monolog\Logger('test'));
        //$result = $client->createBill('+79052194855', 1, 'Платеж и возврат', 230110018, new \DateTime('+2day'), '');
        $result = $client->checkBill(230116168);
        print_r($result);
    }


    public function testRefund()
    {
        // 233562 523e57wuT08g3PB  доступ
        // 6091643 80hPHadM3WkoGAylTmCo

        $client = new RestClient(6091643, '80hPHadM3WkoGAylTmCo', 233562);
        $client->setLogger(new \Monolog\Logger('test'));
        $result = $client->createRefund(233333334, 1, 1);
        print_r($result->getCode());
    }
}
 