<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Button;

interface iContainButtonGroups extends iHaveChildren
{
    /**
     * @return ButtonGroup[]
     */
    public function getButtonGroups();
    
    /**
     * 
     * @param ButtonGroup[]|UxonObject[] $widget_or_uxon_objects
     * @return iContainButtonGroups
     */
    public function setButtonGroups(array $widget_or_uxon_objects);
    
    /**
     *
     * @param ButtonGroup $button_group
     * @param integer $index
     * 
     * @return \exface\Core\Widgets\Toolbar
     */
    public function addButtonGroup(ButtonGroup $button_group, $index);
    
    /**
     *
     * @param ButtonGroup $button_group
     * @return iContainButtonGroups
     */
    public function removeButtonGroup(ButtonGroup $button_group);
    
    /**
     * Returns the first (= main) button group in the toolbar.
     * 
     * If the alignment parameter is passed, the first buttong group with the
     * given alignment will be returned.
     *
     * @return ButtonGroup
     */
    public function getButtonGroupFirst($alignment = null);
    
    /**
     * Returns the index (position) of the given ButtonGroup in the toolbar (starting with 0).
     * 
     * @param ButtonGroup $button_group
     * 
     * @return integer
     */
    public function getButtonGroupIndex(ButtonGroup $button_group);
    
    /**
     * Returns the ButtonGroup with the given index (position) or NULL if there is no such index.
     * 
     * @param integer $index
     * 
     * @return Button|null
     */
    public function getButtonGroup($index);
}