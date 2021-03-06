<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iCanBeRequired;
use exface\Core\Interfaces\Widgets\iCanBeDisabled;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\MessageTypeDataType;

/**
 * This action will show a dialog displaying the default editor of a meta object in read-only mode.
 *
 * Dialogs that show meta objects, will use the default editor description from the object's model, if specified.
 * If not, the action will generate a generic editor listing all widgets with their default editors or respective
 * generic editors of the corresponding data types.
 *
 * If you do not specify a widget type in the object's default editor or set it to "Dialog", the UXON of the default
 * editor will be directly applied to the Dialog. If another widget type is specified, it will be treated as a separate
 * widget and added to the dialog as a child widget. Thus, if the default editor is
 *
 * {"widgets": [{...}, {...}], "caption": "My caption"}
 *
 * the caption of the dialog will be set to "My caption" and all the widgets will get appended to the dialog. On the
 * other hand, the following default editor will produce a single tabs widget, which will be appended to the generic
 * dialog:
 *
 * {"widget_type": "Tabs", "tabs": [...]}
 *
 * If you choose to customize the dialog directly (first example), you can ommit the "widgets" array completely. This
 * will case the default editor widgets to get generated and appended to your custom dialog. This is an easy way to
 * add custom buttons, captions, etc. to generic dialogs.
 *
 * @author Andrej Kabachnik
 *        
 */
class ShowObjectInfoDialog extends ShowDialog
{

    private $show_only_editable_attributes = false;

    private $disable_editing = true;
    
