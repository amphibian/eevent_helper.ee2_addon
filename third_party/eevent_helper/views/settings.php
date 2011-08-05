<?php

    $this->EE =& get_instance();
	$this->EE->cp->load_package_js('settings');
?>

<?php foreach ($cp_messages as $cp_message_type => $cp_message) : ?>
	<p class="notice <?=$cp_message_type?>"><?=$cp_message?></p>
<?php endforeach; ?>	

<?php
	// We need a hidden field called 'file' whose value matches this extension's url slug. (Apparently?)
	echo form_open('C=addons_extensions'.AMP.'M=save_extension_settings', array('id' => $file), array('file' => $file));
?>

<table class="mainTable padTable" border="0" cellspacing="0" cellpadding="0">
	
	<thead>
		<tr>
			<th colspan="2"><?php echo $this->EE->lang->line('events_channels'); ?></th>
		</tr>
	</thead>
<?php
	// Build a settings panel for each events channel
	while($current_channel <= $total_events_channels) :
?>
	<tbody>
		<tr class="odd">
			<td>
				<?php echo $this->EE->lang->line('choose_events_channel'); ?>
			</td>
			<td>
				<?php echo form_dropdown('event_channel[]', $channels, 
				(isset($current['event_channel'][$count])) ? $current['event_channel'][$count] : 'none'); ?>
			</td>
		</tr>
		<tr class="even">
			<td>
				<?php echo $this->EE->lang->line('start_date_field'); ?>
			</td>
			<td>
				<?php echo form_dropdown('start_date_field[]', $start_fields, 
				(isset($current['start_date_field'][$count])) ? $current['start_date_field'][$count] : 'none'); ?>
			</td>
		</tr>
		<tr class="odd">
			<td>
				<?php echo $this->EE->lang->line('end_date_field'); ?>
			</td>
			<td>
				<?php echo form_dropdown('end_date_field[]', $end_fields, 
				(isset($current['end_date_field'][$count])) ? $current['end_date_field'][$count] : 'none'); ?>
			</td>
		</tr>
		<tr class="even">
			<td>
				<?php echo $this->EE->lang->line('clone_date'); ?>
			</td>
			<td>
				<?php echo form_dropdown('clone_date[]', $bool, 
				(isset($current['clone_date'][$count])) ? $current['clone_date'][$count] : 'n'); ?>
			</td>
		</tr>
		<tr class="odd">
			<td>
				<?php echo $this->EE->lang->line('midnight'); ?>
			</td>
			<td>
				<?php echo form_dropdown('midnight[]', $bool, 
				(isset($current['midnight'][$count])) ? $current['midnight'][$count] : 'n'); ?>
			</td>
		</tr>
		<tr class="odd">
			<td>
				<?php echo $this->EE->lang->line('set_expiry'); ?>
			</td>
			<td>
				<?php echo form_dropdown('set_expiry[]', $bool, 
				(isset($current['set_expiry'][$count])) ? $current['set_expiry'][$count] : 'n'); ?>
			</td>
		</tr>
		<tr>
			<td colspan="2" style="background-color: #D0D7Df; height: 1px;"></td>
		</tr>		
	</tbody>
	
<?php $count++; $current_channel++; endwhile; ?>

	<tbody>
		<tr class="even">
			<td style="font-size: 11px;"><a href="#" id="eh_add_channel"><?php echo $this->EE->lang->line('add_channel'); ?></a></td>
			<td style="font-size: 11px; text-align: right; border-left: 0px"><a href="#" id="eh_remove_channel"><?php echo $this->EE->lang->line('remove_channel'); ?></a></td>
		</tr>
	</tbody>
	
</table>
	
<?php	
	echo form_submit(array('name' => 'submit', 'value' => $this->EE->lang->line('save_settings'), 'class' => 'submit'));
	echo form_close();
?>