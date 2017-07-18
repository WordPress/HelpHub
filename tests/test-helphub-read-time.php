<?php
/**
 * Class SampleTest
 *
 * @package Oreore
 */

/**
 * Sample test case.
 */
class Helphub_Read_Time_Test extends WP_UnitTestCase {

	/**
	 * @covers hh_calculate_and_update_post_read_time
	 */
	public function test_hh_calculate_and_update_post_read_time() {
		$user = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $user );

		// 175 words.
		$lorem_ipsum_for_minute = 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget';

		$post = $this->factory->post->create_and_get(
			array(
				'post_content' => $lorem_ipsum_for_minute,
			)
		);
		hh_calculate_and_update_post_read_time( $post->ID, $post, false );
		$this->assertContains( '_read_time', get_post_custom_keys( $post->ID ) );
		$read_time = get_post_meta( $post->ID, '_read_time', true );
		$this->assertEquals( 60, $read_time );

		// Test for <pre>.
		$post_with_pretag = $this->factory->post->create_and_get(
			array(
				'post_content' => '<pre>' . $lorem_ipsum_for_minute . '</pre>',
			)
		);
		hh_calculate_and_update_post_read_time( $post_with_pretag->ID, $post_with_pretag, false );
		$this->assertContains( '_read_time', get_post_custom_keys( $post_with_pretag->ID ) );
		$read_time = get_post_meta( $post_with_pretag->ID, '_read_time', true );
		$this->assertEquals( 120, $read_time );

		// Test for <img>.
		$post_with_img = $this->factory->post->create_and_get(
			array(
				'post_content' => '<img src="dummy.png" />',
			)
		);
		hh_calculate_and_update_post_read_time( $post_with_img->ID, $post_with_img, false );
		$this->assertContains( '_read_time', get_post_custom_keys( $post_with_img->ID ) );
		$read_time = get_post_meta( $post_with_img->ID, '_read_time', true );
		$this->assertEquals( 11, $read_time );

	}
}
