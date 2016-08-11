<?php

namespace Symbio\FulltextSearchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class SitemapGenerateCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addArgument('url', InputArgument::REQUIRED, 'Site to crawl')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Relative path to XML from the project root dir', 'web/')
            ->addOption('filename', null, InputOption::VALUE_OPTIONAL, 'Name of XML file', 'sitemap.xml')
            ->setName('symbio:sitemap:generate')
            ->setDescription('Generate sitemap.xml')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $siteUrl = $input->getArgument('url');

        $relativePath = $input->getOption('path');
        $filename = $input->getOption('filename');
        $sitemapPath = $this->getContainer()->get('kernel')->getRootDir()  . '/../' . $relativePath . (substr($relativePath, -1) != '/' ? '/' : '') . $filename;

        // crawl page
        $output->writeln(sprintf('Starting from "%s" at %s', $siteUrl, date('d.m.Y H:i:s')));

        $crawler = $this->getContainer()->get('symbio_fulltext_search.crawler');
        $pages = $crawler->extractPages($siteUrl);

        $output->writeln(sprintf('Crawling finished at %s', date('d.m.Y H:i:s')));

        // generate XML
        $output->writeln(sprintf('Generate sitemap to "%s"', $sitemapPath));

        $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
';
        foreach($pages as $pageUrl => $pageInfo) {
            $sitemapContent .= sprintf("<url><loc>%s</loc></url>\r\n", $pageUrl);
        }
        $sitemapContent .= '</urlset>';

        // store XML
        file_put_contents($sitemapPath, $sitemapContent);

        $output->writeln('Generating finished');
    }}
