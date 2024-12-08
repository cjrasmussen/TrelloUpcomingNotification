<?php

namespace cjrasmussen\TrelloUpcomingNotification;

use DateTime;
use RuntimeException;

class TrelloUpcomingNotificationItem
{
	private string $title;
	private string $url;
	private DateTime $dueDate;
	private int $type;

	public function __construct(string $title, string $url, DateTime $dueDate, ?int $type = null)
	{
		$this->title = $title;
		$this->url = $url;
		$this->dueDate = $dueDate;
		$this->setType($type);
	}

	/**
	 * Get the item title
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->title;
	}

	/**
	 * Get the item URL
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * Get the item due date
	 *
	 * @return DateTime
	 */
	public function getDueDate(): DateTime
	{
		return $this->dueDate;
	}

	/**
	 * Get the item type
	 *
	 * @return int
	 */
	public function getType(): int
	{
		return $this->type;
	}

	/**
	 * Set the item type
	 *
	 * @param int|null $type
	 * @return void
	 */
	public function setType(?int $type = null): void
	{
		if ($type) {
			if (TrelloUpcomingNotificationItemType::isValidType($type)) {
				$this->type = $type;
			} else {
				$msg = 'Item Type ' . $type . ' not implemented.';
				throw new RuntimeException($msg);
			}
		} else {
			$this->type = TrelloUpcomingNotificationItemType::getDefaultType();
		}
	}
}
