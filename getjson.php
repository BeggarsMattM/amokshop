<?php

//echo "Page start: " . xdebug_time_index() . "<br/>";

require('config.php');
require_once('twitter-api-php/TwitterAPIExchange.php');

$blacklist = array('abcrn', 'boobs');

function get_data($url, $key=null)
{
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	if ($key) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('vine-session-id: '.$key));
	}
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

function post_data($url, $credentials)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $credentials);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}
 
$tag = isset($_GET['tag'])? filter_input(INPUT_GET, 'tag', FILTER_SANITIZE_STRING) : "drawingroom";
$api = filter_input(INPUT_GET, 'api', FILTER_SANITIZE_STRING);

if ($api === 'vine') {

	$vine = "https://api.vineapp.com/users/authenticate";
	$result = json_decode(post_data($vine, $vine_credentials)); 
	$key = $result->data->key;
	$vine2 = "https://api.vineapp.com/timelines/tags/$tag";
	$result = json_decode(get_data($vine2, $key));

	//var_dump($result->data->records); die;

	foreach ($result->data->records as $record)
	{
		$video_tag = "<video class='video' width='250' height='250' src='" . $record->videoUrl . "'></video>";
		$results[] = array('text'=>$video_tag, 'created'=>date('M d H:i:s', strtotime($record->created)), 
			'url'=>$record->shareUrl, 'ts'=>strtotime($record->created));
	}

	//echo "Vine loaded: " . xdebug_time_index() . "<br/>";

} elseif ($api === 'instagram') {

	function instagram_page($next) {

		global $results, $tag, $instagram_client_id;

		$inst =  "https://api.instagram.com/v1/tags/$tag/media/recent?client_id="
				. $instagram_client_id;
		if ($next) $inst .= "&max_id=" . $next;

		$result = json_decode(get_data($inst));

		$next = $result->pagination->next_max_tag_id;

		foreach ($result->data as $photo)
		{
			$img_tag = "<img src='" . $photo->images->standard_resolution->url . "'>";
			$results[] = array('text'=>$img_tag, 'created'=>date('M d H:i:s', $photo->created_time), 
				'url'=>$photo->link, 'ts'=>$photo->created_time);
		}

		return $next;
	}

	$timestodo = 3;
	$next = false;

	while ($timestodo > 0) {
		--$timestodo;
		$next = instagram_page($next, $tag, $instagram_client_id);
	}

	//echo "Instagram loaded: " . xdebug_time_index() . "<br/>";

} elseif ($api === 'facebook') {

	$fb =  'https://graph.facebook.com/search?q='
		  ."%23$tag&type=post&access_token=$fb_access_token";
	$result = json_decode(get_data($fb));

	foreach ($result->data as $message)
	{	
		if (isset($message->picture))
			$text = "<img src='" . $message->picture . "'>";
		else
			$text = '';

		if (isset($message->message))
			$text .= $message->message;
		elseif (isset($message->description))
			$text .= $message->description;
		elseif (isset($message->story))
			$text .= $message->story;
		/* else 
			{ var_dump($message); throw new Exception("Unknown FaceBook message type"); } */

		$kosher = true;
		foreach ($blacklist as $badword) {
			if (strpos($text, $badword) !== false) $kosher = false;
		}
		if ($kosher) {
			if (strlen($text)>500) $text = substr($text, 0, 500) . "...";
			$results[] = array('text'=>$text, 'created'=>date('M d H:i:s', strtotime($message->created_time)), 
				'url'=>"https://www.facebook.com/" . $message->id, 'ts'=>strtotime($message->created_time));
		}
	}

	//echo "FaceBook loaded: " . xdebug_time_index() . "<br/>";

} else {

	$settings = $twitter_settings;

	$url = 'https://api.twitter.com/1.1/search/tweets.json';
	$request_method = 'GET';

	$getfields = "?q=%23$tag+exclude:retweets";

	$twitter = new TwitterAPIExchange($settings);
	$result = $twitter->setGetField($getfields)
				 	  ->buildOAuth($url, $request_method)
				 	  ->performRequest();

	$result = json_decode($result);

	foreach ($result->statuses as $status) 
	{
		$kosher = true;
		foreach ($blacklist as $badword)
		{
			if (strpos($status->text, $badword) !== false) $kosher = false;
		}
		if ($kosher) {
			$results[] = array('text'=>$status->text, 'created'=>date('M d H:i:s', strtotime($status->created_at)),
				'url'=>'https://twitter.com/' . $status->user->screen_name . '/status/' . $status->id,
				'ts'=>strtotime($status->created_at));
		}
	}

	//echo "Twitter loaded: " . xdebug_time_index() . "<br/>";

}

/*function cmp($a, $b)
{
	if ($a['created'] == $b['created']) {
		return 0;
	}
	return ($a['created'] < $b['created']) ? 1 : -1;
}*/

if (!empty($results)) 
{
	//usort($results, "cmp");
	echo json_encode($results);
} else {
	//echo "No results found.";
	echo json_encode(array());
}

//echo "Results sorted: " . xdebug_time_index() . "<br/>";
