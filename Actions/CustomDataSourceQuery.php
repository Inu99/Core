<?php namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\iRunDataSourceQuery;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Exceptions\Actions\ActionInputTypeError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

class CustomDataSourceQuery extends AbstractAction implements iRunDataSourceQuery {
	private $queries = array();
	private $data_connection = null;
	private $aplicable_to_object_alias = null;
	
	protected function init(){
		parent::init();
		$this->set_icon_name('gears');
	}
	
	/**
	 * @return array
	 */
	public function get_queries() {
		return $this->queries;
	}
	
	public function set_queries(array $strings) {
		$this->queries = $strings;
		return $this;
	}
	
	public function add_query($string){
		$this->queries[] = $string;
		return $this;
	}
	
	public function get_data_connection() {
		if (is_null($this->data_connection)){
			$this->set_data_connection($this->get_called_by_widget()->get_meta_object()->get_data_connection());
		}
		return $this->data_connection;
	}
	
	public function set_data_connection($connection_or_alias) {
		if ($connection_or_alias instanceof DataConnectionInterface){
			$this->data_connection = $connection_or_alias;
		} else {
			// TODO
		}
		return $this;
	}  
	
	public function get_aplicable_to_object_alias() {
		return $this->aplicable_to_object_alias;
	}
	
	/**
	 * @return Object
	 */
	public function get_aplicable_to_object(){
		return $this->get_workbench()->model()->get_object($this->get_aplicable_to_object_alias());
	}
	
	public function set_aplicable_to_object_alias($value) {
		$this->aplicable_to_object_alias = $value;
		return $this;
	}  
	
	protected function perform(){
		$counter = 0;
		$data_sheet = $this->get_input_data_sheet()->copy();
		// Check if the action is aplicable to the input object
		if ($this->get_aplicable_to_object_alias()){
			if (!$data_sheet->get_meta_object()->is($this->get_aplicable_to_object_alias())){
				throw new ActionInputTypeError($this, 'Cannot perform action "' . $this->get_alias_with_namespace() . '" on object "' . $data_sheet->get_meta_object()->get_alias_with_namespace() . '": action only aplicable to "' . $this->get_aplicable_to_object_alias() . '"!', '6T5DMU');
			}
		}
		
		// Start transaction
		$transaction = $this->get_workbench()->data()->start_transaction();
		$transaction->add_data_connection($this->get_data_connection());
		
		// Build and perform all queries. Rollback if anything fails
		try {
			foreach ($this->get_queries() as $query){
				// See if the query has any placeholders
				$placeholders = array();
				foreach ($this->get_workbench()->utils()->find_placeholders_in_string($query) as $ph){
					/* @var $col exface\Core\CommonLogic\DataSheets\DataColumn */
					if (!$col = $data_sheet->get_columns()->get(DataColumn::sanitize_column_name($ph))){
						throw new ActionInputMissingError($this, 'Cannot perform custom query in "' . $this->get_alias_with_namespace() . '": placeholder "' . $ph . '" not found in inupt data!', '6T5DNWE');
					}
					$placeholders['[#'.$ph.'#]'] = implode(',', $col->get_values(false));
				}
				$query = str_replace(array_keys($placeholders), array_values($placeholders), $query);
				
				// Perform the query
				$counter = $this->get_data_connection()->query($query);
			}
			$transaction->commit();
		} catch (\Exception $e){
			var_dump($query);
			$transaction->rollback();
			$e->rethrow();
		}
		
		// Refresh the data sheet. Make sure to get only those rows present in the original sheet if there are no filters set.
		// This will mainly happen if the sheet was autogenerated from a users selection. If the sheet was meant to contain all
		// elements of the selected source, it will not be extended by any elements added by the performed query however.
		if ($data_sheet->count_rows() && $data_sheet->get_uid_column() && $data_sheet->get_filters()->is_empty()){
			$data_sheet->add_filter_from_column_values($data_sheet->get_uid_column());
		}
		$data_sheet->data_read();
		$this->set_result_data_sheet($data_sheet);
		$this->set_result('');
		$this->set_result_message($this->get_app()->get_translator()->translate_plural('ACTION.CUSTOMDATAQUERY.RESULT', $counter, array('%number%' => $counter)));
	}
}
?>