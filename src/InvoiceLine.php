<?php

namespace Finance;

/**
 * Invoice Line
 *
 * @author Ian Chan <ichan@csusm.edu>
 * @author David Walker <dwalker@calstate.edu>
 */
class InvoiceLine extends Xml
{
    /**
     * @var int
     */
    public $line_num;

    /**
     * @var int
     */
    public $quantity;
    
    /**
     * @var float
     */
    public $price;
    
    /**
     * @var float
     */
    public $total_price;
    
    /**
     * @var string
     */
    public $description;
    
    /**
     * @var string
     */
    public $sut_applicability;

    /**
     * @var string
     */
    public $ship_to_location;
    
    /**
     * @var Fund[]
     */
    public $funds = array();
    
    /**
     * @var float
     */
    protected $tax_amt;
    
    /**
     * New Invoice List object
     * 
     * @param \SimpleXMLElement $xml
     * @param Config $config
     * @param float $tax_amt
     */
    public function __construct(\SimpleXMLElement $xml, Config $config, $tax_amt) {
        $this->tax_amt = $tax_amt;
        parent::__construct($xml, $config);
    }
    
    /**
     * Map
     */
    protected function map()
    {
        $this->line_num = (int) $this->verifyAlmaFieldData($this->xml, "line_number");
        $this->quantity = (int) $this->verifyAlmaFieldData($this->xml, "quantity");
        $this->price = (float) $this->verifyAlmaFieldData($this->xml, "price");
        $this->total_price = (float) $this->verifyAlmaFieldData($this->xml, "total_price");
        $this->description = $this->verifyAlmaFieldData($this->xml, "line_type");
        
        $this->ship_to_location = $this->config->get('ship_to_location');
        $this->sut_applicability = $this->getSutApplicability();
        
        // process each fund
        
        if ($this->xml->fund_info_list->fund_info != null) {
            foreach ($this->xml->fund_info_list->fund_info as $fund_info) {
                $this->funds[] = new Fund($fund_info, $this->config);
            }
        }
    }
    
    /**
     * Ship-to location
     *
     * @return string
     */
    public function getShipToLocation()
    {
        // ship-to location
        $ship_to_location = $this->ship_to_location;
        
        // multiple ship-to locations specified, so pick one based on SUT
        
        if (is_array($this->ship_to_location)) {
            if (array_key_exists($this->sut_applicability, $this->ship_to_location)) {
                $ship_to_location = $this->ship_to_location[$this->sut_applicability];
            } else {
                $ship_to_location = array_values($this->ship_to_location)[0]; // couldn't find one, so grab first value
            }
        }
        
        return $ship_to_location;
    }
    
    /**
     * Sales and Use Tax flag
     * 
     * @return string
     */
    protected function getSutApplicability()
    {
        if ($this->tax_amt > 0.0) {
            return 'S';
        }
        
        return 'E';
    }
}
