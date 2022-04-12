<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

//include functions
include 'functions.php';

//get year
$year = parse_ini_file("configs/year.ini");
$year = $year['year'];

//district shorthand
$district = $_GET['d'];

//get district name
$name = tba_get('https://www.thebluealliance.com/api/v3/district/'.$year.$district.'/events');
$name = $name[0]['district']['display_name'];

//load xml
$dcmpxml = simplexml_load_file('configs/dcmp.xml');

//get total champs slots
$cmp_slots = 0 + $dcmpxml->$district->cmp_slots;

//get award slots and out of district qualifying slots. Just add 3 for winning allinace for now. Needs to be changed to account for possible backup robots eventually
$award_slots = $dcmpxml->$district->award_slots->chairmans + $dcmpxml->$district->award_slots->ei + $dcmpxml->$district->award_slots->ras+3;
$ood_slots = count($dcmpxml->$district->prequalified_teams->ood->team);

//get district champs capacity
$dcmp_capacity = 0 + $dcmpxml->$district->capacity;

//get district rankings
$rankings = tba_get('https://www.thebluealliance.com/api/v3/district/'.$year.$district.'/rankings',true);

//get dcmp code
$dcmp_code = $year.$dcmpxml->$district->dcmp_code;

//set number of points slots remaining
$points_slots = $cmp_slots - $award_slots - $ood_slots;

//get state of dcmp
$state = event_state($dcmp_code);

//total points available at dcmp, minus winners points because winners auto advance no matter what
$points_remaining = get_points_remaining($dcmp_code);

$points_remaining = $points_remaining['total'] - $points_remaining['quals']; //normal function doesn't handle ei/ras teams correctly for quals

if($state < 7) { //awards not posted
    $points_remaining = $points_remaining - 16; //subtract out EI/RAS points
}
if($state < 6) { //finals not over
    $points_remaining = $points_remaining - 30; //subtract out finals points for winning alliance
}
if($state < 5) { //semis not over
    $points_remaining = $points_remaining - 30; //subtract out semis points for winning alliance
}
if($state < 4) { //quarters not over
    $points_remaining = $points_remaining - 30; //subtract out quarters points for winning alliance
}
if($state < 3) { //alliance selection not over
    $points_remaining = $points_remaining - 26; //subtract out minimum possible selection points for winning alliance
}
if($state < 2) { //quals not over
   $points_remaining = $points_remaining + qptotal($dcmp_capacity);
}

$points_remaining = $points_remaining * 3;

//get number of each cmp qualifying award for the event
$chairmans = $dcmpxml->$district->award_slots->chairmans;
$ei =  $dcmpxml->$district->award_slots->ei;
$ras =  $dcmpxml->$district->award_slots->ras;



//start awards array
$awards = array(
'chairmans' => array(),
'dcmp_winner' => array(),
'ras' => array(),
'ei' => array(),
);

//if finals are over
if ($state == 6){
	//get the alliances at the event
	$alliances = tba_get('https://www.thebluealliance.com/api/v3/event/'.$dcmp_code.'/alliances');
	foreach($alliances as $alliance) {
		//check if alliance won
		if($alliance['status']['status'] == 'won') {
			$winning_alliance = $alliance['picks'];
			if(isset($alliance['backup']['in'])) {
				array_push($winning_alliance,$alliance['backup']['in']);
			}
			break;
		}
	}
	
	//push each winner into the awards array
	foreach($winning_alliance as $winner) {
		array_push($awards['dcmp_winner'],$winner);
	}
	
	
}

//get awards if awards are posted
if($state == 7) {
	$awards_raw = tba_get('https://www.thebluealliance.com/api/v3/event/'.$dcmp_code.'/awards');
	
	foreach ($awards_raw as $award) { //loop through each award to find chairmans, ei, ras, and dcmp winners
			if($award['award_type'] == 0) { //0 = chairmans
				foreach ($award['recipient_list'] as $recipient) {
					array_push($awards['chairmans'],$recipient['team_key']);
				}
			}
			if($award['award_type'] == 1) { //dcmp winner
				foreach ($award['recipient_list'] as $recipient) {
					array_push($awards['dcmp_winner'],$recipient['team_key']);
				}
			}
			if($award['award_type'] == 10) { //ras winner
				foreach ($award['recipient_list'] as $recipient) {
					array_push($awards['ras'],$recipient['team_key']);
				}
			}
			if($award['award_type'] == 9) { //ei winner
				foreach ($award['recipient_list'] as $recipient) {
					array_push($awards['ei'],$recipient['team_key']);
				}
			}
		}
}
if(count($awards['dcmp_winner']) == 4) {
	$points_slots = $points_slots - 1;
}

    //double qualified
    foreach ($rankings as &$team) {
		
        $count_qualified = 0;
        if (in_array($team['team_key'], $awards['chairmans'])) {
            $count_qualified++;
        }
        if (in_array($team['team_key'], $awards['dcmp_winner'])) {
            $count_qualified++;
        }
        if (in_array($team['team_key'], $awards['ras'])) {
            $count_qualified++;
        }
        if (in_array($team['team_key'], $awards['ei'])) {
            $count_qualified++;
        }
        if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->waitlist->team,false)) {
             $count_qualified++;
        }	
        if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->ood->team,false)) {
             $count_qualified++;
        }
        if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->hof->team,false)) {
             $count_qualified++;
        }
        if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->cmp_award->team,false)) {
             $count_qualified++;
        }
        if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->oas->team,false)) {
             $count_qualified++;
        }

        if ($count_qualified > 1) {
            $points_slots += ($count_qualified - 1);
        }
    }


