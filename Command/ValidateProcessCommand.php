<?php
/**
 * Created by PhpStorm.
 * User: dmitriysinichkin
 * Date: 14.01.14 Time: 12:16
 * @author Dmitriy Sinichkin
 */

namespace PaymentSystem\QIWI\Command;

use PaymentSystem\QIWI\Exception\ClientException;
use PaymentSystem\QIWI\Exception\QiwiException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateProcessCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('qiwi:validate:payment')
            ->setDescription('Проверить список проведенных платежей за период')
            ->addArgument('id', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Список ShopID')
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start of the period', '-1day -5min')
            ->addOption('end', null, InputOption::VALUE_OPTIONAL, 'End of period', '-5min')
            ->addOption('interval', null, InputOption::VALUE_OPTIONAL, 'Interval for check', 'P1D')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dateStart = new \DateTime($input->getOption('start'));
        $dateEnd = new \DateTime($input->getOption('end'));
        $dateInterval = new \DateInterval($input->getOption('interval'));
        $datePeriod = new \DatePeriod($dateStart, $dateInterval, $dateEnd);

        $output->writeln('Upload data for the period from <info>' . $dateStart->format('d-M-Y H:i:s') . '</info> to <info>' . $dateEnd->format('d-M-Y H:i:s') . '</info>');

        $configList = [];
        if ($input->getArgument('id')) {
            foreach ($input->getArgument('id') AS $id) {
                if ($config = $this->getContainer()->get('payment_system.qiwi.config_service')->getConfig($id)) {
                    $configList[] = $config;
                }
            }
        } else {
            $configList = $this->getContainer()->get('payment_system.qiwi.config_service')->getActiveConfigList();
        }

        $progress = new ProgressHelper();

        $progress->start($output, count($configList));
        if (count($configList) > 1) {
            $progress->display();
        }

        foreach ($configList AS $config) {
            try {
                $this->processPeriod($datePeriod, $dateInterval, $config, $output);
            } catch (ClientException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            }
            $progress->advance();
        }
        $progress->finish();
    }

    /**
     * @param \DatePeriod $period
     * @param \DateInterval $interval
     * @param $config
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function processPeriod(\DatePeriod $period, \DateInterval $interval, $config, OutputInterface $output)
    {
        $qiwi = $this->getContainer()->get('payment_system.qiwi.handler');
        $client = $qiwi->getClientSoap($config);
        foreach ($period AS $start) {
            $end = (clone $start);
            $end->add($interval);
            $txnList = $client->getBillList($start, $end);
            foreach ($txnList AS $txn => $code) {
                if ($code == $client::CODE_PAID) {
                    if ($transaction = $qiwi->getTransaction($txn)) {
                        $qiwi->actionPay($transaction);
                    } else {
                        $output->writeln('<error>Not found Bill ' . $txn . '</error>');
                        //todo add error log
                    }
                } elseif ($code >= 150) {
                    if ($transaction = $qiwi->getTransaction($txn)) {
                        $qiwi->actionCancel($transaction);
                    }
                }
            }
        }
    }

} 