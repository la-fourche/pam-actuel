<?php
namespace Webkul\ShopifyBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Input\InputArgument;

class ShopifyModuleInstallationCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('shopify:setup:install')
            ->setDescription('Install Shopify Akeneo connector setup')
            ->setHelp('setups shopify bundle installation');
    }

    protected $commandExecutor;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $errorFlag = false;
        /** $this->runCommand(
                 'cache:clear',
                 ['--no-warmup' => true],
                 $output
             );
             //can't be used in one command
        */
        $this->runCommand(
                'cache:warmup',
                [],
                $output                
            );
        $this->runCommand(
                'pim:install:asset',
                [],
                $output
            );

        $this->runCommand(
            'assets:install', 
            [
                //    'web'  => null,
                '--symlink' => null,
                '--relative' => null,
            ], 
            $output
        );

        $this->runCommand(
            'doctrine:schema:update', 
            [
                '--force' => null,
            ], 
            $output
        );

        foreach(['node', 'npm', 'yarn'] as $program) {
            $result = shell_exec($program . ' --version');
            if(strpos($result, 'not installed') || strpos($result, 'Ask your administrator') ) {
                $output->writeln('<error>' . $result . '</error>');
                $errorFlag = true;
            }
        } 

        // run yarn webpack 
        $result = shell_exec('yarn run webpack');

        // success
        if(strpos($result, 'Done in') !== false) {
            $output->writeln('<info>' . $result. '</info>');

        // failure            
        } else if(strpos($result, 'Command failed')) {
            $output->writeln('<error>Webpack error.</error>');
            $output->writeln('<comment>Adding webpack</comment>');
            $output->writeln(
                shell_exec('npm install --save-prod webpack')
            );
            $output->writeln(
                shell_exec('npm install')
            );

            // recheck webpack
            $result = shell_exec('yarn run webpack');
            if(strpos($result, 'Done in') !== false) {
                $output->writeln('<info>' . $result. '</info>');
            } else if(strpos($result, 'Command failed')) {
                $errorFlag = true; 
                $output->writeln('<error>Webpack error. can"t resolve automatically contact support@webkul.com</error>');
            }
        } else {
            $output->writeln($result);
            $errorFlag = true;                        
        }

        if(!exec('grep -r '.escapeshellarg('resource: "@ShopifyBundle/Resources/config/routing.yml"').' ./app/config/routing.yml') ) {
            $output->writeln('<comment>Check app/config/routing.yml, maybe shopify entry is not done in this file. Add entry then re run command</comment>');
        }
    }

    protected function runCommand($name, array $args, $output)
    {
        $command = $this->getApplication()->find($name);
        $commandInput = new ArrayInput(
            $args
        );
        $command->run($commandInput, $output);        
    }
}