<?php
include 'render.php';

render_header();

render_districtlist();

//check if debug is set
if (isset($_GET['debug'])){
	$debug = true;
} else {
	$debug = false;
}

//if district and event are null, you are on the home page
if (!isset($_GET['d']) && !isset($_GET['e']) && !isset($_GET['t'])) {
	render_homepage();
} elseif (isset($_GET['d'])) {
	render_rankings($_GET['d'], $debug);
} elseif (isset($_GET['e'])) {
	render_event($_GET['e'], $debug);
} elseif (isset($_GET['t'])) {
	render_team($_GET['t'], $debug);
}

render_footer();
?>
