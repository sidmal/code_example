<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 16.12.13
 * Time: 17:25
 */

namespace PaymentSystem\QIWI\Model;

use DOL\Currency\Currency;
use DOL\Currency\CurrencyAwareTrait;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * RestServer REST протокол для оповещний о платежах
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class RestServer 
{
    use LoggerAwareTrait, CurrencyAwareTrait;

    const CODE_OK = 0;
    const CODE_PROCESSING = 13;
    const CODE_INVALID_FORMAT = 5;
    const CODE_INVALID_PASSWORD = 150;
    const CODE_INVALID_SIGNATURE = 151;
    const CODE_INTERNAL_ERROR = 300;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var QiwiHandler
     */
    protected $qiwiHandler;

    /**
     * @var ConfigService
     */
    protected $configService;

    function __construct($em)
    {
        $this->em = $em;
    }

    /**
     * @param \PaymentSystem\QIWI\Model\ConfigService $configService
     */
    public function setConfigService($configService)
    {
        $this->configService = $configService;
    }

    /**
     * @param \PaymentSystem\QIWI\Model\QiwiHandler $qiwiHandler
     */
    public function setQiwiHandler(QiwiHandler $qiwiHandler)
    {
        $this->qiwiHandler = $qiwiHandler;
    }


    /**
     * Обработать запрос и отдать ответ по протоколу
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request)
    {
        try {
            return $this->response($this->process($request));
        } catch (\Exception $e) {
            $this->logger->error($e->__toString());
            return $this->response(self::CODE_INTERNAL_ERROR);
        }
    }

    /**
     * Обработать запрос и отдать код ответа
     *
     * @param Request $request
     * @return bool|int
     */
    protected function process(Request $request)
    {
        if ($this->validate($request)) {
            if ($this->auth($request->query->all() + $request->request->all(), $request->headers)) {

                $phone = substr($request->get('user'), 4);
                $amount = floatval($request->get('amount'));
                $currency = $this->getCurrencyManager()->getCurrency($request->get('ccy'));
                $txnId = $request->get('bill_id');

                if ($request->get('status') == 'paid') {
                    return $this->payAction($txnId, $amount, $phone, $currency);
                } elseif ($request->get('status') == 'waiting') {
                    return $this->waitAction($txnId, $amount, $phone, $currency);
                } else {
                    return $this->cancelAction($txnId, $amount, $phone, $currency);
                }
            } else {
                if ($request->headers->get('x-api-signature')) {
                    return self::CODE_INVALID_SIGNATURE;
                } else {
                    return self::CODE_INVALID_PASSWORD;
                }
            }
        } else {
            return self::CODE_INVALID_FORMAT;
        }
    }

    /**
     * Сформировать ответ по протоколу
     *
     * @param $code
     * @return Response
     */
    protected function response($code)
    {
        $xml = new \SimpleXMLElement('<result />');
        $xml->addChild('result_code', $code);
        $response = new Response($xml->asXML());
        $response->headers->set('Content-Type', 'text/xml');
        return $response;
    }

    /**
     * Проверка входящих параметров на формальную проверку соответвия
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return bool
     */
    protected function validate(Request $request)
    {
        $validator = Validation::createValidator();
        $constraint = new Assert\Collection([
            'bill_id'  => [new Assert\NotBlank(), new Assert\Length(array('min' => 3, 'max' => 30))],
            'amount'   => [new Assert\NotBlank(), new Assert\Type('Numeric'), new Assert\Range(['min' => 0.01, 'max' => 15000])],
            'ccy'      => [new Assert\NotBlank(), new Assert\Regex('/^[A-Z]{3}$/')],
            'status'   => [new Assert\NotBlank(), new Assert\Choice(['waiting', 'paid', 'rejected', 'unpaid', 'expired'])],
            'user'     => [new Assert\NotBlank(), new Assert\Regex('/^tel:\+\d{8,18}$/')],
        ]);

        $validateParams = [
            'bill_id'  => $request->get('bill_id'),
            'amount'   => $request->get('amount'),
            'ccy'      => $request->get('ccy'),
            'status'   => $request->get('status'),
            'user'     => $request->get('user'),
        ];

        $violations = $validator->validateValue($validateParams, $constraint);
        if ($violations->count()) {
            $message = '';
            foreach($violations AS $item) {
                /**
                 * @var $item \Symfony\Component\Validator\ConstraintViolation
                 */
                $message .= $item->getPropertyPath() . ' ' . $item->getMessage() . ' Value: ' . $item->getInvalidValue() . "\n";
            }
            $this->logger->warning($message);
            return false;
        } else {
            return true;
        }
    }

    /**
     * Проверить авторизацию
     *
     * @param $params
     * @param $headers \Symfony\Component\HttpFoundation\HeaderBag
     * @return bool
     */
    protected function auth($params, $headers)
    {
        if ($login = $headers->get('php-auth-user')) {
            if ($config = $this->configService->getConfig($login)) {
                if ($headers->get('php-auth-pw') == $config->getPasswordPull()) {
                    return true;
                }
            }
        } elseif ($sign = $headers->get('x-api-signature')) {
            if ($config = $this->configService->getConfig($login)) {
                ksort($params);
                if ($sign == base64_encode(hash_hmac('sha1', implode('|', $params), $config->getPasswordPull(), true))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Проведение платежа
     *
     * @param $txnId
     * @param $amount
     * @param $phone
     * @param \DOL\Currency\Currency $currency
     * @return int
     */
    protected function payAction($txnId, $amount, $phone, Currency $currency)
    {
        if ($transaction = $this->qiwiHandler->getTransaction($txnId)) {
            $this->qiwiHandler->actionPay($transaction, $amount, $currency, $phone);
            return self::CODE_OK;
        } else {
            $this->logger->notice('Not found qiwi transaction ' . $txnId);
        }
        return self::CODE_INTERNAL_ERROR;

    }

    /**
     * Платеж проводится
     *
     * @param $txnId
     * @param $amount
     * @param $phone
     * @param \DOL\Currency\Currency $currency
     * @return int
     */
    protected function waitAction($txnId, $amount, $phone, Currency $currency)
    {

        return self::CODE_OK;
    }

    /**
     * Отмена платежа
     *
     * @param $txnId
     * @param $amount
     * @param $phone
     * @param \DOL\Currency\Currency $currency
     * @return int
     */
    protected function cancelAction($txnId, $amount, $phone, Currency $currency)
    {
        if ($transaction = $this->qiwiHandler->getTransaction($txnId)) {
            $this->qiwiHandler->actionCancel($transaction);
        }

        return self::CODE_OK;
    }


} 