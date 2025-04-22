<?php

declare(strict_types=1);

namespace Evolutive\Module\EvoLinkManager\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Query builder for log grid
 */
class LogQueryBuilder extends AbstractDoctrineQueryBuilder
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
     * Builds the search query for logs
     *
     * @param SearchCriteriaInterface|null $searchCriteria Search criteria
     *
     * @return QueryBuilder
     */
    public function getSearchQueryBuilder(?SearchCriteriaInterface $searchCriteria = null): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('l.*')
            ->from($this->dbPrefix . 'evo_linkmanager_log', 'l')
            ->orderBy('l.date_add', 'DESC');

        if ($searchCriteria) {
            $qb->setFirstResult($searchCriteria->getOffset())
                ->setMaxResults($searchCriteria->getLimit());

            $this->applyFilters($qb, $searchCriteria);
        }

        return $qb;
    }

    /**
     * Builds the count query for logs
     *
     * @param SearchCriteriaInterface|null $searchCriteria Search criteria
     *
     * @return QueryBuilder
     */
    public function getCountQueryBuilder(?SearchCriteriaInterface $searchCriteria = null): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(l.id_log)')
            ->from($this->dbPrefix . 'evo_linkmanager_log', 'l');

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
            if (empty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_log':
                    $qb->andWhere("l.{$filterName} = :{$filterName}")
                        ->setParameter($filterName, $filterValue);
                    break;

                case 'date_add':
                    if (isset($filterValue['from'])) {
                        $qb->andWhere('l.date_add >= :date_from')
                            ->setParameter('date_from', sprintf('%s 00:00:00', $filterValue['from']));
                    }
                    if (isset($filterValue['to'])) {
                        $qb->andWhere('l.date_add <= :date_to')
                            ->setParameter('date_to', sprintf('%s 23:59:59', $filterValue['to']));
                    }
                    break;

                case 'severity':
                case 'action':
                case 'resource_type':
                    $qb->andWhere("l.{$filterName} = :{$filterName}")
                        ->setParameter($filterName, $filterValue);
                    break;

                case 'resource_id':
                    $qb->andWhere("l.{$filterName} = :{$filterName}")
                        ->setParameter($filterName, (int) $filterValue);
                    break;

                case 'employee_name':
                case 'message':
                    $qb->andWhere("l.{$filterName} LIKE :{$filterName}")
                        ->setParameter($filterName, '%' . $filterValue . '%');
                    break;
            }
        }
    }
}
