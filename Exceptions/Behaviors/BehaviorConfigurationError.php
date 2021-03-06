<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\Model\MetaObjectExceptionTrait;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a configuration option for a meta object behavior is invalid or missing.
 *
 * Behaviors are encouraged to produce this error if the user creates an invalid UXON configuration for the behavior
 * invalid option values are set programmatically.
 *
 * @author Andrej Kabachnik
 *        
 */
class BehaviorConfigurationError extends UnexpectedValueException
{
    
    use MetaObjectExceptionTrait;

    /**
     *
     * @param MetaObjectInterface $meta_object            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(MetaObjectInterface $meta_object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($meta_object);
    }
}
?>