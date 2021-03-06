<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\Widgets\WidgetPropertyUnknownError;

/**
 * The configurator for data widgets contains tabs for filters and sorters.
 * 
 * @see WidgetConfigurator
 * 
 * @method Data getWidgetConfigured()
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataConfigurator extends WidgetConfigurator implements iHaveFilters
{
    
    /** @var Filter[] */
    private $quick_search_filters = array();
    
    private $filter_tab = null;
    
    private $sorter_tab = null;
    
    /**
     * Returns an array with all filter widgets.
     *
     * @return Filter[]
     */
    public function getFilters()
    {
        return $this->getFilterTab()->getWidgets();
    }
    
    /**
     * Returns the filter widget matching the given widget id
     *
     * @param string $filter_widget_id
     * @return \exface\Core\Widgets\Filter
     */
    public function getFilter($filter_widget_id)
    {
        foreach ($this->getFilters() as $fltr) {
            if ($fltr->getId() == $filter_widget_id) {
                return $fltr;
            }
        }
    }
    
    /**
     * Returns all filters, that have values and thus will be applied to the result
     *
     * @return \exface\Core\Widgets\AbstractWidget[] array of widgets
     */
    public function getFiltersApplied()
    {
        $result = array();
        foreach ($this->getFilters() as $id => $fltr) {
            if (! is_null($fltr->getValue())) {
                $result[$id] = $fltr;
            }
        }
        return $result;
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
     * Note, that InputComboTable widgets will be automatically generated for related objects if the corresponding
     * filter is defined by the attribute, representing the relation: e.g. for a table of ORDER_POSITIONS,
     * adding the filter ORDER (relation to the order) will give you a InputComboTable, while the filter ORDER__NUMBER
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
     * @uxon-type exface\Core\Widgets\Filter[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @param UxonObject[] $uxon_objects
     * @return DataConfigurator
     */
    public function setFilters(UxonObject $uxon_objects)
    {
        foreach ($uxon_objects as $uxon) {
            $include_in_quick_search = false;
            // Add to quick search if required
            if ($uxon->getProperty('include_in_quick_search') === true) {
                $include_in_quick_search = true;
            }
            $uxon->unsetProperty('include_in_quick_search');
            
            $filter = $this->createFilterWidget($uxon->getProperty('attribute_alias'), $uxon);
            $this->addFilter($filter, $include_in_quick_search);
        }
        return $this;
    }
    
    public function createFilterWidget($attribute_alias = null, UxonObject $uxon_object = null)
    {
        if ($attribute_alias === null) {
            if ($uxon_object->hasProperty('attribute_alias')) {
                $attribute_alias = $uxon_object->getProperty('attribute_alias');
            } elseif (($uxon_object->getProperty('input_widget') instanceof UxonObject) && $uxon_object->getProperty('input_widget')->hasProperty('attribute_alias')) {
                $attribute_alias = $uxon_object->getProperty('input_widget')->getProperty('attribute_alias');
            }
        }
        
        // a filter can only be applied, if the attribute alias is specified and the attribute exists
        if ($attribute_alias !== null && $attribute_alias !== '') {
            try {
                $attr = $this->getMetaObject()->getAttribute($attribute_alias);
            } catch (MetaAttributeNotFoundError $e) {
                throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for attribute alias "' . $attribute_alias . '" in widget "' . $this->getId() . '": attribute not found for object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!', '6T91AR9', $e);
            }
            
            // determine the input widget for the filter
            // Try to extend the default editor widget, by also keep the original UXON of the filter: see below, why.
            $editorUxon = $attr->getDefaultEditorUxon()->copy();
            if ($uxon_object) {
                $editorUxon = $editorUxon->extend($uxon_object);
                $userUxon = $uxon_object;
            } else {
                $userUxon = new UxonObject();
            }
            // Set a special caption for filters on relations, which is derived from the relation itself
            // IDEA this might be obsolete since it probably allways returns the attribute name anyway, but I'm not sure
            if (! $editorUxon->hasProperty('caption') && $attr->isRelation()) {
                $editorUxon->setProperty('caption', $this->getMetaObject()->getRelation($attribute_alias)->getName());
            }
        } elseif ($uxon_object !== null && $uxon_object->hasProperty('condition_group') === true) {
            $userUxon = new UxonObject();
            $editorUxon = $uxon_object;
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for an empty attribute alias in widget "' . $this->getId() . '"!', '6T91AR9');
        }
        
        // Set properties of the filter explicitly while passing everything else to it's input widget.
        // TODO move this to the filter's importUxonObject() method.
        if ($editorUxon->hasProperty('comparator')) {
            $comparator = $editorUxon->getProperty('comparator');
            $editorUxon->unsetProperty('comparator');
            $userUxon->unsetProperty('comparator');
        }
        if ($editorUxon->hasProperty('apply_on_change')) {
            $apply_on_change = $editorUxon->getProperty('apply_on_change');
            $editorUxon->unsetProperty('apply_on_change');
            $userUxon->unsetProperty('apply_on_change');
        } 
        if ($editorUxon->hasProperty('condition_group')) {
            $customConditionGroupUxon = $editorUxon->getProperty('condition_group');
            $editorUxon->unsetProperty('condition_group');
            $userUxon->unsetProperty('condition_group');
        }
        if ($uxon_object !== null && $uxon_object->hasProperty('required')) {
            // A filter is only required, if a user explicitly marked it as required in the filter's UXON
            $required = $uxon_object->getProperty('required');
            $editorUxon->unsetProperty('required');
            $userUxon->unsetProperty('required');
        } 
        
        $filter = $this->getPage()->createWidget('Filter', $this->getFilterTab());
        $filter->setComparator($comparator);
        if (isset($apply_on_change)){
            $filter->setApplyOnChange($apply_on_change);
        }
        if ($customConditionGroupUxon !== null) {
            $filter->setConditionGroup($customConditionGroupUxon);
        }
        
        // Create the input widget
        $page = $this->getPage();
        // If the merged UXON from the default editor and the filter does not work,
        // create a widget from the explicitly defined filter UXON. This can happen
        // if the default editor presumes a widget type, that is not compatible with
        // properties, defined for the filter.
        // TODO this is not a very elegant solution: need a better way, to handle
        // conflicts between the default editor and the filter definition!
        try {
            $inputWidget = WidgetFactory::createFromUxon($page, $editorUxon, $filter);
        } catch (WidgetPropertyUnknownError $e) {
            $inputWidget = WidgetFactory::createFromUxon($page, $userUxon, $filter);
        }
        
        $filter->setInputWidget($inputWidget);
        
        // Set the required option after instantiation to ensure the input widget gets it too.
        if (isset($required)) {
            $filter->setRequired($required);
        }
        
        return $filter;
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
        if ($filter_widget instanceof Filter) {
            $filter = $filter_widget;
        } else {
            $filter = $this->getPage()->createWidget('Filter', $this->getFilterTab());
            $filter->setInputWidget($filter_widget);
        }
        
        $this->setLazyLoadingForFilter($filter);
        
        $this->getFilterTab()->addWidget($filter);
        if ($include_in_quick_search) {
            $this->addQuickSearchFilter($filter);
        }
        return $this;
    }
    
    public function hasFilters()
    {
        return $this->getFilterTab()->hasWidgets();
    }
    
    /**
     * Returns the configurator tab with filters.
     * 
     * @return Tab
     */
    public function getFilterTab()
    {
        if (is_null($this->filter_tab)){
            $this->filter_tab = $this->createFilterTab();
            $this->addTab($this->filter_tab, 0);
        }
        return $this->filter_tab;
    }
    
    /**
     * Creates an empty filter tab and returns it (without adding to the Tabs widget!)
     * 
     * @return Tab
     */
    protected function createFilterTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.FILTER_TAB_CAPTION'));
        $tab->setIcon(Icons::FILTER);
        return $tab;
    }
    
    /**
     * Returns the configurator tab with sorting controls
     * 
     * @return Tab
     */
    public function getSorterTab()
    {
        if (is_null($this->sorter_tab)){
            $this->sorter_tab = $this->createSorterTab();
            $this->addTab($this->sorter_tab, 1);
        }
        return $this->sorter_tab;
    }
    
    /**
     * Creates an empty sorter tab and returns it (without adding to the Tabs widget!)
     *
     * @return Tab
     */
    protected function createSorterTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.SORTER_TAB_CAPTION'));
        $tab->setIcon(Icons::SORT);
        // TODO reenable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
    }
    
    /**
     * Registers a filter for the quick search queries.
     * The filter is passed by reference because it is also contained in the
     * retular filters.
     *
     * @param Filter $widget
     */
    public function addQuickSearchFilter(Filter $widget)
    {
        $this->quick_search_filters[] = $widget;
    }
    
    /**
     * 
     * @return Filter[]
     */
    public function getQuickSearchFilters()
    {
        return $this->quick_search_filters;
    }
    
    /**
     * Replaces the current set of filters used for quick search queries by the given filter array
     *
     * @param Filter[] $filters
     */
    public function setQuickSearchFilters(array $filters)
    {
        $this->quick_search_filters = $filters;
        return $this;
    }
    
    /**
     * Returns an array of filters, that filter over the given attribute.
     * 
     * It will mostly contain only one filter, but if there are different filters with different 
     * comparators (like from+to for numeric or data values), there will be multiple filters in 
     * the list. 
     *
     * @param MetaAttributeInterface $attribute
     * @return Filter[]
     */
    public function findFiltersByAttribute(MetaAttributeInterface $attribute)
    {
        $result = array();
        foreach ($this->getFilters() as $filter_widget) {
            if ($filter_widget->getAttributeAlias() == $attribute->getAliasWithRelationPath()) {
                $result[] = $filter_widget;
            } 
        }
        return $result;
    }
    
    /**
     * TODO Make the method return an array like find_filters_by_attribute() does
     *
     * @param MetaRelationInterface $relation
     * @return Filter
     */
    public function findFilterByRelation(MetaRelationInterface $relation)
    {
        foreach ($this->getFilters() as $filter_widget) {
            if ($filter_widget->getAttributeAlias() == $relation->getAlias()) {
                $found = $filter_widget;
                break;
            } else {
                $found = null;
            }
        }
        if ($found) {
            return $found;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the first filter based on the given object or it's attributes
     *
     * @param MetaObjectInterface $object
     * @return \exface\Core\Widgets\Filter|boolean
     */
    public function findFiltersByObject(MetaObjectInterface $object)
    {
        $result = array();
        foreach ($this->getFilters() as $filter_widget) {
            if ($filter_widget->isBoundToAttribute() === false) {
                continue;
            }
            
            $filter_object = $this->getMetaObject()->getAttribute($filter_widget->getAttributeAlias())->getObject();
            if ($object->is($filter_object)) {
                $result[] = $filter_widget;
            } elseif ($filter_widget->getAttribute()->isRelation() && $object->is($filter_widget->getAttribute()->getRelation()->getRightObject())) {
                $result[] = $filter_widget;
            }
        }
        return $result;
    }
    
    /**
     * Creates and adds a filter based on the given relation
     *
     * @param MetaRelationInterface $relation
     * @return \exface\Core\Widgets\AbstractWidget
     */
    public function createFilterFromRelation(MetaRelationInterface $relation)
    {
        $filter_widget = $this->findFilterByRelation($relation);
        // Create a new hidden filter if there is no such filter already
        if (! $filter_widget) {
            $page = $this->getPage();
            $filter_widget = WidgetFactory::createFromUxon($page, $relation->getLeftKeyAttribute()->getDefaultEditorUxon(), $this);
            $filter_widget->setAttributeAlias($relation->getLeftKeyAttribute()->getAlias());
            $this->addFilter($filter_widget);
        }
        return $filter_widget;
    }
    
    protected function setLazyLoadingForFilter(Filter $filter_widget)
    {
        // Disable filters on Relations if lazy loading is disabled
        if (! $this->getWidgetConfigured()->getLazyLoading() && $filter_widget->getAttribute() && $filter_widget->getAttribute()->isRelation() && $filter_widget->getInputWidget()->is('InputComboTable')) {
            $filter_widget->setDisabled(true);
        }
        return $filter_widget;
    }
    
    public function setLazyLoading(bool $value)
    {
        foreach ($this->getFilters() as $filter) {
            $this->setLazyLoadingForFilter($filter);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter_callback = null)
    {
        if (is_null($this->filter_tab)){
            $this->getFilterTab();
        }
        if (is_null($this->sorter_tab)){
            $this->getSorterTab();
        }
        return parent::getWidgets($filter_callback);
    }
    
    /**
     * Returns the data widget, that this configurator is bound to.
     * 
     * This is similar to getWidgetConfigured(), but the latter may also return other widget types
     * (e.g. those using data like charts or diagrams), whild getDataWidget() allways returns the
     * data widget itself.
     * 
     * @return Data
     */
    public function getDataWidget() : Data
    {
        return $this->getWidgetConfigured();
    }
}
?>