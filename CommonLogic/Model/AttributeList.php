<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Factories\AttributeListFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeListInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\ModelInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method MetaAttributeInterface[] getAll()
 * @method MetaAttributeListInterface|MetaAttributeInterface[] getIterator()
 *        
 */
class AttributeList extends EntityList implements MetaAttributeListInterface
{

    /**
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::add()
     */
    public function add($attribute, $key = null)
    {
        if (is_null($key)) {
            $key = $attribute->getAliasWithRelationPath();
        }
        return parent::add($attribute, $key);
    }

    /**
     *
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getModel()
     */
    public function getModel() : ModelInterface
    {
        return $this->getMetaObject()->getModel();
    }

    /**
     *
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getMetaObject()
     */
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->getParent();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see MetaAttributeListInterface::setMetaObject()
     */
    public function setMetaObject(MetaObjectInterface $meta_object) : MetaAttributeListInterface
    {
        return $this->setParent($meta_object);
    }

    /**
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getByAttributeId()
     */
    public function getByAttributeId($uid)
    {
        foreach ($this->getAll() as $attr) {
            if (strcasecmp($attr->getId(), $uid) == 0) {
                return $attr;
            }
        }
        return false;
    }

    /**
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getRequired()
     */
    public function getRequired() : MetaAttributeListInterface
    {
        return $this->filter(function(MetaAttributeInterface $attr) {
            return $attr->isRequired();
        });
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeListInterface::getWritable()
     */
    public function getWritable() : MetaAttributeListInterface
    {
        return $this->filter(function(MetaAttributeInterface $attr) {
            return $attr->isWritable();
        });
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeListInterface::getReadable()
     */
    public function getReadable() : MetaAttributeListInterface
    {
        return $this->filter(function(MetaAttributeInterface $attr) {
            return $attr->isReadable();
        });
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeListInterface::getEditable()
     */
    public function getEditable() : MetaAttributeListInterface
    {
        return $this->filter(function(MetaAttributeInterface $attr) {
            return $attr->isEditable();
        });
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getSystem()
     */
    public function getSystem() : MetaAttributeListInterface
    {
        return $this->filter(function(MetaAttributeInterface $attr) {
            return $attr->isSystem();
        });
    }

    /**
     * 
     * {@inheritdoc}
     * @see MetaAttributeListInterface::getDefaultDisplayList()
     */
    public function getDefaultDisplayList() : MetaAttributeListInterface
    {
        $object = $this->getMetaObject();
        $defs = AttributeListFactory::createForObject($object);
        foreach ($this->getAll() as $attr) {
            if ($attr->getDefaultDisplayOrder() > 0) {
                if ($attr->isRelation()) {
                    $rel_path = $attr->getAlias();
                    $rel_obj = $object->getRelatedObject($rel_path);
                    $rel_attr = $object->getAttribute(RelationPath::relationPathAdd($rel_path, $rel_obj->getLabelAttributeAlias()));
                    // Leave the name of the relation as attribute name and ensure, that it is visible
                    $rel_attr->setName($attr->getName());
                    $rel_attr->setHidden(false);
                    $defs->add($rel_attr, $attr->getDefaultDisplayOrder());
                } else {
                    $defs->add($attr, $attr->getDefaultDisplayOrder());
                }
            }
        }
        
        // Use the label attribute if there are no defaults defined
        if ($defs->isEmpty() && $label_attribute = $object->getLabelAttribute()) {
            $defs->add($label_attribute);
        }
        
        $defs->sortByKey();
        return $defs;
    }
}