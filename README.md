# bogon-ip-updater

Bogon IP list updater

* Downloads bogon IP lists from sources and merge by removing duplicates
* IP address and list validation just in case
* Support for any daemon or firewall. Raw list to support iptables, ipset or any custom firewall script.
* Updates IP list and reloads the related service

## Requirements

* PHP-CLI with openssl extension

## Installation

* Install PHP-CLI with openssl extension if not installed (OS dependent)

* Install bogon-ip-updater.php to an appropriate location and give execute permission

	$ cd /usr/local/src/

	$ git clone https://github.com/vkucukcakar/bogon-ip-updater.git

	$ cp bogon-ip-updater/bogon-ip-updater.php /usr/local/bin/
	
* Give execute permission if not cloned from github

	$ chmod +x /usr/local/bin/bogon-ip-updater.php
	

## Usage

Usage: bogon-ip-updater.php [OPTIONS]

Available options:

-u, --update                      *: Downloads bogon IP lists, merge by removing duplicates and write to output file

-f, --force                        : Force update

-r, --reload                       : Trigger reload command on list update

-c '<command>', --command=<command>  : Set related service reload command

-t <seconds>, --timeout=<seconds>  : Set download timeout

-n, --nocert                       : No certificate check

-o <filename>, --output=<filename>*: Write IP list as a new raw text file (old file will be overwritten)

-s <urls>, --sources=<urls>        : Override download sources ("spamhaus", "cymru" keywords or space separated URLs)

-v, --version                      : Display version and license information

-h, --help                         : Display usage

 
## Examples

	$ bogon-ip-updater.php -u -o "/etc/bogon-ip-updater.txt"

	$ bogon-ip-updater.php -u --reload --command="myfirewall.sh -r" --output="/etc/bogon-ip-updater.txt"

	$ bogon-ip-updater.php -u --output="/etc/bogon-ip-updater.txt" --sources="spamhaus cymru http://example.com/iplist.txt"
	
## Caveats

* Consider using IP sets (IPSET) if you are planning to use the ip list with iptables.
* On Virtuozzo/OpenVZ servers, IPSET may not work on and maximum allowed number of iptables rules may already be limited.
