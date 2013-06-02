<?php
/**
 * Runs unit tests.
 *
 * @package Elgg
 * @subpackage Test
 */

require_once(dirname( __FILE__ ) . '/../start.php');

$vendor_path = "$CONFIG->path/vendors/simpletest";
$test_path = "$CONFIG->path/engine/tests";

require_once("$vendor_path/unit_tester.php");
require_once("$vendor_path/mock_objects.php");
require_once("$vendor_path/reporter.php");
require_once("$test_path/ElggCoreUnitTest.php");

//don't expect admin session for CLI
if (!TextReporter::inCli()) {
	admin_gatekeeper();
} else {
	$admin = array_shift(elgg_get_admins(array('limit' => 1)));
	if (!login($admin)) {
		echo "Failed to login as administrator.";
		exit(1);
	}
	$CONFIG->debug = 'NOTICE';
}

// turn off system log
elgg_unregister_event_handler('all', 'all', 'system_log_listener');
elgg_unregister_event_handler('log', 'systemlog', 'system_log_default_logger');

// turn off notifications
$notifications = _elgg_services()->notifications;
$events = $notifications->getEvents();
foreach ($events as $type => $subtypes) {
	foreach ($subtypes as $subtype => $actions) {
		$notifications->unregisterEvent($type, $subtype);
	}
}

// Disable maximum execution time.
// Tests take a while...
set_time_limit(0);

$suite = new TestSuite('Elgg Core Unit Tests');

// emit a hook to pull in all tests
$test_files = elgg_trigger_plugin_hook('unit_test', 'system', null, array());
foreach ($test_files as $file) {
	$suite->addFile($file);
}

// Only run tests in debug mode.
if (!isset($CONFIG->debug)) {
	exit ('The site must be in debug mode to run unit tests.');
}

if (TextReporter::inCli()) {
	// In CLI error codes are returned: 0 is success
	$mt = microtime(true);
	$reporter = new TextReporter();
	$result = $suite->Run($reporter) ? 0 : 1 ;
	echo sprintf("Time: %.2f seconds, Memory: %.2fMb\n", 
		microtime(true)-$mt, 
		memory_get_peak_usage() / 1048576. // in megabytes
	);
	exit($result);
}

$old = elgg_set_ignore_access(TRUE);
$suite->Run(new HtmlReporter('utf-8'));
elgg_set_ignore_access($old);
