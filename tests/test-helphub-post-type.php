<?php
/**
 * Class Helphub_Post_Type_Test
 *
 * @package HelpHub
 */

class Helphub_Post_Type_Test extends WP_UnitTestCase {

	/**
	 * `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should have correct `<label>`.
	 */
	function test_the_meta_box_content_render_should_have_label() {
		// See `meta_box_content_render()`.
		$field_types = array(
			'hidden',
			'text',
			'url',
			'textarea',
			'editor',
			'upload',
			'radio',
			'checkbox',
			'multicheck',
			'select',
			'foo', // It is undefined, so it should be applied defaults.
		);

		$fields = array();
		foreach ( $field_types as $type ) {
			$fields[ "$type-field" ] = array(
				'name' => "Hey this is a label for $type field",
				'description' => "Hey this is a description for $type",
				'type' => $type,
				'default' => '',
				'section' => 'info',
				'label' => "this is as label for $type",
			);
		}

		$html = $this->meta_box_content_render( $fields );

		foreach ( $field_types as $type ) {
			// Following shouldn't have `<label>`.
			if ( in_array( $type, array( 'hidden', 'radio', 'checkbox', 'multicheck', 'foo' ) ) ) {
				$this->assertNotContains(
					'<label for="' . $type . '-field">',
					$html
				);
				continue;
			}
			$this->assertContains(
				'<label for="' . $type . '-field">Hey this is a label for ' . $type . ' field</label>',
				$html
			);
		}
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render `<input>`.
	 */
	function test_meta_box_content_render_should_render_input() {
		// See `meta_box_content_render()`.
		$field_types = array(
			'hidden',
			'text',
			'url',
		);

		$fields = array();
		foreach ( $field_types as $type ) {
			$fields[ "$type-field" ] = array(
				'name' => "Hey this is a label for $type field",
				'description' => "Hey this is a description for $type",
				'type' => $type,
				'default' => '',
				'section' => 'info',
				'label' => "this is as label for $type",
			);
		}

		$html = $this->meta_box_content_render( $fields );

		$this->assertContains( '<input name="hidden-field" type="hidden" id="hidden-field" value="" />', $html );
		$this->assertContains( '<input name="text-field" type="text" id="text-field" class="regular-text" value="" />', $html );
		$this->assertContains( '<input name="url-field" type="text" id="url-field" class="regular-text" value="" />', $html );
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render `<textarea>`.
	 */
	function test_meta_box_content_render_should_render_textarea() {
		$fields = array(
			'textarea-field' => array(
				'name' => 'Hey this is a label for textarea field',
				'description' => 'Hey this is a description for textarea',
				'type' => 'textarea',
				'default' => '',
				'section' => 'info',
				'label' => 'this is as label for textarea',
			),
		);
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<textarea name="textarea-field" id="textarea-field" class="large-text"></textarea>', $html );

		$fields['textarea-field']['default'] = '<b>It should be escaped</b>';
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<textarea name="textarea-field" id="textarea-field" class="large-text">&lt;b&gt;It should be escaped&lt;/b&gt;</textarea>', $html );
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render `wp_editor()`.
	 */
	function test_meta_box_content_render_should_render_editor() {
		$fields = array(
			'editor-field' => array(
				'name' => 'Hey this is a label for editor field',
				'description' => 'Hey this is a description for editor',
				'type' => 'editor',
				'default' => '',
				'section' => 'info',
				'label' => 'this is as label for editor',
			),
		);
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<div id="wp-editor-field-wrap" class="wp-core-ui wp-editor-wrap html-active"><div id="wp-editor-field-editor-container" class="wp-editor-container"><div id="qt_editor-field_toolbar" class="quicktags-toolbar"></div><textarea class="wp-editor-area" rows="10" cols="40" name="editor-field" id="editor-field"></textarea></div>', $html );

		$fields['editor-field']['default'] = '<b>It should not be escaped</b>';
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<div id="wp-editor-field-wrap" class="wp-core-ui wp-editor-wrap html-active"><div id="wp-editor-field-editor-container" class="wp-editor-container"><div id="qt_editor-field_toolbar" class="quicktags-toolbar"></div><textarea class="wp-editor-area" rows="10" cols="40" name="editor-field" id="editor-field"><b>It should not be escaped</b></textarea></div>', $html );
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render uploader.
	 */
	function test_meta_box_content_render_should_render_uploader() {
		$fields = array(
			'upload-field' => array(
				'name' => 'Hey this is a label for upload field',
				'description' => 'Hey this is a description for upload',
				'type' => 'upload',
				'default' => '',
				'section' => 'info',
				'label' => 'this is as label for upload',
			),
		);
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<input name="upload-field" type="file" id="upload-field" class="regular-text helphub-upload-field" /><button id="upload-field" class="helphub-upload button">this is as label for upload</button>', $html );
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render radio.
	 */
	function test_meta_box_content_render_should_render_radio() {
		$fields = array(
			'radio-field' => array(
				'name' => 'Hey this is a label for radio field',
				'description' => 'Hey this is a description for radio',
				'type' => 'radio',
				'default' => '',
				'section' => 'info',
				'label' => 'this is as label for radio',
				'options' => array(
					'miya' => 'cool',
					'jon' => 'very cool',
				),
			),
		);
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<p><label for="radio-field-miya"><input id="radio-field-miya" type="radio" name="radio-field" value="miya"  />cool</label></p>', $html );
		$this->assertContains( '<p><label for="radio-field-jon"><input id="radio-field-jon" type="radio" name="radio-field" value="jon"  />very cool</label></p>', $html );

		$fields['radio-field']['default'] = 'jon';
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<p><label for="radio-field-miya"><input id="radio-field-miya" type="radio" name="radio-field" value="miya"  />cool</label></p>', $html );
		$this->assertContains( '<p><label for="radio-field-jon"><input id="radio-field-jon" type="radio" name="radio-field" value="jon"  checked=\'checked\' />very cool</label></p>', $html );
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render multicheck.
	 */
	function test_meta_box_content_render_should_render_multicheck() {
		$fields = array(
			'multicheck-field' => array(
				'name' => 'Hey this is a label for multicheck field',
				'description' => 'Hey this is a description for multicheck',
				'type' => 'multicheck',
				'default' => '',
				'section' => 'info',
				'label' => 'this is as label for multicheck',
				'options' => array(
					'miya' => 'cool',
					'jon' => 'very cool',
				),
			),
		);
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<p><label for="multicheck-field-miya"><input id="multicheck-field-miya" type="checkbox" name="multicheck-field[]" value="miya"  />cool</label></p>', $html );
		$this->assertContains( '<p><label for="multicheck-field-jon"><input id="multicheck-field-jon" type="checkbox" name="multicheck-field[]" value="jon"  />very cool</label></p>', $html );

		$fields['multicheck-field']['default'] = array( 'jon' );
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<p><label for="multicheck-field-miya"><input id="multicheck-field-miya" type="checkbox" name="multicheck-field[]" value="miya"  />cool</label></p>', $html );
		$this->assertContains( '<p><label for="multicheck-field-jon"><input id="multicheck-field-jon" type="checkbox" name="multicheck-field[]" value="jon"  checked=\'checked\' />very cool</label></p>', $html );
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render `<select>`.
	 */
	function test_meta_box_content_render_should_render_select() {
		$fields = array(
			'select-field' => array(
				'name' => 'Hey this is a label for select field',
				'description' => 'Hey this is a description for select',
				'type' => 'select',
				'default' => '',
				'section' => 'info',
				'label' => 'this is as label for select',
				'options' => array(
					'miya' => 'cool',
					'jon' => 'very cool',
				),
			),
		);
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<select name="select-field" id="select-field" ><option value="miya" >cool</option><option value="jon" >very cool</option></select>', $html );

		$fields['select-field']['default'] = 'jon';
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<select name="select-field" id="select-field" ><option value="miya" >cool</option><option value="jon"  selected=\'selected\'>very cool</option></select>', $html );
	}

	/**
	 * The `HelpHub_Post_Types_Post_Type::meta_box_content_render()` should render date.
	 */
	function test_meta_box_content_render_should_render_date() {
		$fields = array(
			'date-field' => array(
				'name' => 'Hey this is a label for date field',
				'description' => 'Hey this is a description for date',
				'type' => 'date',
				'default' => '',
				'section' => 'info',
				'label' => 'this is as label for date',
			),
		);
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<input name="date-field" type="date" id="date-field" class="helphub-meta-date" value="' . esc_attr( date_i18n( 'F d, Y', time() ) ) . '" />', $html );

		$fields['date-field']['default'] = 1483788575;
		$html = $this->meta_box_content_render( $fields );
		$this->assertContains( '<input name="date-field" type="date" id="date-field" class="helphub-meta-date" value="' . esc_attr( date_i18n( 'F d, Y', $fields['date-field']['default'] ) ) . '" />', $html );
	}

	/**
	 * An alias method of the `HelpHub_Post_Types_Post_Type::meta_box_content_render()`.
	 *
	 * @param array $fields An array of the meta fields.
	 * @return string The HTML.
	 */
	private function meta_box_content_render( $fields ) {
		$post_type = new HelpHub_Post_Types_Post_Type(
			'post',
			__( 'Post', 'helphub' ),
			__( 'Posts', 'helphub' ),
			array(
				'menu_icon' => 'dashicons-post',
			)
		);

		ob_start();
		$post_type->meta_box_content_render( $fields );
		$html = ob_get_contents();
		ob_end_clean();

		return trim( str_replace( "\n", '', $html ) );
	}
}
