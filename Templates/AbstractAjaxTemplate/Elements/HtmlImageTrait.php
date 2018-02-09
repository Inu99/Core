<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\Image;
use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsValueDecoratingInterface;

/**
 *
 * @method Image getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait HtmlImageTrait
{
    use JqueryAlignmentTrait;
    
    /**
     * 
     * @see AbstractJqueryElement::generateHtml()
     */
    public function generateHtml()
    {
        return $this->buildHtmlImage($this->getWidget()->getUri());
    }

    /**
     *
     * @see AbstractJqueryElement::generateJs()
     */
    public function generateJs()
    {
        return '';
    }
    
    /**
     * Returns the <img> HTML tag with the given source.
     * 
     * @param string $src
     * @return string
     */
    protected function buildHtmlImage($src)
    {
        $style = '';
        if (! $this->getWidget()->getWidth()->isUndefined()) {
            $style .= 'width:' . $this->getWidth() . '; ';
        }
        if (! $this->getWidget()->getHeight()->isUndefined()) {
            $style .= 'height: ' . $this->getHeight() . '; ';
        }
        
        switch ($this->getWidget()->getAlign()) {
            case EXF_ALIGN_CENTER:
                $style .= 'margin-left: auto; margin-right: auto;';
                break;
            case EXF_ALIGN_RIGHT:
                $style .= 'float: right';
        }
        
        $output = '<img src="' . $src . '" class="' . $this->buildCssElementClass() . '" style="' . $style . '" />';
        return $output;
    }
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        return <<<JS
'{$this->buildHtmlImage("'+" . $value_js . "+'")}'
JS;
    }
}
?>