<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Widgets\iHaveColumnGroups;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iHaveFooter;

/**
 * Data is the base for all widgets displaying tabular data.
 *
 * Many widgets like Chart, ComboTable, etc. contain internal Data sub-widgets, that define the data set used
 * by these widgets. Datas are much like tables: you can define columns, sorters, filters, pagination rules, etc.
 * 
 * @method DataButton[] getButtons()
 * @method DataToolbar[] getToolbars()
 * @method DataToolbar getToolbarMain()
 *
 * @author Andrej Kabachnik
 *        
 */
class Data extends AbstractWidget implements iHaveHeader, iHaveFooter, iHaveColumns, iHaveColumnGroups, iHaveToolbars, iHaveButtons, iHaveFilters, iSupportLazyLoading, iHaveContextualHelp, iHaveConfigurator
{
    use iHaveButtonsAndToolbarsTrait;

    // properties
    private $paginate = true;

    private $paginate_page_size = null;

    private $aggregate_by_attribute_alias = null;

    private $lazy_loading = true;

    // Data should be loaded lazily by defaul (via AJAX) - of course, only if the used template supports this
    private $lazy_loading_action = 'exface.Core.ReadData';

    private $lazy_loading_group_id = null;

    /** @var DataColumnGroup[] */
    private $column_groups = array();
    
    /** @var DataToolbar[] */
    private $toolbars = array();

    // other stuff
    /** @var UxonObject[] */
    private $sorters = array();

    /** @var boolean */
    private $is_editable = false;

    /** @var WidgetLinkInterface */
    private $refresh_with_widget = null;

    private $values_data_sheet = null;

    /**
     * @uxon empty_text The text to be displayed, if there are no data records
     *
     * @var string
     */
    private $empty_text = null;

    private $help_button = null;

    private $hide_help_button = false;
    
    private $configurator = null;
    
    private $hide_refresh_button = null;

    private $hide_header = false;
    
    private $hide_footer = false;
    
    private $has_system_columns = false;

    protected function init()
    {
        parent::init();
        // Add the main column group
        if (count($this->getColumnGroups()) == 0) {
            $this->addColumnGroup($this->getPage()->createWidget('DataColumnGroup', $this));
        }
    }

    public function addColumn(DataColumn $column)
    {
        $this->getColumnGroupMain()->addColumn($column);
        return $this;
    }

    public function createColumnFromAttribute(MetaAttributeInterface $attribute, $caption = null, $hidden = null)
    {
        return $this->getColumnGroupMain()->createColumnFromAttribute($attribute, $caption, $hidden);
    }
    
    public function createColumnFromUxon(UxonObject $uxon)
    {
        return $this->getColumnGroupMain()->createColumnFromUxon($uxon);
    }

    /**
     * Returns the id of the column holding the UID of each row.
     * By default it is the column with the UID attribute of
     * the meta object displayed in by the data widget, but this can be changed in the UXON description if required.
     *
     * @return string
     */
    public function getUidColumnId()
    {
        return $this->getColumnGroupMain()->getUidColumnId();
    }

    /**
     * Sets the id of the column to be used as UID for each data row
     *
     * @uxon-property uid_column_id
     * @uxon-type string
     *
     * @param string $value            
     */
    public function setUidColumnId($value)
    {
        $this->getColumnGroupMain()->setUidColumnId($value);
        return $this;
    }

    /**
     * Returns the UID column as DataColumn
     *
     * @return \exface\Core\Widgets\DataColumn
     */
    public function getUidColumn()
    {
        return $this->getColumnGroupMain()->getUidColumn();
    }

    /**
     * Returns TRUE if this data widget has a UID column or FALSE otherwise.
     *
     * @return boolean
     */
    public function hasUidColumn()
    {
        return $this->getColumnGroupMain()->hasUidColumn();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        // Columns & Totals
        if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
            foreach ($this->getColumns() as $col) {
                // Only add columns, that actually have content. The other columns exist only in the widget
                // TODO This check will get more complicated, once the content can be specified not only via attribute_alias
                // but also with properties like formula, etc.
                if (! $col->getAttributeAlias())
                    continue;
                $data_column = $data_sheet->getColumns()->addFromExpression($col->getAttributeAlias(), $col->getDataColumnName(), $col->isHidden());
                // Add a total to the data sheet, if the column has a footer
                // TODO wouldn't it be better to use the column id here?
                if ($col->hasFooter()) {
                    $total = DataColumnTotalsFactory::createFromString($data_column, $col->getFooter());
                    $data_column->getTotals()->add($total);
                }
            }
        }
        
        // Aggregations
        foreach ($this->getAggregations() as $attr) {
            $data_sheet->getAggregations()->addFromString($attr);
        }
        
        // Pagination
        if ($this->getPaginatePageSize()){
            $data_sheet->setRowsOnPage($this->getPaginatePageSize());
        }
        
