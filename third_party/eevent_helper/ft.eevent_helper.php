<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
    This file is part of EEvent Helper add-on for ExpressionEngine.

    EEvent Helper is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    EEvent Helper is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    Read the terms of the GNU General Public License
    at <http://www.gnu.org/licenses/>.
    
    Copyright 2011 Derek Hogue
*/

class Eevent_helper_ft extends EE_Fieldtype {

	var $info = array(
		'name'		=> 'Event Helper Date',
		'version'	=> '2.1.6'
	);

	var $has_array_data = FALSE;

	
	function Eevent_helper_ft()
	{
		EE_Fieldtype::__construct();
		
		// Backwards-compatibility with pre-2.6 Localize class
		$this->format_date_fn = (version_compare(APP_VER, '2.6', '>=')) ? 'format_date' : 'decode_date';
		$this->string_to_timestamp_fn = (version_compare(APP_VER, '2.6', '>=')) ? 'string_to_timestamp' : 'convert_human_date_to_gmt';
	}

	
	function save($data)
	{
		/*
			If the fieldtype is being used without the EH extension, add the time.
			(The EH extension will have already appended the time.)
		*/
		if(strlen($data) == 10)
		{
			$data = $data.' 12:00:00 AM';
		}
		return $this->EE->localize->{$this->string_to_timestamp_fn}($data);
	}
	
	
	function save_cell($data)
	{
		return $this->save($data);
	}


	function validate($data)
	{
		if($data == '' || preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $data) != FALSE)
		{
			return TRUE;
		}
		else
		{
			$this->EE->lang->loadfile('eevent_helper');
			return $this->EE->lang->line('incorrect_eh_date_formatting');
		}
	}
	
	
	function validate_cell($data)
	{
		return $this->validate($data);
	}


	function display_field($field_data)
	{
		return $this->_display($field_data, $this->field_name, false);
	}
	
	
	function display_cell($field_data)
	{
		return $this->_display($field_data, $this->cell_name, true);
	}
	
	
	function _display($field_data, $field_name, $matrix = false)
	{
		
		if(isset($_POST[$this->field_name]))
		{
			$date = $field_data;
		}
		else
		{
			if(is_numeric($field_data) && $field_data != '0')
			{
				$date = $this->EE->localize->{$this->format_date_fn}('%Y-%m-%d', $field_data);
			}
			else
			{
				$date = '';
			}
		}

		// Include the JS if we haven't already
		if ( ! isset($this->EE->session->cache['eevent_helper']['added_js']))
		{
			$js = '
				function initEventHelperFields(context) {
					$(".event_helper_date", context).datepicker({ dateFormat: "yy-mm-dd" });
					$("a.eh_clear_date", context).click(function(){$(this).prev("input").val(""); return false;});
				}
				
				$(document).ready(function() {
					initEventHelperFields();
				});';
				
			if($matrix)
			{
				$js .= '
					Matrix.bind("eevent_helper", "display", function(cell) {
						initEventHelperFields(cell.dom.$td);
					});';			
			}
			
			$this->EE->javascript->output($js);
			$this->EE->session->cache['eevent_helper']['added_js'] = TRUE;
		}

		$r = form_input(array(
			'name'	=> $field_name,
			'value'	=> $date,
			'class'	=> 'field event_helper_date'
		));
		$r .= NBS.NBS.'<a href="#" class="eh_clear_date">'.$this->EE->lang->line('clear').'</a>';
		return $r;	
	}
	
	
	function pre_process($data)
	{
		return $data;
	}
	

	function replace_tag($data, $params = array(), $tagdata = FALSE)
	{
		$format = (isset($params['format'])) ? $params['format'] : '%U';
		return $this->EE->localize->{$this->format_date_fn}($format, $data);
	}


	function save_settings($data)
	{
		$data['field_fmt'] = 'none';
		$data['field_show_fmt'] = 'n';
		$_POST['update_formatting'] = 'y';
		
		return $data;
	}

	
	function settings_modify_column($data)
	{
		$fields['field_id_'.$data['field_id']] = array(
			'type' 			=> 'INT',
			'constraint'	=> 10,
			'default'		=> 0
			);
		return $fields;
	}

	
	function zenbu_display($entry_id, $channel_id, $data, $table_data = array(), $field_id, $settings, $rules = array())
	{
		$format = (isset($settings['setting'][$channel_id]['extra_options']['field_'.$field_id]['format'])) ? $settings['setting'][$channel_id]['extra_options']['field_'.$field_id]['format'] : '%Y-%m-%d';
		return (!empty($data)) ? $this->EE->localize->{$this->format_date_fn}($format, $data) : '';

	}
	
	
	function zenbu_field_extra_settings($table_col, $channel_id, $extra_options)
	{
		$value = (isset($extra_options['format'])) ? $extra_options['format'] : '';
		$settings = array(
			'format' => form_label($this->EE->lang->line('date_format').NBS.form_input('settings['.$channel_id.']['.$table_col.'][format]', $value))
		);
		return $settings;
	}

		
}