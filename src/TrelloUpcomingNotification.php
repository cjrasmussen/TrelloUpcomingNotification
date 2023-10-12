<?php

namespace cjrasmussen\TrelloUpcomingNotification;

use cjrasmussen\SlackApi\SlackApi;
use cjrasmussen\TrelloApi\TrelloApi;

class TrelloUpcomingNotification
{
	private const NOTIFICATION_TYPE_TODAY = 'today';
	private const NOTIFICATION_TYPE_OVERDUE = 'overdue';

	private const NOTIFICATION_TYPE_NAMES = [
		self::NOTIFICATION_TYPE_TODAY => 'Due Today',
		self::NOTIFICATION_TYPE_OVERDUE => 'Overdue',
	];

	private array $checkLists;
	private array $ignoreLabels;
	private string $checkDate;
	private array $notification = [
		self::NOTIFICATION_TYPE_TODAY => [],
		self::NOTIFICATION_TYPE_OVERDUE => [],
	];

	/**
	 * @param array $checkLists
	 * @param array|string|null $ignoreLabels
	 * @param string|null $checkDate
	 */
	public function __construct(array $checkLists, $ignoreLabels = null, ?string $checkDate = null)
	{
		$this->checkLists = $checkLists;

		if (is_array($ignoreLabels)) {
			$this->ignoreLabels = $ignoreLabels;
		} elseif ($ignoreLabels) {
			$this->ignoreLabels = [$ignoreLabels];
		} else {
			$this->ignoreLabels = [];
		}

		$this->checkDate = ($checkDate) ? date('Y-m-d', strtotime($checkDate)) : date('Y-m-d');
	}

	public function executeCheck(TrelloApi $trelloApi): void
	{
		foreach ($this->checkLists AS $list_id) {
			// GET THE CARD DATA FOR THE LIST
			$data = $trelloApi->request('GET', ('/1/lists/' . $list_id . '/cards'));

			foreach ($data AS $card) {
				if ((!$card->due) || (count(array_intersect($this->ignoreLabels, $card->idLabels))) > 0) {
					continue;
				}

				$due_date = date('Y-m-d', strtotime($card->due));
				if ($due_date === $this->checkDate) {
					$this->notification[self::NOTIFICATION_TYPE_TODAY][] = [
						'title' => $card->name,
						'url' => $card->url,
					];
				} elseif ($due_date < $this->checkDate) {
					$this->notification[self::NOTIFICATION_TYPE_OVERDUE][] = [
						'title' => $card->name,
						'url' => $card->url,
						'due_date' => date('n/j/Y', strtotime($card->due)),
					];
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isNotificationAvailable(): bool
	{
		return ((count($this->notification[self::NOTIFICATION_TYPE_TODAY]) > 0) || (count($this->notification[self::NOTIFICATION_TYPE_OVERDUE]) > 0));
	}

	/**
	 * @param SlackApi $slackApi
	 * @param string|null $channel
	 * @return bool|null
	 */
	public function sendSlackNotification(SlackApi $slackApi, ?string $channel = null): ?bool
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

		foreach ($this->notification AS $notification_type => $notification_items) {
			$item_count = count($notification_items);

			if ($item_count) {
				$item_count_word = 'item' . (($item_count === 1) ? '' : 's');

				$blocks[] = (object)[
					'type' => 'header',
					'text' => (object)[
						'type' => 'plain_text',
						'text' => self::NOTIFICATION_TYPE_NAMES[$notification_type] . ' (' . $item_count . ' ' . $item_count_word . ')',
					],
				];

				foreach ($notification_items AS $item) {
					$blocks[] = (object)[
						'type' => 'section',
						'text' => (object)[
							'type' => 'mrkdwn',
							'text' => '<' . $item['url'] . '|' . $item['title'] . '>',
						],
					];
					if (array_key_exists('due_date', $item)) {
						$blocks[] = (object)[
							'type' => 'context',
							'elements' => [
								(object)[
									'type' => 'mrkdwn',
									'text' => '*Due Date:* ' . $item['due_date'],
								],
							],
						];
					}
				}
			}
		}

		$msg = [
			'blocks' => $blocks,
		];

		if ($channel) {
			$msg['channel'] = $channel;
		}

		return $slackApi->sendMessage($msg);
	}
}
