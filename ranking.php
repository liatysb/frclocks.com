<?php

//include functions
include 'functions.php';

function ranking($d){

//THINGS TO TEST:
//make sure event_state steps through each state at the proper time

//THINGS TO ADD:
//x teams locked in. y slots remaining

//get year
//$year = 2019;
$year = parse_ini_file("configs/year.ini");
$year = $year['year'];

//load capacity config
$capacity = parse_ini_file("configs/capacity.ini");

//check if valid district code
if(!isset($capacity[$d])){
	die("Not a valid district shorthand.");
}

//gather data needed
$events = tba_get('https://www.thebluealliance.com/api/v3/district/'.$year.$d.'/events/simple',true);

//calculate points remaining in the district
$points_remaining = 0; //int points remaining counter
$district_events = 0; //int counter of how many district events
$chairmans_teams = array(); //int chairmans array

//for each event, calculate points remaining and set up other stuff needed later
foreach ($events as $key => &$event) {
	
	//convert date to number
	$event['unix'] = strtotime($event['start_date']);
	
	//add event progress to array
	$event['progress'] = event_state($event['key']);
	
	//set event background color
	if ($event['progress'] < 7){
		$event['color'] = "FFD966";
	} else {
		$event['color'] = "93C47D";
	}
	
	//add teams to array
	$event['teams'] = tba_get('https://www.thebluealliance.com/api/v3/event/'.$event['key'].'/teams/keys',true);
	
	//only count points if the event is a district, not a DCMP or other. District = event type 1
	if ($event['event_type'] == 1) {
		//found a district event, add 1 to counter
		$district_events ++;
		
		//get points remaining in event
		$event['points_remaining'] = get_points_remaining($event['key']);
		
		$points_remaining += $event['points_remaining']['total'];
		
		//while we're looping through the district events, get a list of chairman's winners
		$awards = tba_get('https://www.thebluealliance.com/api/v3/event/'.$event['key'].'/awards',true);

		foreach ($awards as $award) {
			if (!empty($awards) && $award['award_type'] == 0){
				array_push($chairmans_teams, $award['recipient_list'][0]['team_key']);
				break;
			}
		}
	} else {
		//just delete other event types, we're not going to deal with them
		 unset($events[$key]);
	}
}

//push to chairmans array here to test things

//sort events by unix start date
usort($events, "cmp_by_number");

//points slots = total slots - # of chairmans teams. # of chairmans teams = # of district events
$dcmp_slots = $capacity[$d]-$district_events;

//get district rankings
$rankings = tba_get('https://www.thebluealliance.com/api/v3/district/'.$year.$d.'/rankings');

//int chairmans counter
$chairmans_counter = 0;

//set up rankings array
foreach ($rankings as &$team) {
	
	//set a teams lock % to "--" as default
	$team['lock_percent'] = '--'; //april fools, '--'
	
	//set team_number without the leading 'frc'
	$team['team_number'] = substr($team['team_key'], 3);
	
	//overwrite the TBA points total. Their total includes Qual points as they are occuring, which is an issue. We'll calculate our own total.
	//$team['point_total'] = $team['rookie_bonus']; //initialize the points total to just be the rookie bonus.
	$team['inflated_points_total'] = 0; //we will also keep an 'inflated' points total to be used for calculations later, but not to be shown to the user.
	
	//check if team is chairmans. If so set chairmans flag.
	if(in_array($team['team_key'],$chairmans_teams,false)) {
		$team['chairmans'] = 1;
		//found a chairmans team, add to chairmans counter
		$chairmans_counter ++;
	} else {
		$team['chairmans'] = 0;
	}
	
	//add teams to tie to knock out of dcmp.
	$team['teams_to_tie'] = $dcmp_slots-$team['rank']+1+$chairmans_counter;
	
	//get the team's registered events
	$team['registered_events'] = array();
	$team['points_plays_remaining'] = 0;
	foreach ($events as &$event) {
		if ($event['event_type'] == 1 && in_array($team['team_key'],$event['teams'],true)) {
			
			//if there are already 2 other events, then any more events are extra plays
			if (count($team['registered_events']) >= 2) {
				$extra_play = 1;
			} else {
				$extra_play = 0;
			}
			
			//if the event isn't a 3rd or more, and the progress is less then 7, it is still a remaining play
			if ($extra_play == 0 && $event['progress']<7) {
				$team['points_plays_remaining'] ++;
			}
			
			//find the minimum amount of points a team can earn at the event
			$min_points = qprank(count($event['teams']),count($event['teams']));
			
			//if the event isn't an extra play and quals arn't complete, add the min points to the points total for the team as they will at least earn this
			if($extra_play == 0 && $event['progress'] < 1) {
				$team['inflated_points_total'] += $min_points; //add the min points to the inflated points total
			}

			//event output subarray
			$event_data = array(
			'key' => $event['key'],
			'progress' => $event['progress'],
			'extra_play' => $extra_play,
			'team_count' => count($event['teams']),
			'min_points' => $min_points,
			);
			array_push($team['registered_events'], $event_data);
		}
	}
			
	//calculate total points from event 1
	if(isset($team['event_points'][0])) {
		if($team['registered_events'][0]['progress'] > 1) {
			//event is past quals, so its ok to just pass on the TBA total
			//$team['point_total'] += $team['event_points'][0]['total'];
		} else {
			//event is in or before quals, so pass on the total - qual points
			//$team['point_total'] += $team['event_points'][0]['total'] - $team['event_points'][0]['qual_points'];
		}
	}
	
	//calculate total points from event 2
	if(isset($team['event_points'][1])) {
		if($team['registered_events'][1]['progress'] > 1) {
			//event is past quals, so its ok to just pass on the TBA total
			//$team['point_total'] += $team['event_points'][1]['total'];
		} else {
			//event is in or before quals, so pass on the total - qual points
			//$team['point_total'] += $team['event_points'][1]['total'] - $team['event_points'][1]['qual_points'];
		}
	}
	
	//add total points to inflated points. Inflated points includes the min points from events not played yet. This number is used in our calculation.
	$team['inflated_points_total'] += $team['point_total'];
}

//set new dcmp slots value
$dcmp_slots = $dcmp_slots + $chairmans_counter - 1;

//now that our events and rankings arrays are set up properly, we can calculate the points needed to tie each team
//loop through top x teams, where x = dcmp points slots.
for ($i = 0; ($i < count($rankings) && $rankings[$i]['teams_to_tie']>0); $i++) {
	
	$rankings[$i]['TBWPR'] = 0; //initialize teams below with plays remaining (TBWPR)
	$rankings[$i]['points_to_tie'] = 0; //initialize points to tie
	$still_to_play = $rankings[$i]['teams_to_tie']; //set number of teams to search for
	$rankings[$i]['ttt'] = array();
	
	//check if current index ('Team A') is a chairmans team. If yes, just set lock % to DCA. If no, calculate points needed to tie.
	if ($rankings[$i]['chairmans'] == 0) {
		//loop through the next X teams, where X is teams needed to tie the current team.
		for ($x = $i+1; $still_to_play > 0 && $x < count($rankings); $x++) {
			//if you find a team whos inflated points is greater then team As inflated point, subtract from still to play and teams to tie. this simulates team A being bumped down 1 rank by the team with the higher inf. points.
			if ($rankings[$x]['inflated_points_total'] > $rankings[$i]['inflated_points_total']) {
				$still_to_play--;
				$rankings[$i]['teams_to_tie']--;
			}
			//check if this team ('Team B', who is somewhere below 'Team A') has a points play remaining and is not a chairmans team and the inflated points is less then teams As inflated points
			if ($rankings[$x]['points_plays_remaining'] >= 1 && $rankings[$x]['chairmans'] == 0 && $rankings[$x]['inflated_points_total'] <= $rankings[$i]['inflated_points_total']) {
				// && $rankings[$x]['inflated_points_total'] <= $rankings[$i]['inflated_points_total']
				//This is a valid team to add points to tie for. they have a play remaining, are not a chairmans team, and the inflated points value is lower then the current team. Take the difference between Team A's points and Team B's points, add to Points to tie
				$rankings[$i]['points_to_tie'] += $rankings[$i]['inflated_points_total'] - $rankings[$x]['inflated_points_total'];
				//add team to ttt
				$ttt = array(
					'team_key' => $rankings[$x]['team_key'],
					'inflated_points_total' => $rankings[$x]['inflated_points_total'],
					'points_to_tie' => $rankings[$i]['inflated_points_total'] - $rankings[$x]['inflated_points_total'],
					'rank' => $rankings[$x]['rank'],
					'team_number' => $rankings[$x]['team_number'],
				);
				array_push($rankings[$i]['ttt'], $ttt);
				//since we found a valid team with a play left, decrement the still to play counter and add 1 to TBWPR
				$still_to_play --;
				$rankings[$i]['TBWPR'] ++;
			}
		}
	
		//multiply any remaining still to plays by the current teams points, sum.
		$rankings[$i]['points_to_tie'] += $still_to_play * $rankings[$i]['point_total']; //had this line for the 2017 season, not sure why?? Possibly because any remaining teams have 0 points, so current team - 0 = current team points or something?
	
		//check if there are still points remaining, aka the district is still in play
		if ($points_remaining>0){
			//if still in play, set lock percent
			$rankings[$i]['lock_percent'] = round($rankings[$i]['points_to_tie']*100/$points_remaining,2); //april fools, round($rankings[$i]['points_to_tie']*100/$points_remaining,2)
			//also set a raw lock percent that won't be edited by the lock status function later
			$rankings[$i]['raw_lock_percent'] = $rankings[$i]['lock_percent'];
		} else {
			//if not in play, just set it to 100 since we're looping through the DCMP eligible teams only.
			$rankings[$i]['lock_percent'] = 100;
			$rankings[$i]['raw_lock_percent'] = $rankings[$i]['lock_percent'];
		}
	}
}

//set lock status and colors
foreach ($rankings as &$team) {
	
	//if any event is null, set the points to 0
	if(!isset($team['event_points']['0']['total'])){
		$team['event_points']['0']['total'] = 0;
	}
	
	if(!isset($team['event_points']['1']['total'])){
		$team['event_points']['1']['total'] = 0;
	}
	
	$team['lock_status'] = 0; //default to a not locked status
	$team['color'] = "FFD966"; //yellow not locked color
	
	if($team['chairmans'] == 1) {
		//team is chairmans
		$team['lock_percent'] = "DCA"; //set lock percent to DCA
		$team['lock_status'] = 2; //set lock status to chairmans (2)
		$team['color'] = "6D9EEB"; //Banner blue
	} elseif ((isset($team['lock_percent']) && strval($team['lock_percent']) >= 100) || (isset($team['TBWPR']) && $team['teams_to_tie']>$team['TBWPR'])) {
		//team is locked in
		$team['lock_percent'] = "100"; //set lock percent to 100
		$team['lock_status'] = 1; //set to 1 for team is locked
		$team['color'] = "6aa84f"; //Money Green
	} elseif ((isset($team['lock_percent']) && strval($team['lock_percent']) < 100 && strval($team['lock_percent']) >= 0) && is_numeric($team['lock_percent'])) {
		$team['color'] = "b6d7a8"; //less money Green
	} elseif (($team['points_plays_remaining'] == 0 && $team['rank'] > $dcmp_slots) || ($team['points_plays_remaining'] == 0 && isset($team['lock_percent']) && $team['lock_percent']<0) || ($points_remaining == 0 && $team['rank'] > $dcmp_slots) || ($team['teams_to_tie'] <= 0 && $team['points_plays_remaining'] == 0)) {
		//team is locked out
		$team['dcmpslots'] = $dcmp_slots;//test
		$team['lock_status'] = -1; //set to -1 for team is locked out
		$team['color'] = "E06666"; //Regretful Red
	}
}

//build output array
$output = array(
'points_remaining' => $points_remaining,
'rankings' => $rankings,
'events' => $events,
'dcmp_slots' => $capacity[$d],
);

return $output;

}

?>