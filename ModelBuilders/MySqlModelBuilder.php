<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataConnectors\MySqlConnector;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 * @method MySqlConnector getDataConnection()
 * 
 * @author Andrej Kabachnik
 *
 */
class MySqlModelBuilder extends AbstractSqlModelBuilder
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::getAttributeDataFromTableColumns()
     */
    public function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $table_name) : array
    {
        $columns_sql = "
					SHOW FULL COLUMNS FROM " . $table_name . "
				";
        
        // TODO check if it is the right data connector
        $columns_array = $meta_object->getDataConnection()->runSql($columns_sql)->getResultArray();
        $rows = array();
        foreach ($columns_array as $col) {
            $row = [
                'NAME' => $this->generateLabel($col['Field'], $col['Comment']),
                'ALIAS' => $col['Field'],
                'DATATYPE' => $this->getDataTypeId($this->guessDataType($meta_object, $col['Type'])),
                'DATA_ADDRESS' => $col['Field'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['Null'] == 'NO' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['Comment'] ? $col['Comment'] : '')
            ];
            
            $addrProps = new UxonObject();
            if (stripos($col['Type'], 'binary') !== false) {
               $addrProps->setProperty('SQL_DATA_TYPE', 'binary');
            }
            // Add mor data address properties here, if neccessary
            if ($addrProps->isEmpty() === false) {
                $row['DATA_ADDRESS_PROPS'] = $addrProps->toJson();
            }
                
            $rows[] = $row;
        }
        
        return $rows;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::guessDataType()
     */
    protected function guessDataType(MetaObjectInterface $object, string $data_type, $length = null, $number_scale = null) : DataTypeInterface
    {
        $data_type = trim($data_type);
        $details = [];
        $type = trim(StringDataType::substringBefore($data_type, '(', $data_type));
        if ($type !== $data_type) {
            $details = explode(',', substr($data_type, (strlen($type)+1), -1));
        }
        
        switch (mb_strtoupper($type)) {
            case 'TINYINT':
                $type = 'INT';
                break;
        }
        
        return parent::guessDataType($object, $type, trim($details[0]), trim($details[1]));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::findObjectTables()
     */
    protected function findObjectTables(string $mask = null) : array
    {
        if ($mask) {
            $filter = "AND table_name LIKE '{$mask}'";
        }
        
        $sql = "SELECT table_name as ALIAS, table_name as NAME, table_name as DATA_ADDRESS, table_comment as SHORT_DESCRIPTION FROM information_schema.tables where table_schema='{$this->getDataConnection()->getDbase()}' {$filter}";
        $rows = $this->getDataConnection()->runSql($sql)->getResultArray();
        foreach ($rows as $nr => $row) {
            $rows[$nr]['NAME'] = $this->generateLabel($row['NAME'], $row['SHORT_DESCRIPTION']);
        }
        return $rows;
    }
}
?>