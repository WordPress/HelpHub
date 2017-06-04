<?php

function members_get_role_meta_option( $role ) {

	return get_option( "members_{$role}_role_meta", array() );
}

function members_add_role_meta( $role, $meta_key, $meta_value, $unique = false ) {

	$check = apply_filter( 'members_add_role_metadata', null, $role, $meta_key, $meta_value, $unique );

	if ( ! is_null( $check ) )
		return $check;

	$role = members_sanitize_role( $role );


	$option = members_get_role_meta_option( $role );

	$add_value = $option[ $meta_key ][] = $meta_value;

}


function members_get_role_meta( $role, $meta_key = '', $single = false ) {


	// Devs can short-circuit this by returning anything other than `null`.
	$check = apply_filters( 'members_get_role_metadata', null, $role, $meta_key, $single );

	if ( ! is_null( $check ) ) {

		return $single && is_array( $check ) ? $check[0] : $check;
	}


	$role = members_sanitize_role( $role );


	$option = members_get_role_meta_option( $role );

	if ( ! $option || ! isset( $option[ $meta_key ] ) )
		return '';

	$meta_value = $option[ $meta_key ];

	return $single && is_array( $meta_value ) ? $meta_value[0] : $meta_value;
}











