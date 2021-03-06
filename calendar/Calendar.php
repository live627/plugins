<?php
/**
 * Provides functionality for showing the calendar contents.
 *
 * Wedge (http://wedge.org)
 * Copyright � 2010 Ren�-Gilles Deberdt, wedge.org
 * Portions are � 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

/* Original module by Aaron O'Neil - aaron@mud-master.com */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file has only one real task... showing the calendar. Posting is done
	in Post.php - this just has the following functions:

	void CalendarMain()
		- loads the specified month's events and holidays.
		- requires the calendar_view permission.
		- depends on many of the other cal_ settings.
		- uses the calendar_start_day theme option. (Monday/Sunday)
		- uses the main block in the Calendar template.
		- goes to the month and year passed in 'month' and 'year' by
		  get or post.
		- accessed through ?action=calendar.

	void CalendarPost()
		- processes posting/editing/deleting a calendar event.
		- calls Post() function if event is linked to a post.
		- calls insertEvent() to insert the event if not linked to post.
		- requires the calendar_post permission to use.
		- uses the event_post block in the Calendar template.
		- is accessed with ?action=calendar;sa=post.

	void iCalDownload()
		- offers up a download of an event in iCal 2.0 format.
*/

// Show the calendar.
function CalendarMain()
{
	global $txt, $context, $settings, $options;

	// Permissions, permissions, permissions.
	isAllowedTo('calendar_view');

	// Doing something other than calendar viewing?
	$subActions = array(
		'ical' => 'iCalDownload',
		'post' => 'CalendarPost',
	);

	if (isset($_GET['sa'], $subActions[$_GET['sa']]))
		return $subActions[$_GET['sa']]();

	// These are gonna be needed...
	loadPluginLanguage('Wedge:Calendar', 'lang/Calendar');
	loadPluginTemplate('Wedge:Calendar', 'Calendar');
	loadPluginSource('Wedge:Calendar', 'Subs-Calendar');

	add_plugin_css_file('Wedge:Calendar', 'css/calendar', true);

	// Set the page title to mention the calendar ;).
	$context['page_title'] = $txt['calendar'];

	// Is this a week view?
	$context['view_week'] = isset($_GET['viewweek']);

	// Don't let search engines index weekly calendar pages.
	if ($context['view_week'])
		$context['robot_no_index'] = true;

	// Get the current day of month...
	$today = getTodayInfo();

	// If the month and year are not passed in, use today's date as a starting point.
	$curPage = array(
		'day' => isset($_REQUEST['day']) ? (int) $_REQUEST['day'] : $today['day'],
		'month' => isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : $today['month'],
		'year' => isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : $today['year']
	);

	// Make sure the year and month are in valid ranges.
	if ($curPage['month'] < 1 || $curPage['month'] > 12)
		fatal_lang_error('invalid_month', false);
	if ($curPage['year'] < $settings['cal_minyear'] || $curPage['year'] > $settings['cal_maxyear'])
		fatal_lang_error('invalid_year', false);
	// If we have a day, make sure it's valid too.
	if ($context['view_week'])
		if ($curPage['day'] > 31 || mktime(0, 0, 0, $curPage['month'], $curPage['day'], $curPage['year']) === false)
			fatal_lang_error('invalid_day', false);

	// Load all the context information needed to show the calendar grid.
	$calendarOptions = array(
		'start_day' => !empty($options['calendar_start_day']) ? $options['calendar_start_day'] : 0,
		'show_events' => in_array($settings['cal_showevents'], array(1, 2)),
		'show_holidays' => in_array($settings['cal_showholidays'], array(1, 2)),
		'show_week_num' => true,
		'short_day_titles' => false,
		'show_next_prev' => true,
		'show_week_links' => true,
		'size' => 'large',
	);

	// Load up the main view.
	if ($context['view_week'])
		$context['calendar_grid_main'] = getCalendarWeek($curPage['month'], $curPage['year'], $curPage['day'], $calendarOptions);
	else
		$context['calendar_grid_main'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);

	// Load up the previous and next months.
	$calendarOptions['short_day_titles'] = true;
	$calendarOptions['show_next_prev'] = false;
	$calendarOptions['show_week_links'] = false;
	$calendarOptions['size'] = 'small';
	$context['calendar_grid_current'] = getCalendarGrid($curPage['month'], $curPage['year'], $calendarOptions);
	// Only show previous month if it isn't pre-January of the min-year
	if ($context['calendar_grid_current']['previous_calendar']['year'] > $settings['cal_minyear'] || $curPage['month'] != 1)
		$context['calendar_grid_prev'] = getCalendarGrid($context['calendar_grid_current']['previous_calendar']['month'], $context['calendar_grid_current']['previous_calendar']['year'], $calendarOptions);
	// Only show next month if it isn't post-December of the max-year
	if ($context['calendar_grid_current']['next_calendar']['year'] < $settings['cal_maxyear'] || $curPage['month'] != 12)
		$context['calendar_grid_next'] = getCalendarGrid($context['calendar_grid_current']['next_calendar']['month'], $context['calendar_grid_current']['next_calendar']['year'], $calendarOptions);

	// Basic template stuff.
	$context['can_post'] = allowedTo('calendar_post');
	$context['current_day'] = $curPage['day'];
	$context['current_month'] = $curPage['month'];
	$context['current_year'] = $curPage['year'];

	// Set the page title to mention the month or week, too
	$context['page_title'] .= ' - ' . ($context['view_week'] ? sprintf($txt['calendar_week_title'], $context['calendar_grid_main']['week_number'], ($context['calendar_grid_main']['week_number'] == 53 ? $context['current_year'] - 1 : $context['current_year'])) : $txt['months'][$context['current_month']] . ' ' . $context['current_year']);

	// Load up the linktree!
	$context['linktree'][] = array(
		'url' => '<URL>?action=calendar',
		'name' => $txt['calendar']
	);
	// Add the current month to the linktree.
	$context['linktree'][] = array(
		'url' => '<URL>?action=calendar;year=' . $context['current_year'] . ';month=' . $context['current_month'],
		'name' => $txt['months'][$context['current_month']] . ' ' . $context['current_year']
	);
	// If applicable, add the current week to the linktree.
	if ($context['view_week'])
		$context['linktree'][] = array(
			'url' => '<URL>?action=calendar;viewweek;year=' . $context['current_year'] . ';month=' . $context['current_month'] . ';day=' . $context['current_day'],
			'name' => $txt['calendar_week'] . ' ' . $context['calendar_grid_main']['week_number']
		);
}

