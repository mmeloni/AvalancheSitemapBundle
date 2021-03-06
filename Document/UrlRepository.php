<?php

namespace Avalanche\Bundle\SitemapBundle\Document;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Avalanche\Bundle\SitemapBundle\Sitemap\Url;
use Avalanche\Bundle\SitemapBundle\Sitemap\UrlRepositoryInterface;

class UrlRepository extends DocumentRepository implements UrlRepositoryInterface
{
    private $urlsToRemove = array();
    private $urlsToPersist = array();

    public function add(Url $url)
    {
    	$this->scheduleForPersist($url);
        $this->scheduleForCleanup($url);
    }

    public function findAllOnPage($page)
    {
        return $this->createQueryBuilder()
        	//->sort('lastmod', 'desc')
            ->skip(UrlRepositoryInterface::PER_PAGE_LIMIT * ($page - 1))
            ->limit(UrlRepositoryInterface::PER_PAGE_LIMIT)
            
            ->hydrate(false)
            ->getQuery()
            ->execute();
    }

    public function findOneByLoc($loc)
    {
        $url = $this->findOneBy(array('loc' => $loc));
        if (null !== $url) {
            $this->scheduleForCleanup($url);
        }
        return $url;
    }

    public function remove(Url $url)
    {
        $this->dm->remove($url);
        $this->scheduleForCleanup($url);
    }

    public function pages()
    {
        return max(ceil(count($this->findAll()) / UrlRepositoryInterface::PER_PAGE_LIMIT), 1);
    }

    public function flush()
    {
        foreach($this->urlsToPersist as $url) {
            $q = $this->createQueryBuilder();

              // Find the Url
              $q->findAndUpdate()
                        ->field('loc')->equals($url->getLoc());

              // Update found Url
              $q->update()->upsert(true)
                        ->field('lastmod')->set($url->getLastmod())
                        ->field('changefreq')->set($url->getChangefreq())
                        ->field('priority')->set($url->getPriority())
                        ->field('provider')->set($url->getProvider());
                        //->field('images')->set($url->all());

            $q->getQuery()->execute();
        }

        foreach ($this->urlsToRemove as $url) {
            $this->dm->detach($url);
        }
        $this->dm->flush();

        $this->cleanup();
    }

    public function getLastmod($page = null)
    {
    	return new \DateTime(); //It can't find if an url was deleted from a page, so we need to reupdate them.
    }

    private function scheduleForCleanup(Url $url)
    {
        $this->urlsToRemove[] = $url;
    }
    
    private function scheduleForPersist(Url $url)
    {
    	$this->urlsToPersist[] = $url;
    }

    private function cleanup()
    {
        $this->urlsToRemove = array();
        $this->urlsToPersist = array();
    }
}
