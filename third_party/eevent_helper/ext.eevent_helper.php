<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Eevent_helper_ext
{
	var $settings        = array();
	var $name            = 'EEvent Helper';
	var $version         = '2.0';
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
	
	
	function entry_submission_end($entry_id, $meta, $data) {
			
		// Get the array key for this channel's settings
		// (if it is indeed an event channel)
		$key = $this->is_event_channel($meta['channel_id']);
				
		// If a key is found and we haven't already done this dance
		if($key !== FALSE && !defined('EEVENT_HELPER_DONE'))
		{
			// This prevents us from  encountering the update_entry Groundhog Day
			define('EEVENT_HELPER_DONE', TRUE);		
			
			// Get settings for this site
			$settings = $this->get_settings();
			
			// update_entry needs one single array, but this hook splits it up amongst two			
			$data = array_merge($meta, $data);
			
			$midnight = $settings['midnight'][$key];
			$clone = $settings['clone_date'][$key];
			
			// Are we using custom date fields, and do they have data?
			$start_date_field = 
				($settings['start_date_field'][$key] != 'entry_date') 
				? 'field_id_'.$settings['start_date_field'][$key]
				: '';
			$end_date_field = ($settings['end_date_field'][$key] != 'none')
				? 'field_id_'.$settings['end_date_field'][$key]
				: '';
					
			// All dates are passed to us as UNIX timestamps by this hook, so we must convert
			// Both for ease of editing, and because that's what update_entry expects for dates
			
			$entry_date = $this->EE->localize->set_human_time($data['entry_date']);
			$expiration_date = $this->EE->localize->set_human_time($data['expiration_date']);
			
			if($start_date_field && $data[$start_date_field])
			{
				$start_date = $this->EE->localize->set_human_time($data[$start_date_field]);
				$data[$start_date_field] = $start_date;
			}
			
			if($end_date_field && $data[$end_date_field])
			{
				$end_date = $this->EE->localize->set_human_time($data[$end_date_field]);
				$data[$end_date_field] = $end_date;
			}			
											
			// Are we zeroing the time?
			if($midnight == 'y')
			{
				// Zero the appropriate start date
				if($start_date)
				{
					// We submitted a custom start date
					$data[$start_date_field] = substr($start_date, 0, 10) . ' 12:00 AM';
				}
				else
				{
					// Use the entry date
					$data['entry_date'] = substr($entry_date, 0, 10) . ' 12:00 AM';
				}
				
				// Zero the end date if applicable
				if($end_date)
				{
					$data[$end_date_field] = substr($end_date, 0, 10) . ' 12:00 AM';
				}
			}
		
			// Set the expiration date
			if($end_date) // We're using an end date
			{ 
				$data['expiration_date'] = substr($end_date, 0, 10) . ' 11:59 PM';
			}
			else
			{ 
				if($start_date) // We're using a custom start date
				{
					$data['expiration_date'] = substr($start_date, 0, 10) . ' 11:59 PM';
				}
				else // We're using the entry_date
				{
					$data['expiration_date'] = substr($entry_date, 0, 10) . ' 11:59 PM';
				}
			}
			
			// Clone start date to entry date
			if($clone == 'y' && $start_date)
			{
				$data['entry_date'] = $data[$start_date_field];
			}
															
			// Now we update the already-inserted entry with the modified date values
			$this->EE->load->library('api');
			$this->EE->api->instantiate('channel_entries');
			$update = $this->EE->api_channel_entries->update_entry($entry_id, $data);
		}	
	}	


	function activate_extension()
	{

	    $hooks = array(
	    	'entry_submission_end' => 'entry_submission_end'
	    );
	    
	    foreach($hooks as $hook => $method)
	    {
		    $this->EE->db->query($this->EE->db->insert_string('exp_extensions',
		    	array(
					'extension_id' => '',
			        'class'        => ucfirst(get_class($this)),
			        'method'       => $method,
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