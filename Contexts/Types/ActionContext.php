<?php namespace exface\Core\Contexts\Types;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;
use exface\Core\Exceptions\Contexts\ContextLoadError;

class ActionContext extends AbstractContext {
	private $action_history = array();
	private $action_history_raw = array();
	private $action_history_limit = 10;
	private $current_actions = array();
	
	/**
	 * Returns the action being performed at this time. That is the action, for which the context is not closed yet
	 * @return ActionInterfaceActionInterface
	 */
	public function get_current_action() {
		return $this->get_actions()[count($this->get_actions())-1];
	}	
	
	/**
	 * Returns an array with all actions, registered in this context during the current server request
	 * @return ActionInterface[]
	 */
	public function get_actions(){
		return $this->current_actions;
	}
	
	/**
	 * Registers an action in this context
	 * @param ActionInterface $action
	 * @return \exface\Core\Contexts\Types\ActionContext
	 */
	public function add_action(ActionInterface $action){
		$this->current_actions[] = $action;
		return $this;
	}
	
	/**
	 * Returns a specified quantity of action contexts from the history starting from the most recent one. The history holds all actions,
	 * that modify data in the data source.
	 * Returns the entire history, if $steps_back is not specified (=NULL)
	 * IDEA Create a separate class for action history with methods to get the most recent item, etc. This would free the context scope from action specific methods
	 * @param integer $steps_back
	 * @return ActionInterface[]
	 */
	public function get_action_history($steps_back = null){
		// If history not yet loaded, load it now
		if (count($this->action_history_raw) == 0){
			$this->import_uxon_object($this->get_scope()->get_saved_contexts($this->get_alias()));
		}
	
		// Put the last $steps_back actions from the history into an array starting with the most recent entry
		$result_raw = array();
		if ($steps_back > 0){
			for($i=0; $i<$steps_back; $i++){
				if ($step = $this->action_history_raw[count($this->action_history_raw)-1-$i]){
					$result_raw[] = $step;
				}
			}
		} else {
			$result_raw = array_reverse($this->action_history_raw);
		}
		
		// Now instantiate actions for every entry of the array holding the required amount of history steps
		$result = array();
		foreach ($result_raw as $uxon){
			$exface = $this->get_workbench();
			$action = ActionFactory::create_from_uxon($exface, $uxon->action);
			if ($uxon->undo_data){
				$action->set_undo_data($uxon->undo_data);
			}
			$result[] = $action;
		}
		
		// Return the array of actions
		return $result;
	}
	
	/**
	 * @see \exface\Core\Contexts\Types\AbstractContext::get_default_scope()
	 */
	public function get_default_scope(){
		return $this->get_workbench()->context()->get_scope_session();
	}
	
	/**
	 * @see \exface\Core\Contexts\Types\AbstractContext::export_uxon_object()
	 */
	public function export_uxon_object(){
		// First, grab the raw history
		$array = $this->action_history_raw;
		// ... and add the actions performed in the current request to the end of ist
		foreach ($this->get_actions() as $action){
			// Exclude actions, that do not modify data, such as navigation, template scripts, etc. (they are not historized)
			if (!$action->is_data_modified()) continue;
			// Otherwise create a new UXON object to hold the action itself and the undo data, if the action is undoable.
			$uxon = new UxonObject();
			$uxon->action = $action->export_uxon_object();
			if ($action->is_undoable()){
				$uxon->undo_data = $action->get_undo_data_serializable();
			}
			$array[] = $uxon;
		}
		
		// Make sure, the array is not bigger, than the limit
		if (count($array) > $this->action_history_limit){
			$array = array_slice($array, count($array) - $this->action_history_limit);
		}
		
		// Pack into a uxon object
		$uxon = $this->get_workbench()->create_uxon_object();
		if (count($array) > 0){
			$uxon->action_history = $array;
		}
		return $uxon;
	}
	
	/**
	 * @see \exface\Core\Contexts\Types\AbstractContext::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		if (is_array($uxon->action_history)){
			$this->action_history_raw = $uxon->action_history;
		} elseif (!is_null($uxon->action_history)) {
			throw new ContextLoadError($this, 'Cannot load action contexts: expecting UXON objects, received ' . gettype($uxon->action_history) . ' instead!');
		}
		return $this;
	}
}
?>