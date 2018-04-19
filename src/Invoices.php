<?php

namespace Finance;

use phpseclib\Net\SFTP;

/**
 * Invoice file processor
 *
 * @author Ian Chan <ichan@csusm.edu>
 * @author David Walker <dwalker@calstate.edu>
 */
class Invoices
{
    /**
     * @var Config
     */
    protected $config;
    
    /**
     * Path to output filename
     * @var string
     */
    protected $output_file = array();
    
    /**
     * Date of invoice in Y-m-d format
     * @var string
     */
    protected $date;
    
    /**
     * New Invoice object
     * 
     * @param Config $config
     * @param string          date of invoice in Y-m-d format
     */
    public function __construct(Config $config, $date = "")
    {
        $this->config = $config;
        $this->date = $date;
    }

    /**
     * Transform and write current/supplied list of Alma XML files to CSV
     *
     * @param File[] $export_files  [optional] array of Files, otherwise taken from export directory
     * @return bool                 true if files processed, false otherwise
     */
    public function transformAndWriteExportFiles(array $export_files = array())
    {
        if (count($export_files) == 0) {
            $export_files = $this->getAlmaExportFiles();
        }
        
        $output = $this->transformExportFiles($export_files);
        return $this->writeOutput($output);
    }
    
    /**
     * Transform Alma export files into an array for CSV output
     * 
     * @param File[] $export_files  [optional] array of Files, otherwise taken from export directory
     * @return array                file output as associative array
     */
    public function transformExportFiles(array $export_files = array())
    {
        // none supplied, get from export
        
        if (count($export_files) == 0) {
            $export_files = $this->getAlmaExportFiles();
        }
        
        // check again: no files found, do nothing
        
        if (count($export_files) == 0) {
            return array();
        }
        
        $output = []; // file output

        foreach ($export_files as $export_file) {

            $xml_invoice_data = simplexml_load_file($export_file->path);
            
            if ($xml_invoice_data === false ) {
                continue;
            }

            foreach ($xml_invoice_data->invoice_list->invoice as $invoice_xml) {
                
                // skip certain payment types
                
                $skip_types = $this->config->get('skip_payment_methods', false, ['CREDITCARD']);
                
                if (in_array((string) $invoice_xml->payment_method, $skip_types)) {
                    continue;
                }

                // process invoice
                
                $invoice = new Invoice($invoice_xml, $this->config, $export_file->timestamp);
                
                // convert to specified format

                $lines = array();
                
                if ($this->config->get('peoplesoft_voucher_layout') == "interface") {
                    $lines = $invoice->toInterfaceFormat();
                } elseif ($this->config->get('peoplesoft_voucher_layout') == "upload") {
                    $lines = $invoice->toUploadFormat();
                }
                
                // file name
                
                $file = $this->config->get('file_name', true);
                $file = preg_replace('/\{date\}/', $export_file->date, $file); 
                
                // add a business unit name, if necessary
                
                $business_unit_id = $invoice->getBusinessUnitId();
                
                if ($business_unit_id != null) {
                    $file_parts = explode('.', $file);
                    $file_ext = array_pop($file_parts);
                    $file = implode('.', $file_parts) . '-' . $business_unit_id . '.' . $file_ext;
                }
                
                // full path
                $file = $this->config->get('output_filepath') . '/' . $file;
                
                foreach ($lines as $line) {
                    $output[$file][] = $line;
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Write array output to CSV file
     * 
     * @param array $output
     * @throws \Exception
     */
    protected function writeOutput(array $output)
    {
        foreach ($output as $file => $lines) {
        
            $fp = fopen($file, 'a');
            
            if ($fp === false) {
                throw new \Exception('Could not write to file.');
            }
            
            foreach ($lines as $line) {
                fputcsv($fp, $line);
            }
            
            fclose($fp);
        }
        
        return true;
    }

    /**
     * HTTP POST ouput file to the configured HTTP server
     *
     * @throws \Exception
     * @return bool  true if file transfered, false otherwise
     */
    public function sendOutputFileToHttpServer()
    {
        if (count($this->getOutputFiles()) == 0) {
            return false;
        }
        
        $server = $this->config->get('http_url', true);
        $username = $this->config->get('http_username', true);
        $password = $this->config->get('http_password', true);
        
        // send any output files
        
        foreach ($this->getOutputFiles() as $output_file) {

            $infile = realpath($output_file);
            $infile_handle = fopen($infile, 'r');
            $infile_size = filesize($infile);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $server);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/csv']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_UPLOAD, 1);
            curl_setopt($ch, CURLOPT_INFILE, $infile_handle);
            curl_setopt($ch, CURLOPT_INFILESIZE, $infile_size);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $success = curl_exec($ch);
            
            fclose($infile_handle); // need to close the handle
            
            if ($success === false) {
                throw new \Exception("Could not post file to HTTP server");
            }
        }
        
        return true;
    }
    
    /**
     * FTP ouput file to the configured server
     * 
     * @throws \Exception
     * @return bool  true if file transfered, false otherwise
     */
    public function sendOutputFileToSftpServer()
    {
        if (count($this->getOutputFiles()) == 0) {
            return false;
        }
        
        $server = $this->config->get('sftp_server', true);
        $username = $this->config->get('sftp_username', true);
        $password = $this->config->get('sftp_password', true);
        $remote_path = $this->config->get('sftp_path');
        
        // connect
        
        $sftp = new SFTP($server);
        
        if (!$sftp->login($username, $password)) {
            throw new \Exception('SFTP login failed');
        }
        
        // send any output files
                
        foreach ($this->getOutputFiles() as $output_file) {
        
            // get just the filename and extract data
            $parts = explode('/', $output_file);
            $filename = array_pop($parts);
            $data = file_get_contents($output_file);
            
            $success = $sftp->put($remote_path . $filename, $data);
            
            if ($success == false) {
                throw new \Exception("Could not put file '$filename' on SFTP server");
            }
        }
        
        return true;
    }
    
    /**
     * Send the invoices to the configured email address
     * 
     * Currently supports SMTP
     *
     * @return boolean  true on sucess, false otherwise
     */
    public function sendOutputToEmail()
    {
        if (count($this->getOutputFiles()) == 0) {
            return false;
        }
        
        // set up smtp connection
        
        $host = $this->config->get("smtp_server");
        $port = 25;
        
        if (strstr($host, ':')) {
            $parts = explode(':', $host);
            $port = array_pop($parts);
            $host = implode(':', $parts);
        }
        
        $transport = \Swift_SmtpTransport::newInstance($host, $port);
        $mailer = \Swift_Mailer::newInstance($transport);
        
        // create message
        
        $from = $this->config->get('email_from');
        $to = $this->config->get('email_to');
        $to = explode(';', $to);
        $subject = "Invoices processed " . date("Y-m-d", time());        
        $body = "Invoices processed " . date("D g:i a, M j, Y", time());
       
        $message = \Swift_Message::newInstance($subject)
            ->setFrom($from)
            ->setTo($to)
            ->setBody($body);
        
        // add attachments
        
        foreach ($this->getOutputFiles() as $output_file) {
            $attachment = \Swift_Attachment::fromPath($output_file);
            $message->attach($attachment);
        }
        
        // send
        $numSent = $mailer->send($message);
        
        if ($numSent == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Move any output files to archive directory
     * 
     * @throws \Exception if cannot archive
     */
    public function archiveOutputFiles()
    {
        $this->archiveFiles($this->config->get('output_filepath'));
    }

    /**
     * Move any Alma export files to archive directory
     * 
     * @throws \Exception if cannot archive
     */
    public function archiveAlmaExportFiles()
    {
        $this->archiveFiles($this->config->get('alma_export_filepath'));
    }
    
    /**
     * Whether there are any export files
     * 
     * @return boolean
     */
    public function hasAlmaExportFiles()
    {
        if (count($this->getAlmaExportFiles()) == 0) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Copy Alma export files to output directory
     * 
     * @return bool  on success or not
     */
    public function copyAlmaExportFilesToOutput()
    {
        foreach ($this->getAlmaExportFiles() as $file) {
            return copy($file->path, $this->config->get('output_filepath') . '/' . $file->filename);
        }
    }
    
    /**
     * Copy Alma export files to output directory
     *
     * @return bool  on success or not
     */
    public function testAlmaExportFiles()
    {
        // switch output to tmp dir for this action
        
        $output_filepath = $this->config->get('output_filepath');
        $tmp_filepath = $this->config->get('tmp_filepath', false, 'tmp');
        $this->config->set('output_filepath', $tmp_filepath);
        
        // create tmp dir, if not exists
        
        if (! file_exists($tmp_filepath)) {
            throw new \Exception("No directory as '$tmp_filepath'");
        }        
        
        $this->deleteFiles("$tmp_filepath/*");
        
        // change configurations for dates and output file names
        
        $this->config->set('file_name', 'test-' . $this->config->get('file_name', true));
        
        // grab all archived export files and process them
        
        $files = $this->getAlmaExportFiles(true);
        $this->transformAndWriteExportFiles($files);
        
        // compare files in both areas
        
        foreach ($this->findFiles($tmp_filepath) as $test_file) {
            $path = explode('/', $test_file);
            $filename = array_pop($path);
            $filename = str_replace('test-', '', $filename);
            $output_file = $output_filepath . '/archive/' . $filename;
            
            if (file_exists($output_file)) {
                echo "$filename\n";
                echo md5_file($test_file) . "\n";
                echo md5_file($output_file) . "\n\n";
            }
        }
    }
    
    /**
     * Get list of Alma export files
     * 
     * @param bool $all_archived  [optional] set to true to get all archived files
     * @return File[]
     */
    public function getAlmaExportFiles($all_archived = false)
    {
        $files = [];
        $alma_export_filepath = $this->config->get('alma_export_filepath');
        
        if ($this->date != "" || $all_archived == true) {
            $alma_export_filepath .= '/archive';
        }
        
        foreach ($this->findFiles($alma_export_filepath) as $alma_file) {
            $file = new File($alma_file);
            
            // if there is a date set, only grab the files with that date
            if ($this->date != "" && $file->date != $this->date) {
                continue;
            }
            $files[] = $file;
        }
        
        return $files;
    }
    
    /**
     * Get list of output files
     * 
     * @return array
     */
    protected function getOutputFiles()
    {
        $output_filepath = $this->config->get('output_filepath');
        return $this->findFiles($output_filepath);
    }
    
    /**
     * Archive files in a particular directory
     *
     * @param string $filepath  location of files to archive
     * @throws \Exception if cannot archive
     */
    protected function archiveFiles($path)
    {
        $files = $this->findFiles($path);
        
        foreach ($files as $filepath) {
            
            // split out the file name and construct archive path
            $parts = explode('/', $filepath);
            $filename = array_pop($parts);
            $archive_path = implode('/', $parts) . "/archive/$filename";
            
            // move the file
            $success = rename($filepath, $archive_path);
            
            if ($success == false) {
                throw new \Exception("Could not archive file $filename");
            }
        }
    }
    
    /**
     * Find files for processing
     *
     * @param string $path  directory location
     * @return array
     */
    protected function findFiles($path)
    {
        $return = array();
        $files = glob($path . '/*.*'); // get all files at this location
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $return[] = $file;
            }
        }
        
        return $return;
    }
    
    /**
     * Delete all the files in a directory
     * 
     * @param string $path
     */
    public function deleteFiles($path)
    {
        $files = glob($path);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Invoice date
     * 
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }
    
    /**
     * Invoice date in Y-m-d format
     * 
     * @param string $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }
}
