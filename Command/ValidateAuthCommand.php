<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 13.01.14 Time: 18:09
 * @author Dmitriy Sinichkin
 */

namespace PaymentSystem\QIWI\Command;

use PaymentSystem\QIWI\Exception\QiwiException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ValidateAuthCommand
 *
 * @package PaymentSystem\QIWI
 * @author Dmitriy Sinichkin
 */
class ValidateAuthCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('qiwi:validate:auth')
            ->setDescription('Проверить допступы по протоколам для активных ShopID')
            ->addArgument('id', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Список ShopID')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $qiwi = $this->getContainer()->get('payment_system.qiwi.handler');
        $config_service = $this->getContainer()->get('payment_system.qiwi.config_service');
        $configList = [];
        if ($input->getArgument('id')) {
            foreach ($input->getArgument('id') AS $id) {
                if ($config = $config_service->getConfig($id)) {
                    $configList[] = $config;
                }
            }
        } else {
            $configList = $config_service->getActiveConfigList();
        }

        foreach ($configList AS $config) {
            $output->write($config->getId() . ' ');

            try {
                $result = $qiwi->getClientSoap($config)->getBillList(new \DateTime('-1min'), new \DateTime());
                $output->write('<info>SOAP</info> ');
            } catch (QiwiException $e ){
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            }

            try {
                $result = $qiwi->getClientRest($config)->checkBill(123);
                $output->write('<info>REST</info> ');
            } catch (QiwiException $e ){
                if ($e->getCode() != 210) {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                } else {
                    $output->write('<info>REST</info> ');
                }
            }
            $output->writeln('');
        }

    }


} 