function CalendarPost()
{
	global $context, $txt, $settings, $topic;

	// Well - can they?
	isAllowedTo('calendar_post');

	// We need this for all kinds of useful functions.
	loadPluginSource('Wedge:Calendar', 'Subs-Calendar');
	loadPluginLanguage('Wedge:Calendar', 'lang/Calendar');

	// Cast this for safety...
	if (isset($_REQUEST['eventid']))
		$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

	// Submitting?
	if (isset($_POST[$context['session_var']], $_REQUEST['eventid']))
	{
		checkSession();

		// Validate the post...
		if (!isset($_POST['link_to_board']))
			validateEventPost();

		// If you're not allowed to edit any events, you have to be the poster.
		if ($_REQUEST['eventid'] > 0 && !allowedTo('calendar_edit_any'))
			isAllowedTo('calendar_edit_' . (MID && getEventPoster($_REQUEST['eventid']) == MID ? 'own' : 'any'));

		// New - and directing?
		if ($_REQUEST['eventid'] == -1 && isset($_POST['link_to_board']))
		{
			$_REQUEST['calendar'] = 1;
			loadSource('Post');
			return Post();
		}
		// New...
		elseif ($_REQUEST['eventid'] == -1)
		{
			$eventOptions = array(
				'board' => 0,
				'topic' => 0,
				'title' => substr($_REQUEST['evtitle'], 0, 60),
				'member' => MID,
				'start_date' => sprintf('%04d-%02d-%02d', $_POST['year'], $_POST['month'], $_POST['day']),
				'span' => isset($_POST['span']) && $_POST['span'] > 0 ? min((int) $settings['cal_maxspan'], (int) $_POST['span'] - 1) : 0,
			);
			insertEvent($eventOptions);
		}

		// Deleting...
		elseif (isset($_REQUEST['deleteevent']))
			removeEvent($_REQUEST['eventid']);

		// ... or just update it?
		else
		{
			$eventOptions = array(
				'title' => substr($_REQUEST['evtitle'], 0, 60),
				'span' => empty($settings['cal_allowspan']) || empty($_POST['span']) || $_POST['span'] == 1 || empty($settings['cal_maxspan']) || $_POST['span'] > $settings['cal_maxspan'] ? 0 : min((int) $settings['cal_maxspan'], (int) $_POST['span'] - 1),
				'start_date' => strftime('%Y-%m-%d', mktime(0, 0, 0, (int) $_REQUEST['month'], (int) $_REQUEST['day'], (int) $_REQUEST['year'])),
			);

			modifyEvent($_REQUEST['eventid'], $eventOptions);
		}

		updateSettings(array(
			'calendar_updated' => time(),
		));

		// No point hanging around here now...
		redirectexit(SCRIPT . '?action=calendar;month=' . $_POST['month'] . ';year=' . $_POST['year']);
	}

	// If we are not enabled... we are not enabled.
	if (empty($settings['cal_allow_unlinked']) && empty($_REQUEST['eventid']))
	{
		$_REQUEST['calendar'] = 1;
		loadSource('Post');
		return Post();
	}

	// New?
	if (!isset($_REQUEST['eventid']))
	{
		$today = getdate();

		$context['event'] = array(
			'boards' => array(),
			'board' => 0,
			'new' => 1,
			'eventid' => -1,
			'year' => isset($_REQUEST['year']) ? $_REQUEST['year'] : $today['year'],
			'month' => isset($_REQUEST['month']) ? $_REQUEST['month'] : $today['mon'],
			'day' => isset($_REQUEST['day']) ? $_REQUEST['day'] : $today['mday'],
			'title' => '',
			'span' => 1,
		);
		$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

		// Get list of boards that can be posted in.
		$boards = boardsAllowedTo('post_new');
		if (empty($boards))
			fatal_lang_error('cannot_post_new', 'permission');

		// Load the list of boards and categories in the context.
		loadSource('Subs-MessageIndex');
		$boardListOptions = array(
			'included_boards' => in_array(0, $boards) ? null : $boards,
			'not_redirection' => true,
			'use_permissions' => true,
			'selected_board' => !empty($settings['cal_defaultboard']) ? $settings['cal_defaultboard'] : 0,
		);
		$context['event']['categories'] = getBoardList($boardListOptions);
	}
	else
	{
		$context['event'] = getEventProperties($_REQUEST['eventid']);

		if ($context['event'] === false)
			fatal_lang_error('no_access', false);

		// If it has a board, then they should be editing it within the topic.
		if (!empty($context['event']['topic']['id']) && !empty($context['event']['topic']['first_msg']))
		{
			// We load the board up, for a check on the board access rights...
			$topic = $context['event']['topic']['id'];
			loadBoard();
		}

		// Make sure the user is allowed to edit this event.
		if ($context['event']['member'] != MID)
			isAllowedTo('calendar_edit_any');
		elseif (!allowedTo('calendar_edit_any'))
			isAllowedTo('calendar_edit_own');
	}

	// Template, block, etc.
	loadPluginTemplate('Wedge:Calendar', 'Calendar');
	loadPluginTemplate('Wedge:Calendar', 'CalendarIntegration');
	$tpls = array('form_event_details');
	if ($context['event']['new'])
		$tpls[] = 'form_link_calendar';
	wetem::load(array('event_container' => $tpls));

	// Add the date input magic
	add_plugin_css_file('Wedge:Calendar', 'css/dateinput', true);
	add_plugin_js_file('Wedge:Calendar', 'js/dateinput.js');
	add_js('
	var
		days = ' . json_encode(array_values($txt['days'])) . ',
		daysShort = ' . json_encode(array_values($txt['days_short'])) . ',
		months = ' . json_encode(array_values($txt['months'])) . ',
		monthsShort = ' . json_encode(array_values($txt['months_short'])) . ';
	$("#date").dateinput();');

	$context['page_title'] = isset($_REQUEST['eventid']) ? $txt['calendar_edit'] : $txt['calendar_post_event'];
	$context['linktree'][] = array(
		'name' => $context['page_title'],
	);
}

function iCalDownload()
{
	global $context, $settings;

	// Goes without saying that this is required.
	if (!isset($_REQUEST['eventid']))
		fatal_lang_error('no_access', false);

	// This is kinda wanted.
	loadPluginSource('Wedge:Calendar', 'Subs-Calendar');

	// Load up the event in question and check it exists.
	$event = getEventProperties($_REQUEST['eventid']);

	if ($event === false)
		fatal_lang_error('no_access', false);

	// Check the title isn't too long - iCal requires some formatting if so.
	$title = str_split($event['title'], 30);
	foreach ($title as $id => $line)
	{
		if ($id != 0)
			$title[$id] = ' ' . $title[$id];
		$title[$id] .= "\n";
	}

	// Format the date.
	$date = $event['year'] . '-' . ($event['month'] < 10 ? '0' . $event['month'] : $event['month']) . '-' . ($event['day'] < 10 ? '0' . $event['day'] : $event['day']) . 'T';
	$date .= '1200:00:00Z';

	// This is what we will be sending later.
	$filecontents = '';
	$filecontents .= 'BEGIN:VCALENDAR' . "\n";
	$filecontents .= 'VERSION:2.0' . "\n";
	$filecontents .= 'PRODID:-//Wedge.org//Wedge ' . (!defined('WEDGE_VERSION') ? '1.0' : WEDGE_VERSION) . '//EN' . "\n";
	$filecontents .= 'BEGIN:VEVENT' . "\n";
	$filecontents .= 'DTSTART:' . $date . "\n";
	$filecontents .= 'DTEND:' . $date . "\n";
	$filecontents .= 'SUMMARY:' . implode('', $title);
	$filecontents .= 'END:VEVENT' . "\n";
	$filecontents .= 'END:VCALENDAR';

	// Send some standard headers.
	ob_end_clean();
	if (!empty($settings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	// Send the file headers
	header('Pragma: ');
	header('Cache-Control: no-cache');
	if (!we::is('gecko'))
		header('Content-Transfer-Encoding: binary');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . 'GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('Content-Disposition: attachment; filename=' . $event['title'] . '.ics');

	// How big is it?
	if (empty($settings['enableCompressedOutput']))
		header('Content-Length: ' . westr::strlen($filecontents));

	// This is a calendar item!
	header('Content-Type: text/calendar');

	// Chuck out the card.
	echo $filecontents;

	// Off we pop - lovely!
	obExit(false);
}

function calendarWho(&$allowedActions)
{
	$allowedActions['calendar'] = array('calendar_view');
}

?>