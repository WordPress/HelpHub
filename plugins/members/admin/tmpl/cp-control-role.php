<# if ( data.label ) { #>
	<span class="members-cp-label">{{ data.label }}</span>
<# } #>

<# if ( data.desciption ) { #>
	<span class="members-cp-description">{{{ data.description }}}</span>
<# } #>

<div class="members-cp-role-list-wrap">

	<ul class="members-cp-role-list">

	<# _.each( data.roles ), function( label, choice ) { #>

		<li>
			<label>
				<input type="checkbox" name="members_access_role[]" value="{{ data.choice }}" <# if ( -1 !== _.indexOf( data.value, choice ) ) { #> checked="checked" <# } #> />
				{{ label }}
			</label>
		</li>

	<# } ); #>

	</ul>
</div>
