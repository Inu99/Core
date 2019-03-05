<?php
namespace exface\Core\CommonLogic\QueryBuilder;

use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\DataTypes\BooleanDataType;

/**
 * A filter group query part represents a condition group used for filtering in a query.
 *
 * @author Andrej Kabachnik
 *        
 */
class QueryPartFilterGroup extends QueryPart implements iCanBeCopied
{

    private $operator = EXF_LOGICAL_AND;

    private $filters = array();

    private $nested_groups = array();

    private $condition_group = null;

    public function getOperator()
    {
        return $this->operator;
    }

    public function setOperator($value)
    {
        $this->operator = $value;
    }

    /**
     * Adds a filter to the group.
     *
     * @param
     *            QueryPartFilter
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function addFilter(QueryPartFilter $filter)
    {
        // Only add filters based on attributes. A query can only work with meta model attributes, not with other
        // expressions. Filters based on formulas need to be applied by the DataSheet and cannot be handled by queries!
        if ($filter->getAttribute() && ! is_null($filter->getCompareValue()) && $filter->getCompareValue() !== '') {
            $this->filters[] = $filter;
            $this->getConditionGroup()->addCondition($filter->getCondition());
        }
        return $this;
    }

    /**
     * Creates a filter from a given condition object, adds it to the group and returns the resulting query part.
     *
     * @param Condition $condition            
     * @return QueryPartFilter
     */
    public function addCondition(Condition $condition)
    {
        $qpart = $this->createQueryPartFromCondition($condition);
        $this->addFilter($qpart);
        return $qpart;
    }

    /**
     * Adds a nested filter group.
     *
     * @param QueryPartFilterGroup $group            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function addNestedGroup(QueryPartFilterGroup $group)
    {
        $this->nested_groups[] = $group;
        $this->getConditionGroup()->addNestedGroup($group->getConditionGroup());
        return $this;
    }

    /**
     * Creates a filter group from a given condition group, adds it to the group and returns the resulting query part.
     *
     * @param ConditionGroup $group            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function addConditionGroup(ConditionGroup $group)
    {
        $qpart = $this->createQueryPartFromConditionGroup($group);
        $this->addNestedGroup($qpart);
        return $qpart;
    }

    /**
     * Returns all FilterQueryParts in this group, optionally filtering them with the given filter function.
     * 
     * The $filter must implement the following interface `function(QueryPartFilter $filter) : bool`;
     * 
     * @return QueryPartFilter[]
     */
    public function getFilters(callable $filter = null)
    {
        $filters = $this->filters;
        
        if ($filter !== null) {
            $filters = array_filter($filters, $filter);
        }
        
        return $filters;
    }

    /**
     *
     * @return QueryPartFilterGroup[]
     */
    public function getNestedGroups()
    {
        return $this->nested_groups;
    }

    /**
     * Returns an array of filters an nested filter groups - that is, all query parts contained in this filter group
     *
     * @return QueryPart[]
     */
    public function getFiltersAndNestedGroups()
    {
        return array_merge($this->getFilters(), $this->getNestedGroups());
    }

    /**
     * Creates a filter query part from a condition
     *
     * @param Condition $condition            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
     */
    public function createQueryPartFromCondition(Condition $condition)
    {
        $qpart = new QueryPartFilter($condition->getExpression()->toString(), $this->query);
        $qpart->setCondition($condition);
        return $qpart;
    }

    /**
     * Creates a filter group query part from a condition group
     *
     * @param ConditionGroup $group            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup
     */
    public function createQueryPartFromConditionGroup(ConditionGroup $group)
    {
        $qpart = new QueryPartFilterGroup('', $this->query);
        $qpart->setOperator($group->getOperator());
        foreach ($group->getConditions() as $c) {
            $qpart->addCondition($c);
        }
        foreach ($group->getNestedGroups() as $g) {
            $qpart->addConditionGroup($g);
        }
        return $qpart;
    }

