<?php

namespace Finance;

/**
 * Invoice
 *
 * @author Ian Chan <ichan@csusm.edu>
 * @author David Walker <dwalker@calstate.edu>
 */
class Invoice extends Xml
{
    /**
     * @var string
     */
    public $invoice_id;

    /**
     * @var string
     */
    public $invoice_date;

    /**
     * @var string
     */
    public $vendor_id;
    
    /**
     * @var int
     */
    public $vendor_addr_seq_num;
    
    /**
     * @var string
     */
    public $accounting_dt;
        
    /**
     * @var string
     */
    public $unit_of_measure;
    
    /**
     * @var float
     */
    public $gross_amt;

    /**
     * Invoice-level merchandise amount, using gross - charges
     * @var float
     */
    public $merchandise_amt;
    
    /**
     * @var float
     */
    public $tax_amt;
    
    /**
     * @var string
     */
    public $tax_type;
    
    /**
     * @var float
     */
    public $overhead_amt;
    
    /**
     * @var float
     */
    public $misc_chrg_amt;
    
    /**
     * @var float
     */
    public $freight_amt;
    
    /**
     * @var InvoiceLine[]
     */
    public $lines = array();
    
    /**
     * @var int
     */
    protected $timestamp;
    
    /**
     * New Invoice
     * 
     * @param \SimpleXMLElement $xml  Alma XML
     * @param array $config           Config array
     * @param int $timestamp          [optional] unix timestamp, now if blank
     */
    public function __construct(\SimpleXmlElement $xml, Config $config, $timestamp = "")
    {
        if ($timestamp == "") $timestamp = time();
        $this->timestamp = $timestamp;
        return parent::__construct($xml, $config);
    }
    
    /**
     * Map
     */
    protected function map()
    {
        $this->invoice_id = $this->verifyAlmaFieldData($this->xml, "invoice_number");
        $this->gross_amt = (float) $this->verifyAlmaFieldData($this->xml, "invoice_amount/sum");
        $this->tax_amt = (float) $this->verifyAlmaFieldData($this->xml, "vat_info/vat_amount");
        $this->tax_type = $this->verifyAlmaFieldData($this->xml, "vat_info/vat_type");
        $this->overhead_amt = (float) $this->verifyAlmaFieldData($this->xml, "additional_charges/overhead_amount");
        $this->misc_chrg_amt = (float) $this->verifyAlmaFieldData($this->xml, "additional_charges/insurance_amount");

        $this->accounting_dt = date($this->config->get('accounting_format'), $this->timestamp);
        $this->unit_of_measure = $this->config->get('unit_of_measure');
        
        // invoice date
        
        $this->invoice_date = $this->verifyAlmaFieldData($this->xml, "invoice_date");

        if ($this->config->get('peoplesoft_voucher_layout') == "interface") {
            if ($this->config->get('invoice_date_format') != null) {
                $this->invoice_date = date($this->config->get('invoice_date_format'), strtotime($this->invoice_date));
            } else {
                $this->invoice_date = str_replace("/", "", $this->invoice_date);
            }
        }

        // vendor financial system code
        
        $vendor_fsys_code = $this->verifyAlmaFieldData($this->xml, "vendor_FinancialSys_Code");

        if (strpos($vendor_fsys_code, '-') !== false) {
            $vendor_fsys_code_parts = explode("-", $vendor_fsys_code);
            $this->vendor_id =  $vendor_fsys_code_parts[0];
            $this->vendor_addr_seq_num = $vendor_fsys_code_parts[1];
        } else {
            $this->vendor_id = $vendor_fsys_code;
        }

        // discount
        
        if ($this->config->get('discount_in_invoice_line') == true) {
            
            $this->overhead_amt = (float) $this->verifyAlmaFieldData($this->xml, 
                "invoice_line_list/invoice_line[line_type = 'DISCOUNT']/price");
            
            // only if there is a positive dollar value
            if ($this->overhead_amt != "" && $this->overhead_amt != '0.0') {
                $this->overhead_amt = '-' . $this->overhead_amt;
            } else {
                $this->overhead_amt = "";
            }
        }

        // frieght amount
        
        $this->freight_amt = (float) $this->verifyAlmaFieldData($this->xml, "additional_charges/shipment_amount");
        
        if ($this->config->get('shipment_in_invoice_line') == true) {
           
            $this->freight_amt = (float) $this->verifyAlmaFieldData($this->xml, 
                "invoice_line_list/invoice_line[line_type = 'SHIPMENT']/total_price");

            if ($this->tax_type == 'LINEEXCLUSIVE') {
                $this->freight_amt = (float) $this->verifyAlmaFieldData($this->xml, 
                    "invoice_line_list/invoice_line[line_type = 'SHIPMENT']/price");
            }
        }
        
        // invoice-level merchandise amount
        
        $this->merchandise_amt = $this->gross_amt - $this->tax_amt - $this->freight_amt - $this->overhead_amt;

        // invoice lines

        foreach ($this->xml->invoice_line_list as $invoice_line_list) {

            // sort the invoices by line number

            $invoice_lines = array();

            foreach ( $invoice_line_list->invoice_line as $inv_line ) {
                $invoice_lines[(int) $inv_line->line_number] = $inv_line;
            }

            ksort($invoice_lines);

            // which lines are allowed
            // certain lines are place holders and can be safely ignored

            $allowed_lines = array('REGULAR');

            if ($this->config->get('allowed_lines') != "") {
                $allowed_lines = explode(';', $this->config->get('allowed_lines'));
            }
            
            foreach ( $invoice_lines as $inv_line ) {

                $line_type = $this->verifyAlmaFieldData($inv_line, "line_type");
                
                // only include allowed lines

                if (in_array($line_type, $allowed_lines)) {
                    $this->lines[] = new InvoiceLine($inv_line, $this->config, $this->tax_amt);
                }
            }
        }
    }
    
