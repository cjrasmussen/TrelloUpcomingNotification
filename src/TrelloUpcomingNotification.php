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

	/**
	 * @param TrelloApi $trelloApi
	 * @param SlackApi $slackApi
	 */
	public function __construct(TrelloApi $trelloApi, SlackApi $slackApi)
	{
		$this->trelloApi = $trelloApi;
		$this->slackApi = $slackApi;
	}

	/**
	 * Process the Trello data and build a TrelloUpcomingNotificationResponse based on it
	 *
	 * @param array $check_lists
	 * @param array|string|null $ignore_labels
	 * @param string|null $upcoming_date
	 * @param string|null $check_date
	 *
	 * @return void
	 * @throws Exception
	 */
	public function buildNotification(array $check_lists, $ignore_labels = null, ?string $upcoming_date = null, ?string $check_date = null): TrelloUpcomingNotificationResponse
	{
		if (is_null($ignore_labels)) {
			$ignore_labels = [];
		} elseif (!is_array($ignore_labels)) {
			$ignore_labels = [(string)$ignore_labels];
		}

		if (is_numeric($check_date)) {
			$check_date = '@' . $check_date;
		}

		if (is_numeric($upcoming_date)) {
			$upcoming_date = '@' . $upcoming_date;
		}

		try {
			$checkDate = ($check_date) ? new DateTime($check_date) : new DateTime();
		} catch (Exception $e) {
			$checkDate = new DateTime();
		}

		$upcomingDate = null;
		if ($upcoming_date) {
			$upcomingDate = clone $checkDate;
			$upcomingDate->modify($upcoming_date);
		}

		$output = new TrelloUpcomingNotificationResponse($this->slackApi);

		foreach ($check_lists AS $list_id) {
			// GET THE CARD DATA FOR THE LIST
			$data = $this->trelloApi->request('GET', ('/1/lists/' . $list_id . '/cards'));

			foreach ($data AS $card) {
				if ((!$card->due) || (count(array_intersect($ignore_labels, $card->idLabels))) > 0) {
					continue;
				}

				$dueDate = new DateTime($card->due);

				$item = new TrelloUpcomingNotificationItem($card->name, $card->url, $dueDate);

				if ($dueDate->format('Y-m-d') === $checkDate->format('Y-m-d')) {
					$item->setType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_TODAY);
					$output->addItem($item);
				} elseif ($dueDate < $checkDate) {
					$item->setType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_OVERDUE);
					$output->addItem($item);
				} elseif (($upcomingDate) && ($dueDate < $upcomingDate)) {
					$item->setType(TrelloUpcomingNotificationItemType::NOTIFICATION_ITEM_TYPE_UPCOMING);
					$output->addItem($item);
				}
			}
		}

		return $output;
	}
}
