<?php

namespace Symbio\FulltextSearchBundle\Service;

use Ivory\LuceneSearchBundle\Model\LuceneManager;
use ZendSearch\Lucene\Index;

class IndexManager
{
    protected $kernel;
    protected $luceneSearch;

    public function __construct(\AppKernel $kernel, LuceneManager $luceneSearch)
    {
        $this->kernel = $kernel;
        $this->luceneSearch = $luceneSearch;
    }

    public function __call($name, $arguments)
    {
        if (!method_exists($this->luceneSearch, $name)) {
            throw new \Exception(sprintf('Method %s doesn\'t exists in class Symbio\FulltextSearchBundle\Service\IndexManager'));
        }
        return call_user_func_array(array($this->luceneSearch, $name), $arguments);
    }

    public function getIndex($indexName)
    {
        try {
            $index = $this->luceneSearch->getIndex($indexName);
        } catch(\Exception $e) {
            $index = null;
        }

        if (!$index || !($index instanceof Index)) {
            $this->registerIndex($indexName);
            $index = $this->luceneSearch->getIndex($indexName);
        }

        return $index;
    }

    protected function registerIndex($indexName)
    {
        try {
            $index = $this->luceneSearch->getIndex($indexName);
        } catch(\Exception $e) {
            $index = null;
        }

        if (!$index || !($index instanceof Index)) {
            $indexPath = $this->kernel->getRootDir().'/../data/search/'.$indexName;

            if (!file_exists($indexPath)) {
                $oldmask = umask(0);
                mkdir($indexPath, 0777, true);
                chmod($indexPath, 0777);
                umask($oldmask);
            }

            $this->luceneSearch->setIndex($indexName, $indexPath);
        }
    }

    /**
     * @param string $indexName
     * @return bool
     * @throws \Exception
     */
    public function isUpToDate($indexName)
    {
        $indexPath = $this->kernel->getRootDir() . '/../data/search/' . $indexName;

        if (file_exists($indexPath)) {
            foreach (glob("$indexPath/*.cfs") as $indexFile) {
                $changeDate = (new \DateTime())->setTimestamp(filemtime($indexFile));
                if ($changeDate->format('d.m.Y') == (new \DateTime())->format('d.m.Y')) {
                    return true;
                }
            }
        }

        return false;
    }
}
