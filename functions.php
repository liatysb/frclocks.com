<?php
function tba_get($link){
	//calls the tba api and stores the answer for caching purposes. Returns either the api call or the cached version.
	
	//tba key
	$key = "PASTE TBA KEY HERE";
	
	//characters to replace with nothing from url
	$replace[0] = '/';
	$replace[1] = ':';
	$replace[2] = '.';
	$file = 'cache/'.str_replace($replace,"",$link).'.cache';
	
	if (file_exists($file)) {
		//600 seconds = 10 min
		if (time() - filemtime($file) > 600) {
			//file needs to be refreshed
			if(get_http_response_code($link.'?X-TBA-Auth-Key='.$key) != "200"){
				return "error";
			} else {
				$data = json_decode(file_get_contents($link.'?X-TBA-Auth-Key='.$key),true); //call tba
				file_put_contents($file, serialize($data)); //save the file	
			}
		} else {
			//file does not need to be refreshed
			$data = unserialize(file_get_contents($file)); //read the file
		}
	} else {
		//file does not exist
		if(get_http_response_code($link.'?X-TBA-Auth-Key='.$key) != "200"){
				return "error";
			} else {
				$data = json_decode(file_get_contents($link.'?X-TBA-Auth-Key='.$key),true); //call tba
				file_put_contents($file, serialize($data)); //save the file	
				chmod($file, 0777);
			}
	}
	
	return $data;
}

function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}

function cmp_by_number($a, $b) {
  return $a["unix"] - $b["unix"];
}

function event_state($event) {
	
	//returns a number relevant to the current state of the event.
	//-1 = error
	//0 = pre
	//1 = quals
	//2 = selections
	//3 = quarters
	//4 = semis
	//5 = finals
	//6 = awards
	//7 = post
	
	//check if awards are posted
	$awards = tba_get('https://www.thebluealliance.com/api/v3/event/'.$event.'/awards',true);
	if (count($awards)>10) {
		//if awards are posted, the event is over
		return 7;
	}
	
	//get match list
	$matches = tba_get('https://www.thebluealliance.com/api/v3/event/'.$event.'/matches',true);
		if (empty($matches)) {
		//if the match array is empty, no matches have happened yet. Event is in the pre state.
		return 0;
	}
	//TBA will create a batch of 3 matches and keys for each round of elims, then later remove the keys for any unneeded matches.
	//Therefore, the best way to tell what round you are in is to check if all the match keys have been played in the round before, as when the round is complete unplayed matches are removed.
	//to check if a match is complete, uncompleted matches have a score of -1 for each alliance. Check if score is >= 0.
	
	//init counters for quals, quals complete, quarters, quarters complete, semis, semis complete, finals, finals complete.
	$qm = 0;
	$qm_complete = 0;
	$qf = 0;
	$qf_complete = 0;
	$sf = 0;
	$sf_complete = 0;
	$f1 = 0;
	$f1_complete = 0;
	$f1_blue_win = 0;
	$f1_red_win = 0;
	
	//loop through all matches, add to counters
	foreach ($matches as $match) {
		
		//Finals
		if ($match['comp_level'] == 'f') {
			++$f1; //increment finals counter for each finals match
			
			if ($match['alliances']['blue']['score'] > $match['alliances']['red']['score']) {
				++$f1_blue_win; //blue won, add to blue win counter
			}

			if ($match['alliances']['red']['score'] > $match['alliances']['blue']['score']) {
				++$f1_red_win; //red won, add to red win counter
			}


			if ($match['alliances']['blue']['score'] >= 0) {
				++$f1_complete; //increment finals complete counter for each complete finals match (score >=0)
			}
		}
		
		//Semifinals
		if ($match['comp_level'] == 'sf') {
			++$sf; //increment semifinals counter for each semifinals match
			if ($match['alliances']['blue']['score'] >= 0) {
				++$sf_complete; //increment semifinals complete counter for each complete semifinals match (score >=0)
			}
		}	
		
		//Quarters
		if ($match['comp_level'] == 'qf') {
			++$qf; //increment quarterfinals counter for each quarterfinals match
			if ($match['alliances']['blue']['score'] >= 0) {
				++$qf_complete; //increment quarterfinals complete counter for each complete quarterfinals match (score >=0)
			}
		}
		
		//Quals
		if ($match['comp_level'] == 'qm') {
			++$qm; //increment quals counter for each quals match
			if ($match['alliances']['blue']['score'] >= 0) {
				++$qm_complete; //increment quals complete counter for each complete quals match (score >=0)
			}
		}
	}
	
	//Check if all finals matches have been played
	if(($f1_red_win == 2) || ($f1_blue_win == 2)) {
		return 6;
	}
	
	//Check if all semis matches have been played
	if($f1 == 3) {
		return 5;
	}
	
	//Check if all quarters matches have been played
	if($sf == 6) {
		return 4;
	}
	
	//check if alliances have been submitted. If so, you are in quarters.
	$selections = tba_get('https://www.thebluealliance.com/api/v3/event/'.$event.'/alliances',true);
	if (!empty($selections)) {
		return 3;
	}
	
	//check if all qual matches have been played
	if($qm == $qm_complete) {
		return 2;
	} else {
		return 1; //if all quals matches have not been played, event is currently in quals
	}
	
	//just in case some weird shit happens, return -1 as an error
	return -1;
}

