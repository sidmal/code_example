<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.12.13
 * Time: 13:11
 */

namespace PaymentSystem\QIWI\Model;

use PaymentSystem\BaseBundle\Protocol\CheckResponse;
use PaymentSystem\QIWI\Entity\Shop;
use PaymentSystem\QIWI\Exception\ClientException;
use PaymentSystem\QIWI\Exception\QiwiException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * SoapClient для работы с киви
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class SoapClient implements LoggerAwareInterface
{
    public static $code_list = array(
        0 => 'Выставлен',
        52 => 'Проводится',
        60 => 'Оплачен',
        150 => 'Отменен (ошибка на терминале)',
        151 => 'Отменен (ошибка авторизации: недостаточно средств на балансе, отклонен абонентом при оплате с лицевого счета оператора сотовой связи и т.п.).',
        160 => 'Отменен',
        161 => 'Отменен (Истекло время)',
    );

    const CODE_PAID = 60;

    protected $soap_client;

    protected $login;
    protected $password;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param $login string|Shop
     * @param $password string|null
     * @param $logger
     */
    function __construct($login, $password = null, $logger = null)
    {
        if ($login instanceof Shop) {
            $this->login = $login->getIdSoap();
            $this->password = $login->getPasswordSoap();
        } else {
            $this->login = $login;
            $this->password = $password;
        }
        $this->logger = $logger;
        $this->soap_client = new \SoapClient(__DIR__ . '/../Resources/wsdl/ishopRus.wsdl', array('trace' => 1));
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param $function_name
     * @param array $params
     * @return mixed
     * @throws \PaymentSystem\QIWI\Exception\ClientException
     */
    protected function soapCall($function_name, array $params)
    {
        try {
            $params['login'] = $this->login;
            $params['password'] = $this->password;

            $result = $this->soap_client->__soapCall($function_name, [(object)$params]);
            $this->logger->info('Request: ' . $this->soap_client->__getLastRequest() . "\nResponse: " . $this->soap_client->__getLastResponse());
            return $result;
        } catch (\SoapFault $e) {
            $this->logger->error('Request: ' . $this->soap_client->__getLastRequest() . "\nResponse: " . $this->soap_client->__getLastResponse() . "\nError:" . $e->getMessage());
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Простое выставление счета
     * @param $phone (номер телефона)
     * @param $amount сумма, на которую выставляется счет
     * @param $comment коментарий к счету, который увидит пользователь
     * @param $txn уникальный идентификатор счета payment_id
     * @param \DateTime $lifetime время действия счета
     * @param int $alarm отправить оповещение пользователю (смс - 1, звонок - 2, не оповещать - 0)
     * @param bool $create флаг для создания нового пользователя (если он не зарегистрирован в системе)
     * @param int $from параметр для выбора балланса (0 для QIWI Кошелька, 1 для Билайн, 3 для Мегафон, 4 для МТС)
     * @return \PaymentSystem\QIWI\Model\RequestResult
     */
    public function createBill($phone, $amount, $comment, $txn, \DateTime $lifetime, $alarm = 0, $create = true, $from = 0)
    {
        $params['user'] = $phone;
        $params['amount'] = number_format($amount, 2, '.', '');
        $params['comment'] = $comment;
        $params['txn'] = $txn;
        $params['lifetime'] = $lifetime->format('d.m.Y H:i:s');
        $params['alarm'] = $alarm;
        $params['create'] = $create;
        if ($from) {
            $params->from = $from;
            $res = $this->soapCall('createBillExt', $params);
            return new RequestResult(['result_code' => $res->createBillExtResult]);
        } else {
            $res = $this->soapCall('createBill', $params);
            return new RequestResult(['result_code' => $res->createBillResult]);
        }

    }

    /**
     * Запрос на выставление счета пользователю.
     * @param $phone (номер телефона)
     * @param $amount сумма, на которую выставляется счет
     * @param $comment коментарий к счету, который увидит пользователь
     * @param $txn уникальный идентификатор счета payment_id
     * @param \DateTime $lifetime время действия счета
     * @param $currency код валюты согласно ISO 4217
     * @param int $alarm отправить оповещение пользователю (смс - 1, звонок - 2, не оповещать - 0)
     * @param bool $create флаг для создания нового пользователя (если он не зарегистрирован в системе)
     * @param int $from параметр для выбора балланса (0 для QIWI Кошелька, 1 для Билайн, 3 для Мегафон, 4 для МТС)
     * @return \PaymentSystem\QIWI\Model\RequestResult
     */
    public function createBillCcy($phone, $amount, $comment, $txn, \DateTime $lifetime, $currency = 643, $alarm = 0, $create = true, $from = 0)
    {
        $res = $this->soapCall('createBillCcy',[
            'user' => $phone,
            'amount' => number_format($amount, 2, '.', ''),
            'comment' => $comment,
            'txn' => $txn,
            'lifetime' => $lifetime->format('d.m.Y H:i:s'),
            'currency' => $currency,
            'alarm' => $alarm,
            'create' => $create,
            'from' => $from,
        ]);
        return new RequestResult(['result_code' => $res->createBillCcyResult]);
    }

    /**
     * Выполнить проверку платежа
     * @param $txn
     * @return CheckResponse
     */
    public function checkBill($txn)
    {
        $res = $this->soapCall('checkBill', ['txn' => $txn]);

        $response = new CheckResponse($res->status == self::CODE_PAID);
        $response->setAmount($res->amount);
        $response->setCustomer($res->user);
        $response->setStatus($res->status);
        $response->setTranId($txn);
        $response->setDatePaid(\DateTime::createFromFormat('d.m.Y H:i:s', $res->date));
        return $response;
    }

    /**
     * @param $txn
     * @return RequestResult
     */
    public function cancelBill($txn)
    {
        $res = $this->soapCall('cancelBill', ['txn' => $txn]);
        return new RequestResult(['result_code' => $res->cancelBillResult]);
    }

    /**
     * Получить список транзакций в указанном статусе за период
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @param int $status 0 - показать во всех статусах или в указанном
     * @throws \Exception
     * @return array
     */
    public function getBillList(\DateTime $dateFrom, \DateTime $dateTo, $status = 0)
    {
        $res = $this->soapCall('getBillList', [
            'dateFrom' => $dateFrom->format('d.m.Y H:i:s'),
            'dateTo' => $dateTo->format('d.m.Y H:i:s'),
            'status' => $status,
        ]);

        if ($res->count < 0) {
            throw new ClientException('Invalid access by SOAP with login ' . $this->login . ' and password '. $this->password . ' code ' . $res->count);
        }
        $list = array();
        if ($res->txns) {
            $xml = new \SimpleXMLElement($res->txns);
            foreach ($xml->bill AS $item) {
                $attr = 'trm-txn-id';
                $id = intval($item->attributes()->$attr);
                $list[$id] = intval($item->attributes()->status);
            }
        }
        return $list;
    }

    /**
     * Выполнить возврат
     * @param $txn
     * @param $refundId
     * @param $refundAmount
     * @return RequestResult
     */
    public function createRefund($txn, $refundId, $refundAmount)
    {
        $res = $this->soapCall('cancelBillPayedAmount', [
            'txn' => $txn,
            'amount' => number_format($refundAmount, 2, '.', ''),
            'cancelIdx' => $refundId,
        ]);
        return new RequestResult(['result_code' => $res->cancelBillPayedAmountResult]);
    }

    /**
     * Получить информацию о возврате
     * @param $txn
     * @param $refundId
     * @return RequestResult
     */
    public function checkRefund($txn, $refundId)
    {
        $res = $this->soapCall('checkRefund', [
            'txn' => $txn,
            'cancelIdx' => $refundId,
        ]);
        return [$res->status, $res->amount];
    }
} 