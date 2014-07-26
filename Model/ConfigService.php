<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 16.12.13
 * Time: 17:30
 */

namespace PaymentSystem\QIWI\Model;

/**
 * Class ConfigService
 *
 * @package PaymentSystem\QIWI\Model
 * @author Dmitriy Sinichkin
 */
class ConfigService 
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    function __construct($em)
    {
        $this->em = $em;
    }

    /**
     * Получить конфигурацию по идентификатору магазина
     * @param $shop_id
     * @return \PaymentSystem\QIWI\Entity\Shop
     */
    public function getConfig($shop_id)
    {
        return $this->em->getRepository('QIWIBundle:Shop')->find($shop_id);
    }

    /**
     * @param $id
     * @return \PaymentSystem\QIWI\Entity\Shop
     */
    public function getConfigByTxn($id)
    {
        if ($bill = $this->em->getRepository('QIWIBundle:Transaction')->find($id)) {
            return $bill->getShop();
        }
    }

    /**
     * @return array|\PaymentSystem\QIWI\Entity\Shop[]
     */
    public function getActiveConfigList()
    {
        return $this->em->getRepository('QIWIBundle:Shop')->findBy(['isEnabled' => true]);
    }

} 