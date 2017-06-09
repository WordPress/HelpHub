<?php

class WSAL_Adapters_MySQL_Query implements WSAL_Adapters_QueryInterface
{
    protected $connection;

    public function __construct($conn)
    {
        $this->connection = $conn;
    }

    /**
     * @return string Generated sql.
     */
    protected function GetSql($query, &$args = array())
    {
        $conditions = $query->getConditions();
        $searchCondition = $this->SearchCondition($query);
        $sWhereClause = "";
        foreach ($conditions as $fieldName => $fieldValue) {
            if (empty($sWhereClause)) {
                $sWhereClause .= " WHERE ";
            } else {
                $sWhereClause .= " AND ";
            }

            if (is_array($fieldValue)) {
                $subWhereClause = "(";
                foreach ($fieldValue as $orFieldName => $orFieldValue) {
                    if (is_array($orFieldValue)) {
                        foreach ($orFieldValue as $value) {
                            if ($subWhereClause != '(') {
                                $subWhereClause .= " OR ";
                            }
                            $subWhereClause .= $orFieldName;
                            $args[] = $value;
                        }
                    } else {
                        if ($subWhereClause != '(') {
                            $subWhereClause .= " OR ";
                        }
                        $subWhereClause .= $orFieldName;
                        $args[] = $orFieldValue;
                    }
                }
                $subWhereClause .= ")";
                $sWhereClause .= $subWhereClause;
            } else {
                $sWhereClause .= $fieldName;
                $args[] = $fieldValue;
            }
        }

        $fromDataSets = $query->getFrom();
        $columns = $query->getColumns();
        $orderBys = $query->getOrderBy();

        $sLimitClause = "";
        if ($query->getLimit()) {
            $sLimitClause .= " LIMIT ";
            if ($query->getOffset()) {
                $sLimitClause .= $query->getOffset() . ", ";
            }
            $sLimitClause .= $query->getLimit();
        }
        $joinClause = '';
        if ($query->hasMetaJoin()) {
            $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
            $occurrence = new WSAL_Adapters_MySQL_Occurrence($this->connection);
            $joinClause = ' LEFT JOIN '. $meta->GetTable() .' AS meta ON meta.occurrence_id = '. $occurrence->GetTable() .'.id ';
        }
        $fields = (empty($columns))? $fromDataSets[0] . '.*' : implode(',', $columns);
        if (!empty($searchCondition)) {
            $args[] = $searchCondition['args'];
        }
        
        $sql = 'SELECT ' . $fields
            . ' FROM ' . implode(',', $fromDataSets)
            . $joinClause
            . $sWhereClause
            . (!empty($searchCondition) ? (empty($sWhereClause) ? " WHERE ".$searchCondition['sql'] : " AND ".$searchCondition['sql']) : '')
            // @todo GROUP BY goes here
            . (!empty($orderBys) ? (' ORDER BY ' . implode(', ', array_keys($orderBys)) . ' ' . implode(', ', array_values($orderBys))) : '')
            . $sLimitClause;
        return $sql;
    }
    
    protected function getActiveRecordAdapter()
    {
        return new WSAL_Adapters_MySQL_ActiveRecord($this->connection);
    }
    
    /**
     * @return WSAL_Models_ActiveRecord[] Execute query and return data as $ar_cls objects.
     */
    public function Execute($query)
    {
        $args = array();
        $sql = $this->GetSql($query, $args);

        $occurenceAdapter = $query->getConnector()->getAdapter("Occurrence");

        if (in_array($occurenceAdapter->GetTable(), $query->getFrom())) {
            return $occurenceAdapter->LoadMulti($sql, $args);
        } else {
            return $this->getActiveRecordAdapter()->LoadMulti($sql, $args);
        }
    }
    
    /**
     * @return int Use query for counting records.
     */
    public function Count($query)
    {
        // back up columns, use COUNT as default column and generate sql
        $cols = $query->getColumns();
        $query->clearColumns();
        $query->addColumn('COUNT(*)');

        $args = array();
        $sql = $this->GetSql($query, $args);

        // restore columns
        $query->setColumns($cols);
        // execute query and return result
        return $this->getActiveRecordAdapter()->CountQuery($sql, $args);
    }

    public function CountDelete($query)
    {
        $result = $this->GetSqlDelete($query, true);
        // execute query and return result
        return $this->getActiveRecordAdapter()->CountQuery($result['sql'], $result['args']);
    }
    
    /**
     * Use query for deleting records.
     */
    public function Delete($query)
    {
        $result = $this->GetSqlDelete($query);
        $this->DeleteMetas($query, $result['args']);
        return $this->getActiveRecordAdapter()->DeleteQuery($result['sql'], $result['args']);
    }

    public function DeleteMetas($query, $args)
    {
        // back up columns, use COUNT as default column and generate sql
        $cols = $query->getColumns();
        $query->clearColumns();
        $query->addColumn('id');
        $sql = $this->GetSql($query);
        // restore columns
        $query->setColumns($cols);

        $_wpdb = $this->connection;
        $occ_ids = array();
        $sql = (!empty($args) ? $_wpdb->prepare($sql, $args) : $sql);
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            $occ_ids[] = $data['id'];
        }
        $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
        $meta->DeleteByOccurenceIds($occ_ids);
    }

    public function GetSqlDelete($query, $getCount = false)
    {
        $result = array();
        $args = array();
        // back up columns, remove them for DELETE and generate sql
        $cols = $query->getColumns();
        $query->clearColumns();

        $conditions = $query->getConditions();

        $sWhereClause = "";
        foreach ($conditions as $fieldName => $fieldValue) {
            if (empty($sWhereClause)) {
                $sWhereClause .= " WHERE ";
            } else {
                $sWhereClause .= " AND ";
            }
            $sWhereClause .= $fieldName;
            $args[] = $fieldValue;
        }

        $fromDataSets = $query->getFrom();
        $orderBys = $query->getOrderBy();

        $sLimitClause = "";
        if ($query->getLimit()) {
            $sLimitClause .= " LIMIT ";
            if ($query->getOffset()) {
                $sLimitClause .= $query->getOffset() . ", ";
            }
            $sLimitClause .= $query->getLimit();
        }
        $result['sql'] = ($getCount ? 'SELECT COUNT(*) FROM ' : 'DELETE FROM ')
            . implode(',', $fromDataSets)
            . $sWhereClause
            . (!empty($orderBys) ? (' ORDER BY ' . implode(', ', array_keys($orderBys)) . ' ' . implode(', ', array_values($orderBys))) : '')
            . $sLimitClause;
        $result['args'] = $args;
        //restore columns        
        $query->setColumns($cols);
        
        return $result;
    }

    public function SearchCondition($query)
    {
        $condition = $query->getSearchCondition();
        if (empty($condition)) return null;
        $searchConditions = array();
        $meta = new WSAL_Adapters_MySQL_Meta($this->connection);
        $occurrence = new WSAL_Adapters_MySQL_Occurrence($this->connection);
        if (is_numeric($condition) && strlen($condition) == 4) {
            $searchConditions['sql'] = $occurrence->GetTable() .'.alert_id LIKE %s';
        } else {
            $searchConditions['sql'] = $occurrence->GetTable() .'.id IN (
                SELECT DISTINCT occurrence_id
                    FROM ' . $meta->GetTable() . '
                    WHERE TRIM(BOTH "\"" FROM value) LIKE %s
                )';
        }
        $searchConditions['args'] = "%". $condition. "%";
        return $searchConditions;
    }

}
