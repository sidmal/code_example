<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 17.12.13
 * Time: 10:38
 */

namespace PaymentSystem\QIWI\Tests\Model;

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

class RestServerTest extends \PHPUnit_Framework_TestCase
{
    public function testVialotion()
    {
        $params = [
            'bill_id' => '123123',
            'amount'  => '1.00',
            'ccy' => 'RUB',
            'status' => 'paid',
            'user'  => '1tel:+74565464',
        ];

        $validator = Validation::createValidator();
        $constraint = new Assert\Collection([
            'bill_id'  => new Assert\Length(array('min' => 3, 'max' => 30)),
            'amount'   => [new Assert\Type('Numeric'), new Assert\Range(['min' => 0.01, 'max' => 15000])],
            'ccy'      => new Assert\Choice(['RUB']),
            'status'   => new Assert\Choice(['waiting', 'paid', 'rejected', 'unpaid', 'expired']),
            'user'     => new Assert\Regex('/^tel:\+\d{8,18}$/'),
        ]);

        /**
        * @var $violations \Symfony\Component\Validator\ConstraintViolation[]
        */
        $violations = $validator->validateValue($params, $constraint);

        //$this->assertEquals(0, $violations->count());

        foreach($violations AS $item) {
            print_r(
                $item->getPropertyPath() . ' ' .
                $item->getMessage() . ' Value: ' . $item->getInvalidValue()
            );
        }
    }


}
 