        // Filters and sorters only if lazy loading is disabled!
        if (! $this->getLazyLoading()) {
            // Add filters if they have values
            foreach ($this->getFilters() as $filter_widget) {
                if ($filter_widget->getValue()) {
                    $data_sheet->addFilterFromString($filter_widget->getAttributeAlias(), $filter_widget->getValue(), $filter_widget->getComparator());
                }
            }
            // Add sorters
            foreach ($this->getSorters() as $sorter_obj) {
                $data_sheet->getSorters()->addFromString($sorter_obj->getProperty('attribute_alias'), $sorter_obj->getProperty('direction'));
            }
        }
        
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc} To prefill a dataSet we need to filter it's results, so that they are related to the object we prefill
     *               with. Thus, the prefill data needs to contain the UID of that object.
     *              
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        if ($data_sheet->getMetaObject()->isExactly($this->getMetaObject())) {
            // If trying to prefill with an instance of the same object, we actually just need the uid column in the resulting prefill
            // data sheet. It will probably be there anyway, but we still add it here (just in case).
            $data_sheet->getColumns()->addFromExpression($this->getMetaObject()->getUidAttributeAlias());
        } else {
            // If trying to prefill with a different object, we need to find a relation to that object somehow.
            // First we check for filters based on the prefill object. If filters exists, we can be sure, that those
            // are the ones to be prefilled.
            $relevant_filters = $this->getConfiguratorWidget()->findFiltersByObject($data_sheet->getMetaObject());
            $uid_filters_found = false;
            // If there are filters over UIDs of the prefill object, just get data for these filters for the prefill,
            // because it does not make sense to fetch prefill data for UID-filters and attribute filters at the same
            // time. If data for the other filters will be found in the prefill sheet when actually doing the prefilling,
            // it should, of course, be applied too, but we do not tell ExFace to always fetch this data.
            foreach ($relevant_filters as $fltr) {
                if ($fltr->getAttribute()->isRelation() && $fltr->getAttribute()->getRelation()->getRelatedObject()->isExactly($data_sheet->getMetaObject())) {
                    $data_sheet = $fltr->prepareDataSheetToPrefill($data_sheet);
                    $uid_filters_found = true;
                }
            }
            // If thre are no UID-filters, than we can request data for the other filters.
            if (count($relevant_filters) > 0 && ! $uid_filters_found) {
                foreach ($relevant_filters as $fltr) {
                    $data_sheet = $fltr->prepareDataSheetToPrefill($data_sheet);
                }
            }
            
            // If there is no filter defined explicitly, try to find a relation and create a corresponding filter
            if (! $fltr) {
                // TODO currently this only works for direct relations, not for chained ones.
                // FIXME check, if a filter on the current relation is there already, and add it only in this case
                /* @var $rel \exface\Core\CommonLogic\Model\relation */
                if ($rel = $this->getMetaObject()->findRelation($data_sheet->getMetaObject())) {
                    $fltr = $this->getConfiguratorWidget()->createFilterFromRelation($rel);
                    $data_sheet = $fltr->prepareDataSheetToPrefill($data_sheet);
                }
            }
        }
        return $data_sheet;
    }

    /**
     * Returns an array with all columns of the grid.
     * If no columns have been added yet,
     * default display attributes of the meta object are added as columns automatically.
     *
     * @return DataColumn[]
     */
    public function getColumns()
    {
        // If no columns explicitly specified, add the default columns
        if (count($this->getColumnGroups()) == 1 && $this->getColumnGroupMain()->isEmpty()) {
            $this->addColumnsForDefaultDisplayAttributes();
        }
        
        $columns = array();
        if (count($this->getColumnGroups()) == 1) {
            return $this->getColumnGroupMain()->getColumns();
        } else {
            foreach ($this->getColumnGroups() as $group) {
                $columns = array_merge($columns, $group->getColumns());
            }
        }
        
        return $columns;
    }

    /**
     * Returns the number of currently contained columns over all column groups.
     * NOTE: This does not trigger the creation of any default columns!
     *
     * @return number
     */
    public function countColumns()
    {
        $count = 0;
        foreach ($this->getColumnGroups() as $group) {
            $count += $group->countColumns();
        }
        return $count;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::hasColumns()
     */
    public function hasColumns()
    {
        foreach ($this->getColumnGroups() as $group){
            if ($group->hasColumns()){
                return true;
            }
        }
        return false;
    }

    /**
     * Creates and adds columns based on the default attributes of the underlying meta object (the ones marked with default_display_order)
     *
     * @return Data
     */
    public function addColumnsForDefaultDisplayAttributes()
    {
        // add the default columns
        $def_attrs = $this->getMetaObject()->getAttributes()->getDefaultDisplayList();
        foreach ($def_attrs as $attr) {
            $alias = ($attr->getRelationPath()->toString() ? $attr->getRelationPath()->toString() . RelationPath::getRelationSeparator() : '') . $attr->getAlias();
            $attr = $this->getMetaObject()->getAttribute($alias);
            $this->addColumn($this->createColumnFromAttribute($attr, null, $attr->isHidden()));
        }
        return $this;
    }

    function getColumn($column_id)
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getId() === $column_id) {
                return $col;
            }
        }
        return false;
    }

    /**
     * Returns the first column with a matching attribute alias.
     *
     * @param string $alias_with_relation_path            
     * @return \exface\Core\Widgets\DataColumn|boolean
     */
    public function getColumnByAttributeAlias($alias_with_relation_path)
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getAttributeAlias() === $alias_with_relation_path) {
                return $col;
            }
        }
        return false;
    }

    /**
     *
     * @param string $data_sheet_column_name            
     * @return \exface\Core\Widgets\DataColumn|boolean
     */
    public function getColumnByDataColumnName($data_sheet_column_name)
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getDataColumnName() === $data_sheet_column_name) {
                return $col;
            }
        }
        return false;
    }

    /**
     * Returns an array with columns containing system attributes
     *
     * @return \exface\Core\Widgets\DataColumn[]
     */
    public function getColumnsWithSystemAttributes()
    {
        $result = array();
        foreach ($this->getColumns() as $col) {
            if ($col->getAttribute() && $col->getAttribute()->isSystem()) {
                $result[] = $col;
            }
        }
        return $result;
    }

    /**
     * Defines the columns of data: each element of the array can be a DataColumn or a DataColumnGroup widget.
     *
     * To create a column showing an attribute of the Data's meta object, it is sufficient to only set
     * the attribute_alias for each column object. Other properties like caption, align, editor, etc.
     * are optional. If not set, they will be determined from the properties of the attribute.
     *
     * The widget type (DataColumn or DataColumnGroup) can be omitted: it can be determined automatically:
     * E.g. adding {"attribute_group_alias": "~VISIBLE"} as a column is enough to generate a column group
     * with all visible attributes of the object.
     *
     * Column groups with captions will produce grouped columns with mutual headings (s. example below).
     *
     * Example:
     * "columns": [
     * {
     * "attribute_alias": "PRODUCT__LABEL",
     * "caption": "Product"
     * },
     * {
     * "attribute_alias": "PRODUCT__BRAND__LABEL"
     * },
     * {
     * "caption": "Sales",
     * "columns": [
     * {
     * "attribute_alias": "QUANTITY:SUM",
     * "caption": "Qty."
     * },
     * {
     * "attribute_alias": "VALUE:SUM",
     * "caption": "Sum"
     * }
     * ]
     * }
     * ]
     *
     * @uxon-property columns
     * @uxon-type DataColumn[]|DataColumnGroup[] *
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::setColumns()
     */
    public function setColumns(UxonObject $columns)
    {
        $column_groups = array();
        $last_element_was_a_column_group = false;
        
        /*
         * The columns array of a data widget can contain columns or column groups or a mixture of those.
         * At this point, we must sort them apart
         * and make sure, all columns get wrappen in groups. Directly specified columns will get a generated
         * group, which won't have anything but the column list. If we have a user specified column group
         * somewhere in the middle, there will be two generated groups left and right of it. This makes sure,
         * that the get_columns() method, which lists all columns from all groups will list them in exact the
         * same order as the user had specified!
         */
        
        // Loop through all uxon elements in the columns array and separate columns and column groups
        // This is nesseccary because column groups can be created in short notation (just like a regular
        // column with a nested column list and an optional caption).
        // Additionally we will make sure, that all columns are within column groups, so we can jus instatiate
        // the groups, not each column separately. The actual instantiation of the corresponding widgets will
        // follow in the next step.
        foreach ($columns as $c) {
            if ($c instanceof UxonObject) {
                if ($c->isArray()) {
                    // If the element is an array itself (nested in columns), it is a column group
                    $column_groups[] = $c;
                    $last_element_was_a_column_group = true;
                } elseif (strcasecmp($c->getProperty('widget_type'), 'DataColumnGroup') === 0 || $c->hasProperty('columns')) {
                    // If not, check to see if it's widget type is DataColumnGroup or it has an array of columns itself
                    // If so, it still is a column group
                    $column_groups[] = $c;
                    $last_element_was_a_column_group = true;
                } else {
                    // If none of the above applies, it is a regular column, so we need to put it into a column group
                    // We start a new group, if the last element added was a columnt group or append it to the last
                    // group if that was built from single columns already
                    if (! count($column_groups) || $last_element_was_a_column_group) {
                        $group = new UxonObject();
                        $column_groups[] = $group;
                    } else {
                        $group = $column_groups[(count($column_groups) - 1)];
                    }
                    $group->appendToProperty('columns', $c);
                    $last_element_was_a_column_group = false;
                }
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'The elements of "columns" in a data widget must be objects or arrays, "' . gettype($c) . '" given instead!', '6T91RQ5');
            }
        }
        
        // Now that we have put all column into groups, we can instatiate these as widgets.
        foreach ($column_groups as $nr => $group) {
            // The first column group is always treated as the main one. So check to see, if there is a main
            // column group already and, if so, simply make it load the uxon description of the first column
            // group.
            if ($nr == 0 && count($this->getColumnGroups()) > 0) {
                $this->getColumnGroupMain()->importUxonObject($group);
            } else {
                $page = $this->getPage();
                $column_group = WidgetFactory::createFromUxon($page, UxonObject::fromAnything($group), $this, 'DataColumnGroup');
                $this->addColumnGroup($column_group);
            }
        }
        return $this;
    }

    /**
     * Returns an array of button widgets, that are explicitly bound to a double click on a data element
     *
     * @param string $mouse_action            
     * @return DataButton[]
     */
    public function getButtonsBoundToMouseAction($mouse_action)
    {
        $result = array();
        foreach ($this->getButtons() as $btn) {
            if ($btn instanceof DataButton && $btn->getBindToMouseAction() == $mouse_action) {
                $result[] = $btn;
            }
        }
        return $result;
    }

    /**
     * Returns an array with all filter widgets.
     *
     * @return Filter[]
     */
    public function getFilters()
    {
        if (! $this->getConfiguratorWidget()->hasFilters()) {
            $this->addRequiredFilters();
        }
        return $this->getConfiguratorWidget()->getFilters();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFilter()
     */
    public function getFilter($filter_widget_id)
    {
        return $this->getConfiguratorWidget()->getFilter($filter_widget_id);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFiltersApplied()
     */
    public function getFiltersApplied()
    {
        return $this->getConfiguratorWidget()->getFiltersApplied();
    }

    /**
     * Defines filters to be used in this data widget: each being a Filter widget.
     *
     * The simples filter only needs to contain an attribute_alias. ExFace will generate a suitable widget
     * automatically. However, the filter can easily be customized by adding any properties applicable to
     * the respective widget type. You can also override the widget type.
     *
     * Relations and aggregations are fully supported by filters
     *
     * Note, that ComboTable widgets will be automatically generated for related objects if the corresponding
     * filter is defined by the attribute, representing the relation: e.g. for a table of ORDER_POSITIONS,
     * adding the filter ORDER (relation to the order) will give you a ComboTable, while the filter ORDER__NUMBER
     * will yield a numeric input field, because it filter over a number, even thoug a related one.
     *
     * Advanced users can also instantiate a Filter widget manually (widget_type = Filter) gaining control
     * over comparators, etc. The widget displayed can then be defined in the widget-property of the Filter.
     *
     * A good way to start is to copy the columns array and rename it to filters. This will give you filters
     * for all columns.
     *
     * Example:
     *  {
     *      "object_alias": "ORDER_POSITION"
     *      "filters": [
     *          {
     *              "attribute_alias": "ORDER"
     *          },
     *          {
     *              "attribute_alias": "CUSTOMER__CLASS"
     *          },
     *          {
     *              "attribute_alias": "ORDER__ORDER_POSITION__VALUE:SUM",
     *              "caption": "Order total"
     *          },
     *          {
     *              "attribute_alias": "VALUE",
     *              "widget_type": "InputNumberSlider"
     *          }
     *      ]
     *  }
     *
     * @uxon-property filters
     * @uxon-type \exface\Core\Widgets\Filter[]
     *
     * @param UxonObject $uxon_objects
     * @return Data
     */
    public function setFilters(UxonObject $uxon_objects)
    {
        $this->getConfiguratorWidget()->setFilters($uxon_objects);
        $this->addRequiredFilters();
        return $this;
    }

    public function createFilterWidget($attribute_alias = null, UxonObject $uxon_object = null)
    {
        return $this->getConfiguratorWidget()->createFilterWidget($attribute_alias, $uxon_object);
    }

    /**
     *
     * @see \exface\Core\Widgets\AbstractWidget::prefill()
     */
    protected function doPrefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet)
    {
        if ($data_sheet->getMetaObject()->isExactly($this->getMetaObject())) {
            // If the prefill data is based on the same object as the widget, inherit the filter conditions from the prefill
            foreach ($data_sheet->getFilters()->getConditions() as $condition) {
                // For each filter condition look for filters over the same attribute
                $attribute_filters = $this->getConfiguratorWidget()->findFiltersByAttribute($condition->getExpression()->getAttribute());
                // If no filters are there, create one
                if (count($attribute_filters) == 0) {
                    $filter = $this->createFilterWidget($condition->getExpression()->getAttribute()->getAliasWithRelationPath());
                    $this->addFilter($filter);
                    $filter->setValue($condition->getValue());
                    // Disable the filter because if the user changes it, the
                    // prefill will not be consistent anymore (some prefilled
                    // widgets may have different prefill-filters than others)
                    $filter->setDisabled(true);
                } else {
                    // If matching filters were found, prefill them
                    $prefilled = false;
                    foreach ($attribute_filters as $filter) {
                        if ($filter->getComparator() == $condition->getComparator()) {
                            $filter->setValue($condition->getValue());
                            $prefilled = true;
                        }
                    }
                    if ($prefilled == false) {
                        $attribute_filters[0]->setValue($condition->getValue());
                    }
                }
            }
            // If the data should not be loaded layzily, and the prefill has data, use it as value
            if (! $this->getLazyLoading() && ! $data_sheet->isEmpty()) {
                $this->setValuesDataSheet($data_sheet);
            }
        } else {
            // if the prefill contains data for another object, than this data set contains, see if we try to find a relation to
            // the prefill-object. If so, show only data related to the prefill (= add the prefill object as a filter)
            
            // First look if the user already specified a filter with the object we are looking for
            foreach ($this->getConfiguratorWidget()->findFiltersByObject($data_sheet->getMetaObject()) as $fltr) {
                $fltr->prefill($data_sheet);
            }
            
            // Otherwise, try to find a suitable relation via generic relation searcher
            // TODO currently this only works for direct relations, not for chained ones.
            if (! $fltr && $rel = $this->getMetaObject()->findRelation($data_sheet->getMetaObject())) {
                // If anything goes wrong, log away the error but continue, as
                // the prefills are not critical in general.
                try {
                    $filter_widget = $this->getConfiguratorWidget()->createFilterFromRelation($rel);
                    $filter_widget->prefill($data_sheet);
                } catch (\Throwable $e) {
                    $this->getWorkbench()->getLogger()->logException($e);
                }
            }
            
            // Apart from trying to prefill a filter, we should also look if we can reuse filters from the given prefill sheet.
            // This is the case, if this data widget has a filter over exactly the same attribute, as the prefill sheet.
            if (! $data_sheet->getFilters()->isEmpty()) {
                foreach ($data_sheet->getFilters()->getConditions() as $condition) {
                    // Skip conditions without attributes or with broken expressions (we do not want errors from the prefill logic!)
                    if (! $condition->getExpression()->isMetaAttribute() || ! $condition->getExpression()->getAttribute())
                        continue;
                    // See if there are filters in this widget, that work on the very same attribute
                    foreach ($this->getConfiguratorWidget()->findFiltersByObject($condition->getExpression()->getAttribute()->getObject()) as $fltr) {
                        if ($fltr->getAttribute()->getObject()->is($condition->getExpression()->getAttribute()->getObject()) && $fltr->getAttribute()->getAlias() == $condition->getExpression()->getAttribute()->getAlias() && ! $fltr->getValue()) {
                            $fltr->setComparator($condition->getComparator());
                            $fltr->setValue($condition->getValue());
                        }
                    }
                }
            }
        }
    }

    /**
     * Adds a widget as a filter.
     * Any widget, that can be used to input a value, can be used for filtering. It will automatically be wrapped in a filter
     * widget. The second parameter (if set to TRUE) will make the filter automatically get used in quick search queries.
     *
     * @param AbstractWidget $filter_widget            
     * @param boolean $include_in_quick_search            
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::addFilter()
     */
    public function addFilter(AbstractWidget $filter_widget, $include_in_quick_search = false)
    {
        $this->getConfiguratorWidget()->addFilter($filter_widget, $include_in_quick_search);
        return $this;
    }

    protected function addRequiredFilters()
    {
        // Check for required filters
        foreach ($this->getMetaObject()->getDataAddressRequiredPlaceholders() as $ph) {
            // Special placeholders referencing properties of the meta object itself
            // TODO find a better notation for special placeholders to separate them clearly from other attributes
            if ($ph == 'alias' || $ph == 'id')
                continue;
            
            // If the placeholder is an attribute, add a required filter on it (or make an existing filter required)
            if ($ph_attr = $this->getMetaObject()->getAttribute($ph)) {
                if ($this->getConfiguratorWidget()->hasFilters()) {
                    $ph_filters = $this->getConfiguratorWidget()->findFiltersByAttribute($ph_attr);
                    foreach ($ph_filters as $ph_filter) {
                        $ph_filter->setRequired(true);
                    }
                } else {
                    $ph_filter = $this->getConfiguratorWidget()->createFilterWidget($ph);
                    $ph_filter->setRequired(true);
                    $this->addFilter($ph_filter);
                }
            }
        }
        return $this;
    }

    public function hasFilters()
    {
        if (! $this->getConfiguratorWidget()->hasFilters()) {
            $this->addRequiredFilters();
        }
        return $this->getConfiguratorWidget()->hasFilters();
    }

    public function getChildren()
    {
        $children = array_merge([$this->getConfiguratorWidget()], $this->getToolbars(), $this->getColumns());
        
        // Add the help button, so pages will be able to find it when dealing with the ShowHelpDialog action.
        // IMPORTANT: Add the help button to the children only if it is not hidden. This is needed to hide the button in
        // help widgets themselves, because otherwise they would produce their own help widgets, with - in turn - even
        // more help widgets, resulting in an infinite loop.
        if (! $this->getHideHelpButton()) {
            $children[] = $this->getHelpButton();
        }
        return $children;
    }

    public function getPaginate()
    {
        return $this->paginate;
    }

    /**
     * Set to FALSE to disable pagination
     *
     * @uxon-property paginate
     * @uxon-type boolean
     *
     * @param boolean $value            
     */
    public function setPaginate($value)
    {
        $this->paginate = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     * Returns an all data sorters applied to this sheet as an array.
     *
     * @return UxonObject[]
     */
    public function getSorters()
    {
        return $this->sorters;
    }

    /**
     * Defines sorters for the data via array of sorter objects.
     *
     * Example:
     *  {
     *      "sorters": [
     *          {
     *              "attribute_alias": "MY_ALIAS",
     *              "direction": "ASC"
     *          },
     *          {
     *              ...
     *          }
     *      ]
     *  }
     *
     * @uxon-property sorters
     * @uxon-type Object[]
     *
     * TODO use special sorter widgets here instead of plain uxon objects
     * 
     * @param UxonObject $sorters            
     */
    public function setSorters(UxonObject $sorters)
    {
        foreach ($sorters as $uxon){
            $this->addSorter($uxon->getProperty('attribute_alias'), $uxon->getProperty('direction'));
        }
        return $this;
    }
    
    public function addSorter($attribute_alias, $direction)
    {
        $this->getConfiguratorWidget()->addSorter($attribute_alias, $direction);
        // TODO move sorters completely to configuration widget!
        $sorter = new UxonObject();
        $sorter->setProperty('attribute_alias', $attribute_alias);
        $sorter->setProperty('direction', $direction);
        $this->sorters[] = $sorter;
        return $this;
    }

    public function getAggregateByAttributeAlias()
    {
        return $this->aggregate_by_attribute_alias;
    }

    /**
     * Makes the data get aggregated by the given attribute (i.e.
     * GROUP BY attribute_alias in SQL).
     *
     * Multiple attiribute_alias can be passed separated by commas.
     *
     * @uxon-property aggregate_by_attribute_alias
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Widgets\Data
     */
    public function setAggregateByAttributeAlias($value)
    {
        $this->aggregate_by_attribute_alias = str_replace(', ', ',', $value);
        return $this;
    }

    /**
     * Returns aliases of attributes used to aggregate data
     *
     * @return array
     */
    public function getAggregations()
    {
        if ($this->getAggregateByAttributeAlias()) {
            return explode(',', $this->getAggregateByAttributeAlias());
        } else {
            return array();
        }
    }
    
    /**
     * Returns TRUE if the data is aggregated and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasAggregations()
    {
        return count($this->getAggregations()) > 0 ? true : false;
    }

    /**
     * Returns an array of aliases of attributes, that should be used for quick search relative to the meta object of the widget
     * 
     * IDEA move to to configurator?
     *
     * @return array
     */
    public function getAttributesForQuickSearch()
    {
        $aliases = array();
        foreach ($this->getConfiguratorWidget()->getQuickSearchFilters() as $fltr) {
            $aliases[] = $fltr->getAttributeAlias();
        }
        return $aliases;
    }

    /**
     * Returns an array of editor widgets.
     * One for every editable data column.
     *
     * @return AbstractWidget[]
     */
    public function getEditors()
    {
        $editors = array();
        foreach ($this->getColumns() as $col) {
            if ($col->isEditable()) {
                $editors[] = $col->getEditor();
            }
        }
        return $editors;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }

    /**
     * Makes data values get loaded asynchronously in background if the template supports it (i.e.
     * via AJAX).
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = $value;
        $this->getConfiguratorWidget()->setLazyLoading($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
    {
        return $this->lazy_loading_action;
    }

    /**
     * Sets a custom action for lazy data loading.
     *
     * By default, it is the ReadData action, but it can be substituted by any compatible action. Compatible
     * means in this case, that it should fill a given data sheet with data and output the data in a format
     * compatible with the template (e.g. via AbstractAjaxTemplate::encodeData()).
     *
     * @uxon-property lazy_loading_action
     * @uxon-type string
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction($value)
    {
        $this->lazy_loading_action = $value;
        return $this;
    }

    /**
     * Returns TRUE if the table has a footer with total values and FALSE otherwise
     *
     * @return boolean
     */
    public function hasColumnFooters()
    {
        foreach ($this->getColumns() as $col) {
            if ($col->hasFooter()) {
                return true;
            }
        }
        return false;
    }

    public function getEmptyText()
    {
        if (! $this->empty_text) {
            $this->empty_text = $this->translate('WIDGET.DATA.NO_DATA_FOUND');
        }
        return $this->empty_text;
    }

    /**
     * Sets a custom text to be displayed in the Data widget, if not data is found.
     *
     * The text may contain any template-specific formatting: e.g. HTML for HTML-templates.
     *
     * @uxon-property empty_text
     * @uxon-type boolean
     *
     * @param string $value            
     * @return Data
     */
    public function setEmptyText($value)
    {
        $this->empty_text = $value;
        return $this;
    }

    /**
     *
     * @return DataColumnGroup
     */
    public function getColumnGroups()
    {
        return $this->column_groups;
    }

    /**
     *
     * @return \exface\Core\Widgets\DataColumnGroup
     */
    public function getColumnGroupMain()
    {
        return $this->getColumnGroups()[0];
    }

    /**
     *
     * @param DataColumnGroup $column_group            
     * @return Data
     */
    public function addColumnGroup(DataColumnGroup $column_group)
    {
        $this->column_groups[] = $column_group;
        return $this;
    }

    /**
     * Adds columns with system attributes of the main object or any related object.
     * This is very usefull for editable tables as
     * system attributes are needed to save the data.
     *
     * @param string $relation_path            
     */
    public function addColumnsForSystemAttributes($relation_path = null)
    {
        $object = $relation_path ? $this->getMetaObject()->getRelatedObject($relation_path) : $this->getMetaObject();
        foreach ($object->getAttributes()->getSystem()->getAll() as $attr) {
            $system_alias = RelationPath::relationPathAdd($relation_path, $attr->getAlias());
            // Add the system attribute only if it is not there already.
            // Counting the columns first allows to add the system column without searching for it. If we would search over
            // empty data widgets, we would automatically trigger the creation of default columns, which is absolute nonsense
            // at this point - especially since add_columns_for_system_attributes() can get called before all column defintions
            // in UXON are processed.
            if (! $this->has_system_columns || ! $this->getColumnByAttributeAlias($system_alias)) {
                $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($system_alias), null, true);
                $this->addColumn($col);
            }
        }
        
        if (is_null($relation_path)){
            $this->has_system_columns = true;
        }
        
        return $this;
    }

    /**
     * Returns true, if the data table contains at least one editable column
     *
     * @return boolean
     */
    public function isEditable()
    {
        return $this->is_editable;
    }

    /**
     * Set to TRUE to make the table editable or add a column with an editor.
     * FALSE by default.
     *
     * @uxon-property editable
     * @uxon-type boolean
     *
     * @return \exface\Core\Widgets\Data
     */
    public function setEditable($value = true)
    {
        $this->editable = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * @return \exface\Core\Interfaces\Widgets\WidgetLinkInterface
     */
    public function getRefreshWithWidget()
    {
        return $this->refresh_with_widget;
    }

    /**
     * Makes the Data get refreshed after the value of the linked widget changes.
     * Accepts widget links as strings or objects.
     *
     * @uxon-property refresh_with_widget
     * @uxon-type \exface\Core\CommonLogic\WidgetLink
     *
     * @param WidgetLinkInterface|UxonObject|string $value            
     * @return \exface\Core\Widgets\Data
     */
    public function setRefreshWithWidget($widget_link_or_uxon_or_string)
    {
        $exface = $this->getWorkbench();
        if ($link = WidgetLinkFactory::createFromAnything($exface, $widget_link_or_uxon_or_string, $this->getIdSpace())) {
            $this->refresh_with_widget = $link;
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'DataButton';
    }

    public function getValuesDataSheet()
    {
        return $this->values_data_sheet;
    }

    public function setValuesDataSheet(DataSheetInterface $data_sheet)
    {
        $this->values_data_sheet = $data_sheet;
        return $this;
    }

    /**
     * Returns the number of rows to load for a page when pagination is enabled.
     * Defaults to NULL - in this case, the template must decide, how many rows to load.
     *
     * @return integer
     */
    public function getPaginatePageSize()
    {
        return $this->paginate_page_size;
    }

    /**
     * Sets the number of rows to show on one page (only if pagination is enabled).
     * If not set, the template's default value will be used.
     *
     * @uxon-property paginate_page_size
     * @uxon-type number
     *
     * @param integer $value            
     * @return \exface\Core\Widgets\Data
     */
    public function setPaginatePageSize($value)
    {
        $this->paginate_page_size = $value;
        return $this;
    }

    public function getLazyLoadingGroupId()
    {
        return $this->lazy_loading_group_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingGroupId()
     */
    public function setLazyLoadingGroupId($value)
    {
        $this->lazy_loading_group_id = $value;
        return $this;
    }

    public function getHelpButton()
    {
        if (is_null($this->help_button)) {
            $this->help_button = WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this);
            $this->help_button->setActionAlias('exface.Core.ShowHelpDialog');
            $this->help_button->setHidden(true);
        }
        return $this->help_button;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpWidget()
     */
    public function getHelpWidget(iContainOtherWidgets $help_container)
    {
        /**
         *
         * @var DataTable $table
         */
        $table = WidgetFactory::create($help_container->getPage(), 'DataTable', $help_container);
        $object = $this->getWorkbench()->model()->getObject('exface.Core.USER_HELP_ELEMENT');
        $table->setMetaObject($object);
        $table->setCaption($this->getWidgetType() . ($this->getCaption() ? '"' . $this->getCaption() . '"' : ''));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('TITLE')));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('DESCRIPTION')));
        $table->setLazyLoading(false);
        $table->setPaginate(false);
        $table->setNowrap(false);
        $table->setGroupRows(UxonObject::fromArray(array(
            'group_by_column_id' => 'GROUP'
        )));
        
        // IMPORTANT: make sure the help table does not have a help button itself, because that would result in having
        // infinite children!
        $table->setHideHelpButton(true);
        
        $data_sheet = DataSheetFactory::createFromObject($object);
        
        foreach ($this->getFilters() as $filter) {
            $row = array(
                'TITLE' => $filter->getCaption(),
                'GROUP' => $this->translate('WIDGET.DATA.HELP.FILTERS')
            );
            if ($attr = $filter->getAttribute()) {
                $row = array_merge($row, $this->getHelpRowFromAttribute($attr));
            }
            $data_sheet->addRow($row);
        }
        
        foreach ($this->getColumns() as $col) {
            $row = array(
                'TITLE' => $col->getCaption(),
                'GROUP' => $this->translate('WIDGET.DATA.HELP.COLUMNS')
            );
            if ($attr = $col->getAttribute()) {
                $row = array_merge($row, $this->getHelpRowFromAttribute($attr));
            }
            $data_sheet->addRow($row);
        }
        
        $table->prefill($data_sheet);
        
        $help_container->addWidget($table);
        return $help_container;
    }

    /**
     * Returns a row (assotiative array) for a data sheet with exface.Core.USER_HELP_ELEMENT filled with information about
     * the given attribute.
     * The inforation is derived from the attributes meta model.
     *
     * @param MetaAttributeInterface $attr            
     * @return string[]
     */
    protected function getHelpRowFromAttribute(MetaAttributeInterface $attr)
    {
        $row = array();
        $row['DESCRIPTION'] = $attr->getShortDescription() ? rtrim(trim($attr->getShortDescription()), ".") . '.' : '';
        
        if (! $attr->getRelationPath()->isEmpty()) {
            $row['DESCRIPTION'] .= $attr->getObject()->getShortDescription() ? ' ' . rtrim($attr->getObject()->getShortDescription(), ".") . '.' : '';
        }
        return $row;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHideHelpButton()
     */
    public function getHideHelpButton()
    {
        return $this->hide_help_button;
    }

    /**
     * Set to TRUE to remove the contextual help button.
     * Default: FALSE.
     *
     * @uxon-property hide_help_button
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::setHideHelpButton()
     */
    public function setHideHelpButton($value)
    {
        $this->hide_help_button = BooleanDataType::parse($value);
        return $this;
    }

    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        
        $uxon->setProperty('paginate', $this->getPaginate());
        $uxon->setProperty('paginate_page_size', $this->getPaginatePageSize());
        $uxon->setProperty('aggregate_by_attribute_alias', $this->getAggregateByAttributeAlias());
        $uxon->setProperty('lazy_loading', $this->getLazyLoading());
        $uxon->setProperty('lazy_loading_action', $this->getLazyLoadingAction());
        $uxon->setProperty('lazy_loading_group_id', $this->getLazyLoadingGroupId());
        
        foreach ($this->getColumnGroups() as $col_group) {
            $uxon->appendToProperty('columns', $col_group->exportUxonObject());
        }
        
        // TODO export toolbars to UXON instead of buttons. Currently all
        // information about toolbars is lost.
        foreach ($this->getButtons() as $button) {
            $uxon->appendToProperty('buttons', $button->exportUxonObject());
        }
        
        foreach ($this->getFilters() as $filter) {
            $uxon->appendToProperty('filters', $filter->exportUxonObject());
        }
        
        $uxon->setProperty('sorters', $this->getSorters());
        
        if ($this->getRefreshWithWidget()) {
            $uxon->setProperty('refresh_with_widget', $this->getRefreshWithWidget()->exportUxonObject());
        }
        
        return $uxon;
    }
    
    /**
     * The generic Data widget has a simple toolbar, that should merely be a 
     * container for potential buttons. This makes sure all widgets using data
     * internally (like ComboTables, Charts, etc.) do not have to create complex
     * toolbars, that get automatically generated for DataTables, etc.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::getToolbarWidgetType()
     */
    public function getToolbarWidgetType()
    {
        return 'DataToolbar';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidget()
     * @return DataConfigurator
     */
    public function getConfiguratorWidget()
    {
        if (is_null($this->configurator)){
            $this->configurator = WidgetFactory::create($this->getPage(), $this->getConfiguratorWidgetType(), $this);
        }
        return $this->configurator;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::setConfiguratorWidget()
     */
    public function setConfiguratorWidget($widget_or_uxon_object)
    {
        if ($widget_or_uxon_object instanceof iConfigureWidgets){
            $this->configurator = $widget_or_uxon_object->setWidgetConfigured($this);
        } elseif ($widget_or_uxon_object instanceof UxonObject){
            if (! $widget_or_uxon_object->hasProperty('widget_type')){
                $widget_or_uxon_object->setProperty('widget_type', $this->getConfiguratorWidgetType());
            }
            $this->configurator = WidgetFactory::createFromUxon($this->getPage(), $widget_or_uxon_object, $this);
            $this->configurator->setWidgetConfigured($this);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType(){
        return 'DataConfigurator';
    } 
    
    public function getHideHeader()
    {
        return $this->hide_header;
    }
    
    /**
     * Set to TRUE to hide the top toolbar or FALSE to show it.
     *
     * @uxon-property hide_header
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::setHideHeader()
     */
    public function setHideHeader($value)
    {
        $this->hide_header = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }
    
    public function getHideFooter()
    {
        return $this->hide_footer;
    }
    
    /**
     * Set to TRUE to hide the bottom toolbar or FALSE to show it.
     *
     * @uxon-property hide_footer
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::setHideHeader()
     */
    public function setHideFooter($value)
    {
        $this->hide_footer = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }
}

?>