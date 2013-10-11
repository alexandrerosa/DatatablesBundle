<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DatatableData
 *
 * @package Sg\DatatablesBundle\Datatable
 */
class DatatableData implements DatatableDataInterface
{
    /**
     * @var array
     */
    protected $requestParams;

    /**
     * @var ClassMetadata
     */
    protected $metadata;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var DatatableQuery
     */
    protected $datatableQuery;

    /**
     * The name of the primary table.
     *
     * @var string
     */
    protected $tableName;

    /**
     * The name of the entity class.
     *
     * @var string
     */
    protected $entityName;

    /**
     * The field name of the identifier/primary key.
     *
     * @var mixed
     */
    protected $rootEntityIdentifier;

    /**
     * @var array
     */
    protected $response;

    /**
     * @var array
     */
    protected $selectColumns;

    /**
     * @var array
     */
    protected $allColumns;

    /**
     * @var array
     */
    protected $joins;


    //-------------------------------------------------
    // Ctor.
    //-------------------------------------------------

    /**
     * Ctor.
     *
     * @param array         $requestParams All GET params
     * @param ClassMetadata $metadata      A ClassMetadata instance
     * @param EntityManager $em            A EntityManager instance
     * @param Serializer    $serializer    A Serializer instance
     */
    public function __construct(array $requestParams, ClassMetadata $metadata, EntityManager $em, Serializer $serializer)
    {
        $this->requestParams        = $requestParams;
        $this->metadata             = $metadata;
        $this->em                   = $em;
        $this->serializer           = $serializer;
        $this->tableName            = $metadata->getTableName();
        $this->entityName           = $metadata->getName();
        $this->datatableQuery       = new DatatableQuery($em, $this->tableName, $this->entityName, $this->requestParams);
        $identifiers                = $this->metadata->getIdentifierFieldNames();
        $this->rootEntityIdentifier = array_shift($identifiers);
        $this->response             = array();
        $this->selectColumns        = array();
        $this->allColumns           = array();
        $this->joins                = array();

        $this->prepareColumns();
    }


    //-------------------------------------------------
    // Private
    //-------------------------------------------------

    /**
     * Add an entry to the joins[] array.
     *
     * @param array $join
     *
     * @return $this
     */
    private function addJoin(array $join)
    {
        $this->joins[] = $join;

        return $this;
    }

    /**
     * Set associations in joins[].
     *
     * @param array         $associationParts An array of the association parts
     * @param int           $i                Numeric key
     * @param ClassMetadata $metadata         A ClassMetadata instance
     *
     * @return $this
     */
    private function setAssociations($associationParts, $i, $metadata)
    {
        $column = $associationParts[$i];

        if ($metadata->hasAssociation($column) === true) {
            $targetClass          = $metadata->getAssociationTargetClass($column);
            $targetMeta           = $this->em->getClassMetadata($targetClass);
            $targetTableName      = $targetMeta->getTableName();
            $targetIdentifiers    = $targetMeta->getIdentifierFieldNames();
            $targetRootIdentifier = array_shift($targetIdentifiers);

            if (!array_key_exists($targetTableName, $this->selectColumns)) {
                $this->selectColumns[$targetTableName][] = $targetRootIdentifier;

                $this->addJoin(
                    array(
                        'source' => $metadata->getTableName() . '.' . $column,
                        'target' => $targetTableName
                    )
                );
            }

            $i++;
            $this->setAssociations($associationParts, $i, $targetMeta);
        } else {
            $targetTableName      = $metadata->getTableName();
            $targetIdentifiers    = $metadata->getIdentifierFieldNames();
            $targetRootIdentifier = array_shift($targetIdentifiers);

            if ($column !== $targetRootIdentifier) {
                array_push($this->selectColumns[$targetTableName], $column);
            }

            $this->allColumns[] = $targetTableName . '.' . $column;
        }

        return $this;
    }

    /**
     * Prepare selectColumns[], allColumns[] and joins[].
     *
     * @return $this
     */
    private function prepareColumns()
    {
        // start with the tableName and the primary key e.g. 'fos_user' and 'id'
        $this->selectColumns[$this->tableName][] = $this->rootEntityIdentifier;

        for ($i = 0; $i <= $this->requestParams['dql_counter']; $i++) {

            if ($this->requestParams['dql_' . $i] != null) {
                $column = $this->requestParams['dql_' . $i];

                // association delimiter found (e.g. 'posts.comments.title')?
                if (strstr($column, '.') !== false) {
                    $array = explode('.', $column);
                    $this->setAssociations($array, 0, $this->metadata);
                } else {
                    // no association found
                    if ($column !== $this->rootEntityIdentifier) {
                        array_push($this->selectColumns[$this->tableName], $column);
                    }

                    $this->allColumns[] = $this->tableName . '.' . $column;
                }
            }

        }

        return $this;
    }


    //-------------------------------------------------
    // DatatableDataInterface
    //-------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        $this->datatableQuery->setSelectColumns($this->selectColumns);
        $this->datatableQuery->setAllColumns($this->allColumns);
        $this->datatableQuery->setJoins($this->joins);

        $this->datatableQuery->setSelectFrom();
        $this->datatableQuery->setLeftJoins($this->datatableQuery->getQb());
        $this->datatableQuery->setWhere($this->datatableQuery->getQb());
        $this->datatableQuery->setWhereCallbacks($this->datatableQuery->getQb());
        $this->datatableQuery->setOrderBy();
        $this->datatableQuery->setLimit();

        $fresults = new Paginator($this->datatableQuery->execute(), true);
        $output = array("aaData" => array());

        foreach ($fresults as $item) {
            $output['aaData'][] = $item;
        }

        $outputHeader = array(
            "sEcho" => (int) $this->requestParams['sEcho'],
            "iTotalRecords" => $this->datatableQuery->getCountAllResults($this->rootEntityIdentifier),
            "iTotalDisplayRecords" => $this->datatableQuery->getCountFilteredResults($this->rootEntityIdentifier)
        );

        $this->response = array_merge($outputHeader, $output);

        $json = $this->serializer->serialize($this->response, 'json');
        $response = new Response($json);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }


    //-------------------------------------------------
    // Public
    //-------------------------------------------------

    /**
     * Add a callback function.
     *
     * @param string $callback
     *
     * @return DatatableData
     * @throws \Exception
     */
    public function addWhereBuilderCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new \Exception("The callback argument must be callable.");
        }

        $this->datatableQuery->addCallback($callback);

        return $this;
    }
}
