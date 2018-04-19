<?php

namespace Finance;

/**
 * Config
 *
 * @author David Walker <dwalker@calstate.edu>
 */
class Config
{
    /**
     * @var array
     */
    private $config = array();
    
    /**
     * New Config object
     * 
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Get config entry
     * 
     * @param string $name     config entry name
     * @param bool $required   [optional] whether the config is required
     * @param string $default  [optional] default value if none supplied
     * 
     * @return mixed|NULL
     */
    public function get($name, $required = false, $default = "")
    {
        if (array_key_exists($name, $this->config)) {
            return $this->config[$name];
        } else {
            if ($default != "") {
                return $default;
            }
            elseif ($required == true) {
                throw new \Exception("Config entry $name is required.");
            }
            return null;
        }
    }
    
    /**
     * Set config entry
     * 
     * @param string $name   config entry name
     * @param string $value  value to set
     */
    public function set($name, $value)
    {
        $this->config[$name] = $value;
    }
}
