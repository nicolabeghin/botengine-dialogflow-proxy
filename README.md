# botengine-dialogflow-proxy
botengine.ai intent-detection integration with Google's DialogFlow

## What do you get

This script acts as a proxy for intent detection through Google's DialogFlow. You get

* advanced NLP/AI behavior
* automatic small-talk management
* multi-language management
* more to come

Please note: DialogFlow's used for intent-detection only, all the rest is handled by BotEngine.ai since it has better end-user management (images/conversation logs/messages delay).

## How to use
* create, train and maintain your Google DialogFlow intents
* make sure each intent provides a parameter called `botengineid` with value = `YOUR_BOTENGINE_INTERACTION_ID`
* clone the repository and install PHP dependencies with `composer`
* fill-in the required parameters in `dialogflow.inc.php`
* set-up a BotEngine.ai webhook so that it invokes the `dialogflow.php` script


### Example `dialogflow.inc.php`
```
<?php
DEFINE('LOGFILE', 'dialogflow.log');
DEFINE('BOTENGINE_TOKEN', '');
DEFINE('BOTENGINE_FALLBACK_ID', '');
DEFINE('BOTENGINE_USERSAY_ATTRIBUTE', 'userSay');
DEFINE('DIALOGFLOW_CREDENTIALS_FILE', 'yourproject.json');
DEFINE('DIALOGFLOW_PROJECT', '');
?>
```
