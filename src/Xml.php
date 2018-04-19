<?php

namespace Finance;

/**
 * XML processor base class
 *
 * @author Ian Chan <ichan@csusm.edu>
 * @author David Walker <dwalker@calstate.edu>
 */
abstract class Xml
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Original XML
     * @var \SimpleXMLElement
     */
    protected $xml;

    /**
     * @param \SimpleXMLElement $xml  Alma XML
     * @param array $config           Config array
     */
    public function __construct(\SimpleXMLElement $xml, Config $config)
    {
        $this->xml = $xml;
        $this->config = $config;
        $this->map();
    }
    
    /**
     * Map XML to properties
     */
    abstract protected function map();

    /**
     * Query for the value in the XML and return if successful
     *
     * @param \SimpleXMLElement $xml  invoice xml
     * @param string $path           relative xpath expression
     * @return NULL|string           value on success, null otherwise
     */
    protected function verifyAlmaFieldData(\SimpleXMLElement $xml, $path)
    {
        // for logging purposes
        $path_full = $xml->getName() . '/' . $path;

        // pre-fix namespace for xpath query
        $xml->registerXPathNamespace('x', 'http://com/exlibris/repository/acq/invoice/xmlbeans');
        $path = explode('/', $path);
        $path = 'x:' . implode('/x:', $path);
        $path = preg_replace("/\[([^@])/", "[x:$1", $path);
        
        // get results
        $results = $xml->xpath($path);

        // no nodes in results, so it didn't exist
        if ($results === false || count($results) == 0) {
            return null;
        }

        $value = (string) $results[0];

        // no value, so was empty
        if ( empty($value) ) {
            return null;
        }

        return $value;
    }
}
