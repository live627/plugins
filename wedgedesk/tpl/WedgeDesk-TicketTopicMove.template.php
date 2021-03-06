<?php
/**
 * WedgeDesk
 *
 * This file handles gathering user options when moving a ticket to/from the helpdesk, from/to
 * a forum thread, specifically getting details like whether to send a PM to the user (including the PM contents), as well as if sending
 * to a board, which board.
 *
 * @package wedgedesk
 * @copyright 2011 Peter Spicer, portions SimpleDesk 2010-11 used under BSD licence
 * @license http://wedgedesk.com/index.php?action=license
 *
 * @since 1.0
 * @version 1.0
 */

/**
 *	Display a list of requirements for moving a ticket to a topic.
 *
 *	When moving a ticket to the forum, certain information is required: the board to move to, whether to send the ticket starter a
 *	personal message (and if so, the contents of the message) and what to do in the event there are deleted replies to deal with.
 *	This function handles showing the form to the user.
 *
 *	@see shd_tickettotopic()
 *	@see shd_tickettotopic2()
 *
 *	@since 1.0
*/
function template_shd_tickettotopic()
{
	global $txt, $context;

	// Back to the helpdesk.
	echo '
		<div class="pagesection">
			', template_button_strip(array($context['navigation']['back'])), '
		</div>';

	echo '
		<we:cat>
			<img src="', $context['plugins_dir']['Arantor:WedgeDesk'], '/images/tickettotopic.png">
			', $txt['shd_move_ticket_to_topic'], '
		</we:cat>
		<div class="roundframe">
		<form action="<URL>?action=helpdesk;sa=tickettotopic2;ticket=', $context['ticket_id'], '" method="post" onsubmit="submitonce(this);">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_ticket_board'], ':</strong>
					</dt>
					<dd>
						<select name="toboard">';

	foreach ($context['categories'] as $category)
	{
		echo '
							<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $bdata)
			echo '
								<option value="', $bdata['id'], '">', $bdata['child_level'] > 0 ? str_repeat('==', $bdata['child_level']-1) . '=&gt; ' : '', $bdata['name'], '</option>';

		echo '
							</optgroup>';
	}

	echo '
						</select>
					</dd>
					<dt>
						<strong>', $txt['shd_change_ticket_subject'], ':</strong>
					</dt>
					<dd>
						<input type="checkbox" name="change_subject" id="change_subject" onclick="document.getElementById(\'new_subject\').style.display = this.checked ? \'block\' : \'none\';" class="input_check">
					</dd>
				</dl>
				<dl class="settings" style="display: none;" id="new_subject">
					<dt>
						<strong>', $txt['shd_new_subject'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="subject" id="subject" value="', $context['ticket_subject'], '">
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_move_send_pm'], ':</strong>
					</dt>
					<dd>
						<input type="checkbox" name="send_pm" id="send_pm" checked="checked" onclick="document.getElementById(\'pm_message\').style.display = this.checked ? \'block\' : \'none\';" class="input_check">
					</dd>
				</dl>
				<fieldset id="pm_message">
					<dl class="settings">
						<dt>
							', $txt['shd_move_why'], '
						</dt>
						<dd>
							<textarea name="pm_content" rows="9" cols="70">', $txt['shd_move_default'], '</textarea>
						</dd>
					</dl>
				</fieldset>';

	if (!empty($context['deleted_prompt']))
	{
		echo '
				<br>
				<fieldset id="deleted_replies">
					<dl class="settings">
						<dt>
							', $txt['shd_ticket_move_deleted'], '
						</dt>
						<dd>
							<select name="deleted_replies">
								<option value="abort">', $txt['shd_ticket_move_deleted_abort'], '</option>
								<option value="delete">', $txt['shd_ticket_move_deleted_delete'], '</option>
								<option value="undelete">', $txt['shd_ticket_move_deleted_undelete'], '</option>
							</select>
						</dd>
					</dl>
				</fieldset>';
	}

	if (!empty($context['custom_fields']))
	{
		echo '
				<br>
				<fieldset id="custom_fields">
					<dl class="settings">
						<dt>
							<strong>', $txt['shd_ticket_move_cfs'], '</strong>';
		if (!empty($context['custom_fields_warning']))
			echo '
							<div class="error">', $txt['shd_ticket_move_cfs_warn'], '</div>';

		echo '
						</dt>
						<br>';

		foreach ($context['custom_fields'] as $field)
		{
			echo '
						<dt>';

			if (!empty($field['visible_warn']))
				echo '
							<img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/warning.png" alt="', $txt['shd_ticket_move_cfs_warn_user'], '" title="', $txt['shd_ticket_move_cfs_warn_user'], '">';
			else
				echo '
							<img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/perm_yes.png" alt="', $txt['shd_ticket_move_ok'], '" title="', $txt['shd_ticket_move_ok'], '">';

			echo '
							<img src="', $context['plugins_url']['Arantor:WedgeDesk'], '/images/cf_ui_', $context['field_types'][$field['type']][1], '.png" class="icon" alt="', $context['field_types'][$field['type']][0], '" title="', $context['field_types'][$field['type']][0], '">', $field['name'];

			foreach ($field['visible'] as $group => $visible)
			{
				if (!$visible)
					continue;

				echo '
							<img src="' . $context['plugins_url']['Arantor:WedgeDesk'] . '/images/', $group, '.png" alt="', $txt['shd_ticket_move_cfs_' . $group], '" title="', $txt['shd_ticket_move_cfs_' . $group], '" style="margin-right:0px;">';

			}

			echo '
						</dt>
						<dd>
							<select name="field', $field['id_field'], '">
								<option value="keep">', $txt['shd_ticket_move_cfs_embed'], '</option>
								<option value="lose">', $txt['shd_ticket_move_cfs_purge'], '</option>
							</select>
						</dd>';
		}

		echo '
						</dd>
					</dl>';

		if (!empty($context['custom_fields_warning']))
			echo '
					<hr>
					<dl class="settings">
						<dt>
							<strong>', $txt['shd_ticket_move_accept'], '</strong>
							<div class="error">', $txt['shd_ticket_move_reqd'], '</div>
						</dt>
						<dd><input type="checkbox" name="accept_move" class="input_check"></dd>
					</dl>';

		echo '
				</fieldset>';
	}

	echo '
				<input type="submit" value="', $txt['shd_move_ticket'], '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit">
				<input type="submit" name="cancel" value="', $txt['shd_cancel_ticket'], '" accesskey="c" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
			</div>
		</form>
		</div>';
}

