<?php
/*
 * Plugin Name: HelpHub Search
 * Description: Extends WordPress's default search, provide auto suggestion and ability to highlight result results based on search terms.
 * Version:     1.0.0
 * Author:      justingreerbbi
 * Author URI:  https://wordpress.org
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Developer Objectives:
 *
 * HelpHub Search should extend WordPress's default search functionality to provide awesomness 
 * a.k.a tailor search results for HelpHub.
 *
 * Goal 1: Provide ability to show highlighted terms in search returns
 * Goal 2: Provide a non intrusive auto complete or suggestion drop down in search input
 * Goal 3: Dig deeper into the heart and get better tailored results on search submission. 
 * Goal 4: IMPORTANT.... Provide a reason able throttle to prevent abuse. The search after all will be the work horse.
 *
 * Note: To keep things simple and light weight on the front-end, the auto complete feature should only search
 * article titles and maybe excerpts. 
 *
 * Want: Depending on the CPT, category and tag structure, it would be nice to auto suggest defined groups in a
 * nice drop down.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

