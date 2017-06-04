<?php

class WSAL_Models_Query
{
    protected $columns = array();
    protected $conditions = array();
    protected $orderBy = array();
    protected $offset = null;
    protected $limit = null;
    protected $from = array();
    protected $meta_join = false;
    protected $searchCondition = null;
	protected $useDefaultAdapter = false;

    public function __construct()
    {

    }

    public function getConnector()
    {
        if (!empty($this->connector)) {
            return $this->connector;
        }
        if ($this->useDefaultAdapter) {
            $this->connector = WSAL_Connector_ConnectorFactory::GetDefaultConnector();
        } else {
            $this->connector = WSAL_Connector_ConnectorFactory::GetConnector();
        }
        return $this->connector;
    }

    public function getAdapter()
    {
        return $this->getConnector()->getAdapter('Query');
    }

    public function addColumn($column)
    {
        $this->columns[] = $column;
        return $this;
    }

    public function clearColumns()
    {
        $this->columns = array();
        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function setColumns($columns)
    {
        $this->columns = $columns;
        return $this;
    }

    public function addCondition($field, $value)
    {
        $this->conditions[$field] = $value;
        return $this;
    }

    public function addORCondition($aConditions)
    {
        $this->conditions[] = $aConditions;
    }

    public function clearConditions()
    {
        $this->conditions = array();
        return $this;
    }

    public function getConditions()
    {
        return $this->conditions;
    }

    public function addOrderBy($field, $isDescending = false)
    {
        $order = ($isDescending) ? 'DESC' : 'ASC';
        $this->orderBy[$field] = $order;
        return $this;
    }

    public function clearOrderBy()
    {
        $this->orderBy = array();
        return $this;
    }

    public function getOrderBy()
    {
        return $this->orderBy;
    }

    public function addFrom($fromDataSet)
    {
        $this->from[] = $fromDataSet;
        return $this;
    }

    public function clearFrom()
    {
        $this->from = array();
        return $this;
    }

    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Gets the value of limit.
     *
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Sets the value of limit.
     *
     * @param mixed $limit the limit
     *
     * @return self
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Gets the value of offset.
     *
     * @return mixed
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Sets the value of offset.
     *
     * @param mixed $offset the offset
     *
     * @return self
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    public function addSearchCondition($value)
    {
        $this->searchCondition = $value;
        return $this;
    }

    public function getSearchCondition()
    {
        return $this->searchCondition;
    }

    public function hasMetaJoin()
    {
        return $this->meta_join;
    }

    public function addMetaJoin()
    {
        $this->meta_join = true;
        return $this;
    }
}
