<?php

function maxsmileys_moderation_rules(&$known_variables, $admin)
{
	global $txt, $admin_areas;

	loadPluginLanguage('Arantor:MaxSmileys', 'MaxSmileys');
	loadPluginTemplate('Arantor:MaxSmileys', 'MaxSmileys');
	$known_variables['maxsmileys'] = array(
		'type' => 'range',
		'current' => 0,
		'func_val' => 'count_maxsmileys',
		'function' => create_function('$criteria', '
			global $txt;
			return $txt[\'modfilter_cond_\' . $criteria[\'name\']] . \': \' . $txt[\'modfilter_range_\' . $criteria[\'term\']] . \' \' . $criteria[\'value\'];
		'),
	);
}

function displayRow_maxsmileys($rule)
{
	return simpleRange_displayRow($rule, 'maxsmileys');
}

function count_maxsmileys($subject, $body)
{
	$post = parse_bbc($body, true);
	return substr_count($post, '<i class="smiley');
}

?>