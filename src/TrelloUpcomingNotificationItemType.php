<?php

namespace cjrasmussen\TrelloUpcomingNotification;

use RuntimeException;

class TrelloUpcomingNotificationItemType
{
	public const NOTIFICATION_ITEM_TYPE_TODAY = 1;
	public const NOTIFICATION_ITEM_TYPE_OVERDUE = 2;
	public const NOTIFICATION_ITEM_TYPE_UPCOMING = 3;

	private const NOTIFICATION_ITEM_TYPE_NAMES = [
		self::NOTIFICATION_ITEM_TYPE_TODAY => 'Due Today',
		self::NOTIFICATION_ITEM_TYPE_OVERDUE => 'Overdue',
		self::NOTIFICATION_ITEM_TYPE_UPCOMING => 'Upcoming',
	];

	/**
	 * Get the default item type
	 *
	 * @return int
	 */
	public static function getDefaultType(): int
	{
		return self::NOTIFICATION_ITEM_TYPE_TODAY;
	}

	/**
	 * Determine if the supplied item type is valid
	 *
	 * @param int $itemType
	 * @return bool
	 */
	public static function isValidType(int $itemType): bool
	{
		return array_key_exists($itemType, self::NOTIFICATION_ITEM_TYPE_NAMES);
	}

	/**
	 * Get the name for the given item type
	 *
	 * @param int $itemType
	 * @return string
	 */
	public static function getItemTypeName(int $itemType): string
	{
		if (!self::isValidType($itemType)) {
			$msg = 'Item Type ' . $itemType . ' not implemented.';
			throw new RuntimeException($msg);
		}

		return self::NOTIFICATION_ITEM_TYPE_NAMES[$itemType];
	}

	/**
	 * Get an array of valid item types
	 *
	 * @return array
	 */
	public static function getValidItemTypes(): array
	{
		return array_keys(self::NOTIFICATION_ITEM_TYPE_NAMES);
	}
}