    private $showSmallDialogIfLessAttributesThen = 7;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setIcon(Icons::INFO_CIRCLE);
        $this->setShowOnlyEditableAttributes(false);
        // Disable prefilling the widget from contexts as we only whant to fill in data that actually comes from the data source
        $this->setPrefillWithFilterContext(false);
    }
    
    /**
     * 
     * @return bool
     */
    protected function isObjectWritable() : bool
    {
        if ($this->getMetaObject()->hasDataSource()) {
            return $this->getMetaObject()->isWritable();
        } 
        return true;
    }

    /**
     * Create editors for all editable attributes of the object
     *
     * @return WidgetInterface[]
     */
    protected function createWidgetsForAttributes(AbstractWidget $parent_widget)
    {
        $object = $this->getMetaObject();
        $editors = [];
        
        $objectWritable = $this->isObjectWritable();
        
        /* @var $attr \exface\Core\Interfaces\Model\MetaAttributeInterface */
        foreach ($object->getAttributes() as $attr) {
            // Ignore hidden attributes if they are not system attributes
            if ($attr->isHidden())
                continue;
            // Ignore not editable attributes if this feature is not explicitly disabled
            if (! $attr->isEditable() && $this->getShowOnlyEditableAttributes())
                continue;
            // Ignore attributes with fixed values
            if ($attr->getFixedValue())
                continue;
            // Create the widget
            $ed = $this->createWidgetFromAttribute($this->getMetaObject(), $attr->getAlias(), $parent_widget);
            if ($ed instanceof iCanBeRequired) {
                $ed->setRequired($attr->isRequired());
            }
            if ($ed instanceof iCanBeDisabled) {
                $ed->setDisabled(($attr->isEditable() ? false : true));
            }
            
            if (! $objectWritable) {
                $ed->setDisabled(true);
            }
            
            $editors[] = $ed;
        }
        
        if (count($editors) == 0){
            $editors[] = WidgetFactory::create($parent_widget->getPage(), 'Message', $parent_widget)
            ->setType(MessageTypeDataType::WARNING)
            ->setText($this->getApp()->getTranslator()->translate('ACTION.SHOWOBJECTEDITDIALOG.NO_EDITABLE_ATTRIBUTES'));
        }
        
        ksort($editors);
        
        return $editors;
    }

    /**
     * 
     * @param MetaObjectInterface $obj
     * @param string $attribute_alias
     * @param WidgetInterface $parent_widget
     * @return WidgetInterface
     */
    protected function createWidgetFromAttribute(MetaObjectInterface $obj, $attribute_alias, WidgetInterface $parent_widget) : WidgetInterface
    {
        $attr = $obj->getAttribute($attribute_alias);
        $page = $this->getWidgetDefinedIn()->getPage();
        $widget = WidgetFactory::createFromUxon($page, $attr->getDefaultEditorUxon(), $parent_widget);
        $widget->setAttributeAlias($attribute_alias);
        $widget->setCaption($attr->getName());
        $widget->setHint($attr->getHint());
        return $widget;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Actions\ShowDialog::createDialogWidget()
     */
    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
    {
        $dialog = parent::createDialogWidget($page);
        $default_editor_uxon = $dialog->getMetaObject()->getDefaultEditorUxon();
        
        // If there is a default editor, make sure it gets it's own id space, so widget links inside still work
        // if multiple editors of the same object are located in the same page (e.g. for creating, editing, etc.)
        $this->addIdSpaceToWidgetUxon($default_editor_uxon);
        
        // If the content is explicitly defined, just add it to the dialog
        if (! is_null($contained_widget)) {
            $dialog->addWidget($contained_widget);
        } elseif ($default_editor_uxon && ! $default_editor_uxon->isEmpty()) {
            // Otherwise try to generate the widget automatically
            // First check, if there is a default editor for an object, and instantiate it if so
            
            if (! $default_editor_uxon->getProperty('widget_type') || $default_editor_uxon->getProperty('widget_type') == 'Dialog') {
                $dialog->importUxonObject($default_editor_uxon);
                if ($dialog->isEmpty()) {
                    $dialog->addWidgets($this->createWidgetsForAttributes($dialog));
                }
            } else {
                $default_editor = WidgetFactory::createFromUxon($page, $default_editor_uxon, $dialog);
                $dialog->addWidget($default_editor);
            }
        } else {
            // Lastly, try to generate a usefull editor from the meta model
            
            // If there is no editor defined, create one: Add a panel to the dialog and generate editors for all attributes
            // of the object in that panel.
            // IDEA A separate method "create_object_editor" would probably be handy, once we have attribute groups and
            // other information, that would enable us to build better editors (with tabs, etc.)
            $dialog->addWidgets($this->createWidgetsForAttributes($dialog));
            
            if ($dialog->countWidgetsVisible() < $this->getShowSmallDialogIfLessAttributesThen()) {
                $dialog->setColumnsInGrid(1);
            }
        }
        
        return $dialog;
    }
    
    protected function getShowSmallDialogIfLessAttributesThen() : int
    {
        return $this->showSmallDialogIfLessAttributesThen; 
    }
    
    /**
     * Auto-generated editor will be smaller if object has less attributes, than defined here.
     * 
     * @uxon-property show_small_dialog_if_less_attributes_then
     * @uxon-type int
     * @uxon-default 7
     * 
     * @param int $number
     * @return ShowObjectInfoDialog
     */
    public function setShowSmallDialogIfLessAttributesThen(int $number) : ShowObjectInfoDialog
    {
        $this->showSmallDialogIfLessAttributesThen = $number;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowDialog::enhanceDialogWidget()
     */
    protected function enhanceDialogWidget(Dialog $dialog)
    {
        $dialog = parent::enhanceDialogWidget($dialog);
        if ($this->getDisableEditing()) {
            foreach ($dialog->getInputWidgets() as $widget) {
                $widget->setDisabled(true);
            }
        }
        return $dialog;
    }

    /**
     * Returns TRUE if only widgets for editable attributes should be shown or FALSE, if all visible widgets should appear (some being disabled).
     *
     * @return boolean
     */
    public function getShowOnlyEditableAttributes() : bool
    {
        return $this->show_only_editable_attributes;
    }

    /**
     * Set to FALSE to show all attributes and FALSE to only show editable object attributes.
     * 
     * @uxon-property show_only_editable_attributes
     * @uxon-type boolean
     * 
     * @param boolean $value
     * @return \exface\Core\Actions\ShowObjectInfoDialog
     */
    public function setShowOnlyEditableAttributes($value) : ShowObjectInfoDialog
    {
        $this->show_only_editable_attributes = $value;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getDisableEditing() : bool
    {
        return $this->disable_editing;
    }

    /**
     * Set to TRUE to prevent editing of the object regardless of wether the dialog has active editing widgets.
     * 
     * @uxon-property disable_editing
     * @uxon-type boolean
     * 
     * @param boolean $value
     * @return \exface\Core\Actions\ShowObjectInfoDialog
     */
    public function setDisableEditing($value) : ShowObjectInfoDialog
    {
        $this->disable_editing = BooleanDataType::cast($value);
        return $this;
    }
}
?>