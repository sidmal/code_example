<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 20.03.14 Time: 13:22
 * @author Dmitriy Sinichkin
 */

namespace PaymentSystem\QIWI\Model;


use Doctrine\ORM\EntityManager;
use DOL\Currency\Currency;
use DOL\Currency\CurrencyAwareTrait;
use DOL\ProcessingPaymentBundle\Entity\Payments;
use DOL\ProcessingPaymentBundle\Plugin\InvoiceProcessService;
use DOL\RefundBundle\Model\RefundRequest;
use PaymentSystem\QIWI\Entity\Transaction;
use PaymentSystem\QIWI\Exception\ConfigException;
use PaymentSystem\QIWI\Exception\QiwiException;
use Psr\Log\LoggerAwareTrait;

/**
 * Class QiwiHandler
 * Обработчик Киви
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class QiwiHandler
{
    use CurrencyAwareTrait;
    use LoggerAwareTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var InvoiceProcessService
     */
    protected $invoiceService;

    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * @param \DOL\ProcessingPaymentBundle\Plugin\InvoiceProcessService $invoiceService
     */
    public function setInvoiceService(InvoiceProcessService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * @param \Doctrine\ORM\EntityManager $em
     */
    public function setEm(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param \PaymentSystem\QIWI\Model\ConfigService $configService
     */
    public function setConfigService(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * найти транзакцию
     *
     * @param $id
     * @return Transaction|null
     */
    public function getTransaction($id)
    {
        if (ctype_digit($id) || is_integer($id)) {
            return $this->em->getRepository('QIWIBundle:Transaction')->find($id);
        }
        return null;
    }

    /**
     * Создать транзакцию
     *
     * @param Payments $payment
     * @return Transaction
     * @throws \PaymentSystem\QIWI\Exception\ConfigException
     */
    public function createTransaction(Payments $payment)
    {
        $transaction = new Transaction();
        $transaction->setId($payment->getId());
        $transaction->setPayment($payment);
        $transaction->setAmount($payment->getOutAmount());
        $transaction->setCurrency($payment->getOutCurrencyId());
        $transaction->setPhone('');
        if ($config = $this->configService->getConfig($payment->getPsMerchantId())) {
            $transaction->setShop($config);

            $this->em->persist($transaction);
            $this->em->flush();
            return $transaction;
        } else {
            throw new ConfigException('Not found qiwi config for payment ' . $payment->getId());
        }
    }

    /**
     * @param $config
     * @return SoapClient
     */
    public function getClientSoap($config)
    {
        $client = new SoapClient($config);
        $client->setLogger($this->logger);
        return $client;
    }

    /**
     * Получение рестового клиента для выполенения запроса в киви
     *
     * @param $config
     * @return RestClient
     */
    public function getClientRest($config)
    {
        $client = new RestClient($config);
        $client->setLogger($this->logger);
        return $client;
    }

    /**
     * Выполнить запрос на получение информации о платеже
     *
     * @param Transaction $transaction
     * @return bool|\PaymentSystem\BaseBundle\Protocol\CheckResponse
     */
    public function requestCheck(Transaction $transaction)
    {
        return $this->getClientRest($transaction->getShop())->checkBill($transaction->getId());
    }

    /**
     * Запрос на выставление счета в киви
     *
     * @param Transaction $transaction
     * @param $phone
     * @param bool $mobile
     * @return RequestResult
     */
    public function requestCreate(Transaction $transaction, $phone, $mobile = false)
    {
        $comment = $transaction->getPayment()->getInvoice()->getInvoiceParams()->getComment();
        if (!$comment) {
            $comment = 'Pay invoice ' . $transaction->getPayment()->getInvoice()->getId();
        }
        if (!$lifeTime = $transaction->getPayment()->getInvoice()->getInvoiceLifeTime()) {
            $lifeTime = new \DateTime('+1day');
        }

        $client = $this->getClientRest($transaction->getShop());

        $phone = $client->correctPhone($phone);

        $createResult = $client->createBill(
            $phone, $transaction->getAmount(), $comment, $transaction->getId(), $lifeTime, '', $mobile, $transaction->getCurrency()->getCode()
        );

        if ($createResult->isOk()) {
            $transaction->setPhone($phone);
            $this->em->persist($transaction);
            $this->em->flush();
        }
        return $createResult;
    }

    /**
     * Выполнить возврат платежа
     *
     * @param Transaction $transaction
     * @param RefundRequest $refund
     * @return mixed
     */
    public function requestRefund(Transaction $transaction, RefundRequest $refund)
    {
        $result = $this->getClientRest($transaction->getShop())->createRefund($transaction->getId(), $refund->getId(), $refund->getRefundMoney()->getAmount());
        $refund->getLogger()->info('Refund QIWI result code:' . $result->getCode() . ' ' . $result->getDescription());
        if ($result->isOk()) {
            return $refund::STATE_SUCCESS;
        } else {
            return $refund::STATE_FAIL;
        }
    }

    /**
     * Выполнить проведение платежа
     *
     * @param Transaction $transaction
     * @param null $amount
     * @param Currency $currency
     * @param null $phone
     * @throws \PaymentSystem\QIWI\Exception\QiwiException
     */
    public function actionPay(Transaction $transaction, $amount = null, Currency $currency = null, $phone = null)
    {
        $transaction->setState($transaction::STATE_PAID);

        if (!$transaction->getPaidAt()) {
            $transaction->setPaidAt(new \DateTime());
        }

        if (!$amount && !$currency && !$phone) {
            $checkResult = $this->requestCheck($transaction);
            if (!$checkResult->getPaid()) {
                throw new QiwiException('Try to pay qiwi unpaid transaction ' . $transaction->getId());
            }
            $amount = $checkResult->getAmount();
            $currency = $this->getCurrencyManager()->getCurrency($checkResult->getCurrency());
            $phone = $checkResult->getCustomer();
        }

        $transaction->setCurrency($currency->getEntity());
        $transaction->setAmount($amount);
        if ((substr($phone, -10) != substr($transaction->getPhone(), -10))) {
            $transaction->setPhone($phone);
        }

        $payment = $transaction->getPayment();

        $payment->setPsTxnId($transaction->getId());
        $payment->setPaymentPsCloseDate($transaction->getPaidAt());
        $payment->setStatus($payment::STATE_PAID_OK);
        $payment->setInAmount($transaction->getAmount());
        $payment->setInCurrencyId($transaction->getCurrency());

        $this->em->persist($transaction);
        $this->em->persist($payment);
        $this->em->flush();

        $this->invoiceService->processInvoice($payment);
    }

    /**
     * Отмена платежа
     *
     * @param Transaction $transaction
     */
    public function actionCancel(Transaction $transaction)
    {
        $transaction->setState($transaction::STATE_CANCEL);
        $payment = $transaction->getPayment();
        $payment->setStatus($payment::STATE_BILL_CANCELED);
        $this->em->persist($transaction);
        $this->em->persist($payment);
        $this->em->flush();
    }

} 