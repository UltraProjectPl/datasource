<?php

/*
 * This file is part of the FSi Component package.
*
* (c) Lukasz Cybula <lukasz@fsi.pl>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace FSi\Component\DataSource\Driver\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use FSi\Component\DataIndexer\DoctrineDataIndexer;

class DoctrineResult extends ArrayCollection
{
    private $count;

    public function __construct(ManagerRegistry $registry, Paginator $paginator)
    {
        $this->count = $paginator->count();
        $data = $paginator->getIterator();
        $firstElement = current($data);
        $dataIndexer = new DoctrineDataIndexer($registry, get_class($firstElement));

        $result = array();
        foreach ($data as $element) {
            $index = $dataIndexer->getIndex($element);
            $result[$index] = $element;
        }

        parent::__construct($result);
    }

    public function count()
    {
        return $this->count;
    }
}