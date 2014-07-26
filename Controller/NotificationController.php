<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 18.12.13
 * Time: 13:42
 */

namespace PaymentSystem\QIWI\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use System\Log\LoggableInterface;

/**
 * Контроллер для обработки оповещений об изменении статуса платежа
 *
 * @package PaymentSystem\QIWI\Controller
 * @author Dmitriy Sinichkin
 */
class NotificationController extends Controller implements LoggableInterface
{
    /**
     * @Route("")
     */
    public function indexAction(Request $request)
    {
        if (
            $request->isMethod('POST') &&
            strpos($request->getContent(), 'SOAP')
        ) {
            $server = $this->get('payment_system.qiwi.soap_server');
            $response = new Response();
            $response->headers->set('Content-Type', 'text/xml');

            ob_start();
            $server->handle();
            $response->setContent(ob_get_clean());
            $server->getLogger()->info('Request: ' . $request->getContent() . "\nResponse: " . $response->getContent());
            return $response;
        } else {
            return $this->get('payment_system.qiwi.rest_server')->handle($request);
        }
    }
} 