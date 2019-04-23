<?php
namespace exface\Core\Facades;

use exface\Core\Facades\AbstractFacade\AbstractFacade;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use kabachello\FileRoute\FileRouteMiddleware;
use Psr\Http\Message\UriInterface;
use kabachello\FileRoute\Templates\PlaceholderFileTemplate;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Filemanager;
use function GuzzleHttp\Psr7\stream_for;
use exface\Core\Facades\DocsFacade\MarkdownDocsReader;
use exface\Core\Facades\DocsFacade\Middleware\AppUrlRewriterMiddleware;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;

/**
 *  
 * @author Andrej Kabachnik
 *
 */
class DocsFacade extends AbstractFacade implements HttpFacadeInterface
{    
    private $url = null;
    
    protected function init()
    {
        parent::init();
        if (! $this->getWorkbench()->isStarted()){
            $this->getWorkbench()->start();
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $handler = new HttpRequestHandler(new NotFoundHandler());
        
        // Add URL rewriter: it will take care of URLs after the content had been generated by the router
        $handler->add(new AppUrlRewriterMiddleware($this));
        
        // Add router middleware
        $matcher = function(UriInterface $uri) {
            $path = $uri->getPath();
            $url = StringDataType::substringAfter($path, '/api/docs');
            $url = ltrim($url, "/");
            if ($q = $uri->getQuery()) {
                $url .= '?' . $q;
            }
            return $url;
        };
        $reader = new MarkdownDocsReader($this->getWorkbench());
        $templatePath = Filemanager::pathJoin([$this->getApp()->getDirectoryAbsolutePath(), 'Facades/DocsFacade/template.html']);
        $template = new PlaceholderFileTemplate($templatePath, $this->getBaseUrl());
        $template->setBreadcrumbsRootName('Documentation');
        $handler->add(new FileRouteMiddleware($matcher, $this->getWorkbench()->filemanager()->getPathToVendorFolder(), $reader, $template));
        
        return $handler->handle($request);
    }
    
    /**
     * 
     * @return string
     */
    public function getBaseUrl() : string{
        if (is_null($this->url)) {
            $this->url = $this->getWorkbench()->getCMS()->buildUrlToApi() . '/api/docs';
        }
        return $this->url;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns() : array
    {
        return [
            "/\/api\/docs[\/?]/"
        ];
    }
}