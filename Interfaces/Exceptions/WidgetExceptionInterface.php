<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\WidgetInterface;

Interface WidgetExceptionInterface
{

    /**
     *
     * @param WidgetInterface $widget            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(WidgetInterface $widget, $message, $code = null, $previous = null);

    /**
     *
     * @return WidgetInterface
     */
    public function getWidget();

    /**
     *
     * @param WidgetInterface $sheet            
     * @return WidgetExceptionInterface
     */
    public function setWidget(WidgetInterface $widget);
}
?>