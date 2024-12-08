<?php

namespace cjrasmussen\TrelloUpcomingNotification;

use cjrasmussen\SlackApi\SlackApi;
use RuntimeException;

class TrelloUpcomingNotificationResponse
{
	private SlackApi $slackApi;

	/**
	 * @var TrelloUpcomingNotificationItem[]
	 */
	private array $items = [];
	private array $cache = [];

	public function __construct(SlackApi $slackApi)
	{
		$this->slackApi = $slackApi;
	}

	/**
	 * Add an item to the notification response
	 *
	 * @param TrelloUpcomingNotificationItem $item
	 * @return void
	 */
	public function addItem(TrelloUpcomingNotificationItem $item): void
	{
		$this->items[] = $item;
		$this->cache = [];
	}

	/**
	 * Does the notification response have items
	 *
	 * @return bool
	 */
	public function hasItems(): bool
	{
		return count($this->items) > 0;
	}

	/**
	 * Does the notification response have items of the specified item type
	 *
	 * @param int $itemType
	 * @return bool
	 */
	public function hasItemType(int $itemType): bool
	{
		return $this->getItemCountByItemType($itemType) > 0;
	}

	/**
	 * Get the item types that are present in the response
	 *
	 * @return array
	 */
	public function getPresentItemTypes(): array
	{
		if (!array_key_exists('present_item_types', $this->cache)) {
			$this->cache['present_item_types'] = array_map(static function ($item) {
				return $item->getType();
			}, $this->items);

			$this->cache['present_item_types'] = array_unique($this->cache['present_item_types']);
		}

		return $this->cache['present_item_types'];
	}

	/**
	 * Get the number of items present in the response for the specified item type
	 *
	 * @param int $itemType
	 * @return int
	 */
	public function getItemCountByItemType(int $itemType): int
	{
		return count($this->getItemsByItemType($itemType));
	}

	/**
	 * Get the items present in the response for the specified item type
	 *
	 * @param int $itemType
	 * @return TrelloUpcomingNotificationItem[]
	 */
	public function getItemsByItemType(int $itemType): array
	{
		if (!TrelloUpcomingNotificationItemType::isValidType($itemType)) {
			$msg = 'Item Type ' . $itemType . ' not implemented.';
			throw new RuntimeException($msg);
		}

		if (!array_key_exists($itemType, $this->cache)) {
			$this->cache[$itemType] = array_filter($this->items, static function ($item) use ($itemType) {
				return ($item->getType() === $itemType);
			});
		}

		return $this->cache[$itemType];
	}

	/**
	 * Send a Slack notification based on this TrelloUpcomingNotificationResponse data
	 *
	 * @param string|null $channel
	 * @param string|null $overdueMention
	 * @return bool|null
	 */
	public function sendNotification(?string $channel = null, ?string $overdueMention = null): ?bool
	{
		if (!$this->hasItems()) {
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

		if (($overdueMention) && ($this->hasItemType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_OVERDUE))) {
			$blocks[] = (object)[
				'type' => 'section',
				'text' => (object)[
					'type' => 'mrkdwn',
					'text' => '<' . $overdueMention . '>',
				],
			];
		}

		$presentItemType = $this->getPresentItemTypes();
		foreach ($presentItemType AS $itemType) {
			$itemCount = $this->getItemCountByItemType($itemType);
			$items = $this->getItemsByItemType($itemType);

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