//start lock loop
$prequal_counter = 0; //start counter
foreach ($rankings as &$team) {
	//set default lock percent and status
	//lock status:
	//-1 = locked out
	//0 = not locked
	//1 = locked in
	//2 = hof team
	//3 = out of district winner
	//4 = waitlist team
	//5 = cmp award
	//6 = original team
	$team ['lock_status'] = 0;
	$team ['lock_percent'] = '--';
	
	//set team number without the leading 'frc'
	$team['team_number'] = substr($team['team_key'], 3);
	
    //check if awards are set, if they are then check if team shows up in any cmp qualifying awards
	if(isset($awards)) {
		//if district winner
		if(in_array($team['team_key'],$awards['dcmp_winner'],false)) {
			$team['lock_status'] = 7;
			$team['lock_percent'] = "Winner";
		}
		//if ras team
		if(in_array($team['team_key'],$awards['ras'],false)) {
			$team['lock_status'] = 8;
			$team['lock_percent'] = "RAS";
		}
		//if ei team
		if(in_array($team['team_key'],$awards['ei'],false)) {
			$team['lock_status'] = 9;
			$team['lock_percent'] = "EI";
		}
		//if chairmans team
		if(in_array($team['team_key'],$awards['chairmans'],false)) {
			$team['lock_status'] = 10;
			$team['lock_percent'] = "CA";
		}
	}
    
	//check if team is hall of fame, waitlist, out of district, or cmp award status. This is similar to chairmans status in regular district locks
	

	//waitlist teams
	if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->waitlist->team,false)) {
		$team['lock_status'] = 4;
		$team['lock_percent'] = "Waitlist";
	}	

	//ood teams
	if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->ood->team,false)) {
		$team['lock_status'] = 3;
		$team['lock_percent'] = "Regional";
	}

	//hof teams
	if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->hof->team,false)) {
		$team['lock_status'] = 2;
		$team['lock_percent'] = "HOF";
	}
	
	//cmp award teams
	if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->cmp_award->team,false)) {
		$team['lock_status'] = 5;
		$team['lock_percent'] = "CMP Award";
	}
	
	//original teams
	if(in_array($team['team_key'],(array)$dcmpxml->$district->prequalified_teams->oas->team,false)) {
		$team['lock_status'] = 6;
		$team['lock_percent'] = "OG Team";
	}
    
    if ($team['lock_status'] > 0) {
        $prequal_counter++;
    }
	
	//number of teams needed to push them out of a points slot
	$team['teams_to_tie'] = $points_slots-$team['rank']+1+$prequal_counter;
} 

if($state > 2) { //alliance selection over
    $pts_increment = 15; //points can only be given out in chunks of 15 (for award or advancing round)
} else {
    $pts_increment = 3; //points can only be given out in chunks of 3 (b/c of dcmp multiplier)
}

//loop through top x teams, where x = points slots.
foreach ($rankings as &$team) { //for each team, calculate the number of points needed by the next x teams to tie them
	$ttt_counter = 0; //start ttt counter
	$team['ttt'] = array(); //start ttt array
	if ($team['lock_status'] == 0) { //only run the lock calc on teams not already qualified
		$team['points_to_tie'] = 0; //start counter	
		foreach($rankings as &$ttt) {	//find the next x (x=teams to tie) non cmp qualified teams and see how many points they each need to tie team y
			if($ttt['rank']>$team['rank'] && $ttt['lock_status'] == 0){ //check if team is below team y and not prequalified for cmp already
				$data = array(
					'team_key' => $ttt['team_key'],
					'point_total' => $ttt['point_total'],
					'points_to_tie' => $team['point_total'] - $ttt['point_total'] +  ($pts_increment - (($team['point_total'] - $ttt['point_total']) % $pts_increment) % $pts_increment), //must be a multiple of 3
					'rank' => $ttt['rank'],
				);
				array_push($team['ttt'], $data);
				$team['points_to_tie'] += $data['points_to_tie'];; //team isn't qualified, so take the difference in points and add to total
				
				
				
				if ($points_remaining>0){
					//if still in play, set lock percent
					$team['lock_percent'] = round($team['points_to_tie']/$points_remaining*100,2);
                    if ($team['lock_percent'] >= 100) {
                            $team['lock_percent'] = 100;
                            $team['lock_status'] = 1;
                    }
                    if ($state == 6 && $data['points_to_tie'] > 30) { //if in awards and team needs more than two awards to tie
                        $team['lock_percent'] = 100;                 //then lock, since impossible to get more than two awards (safety + 1 more)
                        $team['lock_status'] = 1;
                    }
				} else {
					//if not in play, just set it to 100 since we're looping through the DCMP eligible teams only.
					$team['lock_percent'] = 100;
					$team['lock_status'] = 1;
				}
				
				
				
				$ttt_counter++; //add 1 to ttt counter
				if($ttt_counter == $team['teams_to_tie']) {
					break; //break the loop when you find the right number of teams to tie
				}
			}
		}
	}
	//break the loop at the end of the number of points slots
	if ($team['teams_to_tie'] == 1) {
		break;
	}
}

//build output array
$output = array(
'district_name' => $name,
'points_remaining' => $points_remaining,
'dcmp_capacity' => $dcmp_capacity,
'cmp_slots' => $cmp_slots,
'award_slots' => $award_slots,
'ood_slots' => $ood_slots,
'points_slots' => $points_slots,
'dcmp_state' => $state,
'rankings' => $rankings,
);

print_r(json_encode($output));

?>