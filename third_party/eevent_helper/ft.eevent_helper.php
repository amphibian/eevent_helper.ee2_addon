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
		'name'		=> 'EEvent Helper Date',
		'version'	=> '1.0.2'
	);

	var $has_array_data = FALSE;

	
	function Eevent_helper_ft()
	{
		parent::EE_Fieldtype();
	}

	
	function save($data)
	{
		/*
			If the fieldtype is being used without the EH extension, add the time.
			(The EH extension will have already appended the time.)
		*/
		if(strlen($data) == 10)
		{
			$data = $data.' 12:00 AM';
		}
		return $this->EE->localize->convert_human_date_to_gmt($data);
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


	function display_field($field_data)
	{
		$date = '';
		$date_field = $this->field_name;
		if (isset($_POST[$date_field]) && ! is_numeric($_POST[$date_field]))
		{
			// There's a string in $_POST, probably had a validation error
			if ($_POST[$date_field])
 			{
 				$date = $_POST[$date_field];
			}
		}
		else
		{
			if(strlen($field_data) == 10 && is_numeric($field_data))
			{
				$date = substr($this->EE->localize->set_human_time($field_data, FALSE), 0, 10);
			}
		}

		$this->EE->javascript->output('
			$("#'.$this->field_name.'").datepicker({ dateFormat: "yy-mm-dd" });
			$("a.eh_clear_date").click(function(){$(this).prev("input").val(""); return false;});
		');

		$r = form_input(array(
			'name'	=> $this->field_name,
			'id'	=> $this->field_name,
			'value'	=> $date,
			'class'	=> 'field'
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
		return $this->EE->localize->decode_date($format, $data);
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
}