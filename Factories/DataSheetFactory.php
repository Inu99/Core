<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\DataSheets\DataSheet;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface;
use exface\Core\CommonLogic\DataSheets\DataSheetSubsheet;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

abstract class DataSheetFactory extends AbstractUxonFactory
{

    /**
     * Creates a data sheet for a give object.
     * The object can be passed directly or specified by it's fully qualified alias (with namespace!)
     *
     * @param Workbench $exface            
     * @param MetaObjectInterface|string $meta_object_or_alias            
     * @return DataSheetInterface
     */
    public static function createFromObjectIdOrAlias(Workbench $exface, $meta_object_or_alias = null)
    {
        if ($meta_object_or_alias instanceof MetaObjectInterface) {
            $meta_object = $meta_object_or_alias;
        } else {
            $meta_object = $exface->model()->getObject($meta_object_or_alias);
        }
        return static::createFromObject($meta_object);
    }

    /**
     *
     * @param Workbench $exface            
     * @return DataSheetInterface
     */
    public static function createEmpty(Workbench $exface)
    {
        return static::createFromObjectIdOrAlias($exface);
    }

    /**
     *
     * @param MetaObjectInterface $meta_object            
     * @return DataSheetInterface
     */
    public static function createFromObject(MetaObjectInterface $meta_object)
    {
        $data_sheet = new DataSheet($meta_object);
        return $data_sheet;
    }

    /**
     * Creates a data sheet by parsing the given UXON model.
     * 
     * If the model has no explicit object reference, the optional $fallback_object
     * parameter can be provided as fallback. This is handy to instantiate data
     * sheets from widget and action models, where the the sheet should inherit
     * the meta object.
     * 
     * @param Workbench $exface            
     * @param UxonObject $uxon     
     * @param MetaObjectInterface $fallback_object
     *        
     * @return DataSheetInterface
     */
    public static function createFromUxon(Workbench $exface, UxonObject $uxon, MetaObjectInterface $fallback_object = null)
    {
        $object_ref = $uxon->hasProperty('object_alias') ? $uxon->getProperty('object_alias') : $uxon->getProperty('meta_object_alias');
        if (! $object_ref){
            $object_ref = $uxon->hasProperty('meta_object_id') ? $uxon->getProperty('meta_object_id') : $uxon->getProperty('oId');
        }
        if ($object_ref) {
            $meta_object = $exface->model()->getObject($object_ref);
        } else {
            $meta_object = $fallback_object;
        }
        $data_sheet = self::createFromObject($meta_object);
        $data_sheet->importUxonObject($uxon);
        return $data_sheet;
    }

    /**
     *
     * @param Workbench $exface            
     * @param DataSheetInterface|UxonObject $data_sheet_or_uxon            
     * @throws InvalidArgumentException
     * @return DataSheetInterface
     */
    public static function createFromAnything(Workbench $exface, $data_sheet_or_uxon)
    {
        if ($data_sheet_or_uxon instanceof DataSheetInterface) {
            return $data_sheet_or_uxon;
        } elseif ($data_sheet_or_uxon instanceof UxonObject) {
            return static::createFromUxon($exface, $data_sheet_or_uxon);
        } elseif (is_array($data_sheet_or_uxon) === true) {
            return static::createFromUxon($exface, new UxonObject($data_sheet_or_uxon));
        } elseif (is_string($data_sheet_or_uxon) === true) {
            return static::createFromUxon($exface, UxonObject::fromJson($data_sheet_or_uxon));
        } else {
            throw new InvalidArgumentException('Cannot create data sheet from "' . get_class($data_sheet_or_uxon) . '"!');
        }
    }
    
    /**
     * 
     * @param DataSheetInterface $parentSheet
     * @param MetaObjectInterface $subsheetObject
     * @param string $joinKeyAliasOfSubsheet
     * @param string $joinKeyAliasOfParentSheet
     * @param MetaRelationPathInterface $relationPathFromParentSheet
     * @return DataSheetSubsheetInterface
     */
    public static function createSubsheet(DataSheetInterface $parentSheet, MetaObjectInterface $subsheetObject, string $joinKeyAliasOfSubsheet, string $joinKeyAliasOfParentSheet, MetaRelationPathInterface $relationPathFromParentSheet = null) : DataSheetSubsheetInterface
    {
        return new DataSheetSubsheet($subsheetObject, $parentSheet, $joinKeyAliasOfSubsheet, $joinKeyAliasOfParentSheet, $relationPathFromParentSheet);
    }
}