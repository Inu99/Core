<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Exception thrown if a value does not match with a set of values. Typically this happens when a 
 * function calls another function and expects the return value to be of a certain type or value 
 * not including arithmetic or buffer related errors.
 * 
 * @author Andrej Kabachnik
 *
 */
class UnexpectedValueException extends \UnexpectedValueException implements ExceptionInterface {
	
	use ExceptionTrait;
	
}
?>