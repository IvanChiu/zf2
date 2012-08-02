<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Mvc
 */

namespace Zend\Mvc\Controller;

use Zend\EventManager\EventInterface as Event;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\PhpEnvironment\Response as HttpResponse;
use Zend\Http\Request as HttpRequest;
use Zend\Mvc\Exception;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\DispatchableInterface as Dispatchable;
use Zend\Stdlib\RequestInterface as Request;
use Zend\Stdlib\ResponseInterface as Response;

/**
 * Abstract controller
 *
 * @category   Zend
 * @package    Zend_Mvc
 * @subpackage Controller
 */
abstract class AbstractController implements
    Dispatchable,
    EventManagerAwareInterface,
    InjectApplicationEventInterface,
    ServiceLocatorAwareInterface
{
    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Event
     */
    protected $event;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceManager;

    /**
     * Execute the request
     *
     * @param  MvcEvent $e
     * @return mixed
     */
    abstract public function execute(MvcEvent $e);

    /**
     * Get request object
     *
     * @return Request
     */
    public function getRequest()
    {
        if (!$this->request) {
            $this->request = new HttpRequest();
        }
        return $this->request;
    }

    /**
     * Get response object
     *
     * @return Response
     */
    public function getResponse()
    {
        if (!$this->response) {
            $this->response = new HttpResponse();
        }
        return $this->response;
    }

    /**
     * Set the event manager instance used by this context
     *
     * @param  EventManagerInterface $events
     * @return AbstractRestfulController
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            'Zend\Stdlib\DispatchableInterface',
            __CLASS__,
            get_called_class(),
            substr(get_called_class(), 0, strpos(get_called_class(), '\\'))
        ));
        $this->events = $events;
        $this->attachDefaultListeners();
        return $this;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Set an event to use during dispatch
     *
     * By default, will re-cast to MvcEvent if another event type is provided.
     *
     * @param  Event $e
     * @return void
     */
    public function setEvent(Event $e)
    {
        if ($e instanceof Event && !$e instanceof MvcEvent) {
            $eventParams = $e->getParams();
            $e = new MvcEvent();
            $e->setParams($eventParams);
            unset($eventParams);
        }
        $this->event = $e;
    }

    /**
     * Get the attached event
     *
     * Will create a new MvcEvent if none provided.
     *
     * @return MvcEvent
     */
    public function getEvent()
    {
        if (!$this->event) {
            $this->setEvent(new MvcEvent());
        }
        return $this->event;
    }

    /**
     * Set serviceManager instance
     *
     * @param  ServiceLocatorInterface $serviceManager
     * @return void
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * Retrieve serviceManager instance
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceManager;
    }

    /**
     * Get plugin manager
     *
     * @return PluginManager
     */
    public function getPluginManager()
    {
        if (!$this->plugins) {
            $this->setPluginManager(new PluginManager());
        }
        return $this->plugins;
    }

    /**
     * Set plugin manager
     *
     * @param  string|PluginManager $plugins
     * @return RestfulController
     * @throws Exception\InvalidArgumentException
     */
    public function setPluginManager(PluginManager $plugins)
    {
        $this->plugins = $plugins;
        if (method_exists($plugins, 'setController')) {
            $this->plugins->setController($this);
        }
        return $this;
    }

    /**
     * Get plugin instance
     *
     * @param  string     $name    Name of plugin to return
     * @param  null|array $options Options to pass to plugin constructor (if not already instantiated)
     * @return mixed
     */
    public function plugin($name, array $options = null)
    {
        return $this->getPluginManager()->get($name, $options);
    }

    /**
     * Method overloading: return/call plugins
     *
     * If the plugin is a functor, call it, passing the parameters provided.
     * Otherwise, return the plugin instance.
     *
     * @param  string $method
     * @param  array $params
     * @return mixed
     */
    public function __call($method, $params)
    {
        $plugin = $this->plugin($method);
        if (is_callable($plugin)) {
            return call_user_func_array($plugin, $params);
        }
        return $plugin;
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        $events = $this->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH, array($this, 'execute'));
    }

    /**
     * Transform an "action" token into a method name
     *
     * @param  string $action
     * @return string
     */
    public static function getMethodFromAction($action)
    {
        $method  = str_replace(array('.', '-', '_'), ' ', $action);
        $method  = ucwords($method);
        $method  = str_replace(' ', '', $method);
        $method  = lcfirst($method);
        $method .= 'Action';
        return $method;
    }
}
