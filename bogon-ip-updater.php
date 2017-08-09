#!/usr/bin/env php
<?php
###
# bogon-ip-updater
# Bogon IP list updater
# Copyright (c) 2017 Volkan Kucukcakar
#
# bogon-ip-updater is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# bogon-ip-updater is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# This copyright notice and license must be retained in all files and derivative works.
###

class bogon_ip_updater {

	# Short name
	private static $app_name="bogon-ip-updater";
	# Version
	private static $app_version="2.0.0";
	# Description
	private static $app_description="Bogon IP list updater";
	# PID file
	private static $pid_file="/var/run/bogon-ip-updater.pid";
	# Spamhaus links for "spamhaus" keyword
	private static $spamhaus_links="https://www.spamhaus.org/drop/drop.txt https://www.spamhaus.org/drop/edrop.txt https://www.spamhaus.org/drop/dropv6.txt";
	# Team Cymru links for "cymru" keyword
	private static $cymru_links="http://www.team-cymru.org/Services/Bogons/fullbogons-ipv4.txt";
	
	# The following properties reflect command-line parameters also

	# Update
	public static $update=false;
	# Force update
	public static $force=false;
	# Trigger reload command (true|false)
	public static $reload=false;
	# Related service reload command
	public static $command="";
	# Timeout (Seconds)
	public static $timeout=30;
	# No certificate check (true|false)
	public static $nocert=false;
	# Output filename
	public static $output="";
	# "spamhaus", "cymru" keywords or custom IP list download URLs separated by space
	public static $sources="spamhaus cymru";
	# Display help
	public static $help=false;
	# Display version and license information
	public static $version=false;


	/*
	* Shutdown callback
	*/
	static function shutdown() {
		unlink(self::$pid_file);
	}// function

	/*
	* Custom error exit function that writes error string to STDERR and exits with 1
	*/
	static function error($error_str) {
		fwrite(STDERR, $error_str);
		exit(1);
	}// function

	/*
	* Display version and license information
	*/
	static function version() {
		echo self::$app_name." v".self::$app_version."\n"
			.self::$app_description."\n"
			."Copyright (c) 2017 Volkan Kucukcakar \n"
			."License GPLv2+: GNU GPL version 2 or later\n"
			." <https://www.gnu.org/licenses/gpl-2.0.html>\n"
			."Use option \"-h\" for help\n";
		exit;
	}// function

	/*
	* Display help
	*/
	static function help($long=false) {
		echo self::$app_name."\n".self::$app_description."\n";
		if ($long) {
			echo "Usage: ".self::$app_name.".php [OPTIONS]\n\n"
				."Available options:\n"
				." -u, --update *\n"
				."     Downloads bogon IP lists, merge by removing duplicates and write to output file\n"
				." -f, --force\n"
				."     Force update\n"
				." -r, --reload\n"
				."     Trigger reload command on list update\n"
				." -c <command>, --command=<command>\n"
				."     Set related service reload command\n"
				." -t <seconds>, --timeout=<seconds>\n"
				."     Set download timeout\n"
				." -n, --nocert\n"
				."     No certificate check\n"
				." -o <filename>, --output=<filename> *\n"
				."     Write IP list as a new raw text file (old file will be overwritten)\n"
				." -s <urls>, --sources=<urls>\n"
				."     Override download sources (\"spamhaus\", \"cymru\" keywords or space separated URLs)\n"
				." -v, --version\n"
				."     Display version and license information\n"
				." -h, --help\n"
				."     Display help\n"
				."\nExamples:\n"
				."\$ ".self::$app_name.".php -u -o \"/etc/bogon-ip-updater.txt\"\n"
				."\$ ".self::$app_name.".php -u --reload --command=\"myfirewall.sh -r\" --output=\"/etc/bogon-ip-updater.txt\"\n"
				."\$ ".self::$app_name.".php -u --output=\"/etc/bogon-ip-updater.txt\" --sources=\"spamhaus cymru http://example.com/iplist.txt\"\n"
				."\n";
			exit;
		} else {
			echo "Use option \"-h\" for help\n";
			exit (1);
		}
	}// function

