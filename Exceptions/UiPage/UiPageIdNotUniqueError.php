<?php
namespace exface\Core\Exceptions\UiPage;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if the ID of a UI page is not unique.
 * 
 * @author SFL
 *
 */
class UiPageIdNotUniqueError extends RuntimeException
{
    
    public function getDefaultAlias()
    {
        return '6XQM0HG';
    }
}
