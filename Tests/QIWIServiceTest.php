<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 15.01.14 Time: 11:08
 * @author Dmitriy Sinichkin
 */

namespace PaymentSystem\QIWI\Tests;

use DOL\ProcessingPaymentBundle\Entity\Invoices;
use DOL\ProcessingPaymentBundle\Entity\Payments;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class QIWIServiceTest extends WebTestCase
{
    public function testCreateInterface()
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();
        $ps = static::$kernel->getContainer()->get('payment_system.service');
        /**
         * @var $em \Doctrine\ORM\EntityManager
         */
        $em = static::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        $qiwi = $ps->get('payment_system.qiwi');
        $this->assertTrue($qiwi->hasCreateAction());
        $request = new Request();
        $invoice = new Invoices();
        $payment = new Payments();
        $payment->setOutAmount(10);
        $payment->setPsMerchantId(233562);
        $refPayment = new \ReflectionObject($payment);
        $id = $refPayment->getProperty('id');
        $id->setAccessible(true);
        $id->setValue($payment, 123);

        $invoice->addPayment($payment);

        list($template, $params) = $qiwi->createAction($invoice, $request);

        print_r($params);
    }
}
 