<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

/**
 * Data type for Hexadecimal numbers.
 * 
 * @author Andrej Kabachnik
 *
 */
class HexadecimalNumberDataType extends NumberDataType
{
    
    /**
     *
     * {@inheritdoc}
     * @see NumberDataType::cast()
     */
    public static function cast($string)
    {
        if (is_null($string) || $string === '') {
            return $string;
        } elseif (mb_strtoupper(substr($string, 0, 2)) === '0X') {
            // TODO regex to check for allowed characters
            
            // Hexadecimal numbers in '0x....'-Notation
            return $string;
        } else {
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a hexadecimal number!');
            return '';
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::parse()
     */
    public function parse($string)
    {
        $number = parent::parse($string);
        
        /* TODO
        if (! is_null($this->getMin()) && $number < $this->getMin()) {
            throw new DataTypeValidationError($this, $number . ' is less than the minimum of ' . $this->getMin() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }
        
        if (! is_null($this->getMax()) && $number > $this->getMax()) {
            throw new DataTypeValidationError($this, $number . ' is greater than the maximum of ' . $this->getMax() . ' allowed for data type ' . $this->getAliasWithNamespace() . '!');
        }*/
        
        return $number;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::getBase()
     */
    public function getBase()
    {
        return 16;
    }
}
?>