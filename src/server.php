<?php	// $Format:SambaDAV: commit %h @ %cd$
/*
 * Copyright (C) 2013  Bokxing IT, http://www.bokxing-it.nl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Project page: <https://github.com/bokxing-it/sambadav/>
 *
 */

ini_set('display_errors', 0);

// Source this config file to get at the $enable_webfolders variable;
// if this variable is not unambiguously true, then bail out immediately:
include_once('config/share_userhomes.inc.php');

if (!isset($enable_webfolders) || $enable_webfolders !== true) {
	header('HTTP/1.1 404 Not Found');
	die();
}

use Sabre\DAV;
use Sabre\HTTP;

require_once 'lib/SabreDAV/vendor/autoload.php';
require_once 'include/class.ldap.php';
require_once 'include/class.smbdirectory.php';
require_once 'include/class.smbfile.php';
require_once 'include/function.cache.php';
require_once 'include/plugin.msproperties.php';
require_once 'include/function.loginform.php';

// The base URI is the SambaDAV root dir location on the server.
// Check if the request was rewritten:
$baseuri = (strpos($_SERVER['REQUEST_URI'], SERVER_BASEDIR) === 0) ? SERVER_BASEDIR : '/';

// If ANONYMOUS_ONLY is set to true in the config, don't require credentials;
// also the 'logout' action makes no sense for an anonymous server:
if (ANONYMOUS_ONLY)
{
	$user = false;
	$pass = false;
}
else {
	$auth = new HTTP\BasicAuth();
	$auth->setRealm('Web Folders');

	// If no basic auth creds set, but the variables "user" and "pass" were
	// posted to the page (e.g. from a/the login form), substitute those:
	if (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW'])) {
		if (isset($_POST) && isset($_POST['user']) && isset($_POST['pass'])) {
			$_SERVER['PHP_AUTH_USER'] = $_POST['user'];
			$_SERVER['PHP_AUTH_PW'] = $_POST['pass'];

			// HACK: dynamically change the request method to GET, because
			// otherwise SambaDAV will throw an exception because there is
			// no POST handler installed. This change causes SabreDAV to
			// process this request just like any other basic auth login:
			$_SERVER['REQUEST_METHOD'] = 'GET';
		}
	}

	list($user, $pass) = $auth->getUserPass();
	$user = ($user === null || $user === false || $user === '') ? false : $user;
	$pass = ($pass === null || $pass === false || $pass === '') ? false : $pass;

	// If you're tagged with 'logout' but you're not passing a username/pass, redirect to plain index:
	if (isset($_GET['logout']) && ($user === false || $pass === false)) {
		header("Location: $baseuri");
		return;
	}
	// Otherwise, if you're tagged with 'logout', make sure the authentication is refused,
	// to make the browser flush its cache:
	if (isset($_GET['logout']) || (ANONYMOUS_ALLOW === false && ($user === false || $pass === false))) {
		$auth->requireLogin();
		print_login_form($baseuri);
		return;
	}
	// If we allow anonymous logins, and we did not get all creds, skip authorization:
	if (ANONYMOUS_ALLOW && ($user === false || $pass === false))
	{
		$user = false;
		$pass = false;
	}
	else {
		// Strip possible domain part off the username:
		// WinXP likes to pass this sometimes:
		if (($pos = strpos($user, '\\')) !== false) {
			$user = substr($user, $pos + 1);
		}
		// Check LDAP for group membership:
		// $ldap_groups is sourced from config/config.inc.php:
		if (LDAP_AUTH) {
			$ldap = new \SambaDAV\LDAP();

			if (!isset($share_userhome_ldap)) {
				$share_userhome_ldap = false;
			}
			if ($ldap->verify($user, $pass, $ldap_groups, $share_userhome_ldap) === false) {
				sleep(2);
				$auth->requireLogin();
				print_login_form($baseuri);
				return;
			}
		}
	}
}
// Clean stale cache files every once in a blue moon:
// Time-based throttling to prevent too-frequent rechecking;
// Random-based throttling to prevent contention in the "available" second:
if ((time() % 5) == 0 && rand(0, 9) == 8) {
	cache_clean();
}
// No server, share and path known in root dir:
$rootDir = new \SambaDAV\Directory(false, false, false, false, 'D', $user, $pass);

// Pass LDAP userhome dir if available:
if (isset($ldap) && $ldap->userhome !== false) {
	$rootDir->setUserhome($ldap->userhome);
}
// Otherwise the userhome server if defined:
else if ($user !== false && isset($share_userhomes) && $share_userhomes) {
	$rootDir->setUserhome(sprintf('\\\\%s\%s', $share_userhomes, $user));
}
// The object tree needs in turn to be passed to the server class
$server = new DAV\Server($rootDir);

// We're required to set the base uri. Check if the request was rewritten:
$server->setBaseUri($baseuri);

// Also make sure there is a 'data' directory, writable by the server. This directory is used to store information about locks
$lockBackend = new DAV\Locks\Backend\File('data/locks.dat');
$lockPlugin = new DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

// Browser plugin, for plain directory listings:
$plugin = new \Sabre\DAV\Browser\Plugin();
$server->addPlugin($plugin);

// Content-type plugin:
$plugin = new \Sabre\DAV\Browser\GuessContentType();

// Define some custom types, the default list is really short:
$plugin->extensionMap['gz'] = 'application/x-gzip';
$plugin->extensionMap['bmp'] = 'image/bmp';
$plugin->extensionMap['bz2'] = 'application/x-bzip2';
$plugin->extensionMap['doc'] = 'application/msword';
$plugin->extensionMap['dot'] = 'application/msword';
$plugin->extensionMap['dvi'] = 'application/x-dvi';
$plugin->extensionMap['eps'] = 'application/postscript';
$plugin->extensionMap['htm'] = 'text/html';
$plugin->extensionMap['pdf'] = 'application/pdf';
$plugin->extensionMap['pot'] = 'application/vnd.ms-powerpoint';
$plugin->extensionMap['ppt'] = 'application/vnd.ms-powerpoint';
$plugin->extensionMap['mp3'] = 'audio/mpeg';
$plugin->extensionMap['tgz'] = 'application/x-compressed';
$plugin->extensionMap['tif'] = 'image/tiff';
$plugin->extensionMap['wav'] = 'audio/wav';
$plugin->extensionMap['xla'] = 'application/excel';
$plugin->extensionMap['xlc'] = 'application/excel';
$plugin->extensionMap['xls'] = 'application/excel';
$plugin->extensionMap['xlt'] = 'application/excel';
$plugin->extensionMap['xlw'] = 'application/excel';
$plugin->extensionMap['zip'] = 'application/zip';
$plugin->extensionMap['docx'] = 'application/msword';
$plugin->extensionMap['html'] = 'text/html';
$plugin->extensionMap['mpeg'] = 'video/mpeg';

$server->addPlugin($plugin);

// Custom plugin to add the nonstandard DAV:ishidden and DAV:isreadonly flags:
$server->addPlugin(new \SambaDAV\MSPropertiesPlugin());

// And off we go!
$server->exec();
