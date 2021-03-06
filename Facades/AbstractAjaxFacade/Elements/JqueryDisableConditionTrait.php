<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Factories\WidgetLinkFactory;

/**
 * This trait includes JS-generator methods to make an control disabled on certain conditions.
 * 
 * Use this trait in a facade element representing a widget, that support disable_condition.
 * 
 * How to use:
 * 
 * 1) Call registerDisableConditionAtLinkedElement() in the init() method of your element to
 * make sure, it is called _before_ the onChange handler of the linked widget is rendered.
 * 2) Call buildJsDisableConditionInitializer() in the buildJs() method of your element _after_
 * the element itself is initialized. This method will call the JS disabler if your element
 * needs to be disabled initially.
 * 3) Make sure, the methods buildJsEnabler() and buildJsDisabler produce code suitable for
 * your element. These methods are likely to be inherited, so doublechek ther return values.
 * 
 * @method iHaveValue getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryDisableConditionTrait {

    /**
     * Returns a JavaScript-snippet, which is registered in the onChange-Script of the
     * element linked by the disable condition.
     * Based on the condition and the value
     * of the linked widget, it enables and disables the current widget.
     *
     * @return string
     */
    protected function buildJsDisableCondition()
    {
        $output = '';
        $widget = $this->getWidget();
        if (($condition = $widget->getDisableCondition()) && $condition->getProperty('widget_link')) {
            $link = WidgetLinkFactory::createFromWidget($widget, $condition->getProperty('widget_link'));
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
            if ($linked_element) {
                switch ($condition->getProperty('comparator')) {
                    case EXF_COMPARATOR_IS_NOT: // !=
                    case EXF_COMPARATOR_EQUALS: // ==
                    case EXF_COMPARATOR_EQUALS_NOT: // !==
                    case EXF_COMPARATOR_LESS_THAN: // <
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                    case EXF_COMPARATOR_GREATER_THAN: // >
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                        $enable_widget_script = $widget->isDisabled() ? '' : $this->buildJsEnabler() . ';';
                        
                        $output = <<<JS

						if ({$linked_element->buildJsValueGetter($link->getTargetColumnId())} {$condition->getProperty('comparator')} "{$condition->getProperty('value')}") {
							{$this->buildJsDisabler()};
						} else {
							{$enable_widget_script}
						}
JS;
                        break;
                    case EXF_COMPARATOR_IN: // [
                    case EXF_COMPARATOR_NOT_IN: // ![
                    case EXF_COMPARATOR_IS: // =
                    default:
                    // TODO fuer diese Comparatoren muss noch der JavaScript generiert werden
                }
            }
        }
        return $output;
    }

    /**
     * Returns a JavaScript-snippet, which initializes the disabled-state of elements
     * with a disabled condition.
     *
     * @return string
     */
    protected function buildJsDisableConditionInitializer()
    {
        $output = '';
        $widget = $this->getWidget();
        /* @var $condition \exface\Core\CommonLogic\UxonObject */
        if (($condition = $widget->getDisableCondition()) && $condition->hasProperty('widget_link')) {
            $link = WidgetLinkFactory::createFromWidget($widget, $condition->getProperty('widget_link'));
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
            if ($linked_element) {
                switch ($condition->getProperty('comparator')) {
                    case EXF_COMPARATOR_IS_NOT: // !=
                    case EXF_COMPARATOR_EQUALS: // ==
                    case EXF_COMPARATOR_EQUALS_NOT: // !==
                    case EXF_COMPARATOR_LESS_THAN: // <
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                    case EXF_COMPARATOR_GREATER_THAN: // >
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                        $output .= <<<JS

						// Man muesste eigentlich schauen ob ein bestimmter Wert vorhanden ist: buildJsValueGetter(link->getTargetColumnId()).
						// Da nach einem Prefill dann aber normalerweise ein leerer Wert zurueckkommt, wird beim initialisieren
						// momentan einfach geschaut ob irgendein Wert vorhanden ist.
						if ({$linked_element->buildJsValueGetter()} {$condition->getProperty('comparator')} "{$condition->getProperty('value')}") {
							{$this->buildJsDisabler()};
						}
JS;
                        break;
                    case EXF_COMPARATOR_IN: // [
                    case EXF_COMPARATOR_NOT_IN: // ![
                    case EXF_COMPARATOR_IS: // =
                    default:
                    // TODO fuer diese Comparatoren muss noch der JavaScript generiert werden
                }
            }
        }
        return "setTimeout(function(){ $output }, 0);";
    }

    /**
     * Registers an onChange-Skript on the element linked by the disable condition.
     *
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryLiveReferenceTrait
     */
    protected function registerDisableConditionAtLinkedElement()
    {
        if ($linked_element = $this->getDisableConditionFacadeElement()) {
            $linked_element->addOnChangeScript($this->buildJsDisableCondition());
        }
        return $this;
    }

    /**
     * Returns the widget which is linked by the disable condition.
     *
     * @return
     *
     */
    protected function getDisableConditionFacadeElement()
    {
        $linked_element = null;
        $widget = $this->getWidget();
        if (($condition = $widget->getDisableCondition()) && $condition->hasProperty('widget_link')) {
            $link = WidgetLinkFactory::createFromWidget($widget, $condition->getProperty('widget_link'));
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
        }
        return $linked_element;
    }
}