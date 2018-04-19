<?php

namespace Finance;

/**
 * Fund
 *
 * @author David Walker <dwalker@calstate.edu>
 */
class Fund extends Xml
{
    /**
     * @var float
     */
    public $amount;
    
    /**
     * @var string
     */
    public $business_unit_id;
    
    /**
     * @var string
     */
    public $gl_unit;
    
    /**
     * @var string
     */
    public $speedchart_key;
    
    /**
     * @var string
     */
    public $account_code;
    
    /**
     * @var string
     */
    public $fund_code;
    
    /**
     * @var string
     */
    public $dept_id;
    
    /**
     * @var string
     */
    public $program_code;
    
    /**
     * @var string
     */
    public $class_code;
    
    /**
     * @var string
     */
    public $project_id;
    
    /**
     * Map
     */
    protected function map()
    {
        $this->amount = (float) $this->verifyAlmaFieldData($this->xml, "amount/sum");
        
        // look in the external id field for either speedchart key or account, fund, dept id, project, and class.
        // see external id field layout in documentation for more information.

        $external_id_values = $this->verifyAlmaFieldData($this->xml, "external_id");
        $external_id_array = explode(" ", $external_id_values);
        
        if ($external_id_values != "") {
            
            // business unit
            
            $business_units = $this->config->get('business_units');
            
            if ($this->config->get('multiple_business_units') == true && strlen($external_id_array[0]) == 1) {
                
                $this->business_unit_id = $external_id_array[0];

                if ($this->config->get('populate_gl_unit') == true) {
                    if (array_key_exists($this->business_unit_id, $business_units)) {
                        $this->gl_unit = $business_units[$this->business_unit_id];
                    }
                }
            } elseif ($this->config->get('multiple_business_units') == false 
                && $this->config->get('populate_gl_unit') == true 
                && strlen($external_id_array[0]) != 1) {
                
                // only single business unit in use by library and 
                // no identifier inserted to external_id field
                array_unshift($external_id_array, "");
                $bu_keys = array_keys($business_units);
                
                // there's no business identifier so add empty value to $external_id_array[0]
                // so the speedchart key or chartfield values below are pulled correctly.
                $this->gl_unit = $business_units[$bu_keys[0]];
            } else {
                // there's no business identifier so add empty value to $external_id_array[0]
                // so the speedchart key or chartfield values below are pulled correctly.
                array_unshift($external_id_array, "");
            }
            
            // speedchart key
            
            if ((count($external_id_array) == 2)) {
                $this->speedchart_key = $external_id_array[1];
            } else {
                
                // chartfield codes
                
                if (isset($external_id_array[1])) {
                    $this->account_code = $external_id_array[1];
                }
                if (isset($external_id_array[2])) {
                    $this->fund_code = $external_id_array[2];
                }
                if (isset($external_id_array[3])) {
                    $this->dept_id = $external_id_array[3];
                }
                if (isset($external_id_array[4])) {
                    $this->program_code = $external_id_array[4];
                }
                if (isset($external_id_array[5])) {
                    $this->class_code = $external_id_array[5];
                }
                if (isset($external_id_array[6])) {
                    $this->project_id = $external_id_array[6];
                }
            }
        }
    }
}
