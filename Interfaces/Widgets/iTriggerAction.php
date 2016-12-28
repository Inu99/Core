<?php
namespace exface\Core\Interfaces\Widgets;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
interface iTriggerAction {
	/**
	 * Returns the action object
	 * @return ActionInterface
	 */
	public function get_action();
	
	/**
	 * Sets the action of the button. Accepts either instantiated actions or respective UXON description objects.
	 * Passing ready made actions comes in handy, when creating an action in the code, while passing UXON objects
	 * is an elegant solutions when defining a complex button in UXON:
	 * { widget_type: Button,
	 *   action: {
	 *     alias: ...,
	 *     other_params: ...
	 *   }
	 * }
	 * @param ActionInterface|\stdClass $action_object_or_uxon_description
	 * @throws WidgetPropertyInvalidValueError
	 */
	public function set_action($action_object_or_uxon_description);
	
	/**
	 * Returns the widget, that supplies the input data for the action
	 * @return AbstractWidget $widget
	 */
	public function get_input_widget();
	
	/**
	 * Sets the widget, that supplies the input data for the action
	 * @param AbstractWidget $widget
	 * @return AbstractWidget
	 */
	public function set_input_widget(AbstractWidget $widget);
	
}