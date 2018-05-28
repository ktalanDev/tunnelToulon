<?php
// - Settings 
try {
 	$json_source = file_get_contents('twitterAuthentication.json');
 	if ($json_source === false) {
        echo "file empty";
    }
    else {
    	$jsonOauth = json_decode($json_source, true);
		$consumer_key = $jsonOauth['consumer_key'];
		$consumer_secret = $jsonOauth['consumer_secret'];
		$oauth_token = $jsonOauth['oauth_token'];
		$oauth_token_secret = $jsonOauth['oauth_token_secret'];	
    }
	
 } catch (Exception $e) {
	 echo "file not found";
 } 


//to use an existing class of twitter authentication (https://twitteroauth.com/)
require "vendor/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;

// - Authentication
$connection = new TwitterOAuth($consumer_key, $consumer_secret, $oauth_token, $oauth_token_secret);
/*from:TunnelToulon to get only tweet from TunnelToulon
exclude retweets to exclude all retweets 
tweet_mode extended to have all the text from the tweet
count 40 to test on a lot of tweet
*/
$result = $connection->get("search/tweets", ["q" => "from:TunnelToulon", "exclude" => "retweets",  
"tweet_mode" => "extended", "count" => "40"]);
$jsonResult = json_encode($result);

$arrayLastTweetsFromTunnelToulon = json_decode($jsonResult, true)['statuses'];

//check data of a tweet to extract date and content text
foreach ($arrayLastTweetsFromTunnelToulon as $value) {
    $timeTweet = $value['created_at'];    
    $textTweet = $value['full_text'];  
    print_r(getInfoFromTextTweet($textTweet, $timeTweet));
    echo "\n";
}

//analyze content text of a tweet
function getInfoFromTextTweet($textTweet, $timeTweet) {    
    //delete all "." and "," in the text tweet
    $textTweet = str_replace(".", "", $textTweet);
    $textTweet = str_replace(",", "", $textTweet);
    //add whitespace after "]"
    $textTweet = str_replace("]", "] ", $textTweet);    
    $arrayTextTweet = explode(" ", $textTweet);
    $direction = getDirectionFromTextTweet($arrayTextTweet);
    $state = getStateFromTextTweet($textTweet);    
    $timeTweet = getDateTweet($timeTweet);
    $arrayResult = array(
        "tweet" => $textTweet,
        "direction" => $direction,
        "state" => $state,
        "date" => $timeTweet,
    );
    return $arrayResult;
}

//analyze content text to extract which direction is involved
function getDirectionFromTextTweet($arrayTextTweet) {    
    foreach ($arrayTextTweet as $key => $value) {
        if ($value == "Dir") {
            if ($arrayTextTweet[$key + 1] == "Marseille") {
                return "MARSEILLE";
            }
            if ($arrayTextTweet[$key + 1] == "Nice") {
                return "NICE";
            }
        }
        elseif ($value == "DirNice" or $value == "Marseille/Toulon") {
            return "NICE";
        } 
        elseif ($value == "DirMarseille" or $value == "Nice vers Marseille" or $value == "Nice/Toulon") {
            return "MARSEILLE";
        }         
    }
    return "BOTH";
}

//analyze content text to know if it's open, close, a programmed closure, information about dense traffic or an information  
function getStateFromTextTweet($textTweet) {
    $textTweet = strtolower($textTweet);
    /*
    OUVERT :     
    Fin de la régulation trafic 
    FIN du ralentissement
    réouverture
    Fin de la régulation
    Circulation fluide
    Aucun événement
    Fin Neutralisation
    circulation rétablie
    */
    
    //check if text tweet contain 'réouverture' 'fin' 'circulation fluide' etc...
    if (!empty(stristr($textTweet, "fin ")) or !empty(stristr($textTweet, "circulation fluide")) or 
    !empty(stristr($textTweet, "réouverture")) or !empty(stristr($textTweet, "aucun événement")) or
    !empty(stristr($textTweet, "accident terminé")) or !empty(stristr($textTweet, "circulation rétablie")) ) {
        return "OPEN";
    }

    /*
    FERMER :    
    Fermeture de l’accès n°17 L.Bourgeois +  #TunnelToulon est fermé jusqu'à 06h00 pour #Travaux.       
    Fermeture de 21h00 à 6h00 dans le sens Marseille/Toulon    
    Fermeture du #TunnelToulon dans le sens Marseille/Toulon pour #Travaux de 21h00 à 06h00.
    Dir. Nice Fermeture du #TunnelToulon pour régulation
    Dir Nice fermeture du #TunnelToulon pour travaux.
    *************************************************    
    fermeture sans ce pattern (XXhXX à XXhXX) = fermeture actuelle
    */
    $regExHourToHour = "/[0-9]{1,2}(h|H)[0-9]{1,2}\sà\s[0-9]{1,2}(h|H)[0-9]{1,2}/";
    if (!empty(stristr($textTweet, "fermeture")) and preg_match($regExHourToHour, $textTweet) == 0) {
        return "CLOSE";
    }
    //fermeture + XXhXX à XXhXX = fermeture à prévoir
    if (!empty(stristr($textTweet, "fermeture")) and preg_match($regExHourToHour, $textTweet) == 1) {
        return "PROGRAMMED CLOSURE";
    }

    /*
    TRAFIC DENSE :
    ralentissement
    Ralentissement
    neutralisation de la voie de droite
    Neutralisation voie de gauche
    une voie pour régulation du trafic 
    accident      => prudence
    une voie & régulation
    Trafic chargé 
    */
    if (!empty(stristr($textTweet, "ralentissement")) or 
    (!empty(stristr($textTweet, "neutralisation")) and !empty(stristr($textTweet, "voie de "))) or
    (!empty(stristr($textTweet, "une voie")) and !empty(stristr($textTweet, "régulation"))) or
    !empty(stristr($textTweet, "accident")) or 
    (!empty(stristr($textTweet, "trafic")) and (!empty(stristr($textTweet, "chargé")) or (!empty(stristr($textTweet, "dense")))))) {
        return "DENSE TRAFFIC";
    }

    //if nothing match it's probably an information tweet 
    return "INFORMATION";
}

//add 2 hours to have the right time in france
function getDateTweet($timeTweet) {
    //delete "+0000" from the date
    $timeTweet = preg_replace("/[+]\d*\s/", "", $timeTweet);
    //get nb hour in $output_array
    preg_match("/\s\d{2}:/", $timeTweet, $output_array);
    $nbInitial = $output_array[0];
    $nb = str_replace(" ","",$nbInitial);
    $nb = str_replace(":","",$nb);
    if ($nb < 21) {
        $nb += 2;
    }
    //TODO change day 
    elseif ($nb == 22) {
        $nb = 00;
    }
    elseif ($nb == 23) {
        $nb = 01;
    }    
    $nbNew = " ".$nb.":";
    return str_replace($nbInitial, $nbNew,$timeTweet);    
}
?>