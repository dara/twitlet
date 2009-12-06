<?php

// TWITLET CORE
// http://www.twitlet.com
// Copyright (c) 2009 D. Kilicoglu, B. Gumustas, A. Miharbi
// Licenced under Creative Commons GNU GPL.
// http://creativecommons.org/licenses/GPL/2.0/


if ($_GET['u'] != '' && $_GET['p'] != '') {
	// legacy authorization
	$username = decode($_GET['u']);
	$password = decode($_GET['p']);
} else {
	// new authorization
	$authString = decode($_GET['a']);
	$as = explode(":", $authString);
	$username = $as[0];
	$password = $as[1];
}

$status = textProcessor(stripslashes($_GET['t']));

$url = 'http://twitter.com/statuses/update.xml';
$curl_handle = curl_init();
curl_setopt($curl_handle, CURLOPT_URL, "$url");
curl_setopt($curl_handle, CURLOPT_HEADER, false);
curl_setopt($curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curl_handle, CURLOPT_TIMEOUT, 3);
curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl_handle, CURLOPT_VERBOSE, 1); 
curl_setopt($curl_handle, CURLOPT_POST, 1);
curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "status=".urlencode($status)."&source=twitlet");
curl_setopt($curl_handle, CURLOPT_USERPWD, "$username:$password");
$buffer = curl_exec($curl_handle);


$resultArray = curl_getinfo($curl_handle);

switch ($resultArray['http_code']) {
    case "200":
        logger("SUCCESS");
        break;
    case "401":
        logger("ERROR");
        jsAlert("Twitter says that your authentication credentials were missing or incorrect. >>> " . $status);
        break;
    case "500":
        logger("ERROR");  
        jsAlert("Twitter says something is broken. >>> " . $status);
        break;
    case "502":
        logger("ERROR");  
        jsAlert("Twitter server is down. >>> " . $status);
        break;
    case "503":
        logger("ERROR");  
        jsAlert("Ooops! Heavy blue whale. Twitter is overloaded. >>> " . $status);
        break;
    default:
        logger("ERROR");
        jsAlert("Ooops! Sadly there was no response from Twitter. We're not sure if your message made it to their server or not. >>> " . $status);
        break;
}

curl_close($curl_handle);
exit;



function textProcessor($text) {
    $newText = array();
    foreach (split(" ", $text) as $word) {
	    array_push($newText, wordProcessor($word));
	}
    return join(" ", $newText);
}



function wordProcessor($word) {
    
    // handle Twitlet commands
	if (ereg('^([[:punct:]]*)(#this|#link)([[:punct:]]*$)', $word, $regs))
	    $word = $regs[1].$_SERVER['HTTP_REFERER'].$regs[3]; 
	
	// don't shorten
	if (ereg('^([[:punct:]]*)([a-zA-Z]+://)?([a-zA-Z0-9]+\.)?(tinyurl\.com|is\.gd|bit\.ly|twitlet\.com).*$', $word, $regs)) {
	    return $word;
    } else if (ereg('^([[:punct:]]*)([a-zA-Z]+://|www\.)([-a-zA-Z0-9+&@#/%?=~_|!:,.;]*[a-zA-Z0-9+&@#/%=~_|])([[:punct:]]*$)', $word, $regs)) {
        $start_str  = $regs[1];
        $end_str    = $regs[4];
        $url_str    = $regs[2].$regs[3];
        $word       = $url_str;
	
		$word = str_replace("'", "%27", $word);
		$word = shortenURL($word);
		if ($word == -1)
		    $word = shortenURL($word, 1);
    }
    
    return $start_str.$word.$end_str;
}


function compressURL($longURL, $apiSelect = 0) {    
    $apiURLs = array("http://is.gd/api.php", "http://tinyurl.com/api-create.php");
    $apiVars = array("longurl", "url");
    
    $apiURL  = $apiURLs[$apiSelect]."?".$apiVars[$apiSelect]."=".$longURL;
    $session = curl_init();
    curl_setopt($session, CURLOPT_URL, $apiURL);
    curl_setopt($session, CURLOPT_TIMEOUT, 4);
    curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
    $shortURL = curl_exec($session);
    curl_close($session);

    if (strcmp(substr($shortURL,0,6), "Error:") == 0 || $shortURL == "") {
        logger("ERROR", "{$apiURLs[$apiSelect]} failed shortening the URL: {$longURL}");
        return -1;
    }

	else {
        return $shortURL;
    }
}


function decode($string) {
	// your own decode function here
}


function logger($type, $message="") {        
    global $username, $status, $resultArray;
    
    $info['Time']          = date(DATE_W3C);
    $info['HTTP_Code']     = $resultArray['http_code'];
    $info['Message']       = $message;
    $info['User']          = $username;
    $info['Status(raw)']   = $_GET['t'];
    $info['Status']        = $status;
    $info['Http Referer']  = $_SERVER['HTTP_REFERER'];
    
    if ($type=="ERROR") {
        $file = "../twitlet_errors/".date('Y-m-d').".log";
    } else {
        $file = "../twitlet_updates/".date('Y-m-d').".log";
    }
    
    $fp = fopen($file , "a+");
    fwrite($fp, "---\r");
    foreach ($info as $key => $value) {
        if (!empty($value)) {
            fwrite($fp, "{$key}: {$value} \r");
        }
    }
	fclose($fp);
}


function jsAlert($errorMessage) {
    echo '<script>alert("' . $errorMessage . '");</script>';
    exit;
}

?>