<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;

class DateDataType extends AbstractDataType
{
    public static function cast($string)
    {
        $string = trim($string);
        
        // Return NULL for casting empty values as an empty string '' actually is not a date!
        if (static::isEmptyValue($string) === true) {
            return null;
        }
        
        // If a timestamp is passed (seconds since epoche), it must not be interpreted as
        // a relative date - therefore prefix really large numbers with an @, which will
        // mark it as a timestamp for the \DateTime consturctor.
        if (is_numeric($string) && intval($string) > 100000) {
            $string = '@' . $string;
        }
        
        if ($relative = static::parseRelativeDate($string)){
            return $relative;
        }
        
        if ($short = static::parseShortDate($string)){
            return $short;
        }
        
        try {
            $date = new \DateTime($string);
        } catch (\Exception $e) {
            throw new DataTypeCastingError('Cannot convert "' . $string . '" to a date!', '6W25AB1', $e);
        }
        return static::formatDate($date);
    }
    
    public static function parseShortDate($string)
    {
        $matches = [];
        if (strlen($string) == 4 && is_int($string)){
            return static::formatDate(new \DateTime($string . '-01-01'));
        } elseif (preg_match('/^([0-9]{1,2})[\.-]([0-9]{4})$/', $string, $matches)){
            return $matches[2] . '-' . $matches[1] . '-01';
        } elseif (preg_match('/^([0-9]{1,2})[\.-]([0-9]{1,2})[\.-]?$/', $string, $matches)){
            return date("Y") . '-' . $matches[2] . '-' . $matches[1];
        } else {
            return false;
        }
    }
    
    public static function parseRelativeDate($string)
    {
        $day_period = 'D';
        $week_period = 'W';
        $month_period = 'M';
        $year_period = 'Y';
        
        $matches = [];
        if (preg_match('/^([\+-]?[0-9]+)([dDmMwWyY]?)$/', $string, $matches)){
            $period = $matches[2];
            $quantifier = intval($matches[1]);
            $interval_spec = 'P' . abs($quantifier);
            switch (strtoupper($period)){
                case $day_period:
                case '':
                    $interval_spec .= 'D';
                    break;
                case $week_period:
                    $interval_spec .= 'W';
                    break;
                case $month_period:
                    $interval_spec .= 'M';
                    break;
                case $year_period:
                    $interval_spec .= 'Y';
                    break;
                default:
                    throw new UnexpectedValueException('Invalid period "' . $period . '" used in relative date "' . $quantifier . $period . '"!', '6W25AB1');
            }
            $date = new \DateTime();
            $interval = new \DateInterval($interval_spec);
            if ($quantifier > 0){
                $date->add($interval);
            } else {
                $date->sub($interval);
            }
            return static::formatDate($date);
        }
        return false;
    }
    
    public static function formatDate(\DateTime $date) : string
    {
        return $date->format('Y-m-d');
    }
    
    public static function now() : string
    {
        return static::formatDate((new \DateTime()));
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirectionsDataType::DESC($this->getWorkbench());
    }
}
?>