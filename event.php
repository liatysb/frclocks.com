<pre>

<?php
include 'functions.php';


$event = event_state($_GET['e']);
print_r($event);
?>
</pre>
</br>
	returns a number relevant to the current state of the event.</br>
	-1 = error</br>
	0 = pre</br>
	1 = quals</br>
	2 = selections</br>
	3 = quarters</br>
	4 = semis</br>
	5 = finals</br>
	6 = awards</br>
	7 = post</br>