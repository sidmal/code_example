<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.12.13
 * Time: 15:44
 */

namespace PaymentSystem\QIWI\Model;

use DOL\Protocol\Notification\Log\GuzzleLogPlugin;
use Guzzle\Http\Client;
use PaymentSystem\BaseBundle\Protocol\CheckResponse;
use PaymentSystem\QIWI\Entity\Shop;
use PaymentSystem\QIWI\Exception\ClientException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Guzzle\Common\Event;

/**
 * Class RestClient
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class RestClient implements LoggerAwareInterface
{
    protected $login;
    protected $password;

    protected $_client;

    protected $merchant_id;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param string|Shop $login
     * @param null $password
     * @param null $merchant_id
     */
    function __construct($login, $password = null, $merchant_id = null)
    {
        if ($login instanceof Shop) {
            $this->login = $login->getIdRest();
            $this->password = $login->getPasswordRest();
            $this->merchant_id = $login->getId();
        } else {
            $this->login = $login;
            $this->password = $password;
            $this->merchant_id = $merchant_id;
        }
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Получить http клиента
     * @return Client
     */
    protected function getHttpClient()
    {
        if ($this->_client === null) {
            $this->_client = new Client('https://w.qiwi.com/api/v2/prv/{prv_id}/bills/{bill_id}', [
                'prv_id' => $this->merchant_id,
                'curl.options' => [
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => 30,
                ],
            ]);
            if ($this->logger) {
                $this->_client->addSubscriber(new GuzzleLogPlugin($this->logger));
            }

            $this->_client->getEventDispatcher()->addListener('request.error', function(Event $event) {
                if ($event['response']->getStatusCode() <= 500) {
                    $event->stopPropagation();
                }
            });

            $login = $this->login;
            $password = $this->password;
            $this->_client->getEventDispatcher()->addListener('client.create_request',
                function(Event $event) use ($login, $password) {
                    $event['request']->setHeader('Accept', 'application/json');
                    $event['request']->setHeader('Authorization', 'Basic ' . base64_encode($login . ':' . $password));
                }
            );
        }
        return $this->_client;
    }

    /**
     * Получить информацию о платеже
     * @param $txn
     * @throws \PaymentSystem\QIWI\Exception\ClientException
     * @return CheckResponse
     */
    public function checkBill($txn)
    {
        $request = $this->getHttpClient()->get(['{bill_id}', ['bill_id' => $txn]]);
        try {
            $response = $request->send();
            $bill = $response->json();
        } catch (\Exception $e) {
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }
        if (isset($bill['response']['bill'])) {
            $checkResp = new CheckResponse($bill['response']['bill']['status'] == 'paid');
            $checkResp->setStatus($bill['response']['bill']['status']);
            $checkResp->setAmount($bill['response']['bill']['amount']);
            $checkResp->setCurrency($bill['response']['bill']['ccy']);
            $checkResp->setCustomer(str_replace('tel:', '', $bill['response']['bill']['user']));
            $checkResp->setComment($bill['response']['bill']['comment']);
            $checkResp->setTranId($bill['response']['bill']['bill_id']);
            return $checkResp;
        } else {
            throw new ClientException('ShopID ' . $this->merchant_id . ' ' . json_encode($bill['response']), $bill['response']['result_code']);
        }
    }

    /**
     * Исправить вожможные ошибки в формате номера
     * @param $phone
     * @return string
     */
    public function correctPhone($phone)
    {
        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '+7' . $phone;
        } elseif (preg_match('/^8\d{10}$/', $phone)) {
            $phone = '+7' . substr($phone, 1);
        } elseif (preg_match('/^\d{11}$/', $phone)) {
            $phone = '+' . $phone;
        } elseif (preg_match('/^8?7(\d{9})$/', $phone, $matches)) {
            $phone = '+77' . $matches[1];
        }

        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Создать новый счет
     * @param $phone
     * @param $amount
     * @param $comment
     * @param $txn
     * @param \DateTime $lifetime
     * @param string $prv_name
     * @param bool $mobile
     * @param string $currency
     * @throws \PaymentSystem\QIWI\Exception\ClientException
     * @return RequestResult
     */
    public function createBill($phone, $amount, $comment, $txn, \DateTime $lifetime, $prv_name = '', $mobile = false, $currency = 'RUB')
    {
        $params = array_filter(array(
            'user'       => 'tel:' . $phone,
            'amount'     => number_format($amount, 3, '.', ''),
            'ccy'        => $currency,
            'comment'    => $comment,
            'lifetime'   => $lifetime->format('Y-m-d\TH:i:s'),
            'pay_source' => $mobile ? 'mobile' : 'qw',
            'prv_name'   => $prv_name
        ));

        $request = $this->getHttpClient()->put(['{bill_id}', ['bill_id' => $txn]], null, $params);
        try {
            $response = $request->send();
            $this->logger->debug($response->getBody(true), $params);
            $bill = $response->json();
        } catch (\Exception $e) {
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }
        return new RequestResult($bill['response']);
    }

    /**
     * Создать возврат
     * @param $txn
     * @param $refundId
     * @param $refundAmount
     * @throws \PaymentSystem\QIWI\Exception\ClientException
     * @return RequestResult
     */
    public function createRefund($txn, $refundId, $refundAmount)
    {
        $request = $this->getHttpClient()->put(
            ['{bill_id}/refund/{refund_id}', ['bill_id' => $txn, 'refund_id' => $refundId]],
            null,
            ['amount' => number_format($refundAmount, 3, '.', '')]
        );
        try {
            $response = $request->send();
            $json = $response->json();
        } catch (\Exception $e) {
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }
        return new RequestResult($json['response']);
    }

} 