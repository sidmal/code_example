<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.12.13
 * Time: 11:58
 */

namespace PaymentSystem\QIWI\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Настройки ShopId
 *
 * @ORM\Table(name="qiwi_config" )
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 * @package PaymentSystem\QIWI\Entity
 * @author Dmitriy Sinichkin
 */
class Shop
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     */
    protected $id;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(name="password", type="string", length=255, nullable=true)
     */
    protected $password;

    /**
     * @ORM\Column(type="integer")
     */
    protected $idSoap;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $passwordSoap;

    /**
     * @ORM\Column(type="integer")
     */
    protected $idRest;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $passwordRest;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $passwordPull;

    /**
     * @ORM\Column(type="smallint")
     */
    protected $defaultProtocol = 0;

    /**
     * @ORM\Column(name="is_enabled", type="boolean")
     */
    private $isEnabled = true;

   

    /**
     * Set id
     *
     * @param integer $id
     * @return Shop
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * Set name
     *
     * @param string $name
     * @return Shop
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set idSoap
     *
     * @param integer $idSoap
     * @return Shop
     */
    public function setIdSoap($idSoap)
    {
        $this->idSoap = $idSoap;

        return $this;
    }

    /**
     * Get idSoap
     *
     * @return integer 
     */
    public function getIdSoap()
    {
        return $this->idSoap;
    }

    /**
     * Set passwordSoap
     *
     * @param string $passwordSoap
     * @return Shop
     */
    public function setPasswordSoap($passwordSoap)
    {
        $this->passwordSoap = $passwordSoap;

        return $this;
    }

    /**
     * Get passwordSoap
     *
     * @return string 
     */
    public function getPasswordSoap()
    {
        return $this->passwordSoap;
    }

    /**
     * Set idRest
     *
     * @param integer $idRest
     * @return Shop
     */
    public function setIdRest($idRest)
    {
        $this->idRest = $idRest;

        return $this;
    }

    /**
     * Get idRest
     *
     * @return integer 
     */
    public function getIdRest()
    {
        return $this->idRest;
    }

    /**
     * Set passwordRest
     *
     * @param string $passwordRest
     * @return Shop
     */
    public function setPasswordRest($passwordRest)
    {
        $this->passwordRest = $passwordRest;

        return $this;
    }

    /**
     * Get passwordRest
     *
     * @return string 
     */
    public function getPasswordRest()
    {
        return $this->passwordRest;
    }

    /**
     * Set passwordPull
     *
     * @param string $passwordPull
     * @return Shop
     */
    public function setPasswordPull($passwordPull)
    {
        $this->passwordPull = $passwordPull;

        return $this;
    }

    /**
     * Get passwordPull
     *
     * @return string 
     */
    public function getPasswordPull()
    {
        return $this->passwordPull;
    }

    /**
     * Set defaultProtocol
     *
     * @param integer $defaultProtocol
     * @return Shop
     */
    public function setDefaultProtocol($defaultProtocol)
    {
        $this->defaultProtocol = $defaultProtocol;

        return $this;
    }

    /**
     * Get defaultProtocol
     *
     * @return integer 
     */
    public function getDefaultProtocol()
    {
        return $this->defaultProtocol;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return Shop
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get password
     *
     * @return string 
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set isEnabled
     *
     * @param boolean $isEnabled
     * @return Shop
     */
    public function setIsEnabled($isEnabled)
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    /**
     * Get isEnabled
     *
     * @return boolean 
     */
    public function getIsEnabled()
    {
        return $this->isEnabled;
    }
}
