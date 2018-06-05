<?php
require 'vendor/autoload.php';
require 'dialogflow.inc.php';

use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\QueryInput;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Google\Cloud\Core\ServiceBuilder;

putenv('GOOGLE_APPLICATION_CREDENTIALS='.CREDENTIALS_FILE);

$jsonResponse = [];
try {
	
	// parse BotEngine.ai request
	$botEngineParser = new BotEngineParser(BOTENGINE_TOKEN, [BOTENGINE_USERSAY_ATTRIBUTE]);
	$botEngineParser->parseRequest();
	$text=$botEngineParser->getParameter(BOTENGINE_USERSAY_ATTRIBUTE);
	$sessionId=$botEngineParser->getSessionId();
	
	// handle intent detection through DialogFlow
	$dialogflowProxy = new DialogFlowProxy(DIALOGFLOW_PROJECT, BOTENGINE_FALLBACK_ID);	
	$jsonResponse = ['responses' => $dialogflowProxy->detectIntent($text, $sessionId)];
	
} catch(Exception $e) {
	BotEngineParser::$log->error($e->getMessage());
	$jsonResponse=['responses' => [[ 'type' => 'text', 'elements' => array('ERROR - '.$e->getMessage())]]];
}

header('Content-Type: application/json');
echo json_encode($jsonResponse);

class DialogFlowProxy extends BaseLoggerEnabled {
	
	private $project;
	private $languageCode = 'en-US';
	private $fallbackInteractionId;
	
	function __construct($project, $fallbackInteractionId) {
		parent::__construct();
		$this->project = $project;
		$this->fallbackInteractionId=$fallbackInteractionId;
	}
	
	public function detectIntent($text, $sessionId) {
		self::$log->info('Retrieving intent for text "'.$text.'"');
		$sessionsClient = new SessionsClient();
		$session = $sessionsClient->sessionName($this->project, $sessionId ?: uniqid());
		$textInput = new TextInput();
		$textInput->setText($text);
		$textInput->setLanguageCode($this->languageCode);

		$queryInput = new QueryInput();
		$queryInput->setText($textInput);

		$response=$sessionsClient->detectIntent($session, $queryInput);
		$sessionsClient->close();
		return $this->handleResponse($response);
	}
	
	private function handleResponse($response) {
		
		$queryResult = $response->getQueryResult();
		$intent = $queryResult->getIntent();
		$confidence = $queryResult->getIntentDetectionConfidence();
		$intentParameters=json_decode($queryResult->getParameters()->serializeToJsonString(), true); // workaround
		
		if (!empty($intentParameters['botengineid'])) { // botengine interaction available
			self::$log->info('Detected BotEngine interaction '.$intentParameters['botengineid']);
			return [[ 'type' => 'goto', 'interactionId' => $intentParameters['botengineid']]];
		} else { // botengine interaction not available
			switch($queryResult->getAction()) {
				case 'input.unknown':
				self::$log->info('Detected DialogFlow fallback intent');
				return [
					[ 'type' => 'text', 'elements' => [$queryResult->getFulfillmentText()]],
					[ 'type' => 'goto', 'interactionId' => $this->fallbackInteractionId]
				];
				break;
				default: 
				self::$log->info('Returning DialogFlow fulfillment text');
				return [[ 'type' => 'text', 'elements' => array($queryResult->getFulfillmentText())]];
			}
		}
	}
}

class BotEngineParser extends BaseLoggerEnabled {

	private $mandatoryFields;
	private $parameters = [];
	private $sessionId;
	private $token;

	function __construct($token, array $mandatoryFields) {
		parent::__construct();
		$this->mandatoryFields = $mandatoryFields;
		$this->token = $token;
	}

	public function parseRequest() {
		$this->authBotEngineAuthRequest();
		$rawdata=file_get_contents('php://input');
		$data=json_decode($rawdata, true);
		$parameters=$data['result']['parameters'];
		$requestparams=array();

		// check mandatory fields
		foreach($this->mandatoryFields as $field) {
			if (empty($parameters[$field])) {
				throw new Exception('Mandatory field '.$field.' not provided');
			}
			$this->parameters[$field]=$parameters[$field];
		}
		$this->sessionId = $data['sessionId'];
	}

	private function authBotEngineAuthRequest() {
		if ($_GET['token'] !== $this->token) {
			self::$log->error('Wrong token');
			header('HTTP/1.0 401 Unauthorized');
			exit();
		}

		// verification request
		if ($_SERVER['REQUEST_METHOD'] === 'GET') {
			exit($_GET['challenge']);
		}
	}
	
	public function getParameter($param) {
		if (empty($this->parameters[$param])) throw new Exception('Parameter '.$param.' not available');
		return $this->parameters[$param];
	}
	
	public function getSessionId() {
		return $this->sessionId;
	}
}

abstract class BaseLoggerEnabled {
	
	public static $log;
	
	function __construct() {
		self::$log = new Logger('DialogFlowProxy');
		self::$log->pushHandler(new StreamHandler(LOGFILE, Logger::INFO));
	}
}

?>