    /**
     * Return business unit id 
     * 
     * If any line in the invoice has one
     * @return string|NULL
     */
    public function getBusinessUnitId()
    {
        foreach ($this->lines as $line) {
            foreach ($line->funds as $fund) {
                if ($fund->business_unit_id != "") {
                    $business_units = $this->config->get('business_units');
                    return $business_units[$fund->business_unit_id];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Return invoice lines in upload format
     *
     * @return array
     */
    public function toUploadFormat()
    {
        $csv_lines = array();
        $x = 0;
        
        foreach ($this->lines as $line) {
            $x++;
            
            foreach ($line->funds as $fund) {

                // first line of invoice
                // includes vendor id, gross amount, tax, shipping, misc charges & vendor sequence number
                
                if ($x == 1) {
    
                    // merchandise amount from line
                    $merchandise_amt = $line->price;
                    
                    // take merchandise amount from invoice instead of invoice-line
                    if ($this->config->get('merchandise_amount_in_invoice', false, false) == true) {
                        $merchandise_amt = $this->merchandise_amt;
                    }
                    
                    $csv_lines[] = array(
                        $this->invoice_id,          // A. Invoice # (30 chars)
                        $this->invoice_date,        // B. Invoice Date MM/DD/YYYY (10 chars)
                        $this->vendor_id,           // C. Supplier ID in Oracle PeopleSoft (10 chars)
                        $this->accounting_dt,       // D. Accounting Date MM/DD/YYYY (10 chars)
                        $line->line_num,            // E. Voucher Line in Oracle PeopleSoft (5 chars)
                        $fund->gl_unit,             // F. GL Business Unit (5 chars)
                        "",                         // G. Quantity
                        "",                         // H. Unit of Measure
                        "",                         // I. Unit Price
                        $this->gross_amt,           // J. Voucher Gross Amount [supplier invoice amount] (23.3 length)
                        $merchandise_amt,           // K. Merchandise Amt (28 chars)
                        $line->description,         // L. Description – display on the Voucher Line (30 chars)
                        $fund->speedchart_key,      // M. Speedchart code to enter frequently used chartfield values (10 chars)
                        $fund->account_code,        // N. Account (6 chars)
                        $fund->fund_code,           // O. Fund Code (5 chars)
                        $fund->dept_id,             // P. Department ID (10 chars)
                        $fund->program_code,        // Q. Program Code (5 chars)
                        $fund->class_code,          // R. Class (5 chars)
                        $fund->project_id,          // S. Project ID (15 chars)
                        $line->getShipToLocation(), // T. Ship to Location (10 chars)
                        $line->sut_applicability,   // U. 'S’ for Sales Tax, ‘U’ for Use Tax, ‘E’ for Exempt (1 char)
                        $this->tax_amt,             // V. Sales Tax Amount (23.3 length)
                        $this->freight_amt,         // W. Freight Amount (23.3 length)
                        $this->overhead_amt,        // X. Miscellaneous Charge Amount (23.3 length)
                        "",                         // Y. Supplier Class
                        "",                         // Z. Old Vendor ID
                        "",                         // AA. Name 1
                        "",                         // BB. Name 2
                        "",                         // CC. Supplier Location
                        $this->vendor_addr_seq_num, // DD. Addr Seq # (5 chars)
                        "","","","","","","","",""  // EE-MM. Adress info, payment group, handling code, etc.
                    );
                } elseif ( $this->config->get('voucher_upload_abbr') != true ) {
                    
                    // subsequent invoice lines
                    // unless abbreviated format
                    
                    $csv_lines[] = array(
                        $this->invoice_id,          // A. Invoice # (30 chars)
                        $this->invoice_date,        // B. Invoice Date MM/DD/YYYY (10 chars)
                        "",                         // C. Supplier ID (1st line only)
                        $this->accounting_dt,       // D. Accounting Date MM/DD/YYYY (10 chars)
                        $line->line_num,            // E. Voucher Line in Oracle PeopleSoft (5 chars)
                        $fund->gl_unit,             // F. GL Business Unit (5 chars)
                        "",                         // G. Quantity
                        "",                         // H. Unit of Measure
                        "",                         // I. Unit Price
                        "",                         // J. Voucher Gross Amount [supplier invoice amount] (1st line only)
                        $line->price,               // K. Merchandise Amount (28 chars)
                        $line->description,         // L. Description – display on the Voucher Line (30 chars)
                        $fund->speedchart_key,      // M. Speedchart code to enter frequently used chartfield values (10 chars)
                        $fund->account_code,        // N. Account (6 chars)
                        $fund->fund_code,           // O. Fund Code (5 chars)
                        $fund->dept_id,             // P. Department ID (10 chars)
                        $fund->program_code,        // Q. Program Code (5 chars)
                        $fund->class_code,          // R. Class (5 chars)
                        $fund->project_id,          // S. Project ID (15 chars)
                        $line->getShipToLocation(), // T. Ship to Location (10 chars)
                        $line->sut_applicability,   // U. 'S’ for Sales Tax, ‘U’ for Use Tax, ‘E’ for Exempt (1 char)
                        "","","","","",             // V-Z. Voucher Line 1 only
                        "","","","","","","","",    // AA-HH. Voucher Line 1 only
                        "","","","",""              // II-MM. Voucher Line 1 only
                    );
                }
            }
        }
        
        return $csv_lines;
    }
    
    /**
     * Return invoice lines in interface format
     * 
     * @return array
     */
    public function toInterfaceFormat()
    {
        $csv_lines = array();
        
        // header line
        
        $csv_lines[] = array(
            'H',                  // A. Record Indicator – 'H' for Header Record (1 char)
            $this->invoice_id,    // B. Invoice ID (30 chars)
            $this->invoice_date,  // C. Invoice Date MMDDYYYY (10 chars)
            $this->vendor_id,     // D. Supplier ID (10 chars)
            $this->accounting_dt, // E. Account Date MMDDYYYY (10 chars)
            $this->gross_amt,     // F. Gross Amount (23.3 length)
            $this->tax_amt,       // G. Sales Tax Amount (23.3 length)
            $this->freight_amt,   // H. Freight Amount (23.3 length)
            $this->overhead_amt   // I. Misc Charge Amount (23.3 length)
        );
        
        // invoice lines
        
        foreach ($this->lines as $line) {
            foreach ($line->funds as $fund) {
                
                // mechandise amount is the line price, unless there are multiple funds and
                // also no shipping or overhead, in which case go ahead and use the fund amount
                // @todo talk to sanjose about scenarios where shipping and overhead are present 
                
                $merchandise_amt = $line->price;
                
                if (count($line->funds) > 0 && $this->freight_amt == 0 && $this->overhead_amt == 0) {
                    $merchandise_amt = $fund->amount;
                }
                
                $unit_price = $merchandise_amt / $line->quantity;
                
                $csv_lines[] = array(
                    'L',                        // A. Record Indicator – 'L' for Voucher Line Record (1 char)
                    $line->quantity,            // B. Quantity (11.4 length)
                    $this->unit_of_measure,     // C. Unit of Measure (3 chars)
                    $unit_price,                // D. Unit Price (10.5 length)
                    $merchandise_amt,           // E. Merchandize Amount (23.3 length)
                    $line->description,         // F. Line Description (30 chars)
                    $line->getShipToLocation(), // G. Ship to Location (10 chars)
                    $line->sut_applicability,   // H. Sales Tax = S, Use Tax = U, Exempt = E (1 char)
                    $fund->speedchart_key       // I. SpeedChart (10 chars)
                );
    
                // if not using speedchart key, chartfield string goes on distribution line
                
                if ($fund->speedchart_key == "") {
                    $csv_lines[] = array(
                        'D',                    // A. Record Indicator – 'D' for Voucher Distribution Line Record (1 char)
                        $fund->gl_unit,         // B. GL Business Unit (5 chars)
                        $line->quantity,        // C. Quantity (11.4 length)
                        $merchandise_amt,       // D. Merchandize Amount (23.3 length)
                        $fund->account_code,    // E. Account (10 chars)
                        $fund->fund_code,       // F. Fund (5 chars)
                        $fund->dept_id,         // G. Department (10 chars)
                        $fund->program_code,    // H. Program Code (5 chars)
                        $fund->class_code,      // I. Class (5 chars)
                        $fund->project_id       // J. Project ID (15 chars)
                    );
                }
            }
        }
        
        return $csv_lines;
    }
}
