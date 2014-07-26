<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 20.03.14 Time: 15:14
 * @author Dmitriy Sinichkin
 */

namespace PaymentSystem\QIWI\Model;


use DOL\ProcessingPaymentBundle\Plugin\InvoiceBuilder;
use DOL\RefundBundle\Model\RefundRequest;
use PaymentSystem\BaseBundle\Protocol\CreateControllerInterface;
use PaymentSystem\BaseBundle\Protocol\DetailsResponse;
use PaymentSystem\BaseBundle\Protocol\GetDetailsInterface;
use PaymentSystem\BaseBundle\Protocol\MerchantListInterface;
use PaymentSystem\BaseBundle\Protocol\RefundInterface;
use Psr\Log\LoggerAwareTrait;

abstract class QiwiServiceBasic implements
    CreateControllerInterface,
    MerchantListInterface,
    GetDetailsInterface,
    RefundInterface
{
    use LoggerAwareTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * @var QiwiHandler
     */
    protected $qiwiHandler;
    /**
     * @var InvoiceBuilder
     */
    protected $invoiceBuilder;

    /**
     * @param \Doctrine\ORM\EntityManager $em
     */
    public function setEm($em)
    {
        $this->em = $em;
    }

    /**
     * @param \PaymentSystem\QIWI\Model\QiwiHandler $qiwiHandler
     */
    public function setQiwiHandler(QiwiHandler $qiwiHandler)
    {
        $this->qiwiHandler = $qiwiHandler;
    }


    /**
     * @param \PaymentSystem\QIWI\Model\ConfigService $configService
     */
    public function setConfigService($configService)
    {
        $this->configService = $configService;
    }

    /**
     * @inheritdoc
     */
    public function getMerchantList()
    {
        $list = [];
        if ($shopList = $this->configService->getActiveConfigList()) {
            foreach ($shopList AS $shop) {
                $list[$shop->getId()] = $shop->getId() . ' ' . $shop->getName();
            }
        }
        return $list;
    }

    /**
     * @inheritdoc
     */
    public function refundPayment(RefundRequest $refund)
    {
        if ($transaction = $this->qiwiHandler->getTransaction($refund->getPayment()->getId())) {
            return $this->qiwiHandler->requestRefund($transaction, $refund);
        } else {
            $refund->getLogger()->notice('Not found qiwi transaction ' . $refund->getPayment()->getId() . ' for refund');
            return $refund::STATE_FAIL;
        }
    }

    public function setInvoiceBuilder($invoiceBuilder)
    {
        $this->invoiceBuilder = $invoiceBuilder;
    }

    public function getTransactionDetails($paymentId)
    {
        $transaction = $this->em->getRepository('QIWIBundle:Transaction')
            ->findOneBy(['payment' => $paymentId]);

        if(!$transaction){
            return null;
        }

        $detailsResponse = new DetailsResponse();
        $detailsResponse
            ->setCustomer($transaction->getPhone())
            ->setMerchant($transaction->getShop()->getIdRest())
            ->setAmount($transaction->getAmount())
            ->setCurrency($transaction->getCurrency()->getId())
            ->setTransactionAt($transaction->getPaidAt())
            ->setStatus($transaction->getState());

        return $detailsResponse;
    }
}