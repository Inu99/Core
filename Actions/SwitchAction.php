<?php
namespace exface\Core\Actions;

use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * This action performs calls one of the actions specified in the switch_action_map property depending on
 * the first value of the switch_attribute_alias column in the input data sheet.
 *
 * TODO It seems, that switching actions makes lot's of problems if these actions implements different interfaces.
 * It's not really SwitchAction, but rather SwitchActionConfig - maybe we can attach that kind of switcher-logic
 * to all actions? Maybe this will be an easy-to-built extension for the planned DataSheetMapper?
 *
 * @author Andrej Kabachnik
 *        
 */
class SwitchAction extends ActionChain
{

    private $switch_attribute_alias = null;

    private $switch_action_map = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ActionChain::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $input = $this->getInputDataSheet($task);
        if (! $input->getColumns()->getByExpression($this->getSwitchAttributeAlias())) {
            throw new ActionInputMissingError($this, 'Cannot perform SwitchAction: Missing column "' . $this->getSwitchAttributeAlias() . '" in input data!');
        }
        
        $switch_value = $input->getColumns()->getByExpression($this->getSwitchAttributeAlias())->getCellValue(0);
        if ($action = $this->getActionsArray()[$this->getSwitchActionMap()->getProperty($switch_value)]) {
            $this->getActions()->removeAll()->add($action);
        } else {
            throw new ActionRuntimeError($this, 'No action found to switch to for value "' . $switch_value . '" of "' . $this->getSwitchAttributeAlias() . '"!');
        }
        return parent::perform($task, $transaction);
    }

    protected function getActionsArray()
    {
        return array_values($this->getActions()->getAll());
    }

    public function getSwitchAttributeAlias()
    {
        return $this->switch_attribute_alias;
    }

    public function setSwitchAttributeAlias($value)
    {
        $this->switch_attribute_alias = $value;
        return $this;
    }

    public function getSwitchActionMap()
    {
        return $this->switch_action_map;
    }

    public function setSwitchActionMap($value)
    {
        $this->switch_action_map = $value;
        return $this;
    }
}