	/*
	* Update IP addresses
	*/
	static function update() {
		// Check reload command
		if (self::$reload && ''==self::$command){
			self::error("Error: Reload requested but reload command is not set\n");
		}
		// Verify that output file does not containg anything other than ip addresses to prevent overwriting irrelevant files
		$old_list=(file_exists(self::$output)) ? @file(self::$output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
		if (false!==$old_list) {
			foreach ($old_list as $value) {
				list($x) = explode("/",$value);
				if (filter_var($x, FILTER_VALIDATE_IP) === false) {
					self::error("Error: Output file to be overwritten can only contain raw IP addresses\n");
				}
			}
		}
		// Replace keywords with real urls
		self::$sources=preg_replace('~(?<![^\s])spamhaus(?![^\s])~is', self::$spamhaus_links,self::$sources);
		self::$sources=preg_replace('~(?<![^\s])cymru(?![^\s])~is', self::$cymru_links,self::$sources);
		// Download ip list
		$options = array(
			'http' => array(
				'timeout' => self::$timeout
			),
			'ssl' => array(
				'verify_peer' => ! self::$nocert
			)
		);
		$context  = stream_context_create($options);
		$ip_list=array();
		foreach (preg_split('~\s+~',self::$sources) as $url) {
			echo "Downloading: ".$url."\n";
			$download_data=file_get_contents($url, false, $context);
			if (false!==$download_data) {
				// Parse ip list
				$raw_ip_list=preg_split('~\r?\n+~is',$download_data);
				$ip_add=array();
				foreach ($raw_ip_list as $value) {
					// remove comments if there any
					$ip=preg_replace('~[\s]*[;#].*$~','',trim($value));
					// validate ip after extracting mask
					list($x) = explode("/",$ip);
					if (!filter_var($x, FILTER_VALIDATE_IP) === false) {
						$ip_add[]=$ip;
					}
				}//foreach
				// Validate ip list
				if (count($ip_add)>0) {
					$ip_list=array_merge($ip_list, $ip_add);
				}else{
					self::error("Error: IP list downloaded is not valid.(".$url.")\n");
				}
			} else {
				self::error("Error: Download failed: ".$url."\n");
			}
		}
		// Remove duplicate IP addresses
		$ip_count_non_unique=count($ip_list);
		$ip_list=array_unique($ip_list);
		$ip_count=count($ip_list);
		if ($ip_count<$ip_count_non_unique){
			echo "Removed ".($ip_count_non_unique-$ip_count)." duplicate entries.\n";
		}
		// Convert IP list array to raw text data
		$ip_raw=implode("\n",$ip_list)."\n";
		// Calculate hash of ip list
		$ip_list_hash=sha1($ip_raw);
		// Check if ip list updated and service reload required (change detected)
		if ( (!self::$force) && (false!==$old_list) && (sha1(implode("\n",$old_list)."\n")==$ip_list_hash) ) {
			// IP list not updated
			echo "No changes detected, IP list is up to date.\n";
		} else {
			// Write output file
			if (false!==file_put_contents(self::$output,$ip_raw)) {
				echo $ip_count." total IP addresses/netmask found.\n";
				echo "Updated IP list.\n";
			} else {
				self::error("Error: Output file \"".self::$output."\" could not be written.\n");
			}
			// Reload related service
			if (self::$reload) {
				passthru(self::$command, $return_var);
				if ($return_var===0) {
				    echo "Reload command successfull.\n";
				} else {
				    self::error("Error: Reload command failed.\n");
				}
			}
		}// if
	}// function

	/*
	* Initial function to run
	*/
	static function run() {
		// Set error reporting to report all errors except E_NOTICE
		error_reporting(E_ALL ^ E_NOTICE);
		// Set script time limit just in case
		set_time_limit(900);
		// Set script memory limit just in case
		ini_set('memory_limit', '32M');
		// PHP version check
		if (version_compare(PHP_VERSION, '5.3.0', '<')) {
			self::error("Error: This application requires PHP 5.3.0 or later to run. PHP ".PHP_VERSION." found. Please update PHP-CLI.\n");
		}
		// Single instance check
		$pid=@file_get_contents(self::$pid_file);
		if (false!==$pid) {
			// Check if process is running using POSIX functions or checking for /proc/PID as a last resort
			$pid_running=(function_exists('posix_getpgid')) ? (false!==posix_getpgid($pid)) : file_exists('/proc/'.$pid);
			if ($pid_running) {
				self::error("Error: Another instance of script is already running. PID:".$pid."\n");
			} else {
				//process is not really running, delete pid file
				unlink(self::$pid_file);
			}
		}
		file_put_contents(self::$pid_file,getmypid());
		register_shutdown_function(array(__CLASS__, 'shutdown'));
		// Load "openssl" required for file_get_contents() from "https"
		if ( (!extension_loaded('openssl'))&&(function_exists('dl')) ) {
			dl('openssl.so');
		}
		// Check if allow_url_fopen enabled
		if ( 0==ini_get("allow_url_fopen") ) self::error("Error: 'allow_url_fopen' is not enabled in php.ini for php-cli.\n");
		// Check if openssl loaded
		if (!extension_loaded('openssl')) self::error("Error: 'openssl' extension is not loaded php.ini for php-cli and cannot be loaded by dl().\n");
		// Parse command line arguments and gets options
		$options=getopt("ufrc:t:no:s:vh", array("update", "force", "reload", "command:", "timeout:", "nocert", "output:", "sources:", "version", "help"));
		$stl=array("u"=>"update", "f"=>"force", "r"=>"reload", "c"=>"command", "t"=>"timeout", "n"=>"nocert", "o"=>"output", "s"=>"sources", "v"=>"version", "h"=>"help");
		foreach ($options as $key=>$value) {
			if (1==strlen($key)) {
				// Translate short command line options to long ones
				self::${$stl[$key]}=(is_string($value)) ? $value : true;
			} else {
				// Set class variable using option value or true if option do not accept a value
				self::${$key}=(is_string($value)) ? $value : true;
			}
		}
		// Keep timeout value in a meaningful range
		self::$timeout=(self::$timeout<=300) ? ((self::$timeout>5) ? self::$timeout : 5) : 300;
		// Display version and license information
		if (self::$version)
			self::version();
		// Display long help & usage (on demand)
		if (self::$help)
			self::help(true);
		// Display short help (on error)
		if (! self::$update || ''==self::$output)
			self::help(false);
		// Update IP addresses
		self::update();
	}// function

}// class

bogon_ip_updater::run();
