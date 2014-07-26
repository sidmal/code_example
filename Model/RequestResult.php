<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.01.14 Time: 12:28
 * @author Dmitriy Sinichkin
 */

namespace PaymentSystem\QIWI\Model;


use PaymentSystem\QIWI\Exception\ClientException;

/**
 * Результат выполнения запроса
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class RequestResult
{
    const CODE_OK = 0;

    protected $data;

    function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Получить код пыполнения операции
     *
     * @return int
     * @throws \PaymentSystem\QIWI\Exception\ClientException
     */
    public function getCode()
    {
        if (isset($this->data['result_code'])) {
            return intval($this->data['result_code']);
        } else {
            throw new ClientException('Error data format');
        }
    }

    /**
     * Описание кода выполнения операции
     *
     * @return string
     */
    public function getDescription()
    {
        return isset($this->data['description']) ? $this->data['description'] : '';
    }

    /**
     * Успешно ли выполнение запроса
     * @return bool
     */
    public function isOk()
    {
        return $this->getCode() == self::CODE_OK;
    }

    /**
     * Не верный пул номеров или номер при выславлении счета
     *
     * @return bool
     */
    public function isPhoneInvalid()
    {
        return in_array($this->getCode(), [298, 1019]);
    }

    /**
     * Ошибка авторизации
     *
     * @return bool
     */
    public function isFailAuth()
    {
        return in_array($this->getCode(), [150, 316]);
    }

    /**
     * Операция запрещена
     *
     * @return bool
     */
    public function isNotAllowed()
    {
        return in_array($this->getCode(), [78]);
    }

} 