    /**
     * A filter group uses all relations used by it's filters and subgroups
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\QueryPart::getUsedRelations()
     */
    public function getUsedRelations($relation_type = null)
    {
        $rels = array();
        foreach ($this->getFilters() as $qpart) {
            $rels = array_merge($rels, $qpart->getUsedRelations($relation_type));
        }
        foreach ($this->getNestedGroups() as $qpart) {
            $rels = array_merge($rels, $qpart->getUsedRelations($relation_type));
        }
        return $rels;
    }

    /**
     * Returns the condition group represented by this filter group.
     *
     * IDEA Currently the condition group is updated every time something happens to the filter group (add_filter(), add_nested_group(), etc.). Perhaps it
     * is a better idea to build the condition group on demand, because it is only needed on very rare occasions (e.g. for rebasing conditions in subqueries, etc.)
     * I don't know, if maintainig the condition group all the time has an impact on performance or memory consumption.
     *
     * @return \exface\Core\CommonLogic\Model\ConditionGroup
     */
    public function getConditionGroup()
    {
        if ($this->condition_group === null) {
            $this->condition_group = ConditionGroupFactory::createEmpty($this->getWorkbench(), $this->getOperator());
        }
        return $this->condition_group;
    }

    /**
     * Returns a filter query part matching the given alias or FALSE if no match found.
     * Checks nested filter groups recursively.
     *
     * @param string $alias            
     * @return QueryPartFilter || boolean
     */
    public function findFilterByAlias($alias)
    {
        foreach ($this->getFilters() as $f) {
            if ($f->getAlias() == $alias)
                return $f;
        }
        
        foreach ($this->getNestedGroups() as $g) {
            if ($f = $g->findFilterByAlias($alias))
                return $f;
        }
        
        return false;
    }
    
    /**
     * 
     * @return QueryPartFilterGroup
     */
    public function copy()
    {
        $copy = new QueryPartFilterGroup($this->getAlias(), $this->getQuery());
        $copy->setOperator($this->getOperator());
        foreach ($this->getFilters() as $qpart) {
            $copy->addFilter($qpart->copy());
        }
        foreach ($this->getNestedGroups() as $qpart) {
            $copy->addNestedGroup($qpart->copy());
        }
        return $copy;
    }

    /**
     * Removes the given filter from this group (not from the nested groups)
     * 
     * @param QueryPartFilter $qpart
     * @return QueryPartFilterGroup
     */
    public function removeFilter(QueryPartFilter $qpart) : QueryPartFilterGroup
    {
        $key = array_search($qpart, $this->filters);
        if ($key !== false) {
            $this->getConditionGroup()->removeCondition($qpart->getCondition());
            unset($this->filters[$key]);
            $this->filters = array_values($this->filters);
        }
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasFilters() : bool
    {
        return ! empty($this->filters);
    }
    
    /**
     * 
     * @return bool
     */
    public function hasNestedGroups() : bool
    {
        return ! empty($this->nested_groups);
    }
    
    /**
     * Returns TRUE if the group has neither filters nor nested groups.
     * 
     * @return bool
     */
    public function isEmpty() : bool
    {
        return ! ($this->hasFilters() || $this->hasNestedGroups());
    }
    
    /**
     * Makes all QueryPartFilters in this group and nested groups be applied 
     * in-memory after the actuay reading.
     * 
     * The optional $filter callback is passed on to getFilters() for every 
     * group being processed.
     * 
     * @param boolean $value
     * @return QueryPartFilterGroup
     */
    public function setApplyAfterReading($value, callable $filter = null) : QueryPartFilterGroup
    {
        foreach ($this->getFilters($filter) as $filter) {
            $filter->setApplyAfterReading(true);
        }
        
        foreach ($this->getNestedGroups() as $group) {
            $group->setApplyAfterReading(true, $filter);
        }
        return $this;
    }
}
?>