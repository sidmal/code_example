<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 17.12.13
 * Time: 17:23
 */

namespace PaymentSystem\QIWI\Model;

use Psr\Log\LoggerAwareTrait;

/**
 * Шлюз для обрабки запроса оповещений платежей по SOAP протоколу
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class SoapServer extends \SoapServer
{
    use LoggerAwareTrait;

    const CODE_OK = 0;
    const CODE_PROCESSING = 13;
    const CODE_INVALID_SIGNATURE = 151;
    const CODE_INTERNAL_ERROR = 300;

    public static $return_codes = array(
        0 => 'Успех',
        13 => 'Сервер занят, повторите запрос позже',
        150 => 'Ошибка авторизации (неверный логин/пароль)',
        210 => 'Счет не найден',
        215 => 'Счет с таким txn-id уже существует',
        241 => 'Сумма слишком мала',
        242 => 'Превышена максимальная сумма платежа – 15 000р.',
        278 => 'Превышение максимального интервала получения списка счетов',
        298 => 'Агента не существует в системе',
        300 => 'Неизвестная ошибка',
        330 => 'Ошибка шифрования',
        370 => 'Превышено максимальное кол-во одновременно выполняемых запросов',
    );

    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    function __construct()
    {
        parent::SoapServer(__DIR__ . '/../Resources/wsdl/ishopServer.wsdl');
        $this->setObject($this);
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param \PaymentSystem\QIWI\Model\ConfigService $configService
     */
    public function setConfigService($configService)
    {
        $this->configService = $configService;
    }

    /**
     * @param \Doctrine\ORM\EntityManager $em
     */
    public function setEm($em)
    {
        $this->em = $em;
    }

    /**
     * Простой метод оповещения из WSDL
     * @param $params
     * @return \stdClass
     */
    public function updateBill($params)
    {
        try {
            if ($this->auth($params->login, $params->password, $params->txn)) {
                $code = $this->updateAction($params->login, $params->txn, intval($params->status));
                if ($code === null) {
                    $code = 300;
                }
            } else {
                $code = 150;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->__toString());
            $code = 300;
        }
        $result =  new \stdClass();
        $result->updateBillResult = $code;
        return $result;
    }

    /**
     * Расширенный метод оповещения из WSDL
     * отличается наличием дополнительных параметров
     * @param $params
     * @return \stdClass
     */
    public function updateBillExt($params)
    {
        $other_params = array();
        foreach ($params->params->item AS $item) {
            $other_params[$item->name] = $item->value;
        }

        try {
            if ($this->auth($params->login, $params->password, $params->txn)) {
                $code = $this->updateAction($params->login, $params->txn, intval($params->status), $other_params);
                if ($code === null) {
                    $code = 300;
                }
            } else {
                $code = 150;
            }
        } catch (\Exception $e) {
            $code = 300;
        }

        $result =  new \stdClass();
        $result->updateBillExtResult = $code;
        return $result;
    }

    /**
     * Проверка логина и подписи
     * @param $login
     * @param $password
     * @param $txn
     * @return bool
     */
    protected function auth($login, $password, $txn)
    {
        if ($config = $this->configService->getConfigByTxn($txn)) {
            if (
                $login == $config->getId() &&
                $password == strtoupper(md5($txn . strtoupper(md5($config->getPasswordSoap()))))
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Обновление статуса
     * @param $login
     * @param $txn
     * @param $status
     * @return int
     */
    protected function updateAction($login, $txn, $status)
    {
        if ($status == 60) {
            if ($config = $this->configService->getConfigByTxn($txn)) {
                $client = new SoapClient($config, null, $this->logger);
                $result = $client->checkBill($txn);
                return $this->payAction($txn, $result->getAmount(), $result->getCustomer());
            } else {
                return self::CODE_INVALID_SIGNATURE;
            }
        } elseif ($status >= 150) {
            return $this->cancelAction($txn);
        } else {
            return $this->waitAction($txn);
        }
    }

    /**
     * Проведение платежа
     * @param $id
     * @param $amount
     * @param $user
     * @return int
     */
    protected function payAction($id, $amount, $user)
    {
        return self::CODE_OK;
    }

    /**
     * В обработке.
     * @param $id
     * @return int+
     */
    protected function waitAction($id)
    {
        return self::CODE_OK;
    }

    /**
     * Платеж отменен
     * @param $id
     * @return int
     */
    protected function cancelAction($id)
    {
        return self::CODE_OK;
    }



} 