function get_points_remaining($event){
	
	//return array {qual pts, selection pts, elim pts, award pts, total pts}

	//get event state
	$state = event_state($event); //returns a number relevant to the current state of the event.
	//-1 = error
	//0 = pre
	//1 = quals
	//2 = selections
	//3 = quarters
	//4 = semis
	//5 = finals
	//6 = awards
	//7 = post
	
	//get teams attending, to see who is ineligible for points (3rd or more play teams or out of district teams) and to count how many teams for the qual formula
	$teams = tba_get('https://www.thebluealliance.com/api/v3/event/'.$event.'/teams',true);
	
	
	//int awards
	$awards = 0;
	
	//initiate rookie counter
	$rookies = 0;
	
	//check if award points still available
	if ($state <= 6) {
		
		//set current year to see who is a rookie
		$year = getdate();
		$year = $year['year'];
		
		//count number of rookies
		foreach ($teams as $team) {
			if ($team['rookie_year'] == $year) {
				++$rookies;
			}
		}
		
		//assign award points depending if there is or isn't a rookie at the event
		if ($rookies > 1) {
			//there is a rookie, 76 points in awards
			$awards = 76;
		} elseif ($rookies == 1) {
			//there is 1 rookie, 71 points in awards
			$awards = 71;
		} else {
			//there are no rookies, 63 points in awards
			$awards = 63;
		}
	}
	
	//int elims points
	$elims = 0;
	//check if finals points still available
	if ($state <= 5) {
		$elims += 30;
	}
	
	//check if semis points still available
	if ($state <= 4) {
		$elims += 60;
	}
	
	//check if quarters points still available
	if ($state <= 3) {
		$elims += 120;
	}
	
	//int selections
	$selections = 0;
	//check if selections points still available
	if ($state <= 2) {
		$selections = 236;
	}
	
	//int quals
	$quals = 0;
	$quals_unadjusted = 0;
	$last_place_points = qprank(count($teams),count($teams));
	//check if qual points still available
	if ($state <= 1) {
		//stupid qual formula and stuff
		$quals = qptotal(count($teams));
		$quals_unadjusted = $quals; //save for other use
		//You can assume each team will earn at least the amount of points for last place. Therefore these points are not actually up for grabs and not remaining.
		$quals -= $last_place_points*count($teams);
	}

	
	$points_remaining = array(
		"quals_unadjusted" => $quals_unadjusted,
		"last_place_points" => $last_place_points,
		"team_count" => count($teams),
		"quals" => $quals,
		"selections" => $selections,
		"elims" => $elims,
		"awards" => $awards,
		"rookies" => $rookies,
		"total" => $quals + $selections + $elims + $awards,
	);
	
	return $points_remaining;
}

function inverf($z) {
	//add caching here too, eventually
    return file_get_contents('http://localhost:8080/brandon/robotics/inverseErrorResponse/'.$z);
}

function qptotal($n) {
	$a = 1.07;
	$qptotal = 0;
	for ($i = $n; $i>0; $i--) {
		$qptotal += ceil(inverf(($n-2*$i+2)/($a*$n))*(10/inverf(1/$a))+12);
	}
	return $qptotal;
}

function qprank($n, $r) {
	$a = 1.07;
	$qprank = ceil(inverf(($n-2*$r+2)/($a*$n))*(10/inverf(1/$a))+12);
	return $qprank;
}
?>