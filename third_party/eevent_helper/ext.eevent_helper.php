<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Eevent_helper_ext
{
	var $settings        = array();
	var $name            = 'EEvent Helper';
	var $version         = '2.0.1';
	var $description     = 'Automatically sets the expiration date for event entries, and more.';
	var $settings_exist  = 'y';
	var $docs_url        = 'http://github.com/amphibian/eevent_helper.ee2_addon';
	var $slug			 = 'eevent_helper';
	var $debug			 = FALSE;

	
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
			AND f.field_type = 'date' 
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
	
	
	function is_event_channel($channel_id)
	{
		// Get current site ID
		$site_id = $this->EE->config->item('site_id');

		// Get settings
		$settings = $this->get_settings(TRUE);
		
		// Have we saved our settings for this site?
		if(array_key_exists($site_id, $settings))
		{
			// Find which index in the array we want to take our settings from
			// Will return FALSE if no settings for this channel
			return array_search($channel_id, $settings[$site_id]['event_channel']);
		}
		else
		{
			return FALSE;
		}
	}	
	
	
	function entry_submission_start($channel_id, $autosave) {
			
		// Get the array key for this channel's settings
		// (if it is indeed an event channel)
		$key = $this->is_event_channel($channel_id);
				
		if($key !== FALSE && $autosave == FALSE)
		{	
			// Get settings for this site
			$settings = $this->get_settings();
						
			$midnight = $settings['midnight'][$key];
			$clone = $settings['clone_date'][$key];
			
			// Are we using custom date fields?
			$start_date_field_name = ($settings['start_date_field'][$key] == 'entry_date') ? '' : 'field_id_'.$settings['start_date_field'][$key];
			$end_date_field_name = ($settings['end_date_field'][$key] == 'none') ? '' : 'field_id_'.$settings['end_date_field'][$key];
			
			// Are we using a custom start date field, and is there something in it?
			if($start_date_field_name != '' && $this->EE->api_channel_entries->data[$start_date_field_name] != '')
			{
				$start_date_field_value = $this->EE->api_channel_entries->data[$start_date_field_name];
				// Make sure offset is set to 'n'
				$this->EE->api_channel_entries->data['field_offset_'.$settings['start_date_field'][$key]] = 'n';
			}

			// Are we using a custom end date field, and is there something in it?
			if($end_date_field_name != '' && $this->EE->api_channel_entries->data[$end_date_field_name] != '')
			{
				$end_date_field_value = $this->EE->api_channel_entries->data[$end_date_field_name];
				// Make sure offset is set to 'n'
				$this->EE->api_channel_entries->data['field_offset_'.$settings['end_date_field'][$key]] = 'n';
			}		
											
			// Are we zeroing the time?
			if($midnight == 'y')
			{
				// Zero the appropriate start date
				if(isset($start_date_field_value))
				{
					// We submitted a custom start date
					$this->EE->api_channel_entries->data[$start_date_field_name] = substr($start_date_field_value, 0, 10) . ' 12:00 AM';
				}
				else
				{
					// Use the entry date
					$this->EE->api_channel_entries->data['entry_date'] = substr($this->EE->api_channel_entries->data['entry_date'], 0, 10) . ' 12:00 AM';
				}
				
				// Zero the end date if applicable
				if(isset($end_date_field_value))
				{
					$this->EE->api_channel_entries->data[$end_date_field_name] = substr($end_date_field_value, 0, 10) . ' 12:00 AM';
				}
			}
		
			// Set the expiration date
			if(isset($end_date_field_value)) // We're using an end date
			{ 
				$this->EE->api_channel_entries->data['expiration_date'] = substr($end_date_field_value, 0, 10) . ' 11:59 PM';
			}
			else
			{ 
				if(isset($start_date_field_value)) // We're using a custom start date
				{
					$this->EE->api_channel_entries->data['expiration_date'] = substr($start_date_field_value, 0, 10) . ' 11:59 PM';
				}
				else // We're using the entry_date
				{
					$this->EE->api_channel_entries->data['expiration_date'] = substr($this->EE->api_channel_entries->data['entry_date'], 0, 10) . ' 11:59 PM';
				}
			}
			
			// Clone start date to entry date
			if($clone == 'y' && isset($start_date_field_value))
			{
				$this->EE->api_channel_entries->data['entry_date'] = $this->EE->api_channel_entries->data[$start_date_field_name];
			}
		}	
	}
	
	
	function cp_js_end($data)
	{

		if($this->EE->extensions->last_call !== FALSE)
		{
			$data = $this->EE->extensions->last_call;
		}
					
		// Doesn't appear to be a way to determine where you are in the control panel,
		// as the C and M $_GET variables will also be 'javascript' and 'load'
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
	    
		$this->EE->db->query("UPDATE exp_extensions 
	     	SET version = '". $this->EE->db->escape_str($this->version)."' 
	     	WHERE class = '".ucfirst(get_class($this))."'");
	}

	
	function disable_extension()
	{	    
		$this->EE->db->query("DELETE FROM exp_extensions WHERE class = '".ucfirst(get_class($this))."'");
	}


}