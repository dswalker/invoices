<?php

// This file allows for setting required PeopleSoft fields where the data
// is not stored in Alma and where those fields are static.

return [

// set campus identifier for file name
'campus' => "example",

// Alma XML file path
'alma_export_filepath' => __DIR__ . "/alma_exports",

// Output file location
'output_filepath' => __DIR__ . "/output",

// Error log file location
'error_log_filepath' => __DIR__ . "/logs",

// default setting for accounting date is the date when the file is converted
'accounting_date' => time(),
'accounting_format' => 'm/d/Y',
'invoice_date_format' => 'm/d/Y',

// converted file name
'file_name' => 'alma-{date}.csv',

// PeopleSoft requires a Ship To Location. For San Marcos, this is a static value
'ship_to_location' => "EX101",

// Specify field used for sales tax. Use either vat or overhead.
'tax_field' => "",

// This determines the layout of the output file. A campus would either select interface
// for the AP Voucher Interface layout, or upload, for the AP Voucher Upload layout.
// Specify as either "interface" or "upload"
'peoplesoft_voucher_layout' => "interface",

// If using abbreviated upload format, set to true
'voucher_upload_abbr' => false,

// Specify unit of measure. Examples include EA used by Long Beach and UNT by San Jose
'unit_of_measure' => "EA",

// Are multiple business units being handled by the library? set to true if so
'multiple_business_units' => false,

// Populate business unit (GL_Unit) field in voucher upload/interface file? set to false if not required to do so
'populate_gl_unit' => false,

// SFTP settings
// set sftp_server to the server where you want to send the file
// sftp_path is relative pass on FTP server where to put files

'sftp_server' => '', // 'csslu614.dc.calstate.edu',
'sftp_username' => '',
'sftp_password' => '',
'sftp_path' => '',

// HTTP server

'http_url' => '',
'http_username' => '',
'http_password' => '',

// Email settings
// set smtp_server to your outgoing email server in order to send a copy of the
// output file to the email below

'smtp_server' => 'smtp.example.com:25',
'email_from' => 'library@example.com',
'email_to' => 'someone@example.com',

// Handing of shipping and discounts in invoice lines
'discount_in_invoice_line' => false,
'shipment_in_invoice_line' => false,

// Within the single quotes, insert the single character business unit identifier used in
// the External ID field of Alma fund. within the fouble quotes, insert the corresponding
// business unit code from PeopleSoft. For example, at San Marcos, 'C' is used in the
// External ID field to represent the PeopleSoft business unit code of "SMCMP". Add as
// many additional lines as necessary.

'business_units' => [],
];