/**
 *	Display a list of requirements for moving a topic to a ticket.
 *
 *	When moving a ticket from the forum, only a little information is required; basically, whether to send a personal message (and what message)
 *	to the topic/ticket starter or not.
 *
 *	@see shd_topictoticket()
 *	@see shd_topictoticket2()
 *
 *	@since 1.0
*/
function template_shd_topictoticket()
{
	global $txt, $context;
	echo '
		<we:cat>
			<img src="', $context['plugins_dir']['Arantor:WedgeDesk'], '/images/topictoticket.png">
			', $txt['shd_move_topic_to_ticket'], '
		</we:cat>
		<div class="roundframe">
		<form action="<URL>?action=helpdesk;sa=topictoticket2;topic=', $context['topic_id'], '" method="post" onsubmit="submitonce(this);">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_change_topic_subject'], ':</strong>
					</dt>
					<dd>
						<input type="checkbox" name="change_subject" id="change_subject" onclick="document.getElementById(\'new_subject\').style.display = this.checked ? \'block\' : \'none\';" class="input_check">
					</dd>
				</dl>
				<dl class="settings" style="display: none;" id="new_subject">
					<dt>
						<strong>', $txt['shd_new_subject'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="subject" id="subject" value="', $context['topic_subject'], '">
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_move_send_pm_topic'], ':</strong>
					</dt>
					<dd>
						<input type="checkbox" name="send_pm" id="send_pm" checked="checked" onclick="document.getElementById(\'pm_message\').style.display = this.checked ? \'block\' : \'none\';" class="input_check">
					</dd>
				</dl>
				<fieldset id="pm_message">
					<dl class="settings">
						<dt>
							', $txt['shd_move_why_topic'], '
						</dt>
						<dd>
							<textarea name="pm_content" rows="9" cols="70">', $txt['shd_move_default_topic'], '</textarea>
						</dd>
					</dl>
				</fieldset>';

	if (count($context['dept_list']) == 1)
	{
		$dept = array_keys($context);
		// We can only see one department, so that's the one we will use.
		echo '
				<dl class="settings">
					<dt>', $context['ttm_move_dept'], '</dt>
					<input type="hidden" name="dept" value="', $dept[0], '">
				</dl>';
	}
	else
	{
		// We can see multiple departments, so tell the moderator and also provide a list to pick from.
		echo '
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_move_ticket_department'], ':</strong>
						<div class="smalltext">', $context['ttm_move_dept'], '</div>
					</dt>
					<dd>
						<select name="dept">';

		foreach ($context['dept_list'] as $dept => $dept_name)
			echo '
							<option value="', $dept, '">', $dept_name, '</option>';

		echo '
						</select>
					</dd>
				</dl>';
	}

	echo '
				<input type="submit" value="', $txt['shd_move_topic'], '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit">
				<input type="submit" name="cancel" value="', $txt['shd_cancel_topic'], '" accesskey="c" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
			</div>
		</form>
		</div>';
}

?>