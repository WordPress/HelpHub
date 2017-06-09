<style>
	div.panel {padding: 20px 5px 10px 10px;}
	div.area {font-size:16px; text-align: center; line-height: 30px; width:500px; margin:auto}
	ul.li {padding:2px}
</style>

<div class="panel">

	<br/>
	<div class="area">
		<img src="<?php echo DUPLICATOR_PLUGIN_URL ?>assets/img/logo-dpro-300x50.png"  />
		<h2>
			<?php _e('Transfer your packages to multiple<br/> locations  with Duplicator Professional', 'duplicator') ?>
		</h2>

		<div style='text-align: left; margin:auto; width:200px'>
			<ul>
				<li><i class="fa fa-amazon"></i> <?php _e('Amazon S3', 'duplicator'); ?></li>
				<li><i class="fa fa-dropbox"></i> <?php _e(' Dropbox', 'duplicator'); ?></li>
				<li><i class="fa fa-google"></i> <?php _e('Google Drive', 'duplicator'); ?></li>
				<li><i class="fa fa-upload"></i> <?php _e('FTP', 'duplicator'); ?></li>
				<li><i class="fa fa-folder-open-o"></i> <?php _e('Custom Directory', 'duplicator'); ?></li>
			</ul>
		</div>
		<?php
			_e('Setup a one-time storage location and automatically <br/> push the package to your destination.', 'duplicator');
		?>
	</div><br/>

	<p style="text-align:center">
		<a href="https://snapcreek.com/duplicator/?utm_source=duplicator_free&utm_medium=wordpress_plugin&utm_content=manual_transfer&utm_campaign=duplicator_pro" target="_blank" class="button button-primary button-large dup-check-it-btn" >
			<?php _e('Learn More', 'duplicator') ?>
		</a>
	</p>
</div>
