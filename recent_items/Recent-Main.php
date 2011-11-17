<?php

if (!defined('WEDGE'))
	die('Hacking attempt...');

// For the info center
function recentitems_ic()
{
	global $modSettings, $context;

	if (empty($modSettings['recentitems_show']) || empty($modSettings['recentitems_sidebar_infocenter']))
		return;

	recentitems_common();

	if (!empty($context['latest_posts']))
		wetem::load('recentitems_infocenter', 'info_center', 'first');
}

// Common stuff, like loading templates and language strings.
function recentitems_common()
{
	global $modSettings, $user_info, $context;

	if (isset($context['latest_posts']))
		return;

	loadPluginTemplate('Arantor:RecentItems', 'Recent');
	loadPluginLanguage('Arantor:RecentItems', 'Recent-Main');

	if (empty($modSettings['recentitems_posttopic']) || ($modSettings['recentitems_posttopic'] != 'post' && $modSettings['recentitems_posttopic'] != 'topic'))
		$modSettings['recentitems_posttopic'] = 'post';

	$temp = cache_get_data('boards-latest_' . $modSettings['recentitems_posttopic'] . ':' . md5($user_info['query_wanna_see_board'] . $user_info['language']), 90);
	if ($temp !== null)
	{
		// Before we just throw it at the user, reformat the time. It will have been cached with whatever format the user had at the time.
		$context['latest_posts'] = $temp;
		foreach ($context['latest_posts'] as $k => $post)
		{
			$context['latest_posts'][$k]['time'] = timeformat($post['raw_timestamp']);
			$context['latest_posts'][$k]['timestamp'] = forum_time(true, $post['raw_timestamp']);
		}
		return;
	}

	// First, get the message ids.
	$context['latest_posts'] = array();
	if ($modSettings['recentitems_posttopic'] == 'post')
	{
		$request = wesql::query('
			SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : '') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			array(
				'limit' => $modSettings['recentitems_show'],
				'recycle_board' => $modSettings['recycle_board'],
				'is_approved' => 1,
			)
		);
		while ($row = wesql::fetch_row($request))
			$context['latest_posts'][$row[0]] = $row[0];
		wesql::free_result($request);
	}
	elseif ($modSettings['recentitems_posttopic'] == 'topic')
	{
		$request = wesql::query('
			SELECT MAX(id_msg) AS max_id
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : '') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}
				AND m.approved = {int:is_approved}' : '') . '
			GROUP BY t.id_topic
			ORDER BY max_id DESC
			LIMIT {int:limit}',
			array(
				'limit' => $modSettings['recentitems_show'],
				'recycle_board' => $modSettings['recycle_board'],
				'is_approved' => 1,
			)
		);
		while ($row = wesql::fetch_row($request))
			$context['latest_posts'][$row[0]] = $row[0];
		wesql::free_result($request);
	}

	if (!empty($context['latest_posts']))
	{
		$request = wesql::query('
			SELECT
				m.id_msg, m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg,
				IFNULL(mem.real_name, m.poster_name) AS poster_name, b.id_board, b.name AS board_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg IN ({array_int:msgs})',
			array(
				'msgs' => $context['latest_posts'],
			)
		);

		while ($row = wesql::fetch_assoc($request))
		{
			// Censor the subject and post for the preview ;).
			censorText($row['subject']);

			// Build the array.
			$context['latest_posts'][$row['id_msg']] = array(
				'board' => array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'href' => '<URL>?board=' . $row['id_board'] . '.0',
					'link' => '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
				),
				'topic' => $row['id_topic'],
				'poster' => array(
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'href' => empty($row['id_member']) ? '' : '<URL>?action=profile;u=' . $row['id_member'],
					'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
				),
				'subject' => $row['subject'],
				'short_subject' => shorten_subject($row['subject'], 24),
				'time' => timeformat($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'raw_timestamp' => $row['poster_time'],
				'href' => '<URL>?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'],
				'link' => '<a href="<URL>?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>'
			);
		}
		wesql::free_result($request);
	}

	cache_put_data('boards-latest_' . $modSettings['recentitems_posttopic'] . ':' . md5($user_info['query_wanna_see_board'] . $user_info['language']), $context['latest_posts'], 90);
}

?>