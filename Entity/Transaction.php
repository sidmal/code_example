<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.12.13
 * Time: 12:17
 */

namespace PaymentSystem\QIWI\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Transaction
 *
 * @ORM\Table(name="qiwi_transaction")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 * @package PaymentSystem\QIWI\Entity
 * @author Dmitriy Sinichkin
 */
class Transaction 
{
    const STATE_NEW    = 0;
    const STATE_PAID   = 1;
    const STATE_CANCEL = 2;

    /**
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     */
    protected $id;

    /**
     * Дата создания платежа
     * @var \DateTime
     * @ORM\Column(type="datetime")
     */
    protected $created_at;

    /**
     * Дата проведения платежа
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $paid_at;

    /**
     * @ORM\ManyToOne(targetEntity="DOL\ProcessingPaymentBundle\Entity\Payments")
     * @ORM\JoinColumn(name="payment_id", referencedColumnName="id", nullable=true)
     */
    protected $payment;

    /**
     * @ORM\ManyToOne(targetEntity="Shop")
     * @ORM\JoinColumn(name="shop_id", referencedColumnName="id", nullable=false)
     */
    protected $shop;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    protected $phone;

    /**
     * @ORM\Column(type="decimal", scale=3, nullable=false)
     */
    protected $amount;

    /**
     * @ORM\ManyToOne(targetEntity="DOL\Currency\Entity\CurrencyList")
     * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
     */
    private $currency;

    /**
     * @ORM\Column(type="smallint")
     */
    protected $state = 0;

    /**
     * @ORM\PrePersist
     */
    public function setCreatedValue()
    {
        $this->created_at = new \DateTime();
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set created_at
     *
     * @param \DateTime $createdAt
     * @return Transaction
     */
    public function setCreatedAt($createdAt)
    {
        $this->created_at = $createdAt;

        return $this;
    }

    /**
     * Get created_at
     *
     * @return \DateTime 
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set phone
     *
     * @param string $phone
     * @return Transaction
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone
     *
     * @return string 
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set payment
     *
     * @param \DOL\ProcessingPaymentBundle\Entity\Payments $payment
     * @return Transaction
     */
    public function setPayment(\DOL\ProcessingPaymentBundle\Entity\Payments $payment = null)
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * Get payment
     *
     * @return \DOL\ProcessingPaymentBundle\Entity\Payments 
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * Set shop
     *
     * @param \PaymentSystem\QIWI\Entity\Shop $shop
     * @return Transaction
     */
    public function setShop(\PaymentSystem\QIWI\Entity\Shop $shop)
    {
        $this->shop = $shop;

        return $this;
    }

    /**
     * Get shop
     *
     * @return \PaymentSystem\QIWI\Entity\Shop 
     */
    public function getShop()
    {
        return $this->shop;
    }

    /**
     * Set id
     *
     * @param integer $id
     * @return Transaction
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set amount
     *
     * @param string $amount
     * @return Transaction
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Get amount
     *
     * @return string 
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set state
     *
     * @param integer $state
     * @return Transaction
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get state
     *
     * @return integer 
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set paid_at
     *
     * @param \DateTime $paidAt
     * @return Transaction
     */
    public function setPaidAt($paidAt)
    {
        $this->paid_at = $paidAt;

        return $this;
    }

    /**
     * Get paid_at
     *
     * @return \DateTime 
     */
    public function getPaidAt()
    {
        return $this->paid_at;
    }



    /**
     * Set currency
     *
     * @param \DOL\Currency\Entity\CurrencyList $currency
     * @return Transaction
     */
    public function setCurrency(\DOL\Currency\Entity\CurrencyList $currency = null)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get currency
     *
     * @return \DOL\Currency\Entity\CurrencyList 
     */
    public function getCurrency()
    {
        return $this->currency;
    }
}
