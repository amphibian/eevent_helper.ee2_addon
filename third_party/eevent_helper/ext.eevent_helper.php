<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
    This file is part of EEvent Helper add-on for ExpressionEngine.

    EEvent Helper is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Foobar is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    Read the terms of the GNU General Public License
    at <http://www.gnu.org/licenses/>.

    Copyright 2011 Derek Hogue
*/

class Eevent_helper_ext
{
	var $settings = array();
	var $name = 'EEvent Helper';
	var $version = '2.0.6';
	var $description = 'Automatically sets the expiration date for event entries, and more.';
	var $settings_exist = 'y';
	var $docs_url = 'http://github.com/amphibian/eevent_helper.ee2_addon';
	var $slug = 'eevent_helper';
	var $debug = FALSE;


	function Eevent_helper_ext($settings='')
	{
	    $this->settings = $settings;
	    $this->EE =& get_instance();
	}


	function settings_form($current)
	{

		if($this->debug == TRUE)
		{
			print '<pre>';
			print_r($current);
			print '</pre>';
		}

		// Initialize our variable array
		$vars = array();

		// We need our file name for the settings form
		$vars['file'] = $this->slug;

		// Get current site ID
		$site_id = $this->EE->config->item('site_id');

		// Add our current site's settings, if they exist
		$vars['current'] = (array_key_exists($site_id, $current)) ? $current[$site_id] : array();

		// How many event channels do we have settings for?
		$vars['total_events_channels'] = (array_key_exists('event_channel', $vars['current'])
		&& !empty($vars['current']['event_channel']) ) ? count($vars['current']['event_channel']) : 1;

		// Current count is 1
		$vars['current_channel'] = 1;

		// Settings array starts at 0
		$vars['count'] = 0;

		// Initialize channel list
		$vars['channels']['none'] = '--';

		// Get a list of channels for the current site
		$channels = $this->EE->db->query("SELECT channel_title, channel_id
			FROM exp_channels
			WHERE site_id = '".$this->EE->db->escape_str($site_id)."'
			ORDER BY channel_title ASC");

		foreach($channels->result_array() as $value)
		{
			extract($value);
			$vars['channels'][$channel_id] = $channel_title;
		}


		// Initialize field lists
		$vars['start_fields']['entry_date'] = $this->EE->lang->line('use_entry_date');
		$vars['end_fields']['none'] = $this->EE->lang->line('none');

		// Get a list of date fields for the current site
		$fields = $this->EE->db->query("SELECT c.channel_title, f.field_id, f.field_label
			FROM exp_channels as c, exp_channel_fields as f
			WHERE c.field_group = f.group_id
			AND c.site_id = '".$this->EE->db->escape_str($site_id)."'
			AND f.field_type IN('date','eevent_helper','dropdate')
			ORDER BY c.channel_title ASC,f.field_order ASC");

		foreach($fields->result_array() as $value)
		{
			extract($value);
			$vars['start_fields'][$field_id] = $channel_title . ': ' . $field_label;
			$vars['end_fields'][$field_id] = $channel_title . ': ' . $field_label;
		}

		// Array for boolean settings
		$vars['bool'] = array(
			'n' => $this->EE->lang->line('no'),
			'y' => $this->EE->lang->line('yes')
		);

		// We have our vars set, so load and return the view file
		return $this->EE->load->view('settings', $vars, TRUE);

	}


	function save_settings()
	{
		// Get current site ID
		$site_id = $this->EE->config->item('site_id');

		// Get settings
		$settings = $this->get_settings(TRUE);

		$settings[$site_id] = array(
			'event_channel' => $_POST['event_channel'],
			'start_date_field' => $_POST['start_date_field'],
			'end_date_field' => $_POST['end_date_field'],
			'clone_date' => $_POST['clone_date'],
			'midnight' => $_POST['midnight']
		);

		$this->EE->db->where('class', ucfirst(get_class($this)));
		$this->EE->db->update('extensions', array('settings' => serialize($settings)));

		$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('preferences_updated'));
	}


	function get_settings($all_sites = FALSE)
	{
		$get_settings = $this->EE->db->query("SELECT settings
			FROM exp_extensions
			WHERE class = '".ucfirst(get_class($this))."'
			LIMIT 1");

		$this->EE->load->helper('string');

		if ($get_settings->num_rows() > 0 && $get_settings->row('settings') != '')
        {
        	$settings = strip_slashes(unserialize($get_settings->row('settings')));
        	$settings = ($all_sites == TRUE) ? $settings : $settings[$this->EE->config->item('site_id')];
        }
        else
        {
        	$settings = array();
        }
        return $settings;
	}


	function process_dates($channel_id, $hook, $custom_fields = '')
	{
		/*
			Get the array key for this channel's settings
			(if it is indeed an event channel).
		*/
		$key = $this->is_event_channel($channel_id);

		if($key !== FALSE)
		{
			// Get settings for this site
			$settings = $this->get_settings();

			// Initialize our new data
			$new = array();
			if(isset($_POST['entry_date']) && !empty($_POST['entry_date']))
			{
				$new['entry_date'] = $_POST['entry_date'];
			}
			else
			{
				$new['entry_date'] = $this->EE->localize->set_human_time();
			}
			if(isset($_POST['expiration_date']) && !empty($_POST['expiration_date']))
			{
				$new['expiration_date'] = $_POST['expiration_date'];
			}

			/*
				When looking custom start and end date fields,
				we need to look for both control panel-style field names (e.g., field_id_x)
				and SafeCracker-style field_names (e.g., my_start_date).

				We refer to these as x_date_field_name and x_date_field_short name respectively.
				SafeCracker suuplies us with an array of custom field data
				that includes both field_name and field_id,
				so we can avoid any extra database calls.

				For each custom date field we also check its format to see if it's an
				EE date/EH date field, or a DropDate field, so we can format accordingly.
			*/

			// Are we using a custom start date field?

			if($settings['start_date_field'][$key] != 'entry_date')
			{
				$start_date_field_name = 'field_id_'.$settings['start_date_field'][$key];
				if(is_array($custom_fields))
				{
					foreach($custom_fields as $field_name => $attributes)
					{
						if($attributes['field_id'] == $settings['start_date_field'][$key])
						{
							$start_date_field_short_name = $field_name;
						}
					}
				}
			}

			if(isset($start_date_field_name) && isset($_POST[$start_date_field_name]) && !empty($_POST[$start_date_field_name]))
			{
				$new[$start_date_field_name] = $this->prepare_date_field($_POST[$start_date_field_name]);
			}

			if(isset($start_date_field_short_name) && isset($_POST[$start_date_field_short_name]) && !empty($_POST[$start_date_field_short_name]))
			{
				$new[$start_date_field_short_name] = $this->prepare_date_field($_POST[$start_date_field_short_name]);
			}

			// Are we using a custom end date field?

			if($settings['end_date_field'][$key] != 'none')
			{
				$end_date_field_name = 'field_id_'.$settings['end_date_field'][$key];
				if(is_array($custom_fields))
				{
					foreach($custom_fields as $field_name => $attributes)
					{
						if($attributes['field_id'] == $settings['end_date_field'][$key])
						{
							$end_date_field_short_name = $field_name;
						}
					}
				}
			}

			if(isset($end_date_field_name) && isset($_POST[$end_date_field_name]) && !empty($_POST[$end_date_field_name]))
			{
				$new[$end_date_field_name] = $this->prepare_date_field($_POST[$end_date_field_name], 'end');
			}

			if(isset($end_date_field_short_name) && isset($_POST[$end_date_field_short_name]) && !empty($_POST[$end_date_field_short_name]))
			{
				$new[$end_date_field_short_name] = $this->prepare_date_field($_POST[$end_date_field_short_name], 'end');
			}

			// Are we zeroing the time?

			if($settings['midnight'][$key] == 'y')
			{
				// Zero the appropriate start date
				if(isset($start_date_field_name) && isset($new[$start_date_field_name]) && !empty($new[$start_date_field_name]))
				{
					// We submitted a custom start date via the CP, fix it
					$new[$start_date_field_name]= substr($new[$start_date_field_name], 0, 10) . ' 00:01';
				}
				elseif(isset($start_date_field_short_name) && isset($new[$start_date_field_short_name]) && !empty($new[$start_date_field_short_name]))
				{
					// We submitted a custom start date via SafeCracker, fix it
					$new[$start_date_field_short_name]= substr($new[$start_date_field_short_name], 0, 10) . ' 00:01';
				}
				else
				{
					// Fix the entry date instead
					$new['entry_date'] = substr($new['entry_date'], 0, 10) . ' 00:01';
				}

				// Zero the end date if applicable
				if(isset($end_date_field_name) && isset($new[$end_date_field_name]) && !empty($new[$end_date_field_name]))
				{
					// We submitted a custom end date via the CP, fix it
					$new[$end_date_field_name] = substr($new[$end_date_field_name], 0, 10) . ' 00:01';
				}
				if(isset($end_date_field_short_name) && isset($new[$end_date_field_short_name]) && !empty($new[$end_date_field_short_name]))
				{
					// We submitted a custom end date via SafeCracker, fix it
					$new[$end_date_field_short_name] = substr($new[$end_date_field_short_name], 0, 10) . ' 00:01';
				}
			}

			// Set the expiration date

			if(isset($end_date_field_name) && isset($new[$end_date_field_name]) && !empty($new[$end_date_field_name]))
			{
				// We're using an end date via the CP
				$new['expiration_date'] = substr($new[$end_date_field_name], 0, 10) . ' 23:59';
			}
			elseif(isset($end_date_field_short_name) && isset($new[$end_date_field_short_name]) && !empty($new[$end_date_field_short_name]))
			{
				// We're using an end date via SafeCracker
				$new['expiration_date'] = substr($new[$end_date_field_short_name], 0, 10) . ' 23:59';
			}
			else
			{
				if(isset($start_date_field_name) && isset($new[$start_date_field_name]) && !empty($new[$start_date_field_name]))
				{
					// We're using a custom start date via the CP
					$new['expiration_date'] = substr($new[$start_date_field_name], 0, 10) . ' 23:59';
				}
				elseif(isset($start_date_field_short_name) && isset($new[$start_date_field_short_name]) && !empty($new[$start_date_field_short_name]))
				{
					// We're using a custom start date via SafeCracker
					$new['expiration_date'] = substr($new[$start_date_field_short_name], 0, 10) . ' 23:59';
				}
				else
				{
					// We're using the entry_date
					$new['expiration_date'] = substr($new['entry_date'], 0, 10) . ' 23:59';
				}
			}

			// Clone start date to entry date

			if($settings['clone_date'][$key] == 'y')
			{
				if(isset($start_date_field_name) && isset($new[$start_date_field_name]) && !empty($new[$start_date_field_name]))
				{
					// We're using a custom start date via the CP
					$new['entry_date'] = $new[$start_date_field_name];
				}
				elseif(isset($start_date_field_short_name) && isset($new[$start_date_field_short_name]) && !empty($new[$start_date_field_short_name]))
				{
					// We're using a custom start date via SafeCracker
					$new['entry_date'] = $new[$start_date_field_short_name];
				}
			}

			/* GDmac, fix for DST indiscrepancies.
			 *
			 * The channel entries API runs convert_human_date_to_gmt() on the entry_date etc.
			 * when it's a string, and we don't want to hack core.
			 * The easy way would have been $date=strtotime($date) but
			 * eevent_helper fieldtype doesn't like timestamps, so we do it a bit different.
			 *
			 * Essentially, when the system is in DST and you enter dates outside DST
			 * then convert_human_date_to_gmt() will add or substract an extra hour.
			 * So... we compensate.
			 */

			$system_dst = date("I", $this->EE->localize->set_server_time());

			foreach($new as $key => $date)
			{
				// don't we all love unix timestamps
				$date_stamp = $this->EE->localize->timestamp_to_gmt($date);

				if($system_dst == 1)
				{
					// system is in DST, but entry is not, add an extra hour, system will subtract one for us :P
					if( date("I", $date_stamp) == 0) $date_stamp = $date_stamp + 3600;
				}
				else
				{
					if(date("I", $date_stamp) == 1) $date_stamp = $date_stamp - 3600;
				}

				$new[$key] = $this->EE->localize->set_human_time( $date_stamp, FALSE );

			}

			// Revert and DropDate fields back to their original posted states
			// (Or DropDate won't validate them nor know what to do with them)

			if(isset($start_date_field_name) && isset($_POST[$start_date_field_name]) && $this->is_dropdate($_POST[$start_date_field_name]))
			{
					unset($new[$start_date_field_name]);
			}
			if(isset($start_date_field_short_name) && isset($_POST[$start_date_field_short_name]) && $this->is_dropdate($_POST[$start_date_field_short_name]))
			{
				unset($new[$start_date_field_short_name]);
			}
			if(isset($end_date_field_name) && isset($_POST[$end_date_field_name]) && $this->is_dropdate($_POST[$end_date_field_name]))
			{
					unset($new[$end_date_field_name]);
			}
			if(isset($end_date_field_short_name) && isset($_POST[$end_date_field_short_name]) && $this->is_dropdate($_POST[$end_date_field_short_name]))
			{
				unset($new[$end_date_field_short_name]);
			}

			// Update different arrays based on which hook is used

			switch($hook)
			{
				// Control panel submission
				case 'entry_submission_start' :
					foreach($new as $k => $v)
					{
						$this->EE->api_channel_entries->data[$k] = $v;
					}
					break;

				// SafeCracker submission
				case 'safecracker_submit_entry_start' :
					foreach($new as $k => $v)
					{
						$_POST[$k] = $v;
					}
					break;
			}

		}
	}


	function prepare_date_field($date)
	{
		if($this->is_dropdate($date))
		{
			if(array_search('0', $date) === FALSE)
			{
				// Looks like a valid DropDate field, with no empty selections
				return $date[2].'-'.str_pad($date[1], 2, '0', STR_PAD_LEFT).'-'.str_pad($date[0], 2, '0', STR_PAD_LEFT);
			}
			else
			{
				// The date wasn't properly submitted (missing parts)
				return FALSE;
			}
		}
		else
		{
			// Must be a Date or EEvent Helper Date field
			return $date;
		}
	}


	function is_dropdate($date)
	{
		if(is_array($date) && count($date) == 3)
		{
			return TRUE;
		}
	}


	function is_event_channel($channel_id)
	{
		// Get current site ID
		$site_id = $this->EE->config->item('site_id');

		// Get settings
		$settings = $this->get_settings(TRUE);

		// Have we saved our settings for this site?
		if(array_key_exists($site_id, $settings))
		{
			/*
				Find which index in the array we want to take our settings from.
				(Will return FALSE if no settings for this channel.)
			*/
			return array_search($channel_id, $settings[$site_id]['event_channel']);
		}
		else
		{
			return FALSE;
		}
	}


	function entry_submission_start($channel_id, $autosave)
	{
		if($autosave == FALSE)
		{
			$this->process_dates($channel_id, 'entry_submission_start');
		}
	}


	function safecracker_submit_entry_start($SC)
	{
		$this->process_dates($SC->channel['channel_id'], 'safecracker_submit_entry_start', $SC->custom_fields);
	}


	function cp_js_end($data)
	{

		if($this->EE->extensions->last_call !== FALSE)
		{
			$data = $this->EE->extensions->last_call;
		}
		
		// the offset field has been removed from EE 2.1.4 beta 
		// returning here
		return $data;

		// Doesn't appear to be a way to determine where you are in the control panel,
		// as the C and M $_GET variables will always be 'javascript' and 'load'
		// So I guess we just load this on every screen?
		$settings = $this->get_settings();
		foreach($settings['start_date_field'] as $setting)
		{
			if($setting != 'entry_date')
			{
				$data .= "$('select#field_offset_".$setting."').hide();".NL;
			}
		}
		foreach($settings['end_date_field'] as $setting)
		{
			if($setting != 'none')
			{
				$data .= "$('select#field_offset_".$setting."').hide();".NL;
			}
		}

		return $data;
	}


	function activate_extension()
	{

	    $hooks = array(
	    	'entry_submission_start',
	    	'safecracker_submit_entry_start',
	    	'cp_js_end'
	    );

	    foreach($hooks as $hook)
	    {
		    $this->EE->db->query($this->EE->db->insert_string('exp_extensions',
		    	array(
					'extension_id' => '',
			        'class'        => ucfirst(get_class($this)),
			        'method'       => $hook,
			        'hook'         => $hook,
			        'settings'     => '',
			        'priority'     => 10,
			        'version'      => $this->version,
			        'enabled'      => "y"
					)
				)
			);
	    }
	}


	function update_extension($current='')
	{
	    if ($current == '' OR $current == $this->version)
	    {
	        return FALSE;
	    }

		if($current <= '2.0.2')
		{
			$this->EE->db->query($this->EE->db->insert_string('exp_extensions',
					array(
						'extension_id' => '',
						'class'        => ucfirst(get_class($this)),
						'method'       => 'safecracker_submit_entry_start',
						'hook'         => 'safecracker_submit_entry_start',
						'settings'     => '',
						'priority'     => 10,
						'version'      => $this->version,
						'enabled'      => "y"
						)
					)
				);
		}

		$this->EE->db->query("UPDATE exp_extensions
	     	SET version = '". $this->EE->db->escape_str($this->version)."'
	     	WHERE class = '".ucfirst(get_class($this))."'");
	}


	function disable_extension()
	{
		$this->EE->db->query("DELETE FROM exp_extensions WHERE class = '".ucfirst(get_class($this))."'");
	}


}