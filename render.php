<?php

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

include 'ranking.php';

function render_header(){

	//2020 fucked everything up in chs, check if you're viewing chs page and if yes redirect to pchild sheet
	if ($_GET["d"] == "chs") {
		header("Location: https://docs.google.com/spreadsheets/d/1gGekBB92K-NU-nrTIQ2Gt3FhzLvs0LguhGsg8bORkI4/edit?usp=");
		die();
	}

?>



<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<!-- Global Site Tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=UA-96149097-1"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'UA-96149097-1');
</script>
<style type="text/css">
table, th, td {
border: 1px solid black;
border-spacing: 0;
}
a:link {
    color: #202020;
}

a:visited {
    color: #202020;
}

a:hover {
    color: #404040;
}

body {
    background-color: #888888;
}
html *
{
   font-family: Verdana;
}
</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>FRCLocks.com</title>
</head>
<body>
<center>
<?php
}

function render_homepage() {
	//echo '<br/><i>Now more official than the official FRC rankings!™</i><br/>';
	echo '<h3>Select a district.</h3>';
	//echo '<img src="/memes/thisisfine.png">';
}

function render_rankings($d, $debug){
	//check if dcmp is happening, if so just use generic rankings page with no locks
	//currently lazy and not checking if dcmp is happening I'm just using an ini
	$dcmp = parse_ini_file("configs/dcmp.ini");
	
	if ($dcmp[$d] == 2) {
		render_cmp_locks();
		return;
	}
	
	$ranking = ranking($d); //generate rankings and locks and shit
	file_put_contents("cache/rankings-".$d.".cache", serialize($ranking)); //cache rankings to file for other use
	chmod("cache/rankings-".$d.".cache", 0777);
	
	if ($dcmp[$d] == 1) {
		render_dcmp_rankings($ranking);
		return;
	}
	echo '<h2>'.$ranking['events'][0]['district']['display_name'].' District</h2>'
	?>
	<table align='center' width="350" style="table-layout: fixed;">
		<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
			<td>
				Statistic
			</td>
			<td style='width:60px'>
				Value
			</td>
		</tr>
		<!--
		<tr bgcolor='FFD966'>
			<td>
				Current Point Cutoff
			</td>
			<td align='right'>
				<?php 
				
				foreach ($ranking['rankings'] as $rank) {
					if (isset($rank['raw_lock_percent'])) {
						//echo ceil((($rank['inflated_points_total']*100)/$rank['raw_lock_percent']));
						break;
					}
				}
				
				
				?>
			</td>
		</tr>
		//-->
		<tr bgcolor='FFD966'>
			<td>
				Points Remaining in the District
			</td>
			<td align='right'>
				<?php echo $ranking['points_remaining'];?>
			</td>
		</tr>
		<tr bgcolor='FFD966'>
			<td>
				Available District Champs Spots
			</td>
			<td align='right'>
				<?php echo $ranking['dcmp_slots'];?>
			</td>
		</tr>
	</table>
	<h2>District Events</h2>
	<table align='center' width="700" style="table-layout: fixed;">
		<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
			<td>
				Event
			</td>
			<td style='width:130px'>
				Status
			</td>
			<td style='width:90px'>
				# Teams
			</td>
			<td style='width:125px'>
				Pts Available
			</td>
		</tr>
		<?php
		foreach ($ranking['events'] as $event) {
			echo "<tr bgcolor='".$event['color']."'>";
			
			echo "<td>";
			echo "<a href='https://www.thebluealliance.com/event/".$event['key']."'>".$event['name']."</a>";
			echo "</td>";
			
			echo "<td align='center'>";
			switch ($event['progress']) {
				case 0:
					echo 'Pre-Event';
					break;
				case 1:
					echo 'Qualifications';
					break;
				case 2:
					echo 'Selections';
					break;
				case 3:
					echo 'Quarters';
					break;
				case 4:
					echo 'Semis';
					break;
				case 5:
					echo 'Finals';
					break;
				case 6:
					echo 'Awards';
					break;
				case 7:
					echo 'Complete';
					break;
			}
			echo "</td>";
			
			echo "<td align='right'>";
			echo count($event['teams']);
			echo "</td>";
			
			echo "<td align='right'>";
			echo "<a href='index.php?e=".$event['key']."'>".$event['points_remaining']['total']."</a>";
			echo "</td>";
			
			echo "</tr>";
		}
		?>
	</table>
	<h2>District Rankings</h2>
	<img src="key.png" alt="key"/>
	<br/>
	<br/>
	<table align='center' width="700" style="table-layout: fixed;">
		<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
			<td>
				Rank
			</td>
			<td>
				Team
			</td>
			<td>
				Event 1
			</td>
			<td>
				Event 2
			</td>
			<td>
				Age Bonus
			</td>
			<td>
				Total
			</td>
			<td>
				Locked?
			</td>
		</tr>
	<?php
	
	foreach ($ranking['rankings'] as $team) {
		echo "<tr bgcolor='".$team['color']."'>";
		
		echo "<td align='right'>";
		echo $team['rank'];
		echo "</td>";
		
		echo "<td align='center'>";
		echo "<b><a href='https://www.thebluealliance.com/team/".$team['team_number']."' target='_blank'>".$team['team_key']."</a></b>";
		echo "</td>";

		echo "<td align='right'>";
		if (isset($team['event_points']['0']['total'])) {
			echo $team['event_points']['0']['total'];
		} else {
			echo '0';
		}
		echo "</td>";
		
		echo "<td align='right'>";
		if (isset($team['event_points']['1']['total'])) {
			echo $team['event_points']['1']['total'];
		} else {
			echo '0';
		}
		echo "</td>";
		
		echo "<td align='right'>";
		echo $team['rookie_bonus'];
		echo "</td>";
		
		echo "<td align='right'>";
		echo $team['point_total'];
		echo "</td>";
		
		echo "<td align='right'>";
		
		if (is_numeric($team['lock_percent'])) {
			echo "<a href='index.php?t=".$team['team_key']."'>";
			
			//if ($team['lock_percent'] == 100){
			//	echo "Yes";
			//} else {
			//	echo "No";
			//}
			
			echo $team['lock_percent']."%";
			echo "</a>";
		} else {
			echo $team['lock_percent'];
		}
		echo "</td>";
		
		echo "</tr>";
	}
	 echo "</table>";
}

