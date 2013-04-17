<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
    This file is part of Event Helper add-on for ExpressionEngine.

    Event Helper is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Event Helper is distributed in the hope that it will be useful,
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
	var $name = 'Event Helper';
	var $version = '2.1.6';
	var $description = 'Automatically sets the expiration date for event entries, and more.';
	var $settings_exist = 'y';
	var $docs_url = 'http://github.com/amphibian/eevent_helper.ee2_addon';
	var $slug = 'eevent_helper';
	var $debug = FALSE;

	var $new_data = array();
	var $sd_id = FALSE;
	var $sd_name = FALSE;
	var $ed_id = FALSE;
	var $ed_name = FALSE;	
		
	
	function Eevent_helper_ext($settings='')
	{
	    $this->settings = $settings;
	    $this->EE =& get_instance();
	    
	    // Backwards-compatibility with pre-2.6 Localize class
		$this->human_time_fn = (version_compare(APP_VER, '2.6', '>=')) ? 'human_time' : 'set_human_time';
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
		$fields = $this->EE->db->query("SELECT g.group_name, f.field_id, f.field_label 
			FROM exp_field_groups g, exp_channel_fields f 
			WHERE g.group_id = f.group_id 
			AND g.site_id = '".$this->EE->db->escape_str($site_id)."' 
			AND f.field_type IN('date','eevent_helper','dropdate') 
			ORDER BY g.group_name ASC,f.field_order ASC");
			
		foreach($fields->result_array() as $value)
		{
			extract($value);
			$vars['start_fields'][$field_id] = $group_name . ': ' . $field_label;
			$vars['end_fields'][$field_id] = $group_name . ': ' . $field_label;
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
		
		// Get settings for all sites
		$settings = $this->get_settings(TRUE);
		
		$settings[$site_id] = array(
			'event_channel' => $_POST['event_channel'],
			'start_date_field' => $_POST['start_date_field'],
			'end_date_field' => $_POST['end_date_field'],
			'clone_date' => $_POST['clone_date'],
			'midnight' => $_POST['midnight'],
			'set_expiry' => $_POST['set_expiry']
		);
		
		$this->EE->db->where('class', ucfirst(get_class($this)));
		$this->EE->db->update('extensions', array('settings' => serialize($settings)));
		
		$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('preferences_updated'));
	}

	
	function get_settings($all_sites = FALSE)
	{
		// Get current site ID
		$site_id = $this->EE->config->item('site_id');	
		
		$get_settings = $this->EE->db->query("SELECT settings 
			FROM exp_extensions 
			WHERE class = '".ucfirst(get_class($this))."' 
			LIMIT 1");
		
		$this->EE->load->helper('string');
		
		if($get_settings->num_rows() > 0 && $get_settings->row('settings') != '')
        {
        	$settings = strip_slashes(unserialize($get_settings->row('settings')));
        	if($all_sites == FALSE)
        	{
        		$settings = (isset($settings[$site_id])) ? $settings[$site_id] : array();
        	}
        }
        else
        {
        	$settings = array();
        }
        return $settings;
	}
	

	function entry_submission_start($channel_id, $autosave)
	{
		if($autosave == FALSE)
		{
			$this->_process_dates($channel_id, 'entry_submission_start');
		}
	}
	
	
	function safecracker_submit_entry_start($sc)
	{
		// print_r($sc); exit();
		$this->_process_dates($sc->channel['channel_id'], 'safecracker_submit_entry_start', $sc->custom_fields);
	}
	
	
	function _process_dates($channel_id, $hook, $custom_fields = '')
	{

		$key = $this->_is_event_channel($channel_id);
		
		// REQ == 'CP' is checked because we can't work with programatically-loaded Channel Entries API calls		
		if($key !== FALSE && ($hook == 'safecracker_submit_entry_start' || REQ == 'CP') )
		{							
			$this->settings = $this->get_settings();

			$this->_set_basics();			
			$this->_check_start_date($key, $custom_fields);
			$this->_check_end_date($key, $custom_fields);
									
			if($this->settings['midnight'][$key] == 'y')
			{
				$this->_set_midnight();
			}

			if($this->settings['set_expiry'][$key] == 'y')
			{
				$this->_set_expiry();
			}		

			if($this->settings['clone_date'][$key] == 'y')
			{
				$this->_clone_start_date();	
			}
			
			$this->_revert_dropdate();
						
			switch($hook)
			{
				// Control panel submission
				case 'entry_submission_start' : 
					foreach($this->new_data as $k => $v)
					{
						$this->EE->api_channel_entries->data[$k] = $v;					
					}
					break;
					
				// SafeCracker submission
				case 'safecracker_submit_entry_start' : 
					foreach($this->new_data as $k => $v)
					{
						$_POST[$k] = $v;					
					}
					break;				
			}

		}
	}
	

	function _set_basics()
	{
		if(isset($_POST['entry_date']) && !empty($_POST['entry_date']))
		{
			$this->new_data['entry_date'] = $_POST['entry_date'];
		}
		else
		{
			$this->new_data['entry_date'] = $this->EE->localize->{$this->human_time_fn}();
		}
		if(isset($_POST['expiration_date']) && !empty($_POST['expiration_date']))
		{
			$this->new_data['expiration_date'] = $_POST['expiration_date'];
		}
	}
	
	
	function _check_start_date($key, $custom_fields)
	{
		if($this->settings['start_date_field'][$key] != 'entry_date')
		{
			// We're using a custom start date
			$this->sd_id = 'field_id_'.$this->settings['start_date_field'][$key];
			if(is_array($custom_fields))
			{
				// We're coming from SafeCracker
				foreach($custom_fields as $field_name => $attributes)
				{
					if($attributes['field_id'] == $this->settings['start_date_field'][$key])
					{
						$this->sd_name = $field_name;
					}
				}
			}
		}
		
		if(isset($this->sd_id) && isset($_POST[$this->sd_id]) && !empty($_POST[$this->sd_id]))
		{
			$this->new_data[$this->sd_id] = $this->_prepare_date_field($_POST[$this->sd_id]);
		}
		
		if(isset($this->sd_name) && isset($_POST[$this->sd_name]) && !empty($_POST[$this->sd_name]))
		{
			$this->new_data[$this->sd_name] = $this->_prepare_date_field($_POST[$this->sd_name]);
		}
	}


	function _check_end_date($key, $custom_fields)
	{
		if($this->settings['end_date_field'][$key] != 'none')
		{
			$this->ed_id = 'field_id_'.$this->settings['end_date_field'][$key];
			if(is_array($custom_fields))
			{
				foreach($custom_fields as $field_name => $attributes)
				{
					if($attributes['field_id'] == $this->settings['end_date_field'][$key])
					{
						$this->ed_name = $field_name;
					}
				}
			}
		}
		
		if(isset($this->ed_id) && isset($_POST[$this->ed_id]) && !empty($_POST[$this->ed_id]))
		{
			$this->new_data[$this->ed_id] = $this->_prepare_date_field($_POST[$this->ed_id]);
		}
		
		if(isset($this->ed_name) && isset($_POST[$this->ed_name]) && !empty($_POST[$this->ed_name]))
		{
			$this->new_data[$this->ed_name] = $this->_prepare_date_field($_POST[$this->ed_name]);
		}
	}
		
	
	function _prepare_date_field($date)
	{
		if($this->_is_dropdate($date))
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
	
	
	function _is_dropdate($date)
	{
		if(is_array($date) && count($date) == 3)
		{
			return TRUE;
		}
	}
	
	
	function _set_midnight()
	{
		// Zero the appropriate start date
		if(isset($this->sd_id) && isset($this->new_data[$this->sd_id]) && !empty($this->new_data[$this->sd_id]))
		{
			// We submitted a custom start date via the CP, fix it
			$this->new_data[$this->sd_id]= substr($this->new_data[$this->sd_id], 0, 10) . ' 12:00:00 AM';
		}
		elseif(isset($this->sd_name) && isset($this->new_data[$this->sd_name]) && !empty($this->new_data[$this->sd_name]))
		{
			// We submitted a custom start date via SafeCracker, fix it
			$this->new_data[$this->sd_name]= substr($this->new_data[$this->sd_name], 0, 10) . ' 12:00:00 AM';
		}
		else
		{
			// Fix the entry date instead
			$this->new_data['entry_date'] = substr($this->new_data['entry_date'], 0, 10) . ' 12:00:00 AM';
		}
		
		// Zero the end date if applicable
		if(isset($this->ed_id) && isset($this->new_data[$this->ed_id]) && !empty($this->new_data[$this->ed_id]))
		{
			// We submitted a custom end date via the CP, fix it
			$this->new_data[$this->ed_id] = substr($this->new_data[$this->ed_id], 0, 10) . ' 12:00:00 AM';
		}
		if(isset($this->ed_name) && isset($this->new_data[$this->ed_name]) && !empty($this->new_data[$this->ed_name]))
		{
			// We submitted a custom end date via SafeCracker, fix it
			$this->new_data[$this->ed_name] = substr($this->new_data[$this->ed_name], 0, 10) . ' 12:00:00 AM';
		}				
	}


	function _set_expiry()
	{
		if(isset($this->ed_id) && isset($this->new_data[$this->ed_id]) && !empty($this->new_data[$this->ed_id]))
		{ 
			// We're using an end date via the CP
			$this->new_data['expiration_date'] = substr($this->new_data[$this->ed_id], 0, 10) . ' 11:59:59 PM';
		}
		elseif(isset($this->ed_name) && isset($this->new_data[$this->ed_name]) && !empty($this->new_data[$this->ed_name]))
		{ 
			// We're using an end date via SafeCracker
			$this->new_data['expiration_date'] = substr($this->new_data[$this->ed_name], 0, 10) . ' 11:59:59 PM';
		}
		else
		{ 
			if(isset($this->sd_id) && isset($this->new_data[$this->sd_id]) && !empty($this->new_data[$this->sd_id]))
			{
				// We're using a custom start date via the CP
				$this->new_data['expiration_date'] = substr($this->new_data[$this->sd_id], 0, 10) . ' 11:59:59 PM';
			}
			elseif(isset($this->sd_name) && isset($this->new_data[$this->sd_name]) && !empty($this->new_data[$this->sd_name]))
			{
				// We're using a custom start date via SafeCracker
				$this->new_data['expiration_date'] = substr($this->new_data[$this->sd_name], 0, 10) . ' 11:59:59 PM';
			}
			else
			{
				// We're using the entry_date
				$this->new_data['expiration_date'] = substr($this->new_data['entry_date'], 0, 10) . ' 11:59:59 PM';
			}
		}	
	}
	
	
	function _clone_start_date()
	{
		if(isset($this->sd_id) && isset($this->new_data[$this->sd_id]) && !empty($this->new_data[$this->sd_id]))
		{
			// We're using a custom start date via the CP
			$this->new_data['entry_date'] = (strlen($this->new_data[$this->sd_id]) == 10) ? $this->new_data[$this->sd_id].' 12:00:00 AM' : $this->new_data[$this->sd_id];
		}
		elseif(isset($this->sd_name) && isset($this->new_data[$this->sd_name]) && !empty($this->new_data[$this->sd_name]))
		{
			// We're using a custom start date via SafeCracker
			$this->new_data['entry_date'] = (strlen($this->new_data[$this->sd_name]) == 10) ? $this->new_data[$this->sd_name].' 12:00:00 AM' : $this->new_data[$this->sd_name];
		}
	}
	
	
	function _revert_dropdate()
	{
		/*
			Revert DropDate fields back to their original posted states
			(Or DropDate won't validate them nor know what to do with them)
		*/
		if(isset($this->sd_id) && $this->_is_dropdate($this->EE->input->post($this->sd_id)))
		{
			unset($this->new_data[$this->sd_id]);
		}
		if(isset($this->sd_name) && $this->_is_dropdate($this->EE->input->post($this->sd_name)))
		{
			unset($this->new_data[$this->sd_name]);
		}
		if(isset($this->ed_id) && $this->_is_dropdate($this->EE->input->post($this->ed_id)))
		{
			unset($this->new_data[$this->ed_id]);
		}
		if(isset($this->ed_name) && $this->_is_dropdate($this->EE->input->post($this->ed_name)))
		{
			unset($this->new_data[$this->ed_name]);
		}	
	}


	function _is_event_channel($channel_id)
	{
		/*
			Return the array key for this channel's settings
			(if it is indeed an event channel).
		*/
		
		// Get current site ID
		$site_id = $this->EE->config->item('site_id');
		
		// Have we saved our settings for this site?
		if(array_key_exists($site_id, $this->settings))
		{
			/*
				Find which index in the array we want to take our settings from.
				(Will return FALSE if no settings for this channel.)
			*/
			return array_search($channel_id, $this->settings[$site_id]['event_channel']);
		}
		else
		{
			return FALSE;
		}
	}
	
	
	function cp_js_end($data)
	{

		if($this->EE->extensions->last_call !== FALSE)
		{
			$data = $this->EE->extensions->last_call;
		}
					
		// Doesn't appear to be a way to determine where you are in the control panel,
		// as the C and M $_GET variables will always be 'javascript' and 'load'
		// So I guess we just load this on every screen?
		$settings = $this->get_settings();
		if(!empty($settings))
		{
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
					$data .= "$('select#field_offset_".$setting."').hide();";
				}
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