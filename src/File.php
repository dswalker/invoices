<?php

namespace Finance;

/**
 * File
 *
 * @author David Walker <dwalker@calstate.edu>
 */
class File
{
    /**
     * Full path to file
     * @var string
     */
    public $path;
    
    /**
     * File timestamp
     * @var int
     */
    public $timestamp;
    
    /**
     * File date in Y-m-d format
     * @var string
     */
    public $date;
    
    /**
     * Just the filename portion of the path
     * @var string
     */
    public $filename;
    
    /**
     * New File
     * @param string $file  path to file
     */
    public function __construct($file)
    {
        $matches = [];
        if (preg_match('/[0-9]*-([0-9]*).xml/', $file, $matches)) {

            $this->path = $file;
            $this->timestamp = $matches[1] / 1000;
            $this->date = date("Y-m-d", $this->timestamp);
            
            $parts = explode('/', $this->path);
            $this->filename = array_pop($parts);
        }
    }
}
