<?php
$db_host = "localhost";
$db_user = "root";
$db_pass = "password";
$db_name = "osticket";

// max number of messages you're expecting for one ticket, the more unneeded, the more selectors you'll have to fulfill.
$max_message_number = 8; 

// max number of attachments you're expecting for one ticket, the more unneeded, the more selectors you'll have to fulfill.
// each attachment would also be inclused in a comment, as explained in https://jira.atlassian.com/browse/JSD-43, comment 
// "kevinb added a comment - 14/Jan/14 9:43 PM"
$max_attachment_number = 5; 

// for testing purposes allows to import the only X first lines, -1 to disable the limit
$lines_limit = -1; 

// change to <br> if you want to inspect resulting file in your browser
$EOL = "\n"; 

// prefix to url of your attachments, they must be readable from outside, for me I've done a symbolik link to make them visible during migration process
$attachments_files_basepath = "http://osticketserver/attachments/"; 

function clean_chars($string) {
	//$string = preg_replace("/[^\p{L}0-9()@_'&-\s]+/u", '', $string);
	//$string = str_replace ("\x1a", "", $string);
	//$string = iconv("UTF-8", "UTF-8//IGNORE", $string);
	//$string = preg_replace('/[\x00-\x1F]/','',$string);
	$string = str_replace('"','""',$string);
	return $string;
}

// Connecting
$db=mysql_connect($db_host,$db_user,$db_pass) or die('connexion impossible');

// DB selection
$connection=mysql_select_db($db_name,$db) or die('table non trouvée');

// main SQL request
$sql = 'SELECT * FROM ost_ticket';

// launching main request
$req = mysql_query($sql) or die('Erreur SQL !<br>'.$sql.'<br>'.mysql_error());

$i=0;
// looping each record
while($data = mysql_fetch_assoc($req)) {
	// First line, printing headers;
	if ($i==0) {
		// fixed values column
		print '"issue type (fixed)", ';
		print '"status 2 (for correspondance to solved/not solved)", ';
		print '"ticketID 2 (for correspondance to keyword)", ';
		// regular columns
		foreach($data as $column => $info) {
			print '"'.$column.'", ';
		}
		while($comment_header_num<=($max_message_number-1)) {
			print '"comment'.($comment_header_num + 1).'", ';
			$comment_header_num++;
		}
		while($attachment_header_num<=($max_attachment_number-1)) {
			print '"attachment'.($attachment_header_num+ 1).'", ';
			$attachment_header_num++;
		}
		$attachment_header_num=0;
		while($attachment_header_num<=($max_attachment_number-1)) {
			print '"attachment_comment'.($attachment_header_num+ 1).'", ';
			$attachment_header_num++;
		}
		print '"end of line"';
	}
	$i++;
	print $EOL;

	// fixed values column
	print '"issue", ';
	print '"'.$data[status].'", ';
	print '"'.$data[keywordID].'", ';

	//Summary column cannot be blank in JIRA
	if(($data[subject] === "") || (!$data[subject])) {
		$data[subject] = "-";
	}

	// printing current data infos
	foreach($data as $column => $info) {
		print '"'.clean_chars($info).'",';
	}

	// messages
	$sql2 = 'SELECT concat(created,";","'.$data[email].'",";",replace(message,"\"","\"\"")) as comment from ost_ticket_message as otn where ticket_id='.$data[ticket_id];
	$req2 = mysql_query($sql2) or die ('Erreur SQL2 !'.$sql2.'<br>'.mysql_error());
	$j=1;
	while($data2 = mysql_fetch_assoc($req2)) {
		print '"'.clean_chars($data2[comment]).'", ';
		$j++;
	}
	while($j<=$max_message_number) {
		print '"", ';
		$j++;
	}

	// attachments
	$sql3 = 'select concat("'.$attachments_files_basepath.'",DATE_FORMAT(created,"%m%y"),"/",file_key,"_",file_name) as attachment from ost_ticket_attachment where ticket_id='.$data[ticket_id];
	$req3 = mysql_query($sql3) or die ('Erreur SQL3 !'.$sql3.'<br>'.mysql_error());
	$k=1;
	while($data3 = mysql_fetch_assoc($req3)) {
		$headers = get_headers($data3[attachment], 1);
		if ($headers[0] == 'HTTP/1.1 200 OK') {
			print '"'.clean_chars($data3[attachment]).'", ';
		} else {
			print '"", ';
		}
		$k++;
	}
	while($k<=$max_attachment_number) {
		print '"", ';
		$k++;
	}

	// attachments comments to properly display attachments inside service desk customer portal
	$sql4 = 'select concat(created,";","gm@ideesculture.com",";","!",file_key,"_",file_name,"|",file_name,"!") as comment, concat("'.$attachments_files_basepath.'",DATE_FORMAT(created,"%m%y"),"/",file_key,"_",file_name) as attachment from ost_ticket_attachment where ticket_id='.$data[ticket_id];
	$req4 = mysql_query($sql4) or die ('Erreur SQL4 !'.$sql4.'<br>'.mysql_error());
	$k=1;
	while($data4 = mysql_fetch_assoc($req4)) {
		$headers = get_headers($data4[attachment], 1);
		if ($headers[0] == 'HTTP/1.1 200 OK') {
			print '"'.clean_chars($data4[comment]).'", ';
		} else {
			print '"", ';
		}
		$k++;
	}
	while($k<=$max_attachment_number) {
		print '"", ';
		$k++;
	}
	print '"end of line"';
	if ($i==$lines_limit) break;
}
print $EOL;
// on ferme la connexion à mysql
mysql_close();