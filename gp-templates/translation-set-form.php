<dl>
	<dt><label for="set[name]"><?php _e('Name');  ?></label></dt>
	<dd><input type="text" name="set[name]" value="<?php echo gp_h( $set->name ); ?>" id="set[name]"></dd>
	
	<!-- TODO: make slug edit WordPress style -->
	<dt><label for="set[slug]"><?php _e('Slug');  ?></label></dt>
	<dd><input type="text" name="set[slug]" value="<?php echo gp_h( $set->slug ); ?>" id="set[slug]"></dd>

	<dt><label for="set[project_id]"><?php _e('Project');  ?></label></dt>
	<dd><?php echo gp_select( 'set[project_id]', $all_project_options, $set->project_id); ?></dd>
	
	<dt><label for="set[locale]"><?php _e('Locale');  ?></label></dt>
	<dd><?php echo gp_select( 'set[locale]', $all_locale_options, $set->locale); ?></dd>
	
</dl>
<?php echo gp_js_focus_on( 'set[name]' ); ?>