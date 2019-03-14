<?php
/**
 * Created by PhpStorm.
 * User: tomas.vitek
 * Date: 2019-03-14
 * Time: 12:59
 */

namespace Symbio\FulltextSearchBundle\Command;

use Symbio\FulltextSearchBundle\DependencyInjection\SymbioFulltextSearchExtension;
use Symbio\FulltextSearchBundle\Service\Crawler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IsIndexAliveCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addOption('index', null, InputOption::VALUE_OPTIONAL, 'Index name', null)
            ->setName('symbio:fulltext:is-alive')
            ->setDescription('Check if indexing lives')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexName = $input->getOption('index');

        $search = $this->getContainer()->get('symbio_fulltext_search');

        if ($search->isIndexAlive($indexName ?: null)) {
            $output->writeln(sprintf('Index%s is alive', $indexName ? ' '.$indexName : ''));
        } else {
            $output->writeln(sprintf('Index%s is NOT alive', $indexName ? ' '.$indexName : ''));

            $failureEmails = $this->getContainer()->getParameter(sprintf(
                '%s.%s',
                SymbioFulltextSearchExtension::ROOT_NAME,
                Crawler::FAILURE_EMAILS
            ));

            if ($failureEmails) {
                $instanceIdentification = $_SERVER['PWD'];

                $message = \Swift_Message::newInstance()
                    ->setFrom('no-reply@symbio.agency')
                    ->setTo($failureEmails)
                    ->setSubject('Nefunkční indexace fulltextu')
                    ->setBody(<<<EMAIL
Ahoj,

na webu v cestě $instanceIdentification není funkční indexace fulltextu.

SYMBIO
EMAIL
, 'text/plain')
                ;
                $this->getContainer()->get('mailer')->send($message);
            }
        }
    }
}
