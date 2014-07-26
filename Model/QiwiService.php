<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 18.12.13
 * Time: 16:37
 */

namespace PaymentSystem\QIWI\Model;


use DOL\ProcessingPaymentBundle\Entity\Invoices;
use DOL\ProcessingPaymentBundle\Entity\Payments;
use DOL\ProcessingPaymentBundle\Plugin\InvoiceBuilder;
use DOL\ProcessingPaymentBundle\Plugin\InvoiceProcessService;
use DOL\RefundBundle\Model\RefundRequest;
use PaymentSystem\BaseBundle\Protocol\CreateControllerInterface;
use PaymentSystem\BaseBundle\Protocol\MerchantListInterface;
use PaymentSystem\BaseBundle\Protocol\RefundInterface;
use PaymentSystem\QIWI\Entity\Transaction;
use PaymentSystem\QIWI\Exception\ConfigException;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class QiwiService
 * Сервис платежной системы киви
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class QiwiService extends QiwiServiceBasic
{

    /**
     * Выставлять счет на мобильную оплату
     *
     * @var bool
     */
    protected $mobile = false;

    /**
     * true = Выставлять счет на мобильную оплату
     *
     * @param boolean $mobile
     */
    public function setMobile($mobile)
    {
        $this->mobile = $mobile;
    }


    /**
     * @inheritdoc
     */
    public function createAction(Invoices $invoice, Request $request)
    {

        if (!($payment = $invoice->getPayments()->first())) {
            $payment = $this->invoiceBuilder->createPaymentByInvoice($invoice);
            $payment->setInAmount($payment->getOutAmount());
            $payment->setInCurrencyId($payment->getOutCurrencyId());
            $this->em->persist($payment);
            $this->em->flush();
        }

        if (!$transaction = $this->qiwiHandler->getTransaction($payment->getId())) {
            $transaction = $this->qiwiHandler->createTransaction($payment);
        }

        if ($transaction->getPhone()) {
            return $this->returnSuccessForm($transaction);
        }

        if ($phone = $request->get('phone')) {
            $createResult = $this->qiwiHandler->requestCreate($transaction, $phone, $this->mobile);
            if ($createResult->isOk()) {
                return $this->returnSuccessForm($transaction);
            } else {
                if ($createResult->isPhoneInvalid()) {
                    $error = 'Invalid phone number.';
                } elseif ($createResult->isFailAuth() || $createResult->isNotAllowed()) {
                    $error = 'Payments are prohibited';
                } else {
                    $error = $createResult->getDescription();
                }
                return $this->returnRequestForm($transaction, $error);
            }
        }
        return $this->returnRequestForm($transaction);
    }

    /**
     * Вернуть форму запроса номера телефона для выставления счета
     *
     * @param Transaction $transaction
     * @param string $error
     * @return array
     */
    protected function returnRequestForm(Transaction $transaction, $error = '')
    {
        return ['ps/qiwi/phone.twig', [
            'transaction' => $transaction,
            'error_message' => $error,
        ]];
    }

    /**
     * Вернуть успешную форму с перенаправлением на оплату в киви
     *
     * @param Transaction $transaction
     * @return array
     */
    protected function returnSuccessForm(Transaction $transaction)
    {
        return ['ps/qiwi/iframe.twig', [
            'transaction' => $transaction,
            'iframe_url' => 'https://w.qiwi.com/order/external/main.action?'. http_build_query(array(
                    'successUrl' => 'https://www.onlinedengi.ru/ok.php?payment_id=1',
                    'failUrl' => 'https://www.onlinedengi.ru/fail.php?payment_id=1',
                    'shop' => $transaction->getShop()->getId(),
                    'transaction' => $transaction->getId(),
                    'iframe' => 'true',  )),
        ]];
    }


}