# TrelloUpcomingNotification

Simple class for checking one or more Trello lists for due or overdue cards, then sending a message about them via a Slack webhook. Not affiliated with Trello or Slack.


## Usage

```php
use cjrasmussen\SlackApi\SlackApi;
use cjrasmussen\TrelloApi\TrelloApi;
use cjrasmussen\TrelloUpcomingNotification\TrelloUpcomingNotification;

$trello_lists = [
	'123456789012345678901234', // In Progress
	'098765432109876543210987', // Not Started
];
$trello_ignore_label = 'asdfghjklpoiuytrewqzxcvb';

$trelloNotification = new TrelloUpcomingNotification($trello_lists, $trello_ignore_label);

$trello = new TrelloApi($key, $token);
$trelloNotification->executeCheck($trello);
if ($trelloNotification->isNotificationAvailable()) {
	$slack = new SlackApi($slack_webhook_url);
	$trelloNotification->sendSlackNotification($slack);
}
```

## Installation

Simply add a dependency on cjrasmussen/trello-upcoming-notification to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require cjrasmussen/trello-upcoming-notification
```

TrelloUpcomingNotification has dependencies on `cjrasmussen\SlackApi` and `cjrasmussen\TrelloApi` so these will be installed as well.

Although it's recommended to use Composer, you can actually include the file(s) any way you want.


## License

TrelloUpcomingNotification is [MIT](http://opensource.org/licenses/MIT) licensed.