function render_event($e, $debug){
$event = tba_get("https://www.thebluealliance.com/api/v3/event/".$e."/simple");
//print_r($event['district']['abbreviation']);
echo "<h2>".$event['name']."</h2>";
$district = unserialize(file_get_contents("cache/rankings-".$event['district']['abbreviation'].".cache"));
//print_r($district['events']);
foreach($district['events'] as $events){
	if($events['key'] == $e){
		$event = $events;
		break;
	}
}
echo "<h3>Points Remaining</h3>";
?>
<table align='center' width="700" style="table-layout: fixed;">
	<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
		<td>
			Description
		</td>
		<td style='width:60px'>
			Points
		</td>
	</tr>
	<tr bgcolor='FFD966'>
		<td>
			Qualification Points
		</td>
		<td align='right'>
			<?php
			echo $event["points_remaining"]["quals_unadjusted"];
			?>
		</td>
	</tr>
	<?php if($event["progress"] <=1) { ?>
	<tr bgcolor='FFD966'>
		<td>
			Subtract last place qual points * number of teams
		</td>
		<td align='right'>
			<?php
			echo "-".$event["points_remaining"]["last_place_points"]*$event["points_remaining"]["team_count"];
			?>
		</td>
	</tr>
	<?php }?>
	<tr bgcolor='FFD966'>
		<td>
			Alliance captain/alliance selection points
		</td>
		<td align='right'>
			<?php
			echo $event["points_remaining"]["selections"];
			?>
		</td>
	</tr>
	<tr bgcolor='FFD966'>
		<td>
			Elimination points
		</td>
		<td align='right'>
			<?php
			echo $event["points_remaining"]["elims"];
			?>
		</td>
	</tr>
	<tr bgcolor='FFD966'>
		<td>
			Award points (Don't include chairmans or unobtainable rookie awards)
		</td>
		<td align='right'>
			<?php
			echo $event["points_remaining"]["awards"];
			?>
		</td>
	</tr>
	<tr bgcolor='FFD966'>
		<td>
			<b>Total Points Remaining</b>
		</td>
		<td align='right'>
			<b>
			<?php
			echo $event["points_remaining"]["total"];
			?>
			</b>
		</td>
	</tr>
</table>
<?php
}

function render_team($t, $debug){
$team = tba_get("https://www.thebluealliance.com/api/v3/team/".$t."/districts");

if(empty($_GET['dcmp'])) {
	$district = unserialize(file_get_contents("cache/rankings-".$team[count($team)-1]['abbreviation'].".cache"));
} else {
	$district = unserialize(file_get_contents("cache/dcmp-".$team[count($team)-1]['abbreviation'].".cache"));
}

$points_remaining = $district['points_remaining'];
foreach($district['rankings'] as $teams){
	if($teams['team_key'] == $t){
		$team = $teams;
		break;
	}
}
?>
<h2><?php echo $team['team_key']?></h2>
<table align='center' width="450" style="table-layout: fixed;">
	<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
		<td>
			Description
		</td>
		<td style='width:75px'>
			Points
		</td>
	</tr>
	<?php
	if(empty($_GET['dcmp'])) {
	?>
	<tr bgcolor='FFD966'>
		<td>
			Points + 4pts per unplayed event (if any)
		</td>
		<td align='right'>
			<?php echo $team['inflated_points_total']; ?>
		</td>
	</tr>
	<?php
	}
	?>
	<tr bgcolor='FFD966'>
		<td>
			Points needed by teams below to tie <?php echo $team['team_key']?>
		</td>
		<td align='right'>
			<?php echo $team['points_to_tie']?>
		</td>
	</tr>
	<tr bgcolor='FFD966'>
		<td>
			Points remaining in the district
		</td>
		<td align='right'>
			<?php echo $points_remaining ?>
		</td>
	</tr>
	<tr bgcolor='FFD966'>
		<td>
			% of Lock Achieved (Points to tie/points remaining)
		</td>
		<td align='right'>
			<?php if(empty($_GET['dcmp'])) {echo $team['raw_lock_percent'];} else {echo $team['lock_percent'];}?>%
		</td>
	</tr>
</table>

<h2>Points to Tie</h2>
<table align='center' width="450" style="table-layout: fixed;">
	<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
		<td>
			Rank
		</td>
		<td>
			Team
		</td>
		<td>
			Adj. Pts
		</td>
		<td>
			Pts to Tie
		</td>
	</tr>
<?php
foreach ($team['ttt'] as $ttt) {
	echo "<tr bgcolor='FFD966'>";
	echo "<td align='right'>";
	echo $ttt['rank'];
	echo "</td>";
	echo "<td align='center'>";
	echo "<b><a href='https://www.thebluealliance.com/team/".$ttt['team_number']."' target='_blank'>".$ttt['team_key']."</a></b>";
	echo "</td>";
	echo "<td align='right'>";
	echo $ttt['inflated_points_total'];
	echo "</td>";
	echo "<td align='right'>";
	echo $ttt['points_to_tie'];
	echo "</td>";
	echo "</tr>";
}
?>
	<tr bgcolor='FFD966'>
		<td colspan="3">
			<b>Total</b>
		</td>
		<td align='right'>
			<b><?php echo $team['points_to_tie']?></b>
		</td>
	</tr>
</table>
	
<?php
//echo "<pre>";
//print_r($team);
//echo "</pre>";
	
}

function render_districtlist(){
	?>
	<h1>FRC District Championship Locks</h1>
	<a href='index.php?d=chs'>Chesapeake</a> <a href='index.php?d=fim'>Michigan</a> <a href='index.php?d=fin'>Indiana</a> <a href='index.php?d=isr'>Israel</a> <a href='index.php?d=fma'>Mid-Atlantic</a> <a href='index.php?d=fnc'>North Carolina</a> <a href='index.php?d=ne'>New England</a> <a href='index.php?d=ont'>Ontario</a> <a href='index.php?d=pch'>Peachtree</a> <a href='index.php?d=pnw'>Pacific Northwest</a> <a href='index.php?d=fit'>Texas &#x1F920</a><br/>
	<?php
}

function render_dcmp_rankings($ranking) {
	echo '<h2>'.$ranking['events'][0]['district']['display_name'].' District</h2>'
	?>
	<h2>District Rankings</h2>
	<i>This page is purely a rankings page, there are no lock calculations</i>
	<br/><br/>
	<table align='center' width="700" style="table-layout: fixed;">
		<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
			<td>
				Rank
			</td>
			<td>
				Team
			</td>
			<td>
				Event 1
			</td>
			<td>
				Event 2
			</td>
			<td>
				Age Bonus
			</td>
			<td>
				DCMP
			</td>
			<td>
				Total
			</td>
		</tr>
	<?php
	
	foreach ($ranking['rankings'] as $team) {
		echo "<tr bgcolor='FFD966'>";
		
		echo "<td align='right'>";
		echo $team['rank'];
		echo "</td>";
		
		echo "<td align='center'>";
		echo "<b><a href='https://www.thebluealliance.com/team/".$team['team_number']."' target='_blank'>".$team['team_key']."</a></b>";
		echo "</td>";

		echo "<td align='right'>";
		if (isset($team['event_points']['0']['total'])) {
			echo $team['event_points']['0']['total'];
		} else {
			echo '0';
		}
		echo "</td>";
		
		echo "<td align='right'>";
		if (isset($team['event_points']['1']['total'])) {
			echo $team['event_points']['1']['total'];
		} else {
			echo '0';
		}
		echo "</td>";
		
		echo "<td align='right'>";
		echo $team['rookie_bonus'];
		echo "</td>";
		
		echo "<td align='right'>";
		echo $team['point_total']-($team['event_points']['0']['total']+$team['event_points']['1']['total']+$team['rookie_bonus']);
		echo "</td>";
		
		echo "<td align='right'>";
		echo $team['point_total'];
		echo "</td>";
		
		echo "</tr>";
	}
	 echo "</table>";
}

function render_cmp_locks() {
	$ranking = json_decode(file_get_contents('https://frclocks.com/dcmp_ranking.php?d='.$_GET['d']),true);
	
	file_put_contents("cache/dcmp-".$_GET['d'].".cache", serialize($ranking)); //cache rankings to file for other use
	chmod("cache/rankings-".$d.".cache", 0777);
	
	//print_r($ranking);
	echo '<h2>'.$ranking['district_name'].' District</h2>';
		?>
	<h2>Championship Locks</h2>
	<i>Experimental feature, results may vary</i>
	<br/><br/>
	<table align='center' width="400" style="table-layout: fixed;">
		<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
			<td>
				Statistic
			</td>
			<td style='width:120px'>
				Value
			</td>
		</tr>
		<tr bgcolor='FFD966'>
			<td>
				Points Remaining
			</td>
			<td align='right'>
				<?php echo $ranking['points_remaining'];?>
			</td>
		</tr>
		<tr bgcolor='FFD966'>
			<td>
				Event State
			</td>
			<td align='right' >
				<?php
                switch ($ranking['dcmp_state']) {
                    case 0:
                        echo 'Pre-Event';
                        break;
                    case 1:
                        echo 'Qualifications';
                        break;
                    case 2:
                        echo 'Selections';
                        break;
                    case 3:
                        echo 'Quarters';
                        break;
                    case 4:
                        echo 'Semis';
                        break;
                    case 5:
                        echo 'Finals';
                        break;
                    case 6:
                        echo 'Awards';
                        break;
                    case 7:
                        echo 'Complete';
                        break;
                }
                ?>
			</td>
		</tr>
		<tr bgcolor='FFD966'>
			<td>
				Championship Slots
			</td>
			<td align='right'>
				<?php echo $ranking['cmp_slots'];?>
			</td>
		</tr>
		<tr bgcolor='FFD966'>
			<td>
				Dedicated Award Slots
			</td>
			<td align='right'>
				<?php echo $ranking['award_slots'];?>
			</td>
		</tr>
		<tr bgcolor='FFD966'>
			<td>
				Regional Winners/Wildcards
			</td>
			<td align='right'>
				<?php echo $ranking['ood_slots'];?>
			</td>
		</tr>
		<tr bgcolor='FFD966'>
			<td>
				Points Slots
			</td>
			<td align='right'>
				<?php echo $ranking['points_slots'];?>
			</td>
		</tr>
	</table>
	<h2>District Rankings</h2>
	<i>Only calculating lock ins, no lock outs/red teams. Green = points locked, Purple = prequalified, Blue = qualifying award</i><br/><br/>
	<table align='center' width="700" style="table-layout: fixed;">
		<tr style="font-weight: bold; text-align: center; background-color: black; color: White;">
			<td>
				Rank
			</td>
			<td>
				Team
			</td>
			<td>
				Districts
			</td>
			<td>
				Age Bonus
			</td>
			<td>
				DCMP
			</td>
			<td>
				Total
			</td>
			<td>
				Locked?
			</td>
		</tr>
	<?php
	foreach ($ranking['rankings'] as $team) {
		echo "<tr bgcolor='";
		if ($team['lock_status'] >= 2 && $team['lock_status']<=6 ) {
			echo '8E7CC3';
		} elseif ($team['lock_status'] >= 7 && $team['lock_status']<=10) {
			echo '6D9EEB';
		} elseif ($team['lock_status'] == 1) {
			echo '93C47D';
		}else {
			echo 'FFD966';
		}
		echo "'>";
		echo "<td align='right'>";
		echo $team['rank'];
		echo "</td>";
		
		echo "<td align='center'>";
		echo "<b><a href='https://www.thebluealliance.com/team/".$team['team_number']."' target='_blank'>".$team['team_key']."</a></b>";
		echo "</td>";

		echo "<td align='right'>";
		echo $team['event_points']['0']['total'] + $team['event_points']['1']['total'];
		echo "</td>";

		echo "<td align='right'>";
		echo $team['rookie_bonus'];
		echo "</td>";
		
		echo "<td align='right'>";
		echo $team['point_total']-($team['event_points']['0']['total']+$team['event_points']['1']['total']+$team['rookie_bonus']);
		echo "</td>";
		
		echo "<td align='right'>";
		echo $team['point_total'];
		echo "</td>";
		
		echo "<td align='right'>";
		if(is_numeric($team['lock_percent'])) {
			echo "<a href='index.php?t=".$team['team_key']."&dcmp=true'>".$team['lock_percent']."</a>";
		} else {
			echo $team['lock_percent'];
		}
		echo "</td>";
		
		echo "</tr>";
	}
	 echo "</table>";
	
}

function render_footer(){
?>
<p>
<font size='2'>Written by</font><br/><a href='https://www.chiefdelphi.com/u/Brandon_L/'>Brandon Liatys</a> / <a href='https://www.thebluealliance.com/team/2180'>frc2180</a><br/><br/>
<font size='2'>Based on algorithm by</font><br/><a href='https://www.chiefdelphi.com/u/agpapa/'>Antonio Papa</a> / <a href='https://www.thebluealliance.com/team/2590'>frc2590</a><br/><br/>
<font size='2'>Powered by</font><br/><a href='https://www.thebluealliance.com/'>The Blue Alliance</a>
</p>
<p>
<a href="https://www.thethriftybot.com/"><img src="https://le-cdn.website-editor.net/fa58a7b7b8454a6493c249d451adf7aa/dms3rep/multi/opt/thriftbot-6b06aa34-1920w.png" alt="Thriftybot" width="200"/></a>
</p>
<p>
<a href="https://www.firstinspires.org/"><img src="http://frclocks.com/FIRST_Horz_RGB.png" alt="FIRST Robotics" width="100" /></a>
</p>
</center>
</body>
</html>
<?php
}
?>
