<?php

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
	This is the 'Skin Selector' plugin for Wedge.
	Note that the structure of this file is not typical: the source and template are in the same file.
	The contents are still structurally separate but the two are in the same file for efficiency.
	-- Arantor
*/

function skinSelector()
{
	global $txt, $language, $context, $settings, $board_info;

	if (!empty(we::$user['possibly_robot']) || (isset($board_info) && !empty($board_info['theme']) && $board_info['override_theme']))
		return;

	// Will need this whatever.
	loadSource('Themes');

	$temp = cache_get_data('wedgeward_skin_listing', 180);
	if ($temp === null)
	{
		// Get all the themes...
		$request = wesql::query('
			SELECT id_theme AS id, value AS name
			FROM {db_prefix}themes
			WHERE variable = {literal:name}'
		);
		$temp = array();
		while ($row = wesql::fetch_assoc($request))
			$temp[$row['id']] = $row;
		wesql::free_result($request);

		// Get theme dir for all themes
		$request = wesql::query('
			SELECT id_theme AS id, value AS dir
			FROM {db_prefix}themes
			WHERE variable = {literal:theme_dir}'
		);
		while ($row = wesql::fetch_assoc($request))
			$temp[$row['id']]['skins'] = wedge_get_skin_list($row['dir'] . '/skins');
		wesql::free_result($request);

		cache_put_data('wedgeward_skin_listing', $temp, 180);
	}

	// So, now we have a list of all the skins.
	$context['skin_selector'] = $temp;
	wetem::add('sidebar', 'sidebar_skin_selector');
}

function template_sidebar_skin_selector()
{
	global $context, $theme, $txt;

	loadPluginLanguage('Wedgeward:SkinSelector', 'SkinSelector');

	$only_one_theme = count($context['skin_selector']) == 1;
	$output = '';
	foreach ($context['skin_selector'] as $th)
	{
		if (!$only_one_theme)
			$output .= '<optgroup label="' . $th['name'] . '">';
		if (!empty($th['skins']))
			$output .= wedge_show_skins($th, $th['skins'], $theme['theme_id'], $context['skin'], '', true);
		if (!$only_one_theme)
			$output .= '</optgroup>';
	}
	$current_skin = isset($context['skin_names'][$context['skin']]) ? $context['skin_names'][$context['skin']] : substr(strrchr($context['skin'], '/'), 1);

	// !! westr::safe($current_skin), maybe..? Probably not useful.
	echo '
	<section>
		<we:title>
			', $txt['skin_selector'], '
		</we:title>
		<p>
			<select name="boardtheme" id="boardtheme" data-default="', $current_skin, '">',
				$output, '
			</select>
		</p>
	</section>';

	if (we::$is_guest)
		add_js('
	$("#boardtheme").change(function () {
		var len, sAnchor = "", sUrl = location.href.replace(/theme=([\w+/=]+);?/i, ""), search = sUrl.indexOf("#");
		if (search != -1)
		{
			sAnchor = sUrl.slice(search);
			sUrl = sUrl.slice(0, search);
		}
		location = sUrl + (sUrl.search(/[?;]$/) != -1 ? "" : sUrl.indexOf("?") < 0 ? "?" : ";") + "theme=" + this.value + sAnchor;
	});');
	else
		add_js('
	$("#boardtheme").change(function () {
		location = weUrl("action=skin;th=" + this.value + ";" + we_sessvar + "=" + we_sessid);
	});');
}
