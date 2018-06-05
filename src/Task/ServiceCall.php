<?php

namespace MyCLabs\Work\Task;

/**
 * Represents the call of the method of a service.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class ServiceCall implements Task
{
    /**
     * @var string
     */
    protected $serviceName;

    /**
     * @var string
     */
    protected $methodName;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param string $serviceName Name of the service class
     * @param string $methodName  Name of the method to call
     * @param array  $parameters  Parameters for the method call, must be serializable
     */
    public function __construct($serviceName, $methodName, array $parameters = [])
    {
        $this->serviceName = $serviceName;
        $this->methodName = $methodName;
        $this->parameters = $parameters;
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Useful for logging and debugging.
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('%s %s::%s()', get_class(), $this->serviceName, $this->methodName);
    }
}
