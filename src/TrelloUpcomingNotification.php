<?php

namespace cjrasmussen\TrelloUpcomingNotification;

use cjrasmussen\SlackApi\SlackApi;
use cjrasmussen\TrelloApi\TrelloApi;
use DateTime;
use Exception;

class TrelloUpcomingNotification
{
	private TrelloApi $trelloApi;
	private SlackApi $slackApi;
	private array $checkLists;
	private array $ignoreLabels;
	private ?DateTime $upcomingDate = null;
	private DateTime $checkDate;
	private TrelloUpcomingNotificationCollection $collection;

	/**
	 * @param TrelloApi $trelloApi
	 * @param SlackApi $slackApi
	 * @param array $checkLists
	 * @param array|string|null $ignoreLabels
	 * @param string|null $upcomingDate
	 * @param string|null $checkDate
	 */
	public function __construct(TrelloApi $trelloApi, SlackApi $slackApi, array $checkLists, $ignoreLabels = null, ?string $upcomingDate = null, ?string $checkDate = null)
	{
		$this->trelloApi = $trelloApi;
		$this->slackApi = $slackApi;
		$this->checkLists = $checkLists;

		if (is_array($ignoreLabels)) {
			$this->ignoreLabels = $ignoreLabels;
		} elseif ($ignoreLabels) {
			$this->ignoreLabels = [$ignoreLabels];
		} else {
			$this->ignoreLabels = [];
		}

		if (is_numeric($checkDate)) {
			$checkDate = '@' . $checkDate;
		}

		if (is_numeric($upcomingDate)) {
			$upcomingDate = '@' . $upcomingDate;
		}

		try {
			$this->checkDate = ($checkDate) ? new DateTime($checkDate) : new DateTime();
		} catch (Exception $e) {
			$this->checkDate = new DateTime();
		}

		if ($upcomingDate) {
			$this->upcomingDate = clone $this->checkDate;
			$this->upcomingDate->modify($upcomingDate);
		}

		$this->collection = new TrelloUpcomingNotificationCollection();
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function executeCheck(): void
	{
		foreach ($this->checkLists AS $list_id) {
			// GET THE CARD DATA FOR THE LIST
			$data = $this->trelloApi->request('GET', ('/1/lists/' . $list_id . '/cards'));

			foreach ($data AS $card) {
				if ((!$card->due) || (count(array_intersect($this->ignoreLabels, $card->idLabels))) > 0) {
					continue;
				}

				$dueDate = new DateTime($card->due);

				$item = new TrelloUpcomingNotificationItem($card->name, $card->url, $dueDate);

				if ($dueDate->format('Y-m-d') === $this->checkDate->format('Y-m-d')) {
					$item->setType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_TODAY);
					$this->collection->addItem($item);
				} elseif ($dueDate < $this->checkDate) {
					$item->setType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_OVERDUE);
					$this->collection->addItem($item);
				} elseif (($this->upcomingDate) && ($dueDate < $this->upcomingDate)) {
					$item->setType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_UPCOMING);
					$this->collection->addItem($item);
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isNotificationAvailable(): bool
	{
		return $this->collection->hasItems();
	}

	/**
	 * @param string|null $channel
	 * @param string|null $overdueMention
	 * @return bool|null
	 */
	public function sendSlackNotification(?string $channel = null, ?string $overdueMention = null): ?bool
	{
		if (!$this->isNotificationAvailable()) {
			return null;
		}

		$blocks = [
			(object)[
				'type' => 'header',
				'text' => (object)[
					'type' => 'plain_text',
					'text' => 'Upcoming Due Trello Tasks',
				],
			],
			(object)[
				'type' => 'divider',
			],
		];

		if (($overdueMention) && ($this->collection->hasItemType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_OVERDUE))) {
			$blocks[] = (object)[
				'type' => 'section',
				'text' => (object)[
					'type' => 'mrkdwn',
					'text' => '<' . $overdueMention . '>',
				],
			];
		}

		$presentItemType = $this->collection->getPresentItemTypes();
		foreach ($presentItemType AS $itemType) {
			$itemCount = $this->collection->getItemCountByItemType($itemType);
			$items = $this->collection->getItemsByItemType($itemType);

			$item_count_word = 'item' . (($itemCount === 1) ? '' : 's');

			$blocks[] = (object)[
				'type' => 'header',
				'text' => (object)[
					'type' => 'plain_text',
					'text' => TrelloUpcomingNotificationItemType::getItemTypeName($itemType) . ' (' . $itemCount . ' ' . $item_count_word . ')',
				],
			];

			foreach ($items AS $item) {
				$blocks[] = (object)[
					'type' => 'section',
					'text' => (object)[
						'type' => 'mrkdwn',
						'text' => '<' . $item->getUrl() . '|' . $item->getTitle() . '>',
					],
				];
				if (in_array($itemType, [TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_OVERDUE, TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_UPCOMING], true)) {
					$blocks[] = (object)[
						'type' => 'context',
						'elements' => [
							(object)[
								'type' => 'mrkdwn',
								'text' => '*Due Date:* ' . $item->getDueDate()->format('n/j/Y'),
							],
						],
					];
				}
			}
		}

		$msg = [
			'blocks' => $blocks,
		];

		if ($channel) {
			$msg['channel'] = $channel;
		}

		return $this->slackApi->sendMessage($msg);
	}
}
