<?php 
$file='stories.json';
$outfile='intents.json';

if (file_exists($file)==false) {
	throw new Exception($file.' does not exist');
}
$data=file_get_contents($file);
$out=array();
$stories=json_decode($data);
echo "Detected ".count($stories)." BotEngine stories";

// TODO merge media1 e media2 in media

function findInteractionById($id) {
	global $stories;
	foreach($stories as $s) {
		if ($s->id==$id) {
			return $s;
		}
	}
	return null;
}

function handleResponse($response, &$a) {
	switch($response->type) {
		case 'text': 
			if (!empty($response->elements)) {
				$a['messages'][]=[
					'text' => [
						'text' => [
							$response->elements[0]
						]
					]
				];
			}
			break;
		case 'cards':
			foreach($response->elements as $element) {
				$a['messages'][]=[
					'text' => [
						'text' => [
							'CARD '.$element->title
						]
					]
				];
			}
			break;
		case 'card':
			$a['messages'][]=[
				'text' => [
					'text' => [
						'CARD '.$response->title
					]
				]
			];
			break;
		case 'goto':
			$s=findInteractionById($response->interactionId);
			if ($s!=null) {
				handleResponses($s->responses, $a);				
			}
			break;
	}
}

function handleResponses($responses, &$a) {
	foreach($responses as $response) {
		handleResponse($response, $a);
	}
}

foreach($stories as $story) {
	if (!empty($story->userSays)) {
		$a=array(
			'display_name' => $story->name,
			'trainingPhrases' => [],
			'messages' => [],
			'events' => [$story->id],
			'parameters' => [
				[
					'displayName' => 'botengineid',
					'value' => $story->id,
				]
			]
		);
		foreach($story->userSays as $userSay) {
			$a['trainingPhrases'][]=array(
				'type' => 'EXAMPLE',
				'parts' => ['text' => $userSay]
			);
		}
		handleResponses($story->responses, $a);
		// foreach($story->responses as $response) {
		// 	handleResponse($response, $a);
		// }
		$out[]=$a;		
	}
}
file_put_contents($outfile, json_encode(['intentBatchInline' => ['intents' => $out]]));
?>