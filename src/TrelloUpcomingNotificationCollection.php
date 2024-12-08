<?php

namespace cjrasmussen\TrelloUpcomingNotification;

use RuntimeException;

class TrelloUpcomingNotificationCollection
{
	/**
	 * @var TrelloUpcomingNotificationItem[]
	 */
	private array $items = [];
	private array $cache = [];

	/**
	 * Add an item to the notification collection
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
	 * Does the notification collection have items
	 *
	 * @return bool
	 */
	public function hasItems(): bool
	{
		return count($this->items) > 0;
	}

	/**
	 * Does the notification collection have items of the specified item type
	 *
	 * @param int $itemType
	 * @return bool
	 */
	public function hasItemType(int $itemType): bool
	{
		return $this->getItemCountByItemType($itemType) > 0;
	}

	/**
	 * Get the item types that are present in the collection
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
	 * Get the number of items present in the collection for the specified item type
	 *
	 * @param int $itemType
	 * @return int
	 */
	public function getItemCountByItemType(int $itemType): int
	{
		return count($this->getItemsByItemType($itemType));
	}

	/**
	 * Get the items present in the collection for the specified item type
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
}