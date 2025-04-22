<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Query builder for link grid
 */
class LinkQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /**
     * @var string
     */
    protected $dbPrefix;

    /**
     * @param Connection $connection Database connection
     * @param string $dbPrefix Database prefix
     * @param DoctrineSearchCriteriaApplicatorInterface|null $searchCriteriaApplicator Search criteria applicator
     */
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        ?DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator = null,
    ) {
        parent::__construct($connection, $searchCriteriaApplicator);
        $this->dbPrefix = $dbPrefix;
    }

    /**
     * Builds the search query for links
     *
     * @param SearchCriteriaInterface|null $searchCriteria Search criteria
     *
     * @return QueryBuilder
     */
    public function getSearchQueryBuilder(?SearchCriteriaInterface $searchCriteria = null): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('l.*')
            // Add a left join to get placement identifiers
            ->addSelect('p.identifier')
            ->from($this->dbPrefix . 'evo_linkmanager_link', 'l')
            ->leftJoin(
                'l',
                $this->dbPrefix . 'evo_linkmanager_placement_link',
                'pl',
                'l.id_link = pl.id_link'
            )
            ->leftJoin(
                'pl',
                $this->dbPrefix . 'evo_linkmanager_placement',
                'p',
                'pl.id_placement = p.id_placement'
            )
            ->orderBy('l.position', 'ASC');

        if ($searchCriteria) {
            $qb->setFirstResult($searchCriteria->getOffset())
                ->setMaxResults($searchCriteria->getLimit());

            $this->applyFilters($qb, $searchCriteria);
        }

        return $qb;
    }

    /**
     * Builds the count query for links
     *
     * @param SearchCriteriaInterface|null $searchCriteria Search criteria
     *
     * @return QueryBuilder
     */
    public function getCountQueryBuilder(?SearchCriteriaInterface $searchCriteria = null): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(DISTINCT l.id_link)')
            ->from($this->dbPrefix . 'evo_linkmanager_link', 'l')
            ->leftJoin(
                'l',
                $this->dbPrefix . 'evo_linkmanager_placement_link',
                'pl',
                'l.id_link = pl.id_link'
            )
            ->leftJoin(
                'pl',
                $this->dbPrefix . 'evo_linkmanager_placement',
                'p',
                'pl.id_placement = p.id_placement'
            );

        if ($searchCriteria) {
            $this->applyFilters($qb, $searchCriteria);
        }

        return $qb;
    }

    /**
     * Applies filters to the query
     *
     * @param QueryBuilder $qb Query builder
     * @param SearchCriteriaInterface $criteria Search criteria
     *
     * @return void
     */
    private function applyFilters(QueryBuilder $qb, SearchCriteriaInterface $criteria): void
    {
        $filters = $criteria->getFilters();

        foreach ($filters as $filterName => $filterValue) {
            switch ($filterName) {
                case 'id_link':
                    $qb->andWhere("l.{$filterName} = :{$filterName}")
                        ->setParameter($filterName, $filterValue);
                    break;

                case 'active':
                    $qb->andWhere("l.{$filterName} = :{$filterName}")
                        ->setParameter($filterName, $filterValue);
                    break;

                case 'link_type':
                    $qb->andWhere("l.{$filterName} = :{$filterName}")
                        ->setParameter($filterName, $filterValue);
                    break;

                case 'identifier':
                    $qb->andWhere("p.{$filterName} LIKE :{$filterName}")
                        ->setParameter($filterName, '%' . $filterValue . '%');
                    break;

                case 'name':
                case 'url':
                    $qb->andWhere("l.{$filterName} LIKE :{$filterName}")
                        ->setParameter($filterName, '%' . $filterValue . '%');
                    break;
            }
        }
    }
}
