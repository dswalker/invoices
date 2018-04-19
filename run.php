<?php

chdir(dirname(__file__));

include("vendor/autoload.php");

use Finance\Invoices;
use Finance\Config;

// command line params

$campus = ""; if (array_key_exists(1, $argv)) $campus = $argv[1];
$action = ""; if (array_key_exists(2, $argv)) $action = $argv[2];
$date = ""; if (array_key_exists(3, $argv)) $date = $argv[3];

// specify campus(es)

$campuses = array();

if ($campus == "") {
    die('No campus specified');
}

if ($campus == 'all') {
    $list = glob('campuses/*');
    foreach ($list as $entry) {
        if (is_dir($entry)) {
            $entry  = str_replace('campuses/', '', $entry);
            $campuses[] = $entry;
        }
    }
} else {
    $campuses = array($campus);
}

// process invoices for each library

foreach ($campuses as $campus) {
    
    try {

        $config_array = include("campuses/$campus/config.php");
        $config = new Config($config_array);
        $invoices = new Invoices($config, $date);
        
        echo "\n========================\n";
        echo strtoupper($campus) . "\n";
        echo "========================\n";

        if ($action == "test") {
            $invoices->testAlmaExportFiles(); exit;
        }
        
        if (! $invoices->hasAlmaExportFiles() ) {
            echo "No Alma export files to process! \n";
            continue;
        }
    
        $debug = false;
        
        if (strstr(realpath('./'), 'test') || $config->get('debug') == true) {
            $debug = true;
            echo "Debugging! \n";
        }
        
        echo "Converting alma export files . . . ";
    
        $invoices->transformAndWriteExportFiles();
        
        echo "done!\n";
    
        if ($config->get('sftp_server') != "" && $debug == false) {
            
            echo "FTP-ing processed files to CFS . . . ";
            
            $success = $invoices->sendOutputFileToSftpServer();
            
            echo "done!\n";
        }
    
        if ($config->get('http_url') != "" && $debug == false) {
            
            echo "HTTP POST-ing processed files to CFS . . . ";
            
            $success = $invoices->sendOutputFileToHttpServer();
            
            echo "done!\n";
        }
        
        if ($config->get('smtp_server') != "") {
            
            echo "Emailing processed files . . . ";
            
            if ($debug == true) {
                if ($action == 'email') {
                    $config->set('email_to', 'dwalker@calstate.edu');
                    $invoices->sendOutputToEmail();
                }
            } else {
                $invoices->sendOutputToEmail();
            }
            
            echo "done!\n";
        }
        
        if ($date != "" && $debug == true) {
            
            echo "Copying export file to output . . . ";
            
            $invoices->copyAlmaExportFilesToOutput();
            
            echo "done!\n";
        }
        
        if ($debug == true) {
            echo "done debugging! \n\n";
            continue;
        }
    
        echo "Archiving Alma and Output files . . . ";
    
        $invoices->archiveOutputFiles();
        $invoices->archiveAlmaExportFiles();
    
        echo "done!\n";
        echo "\n";
        
    } catch (Exception $e) {
        echo $e->getMessage();
        continue;
    }
}
