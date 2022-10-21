<?php

namespace Modules\CustomRemoteModule\Kickstart;

class CustomKickstart
{
    /**
     * @var string
     */
    protected $script;

    /**
     * @var array
     */
    protected $params;

    /**
     * @param string $script
     * @param array $params
     */
    public function __construct(string $script, array $params)
    {
        $this->setScript($script);
        $this->setParams($params);
    }

    /**
     * @return string
     */
    public function generate()
    {
        $script = $this->getScript();
        $params = $this->getParams();

        /** Change and parse the script here */

        return $script;
    }

    /**
     * Get the value of params
     *
     * @return  array
     */ 
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Set the value of params
     *
     * @param  array  $params
     *
     * @return  self
     */ 
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Get the value of script
     *
     * @return  string
     */ 
    public function getScript()
    {
        return $this->script;
    }

    /**
     * Set the value of script
     *
     * @param  string  $script
     *
     * @return  self
     */ 
    public function setScript(string $script)
    {
        $this->script = $script;

        return $this;
    }
}
