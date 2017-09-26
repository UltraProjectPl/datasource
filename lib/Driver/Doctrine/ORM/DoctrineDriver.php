<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Driver\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FSi\Component\DataSource\Driver\Doctrine\ORM\Exception\DoctrineDriverException;
use FSi\Component\DataSource\Driver\DriverAbstract;

/**
 * Driver to fetch data from databases using Doctrine.
 */
class DoctrineDriver extends DriverAbstract
{
    /**
     * Default alias for entity during building query when no alias is specified.
     */
    const DEFAULT_ENTITY_ALIAS = 'e';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * Alias, that can be used with preconfigured query when fetching one entity and field mappings
     * don't have mappings prefixed with aliases.
     *
     * @var string
     */
    private $alias;

    /**
     * Template query builder.
     *
     * @var QueryBuilder
     */
    private $query;

    /**
     * Query builder available during preGetResult event.
     *
     * @var QueryBuilder
     */
    private $currentQuery;

    /**
     * @var bool
     */
    private $useOutputWalkers;

    /**
     * @param array $extensions
     * @param EntityManagerInterface $em
     * @param string|QueryBuilder $entity
     * @param string $alias
     * @param bool|null $useOutputWalkers
     *
     * @throws DoctrineDriverException
     */
    public function __construct($extensions, EntityManagerInterface $em, $entity, $alias = null, $useOutputWalkers = null)
    {
        parent::__construct($extensions);

        $this->em = $em;

        if (isset($alias)) {
            $this->alias = (string) $alias;
        } else if ($entity instanceof QueryBuilder) {
            $this->alias = $entity->getRootAlias();
        } else {
            $this->alias = self::DEFAULT_ENTITY_ALIAS;
        }

        if ($entity instanceof QueryBuilder) {
            $this->query = $entity;
        } else {
            if (empty($entity)) {
                throw new DoctrineDriverException('Name of entity can\'t be empty.');
            }

            $this->query = $this->em->createQueryBuilder();
            $this->query
                ->select($this->alias)
                ->from((string) $entity, $this->alias)
            ;
        }

        $this->useOutputWalkers = $useOutputWalkers;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'doctrine-orm';
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * {@inheritdoc}
     */
    public function initResult()
    {
        $this->currentQuery = clone $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult($fields, $first, $max)
    {
        foreach ($fields as $field) {
            if (!$field instanceof DoctrineFieldInterface) {
                throw new DoctrineDriverException(sprintf(
                    'All fields must be instances of FSi\Component\DataSource\Driver\Doctrine\ORM\DoctrineFieldInterface.'
                ));
            }

            $field->buildQuery($this->currentQuery, $this->alias);
        }

        if ($max > 0) {
            $this->currentQuery->setMaxResults($max);
            $this->currentQuery->setFirstResult($first);
        }

        $result = new Paginator($this->currentQuery);
        $result->setUseOutputWalkers($this->useOutputWalkers);

        $this->currentQuery = null;

        return $result;
    }

    /**
     * Returns query builder.
     *
     * If query is set to null (so when getResult method is NOT executed at the moment) exception is throwed.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if (!isset($this->currentQuery)) {
            throw new DoctrineDriverException('Query is accessible only during preGetResult event.');
        }

        return $this->currentQuery;
    }
}
