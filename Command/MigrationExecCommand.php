<?php
/**
 * Created by v.taneev.
 * Date: 06.06.18
 * Time: 18:00
 */


namespace Iswin\Borm\Command;


use Bitrix\Main\Application;
use Doctrine\Bundle\MigrationsBundle\Command\MigrationsExecuteDoctrineCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationExecCommand extends MigrationsExecuteDoctrineCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('borm:migrations:execute')
            ->addOption('db', null, InputOption::VALUE_REQUIRED, 'The database connection to use for this command.')
            ->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command.')
            ->addOption('shard', null, InputOption::VALUE_REQUIRED, 'The shard connection to use for this command.')
        ;
    }

    public function execute (InputInterface $input, OutputInterface $output)
    {
        $ret = parent::execute($input, $output);

        $managedCache = Application::getInstance()->getManagedCache();
        $managedCache->cleanDir('b_user_field');

        return $ret;
    }
}