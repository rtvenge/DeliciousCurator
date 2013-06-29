<?php
/*
Plugin Name: Delicious Curator
Plugin URI: http://ryantvenge.com
Description: Create WP-post based on Delicious tag
Version: 0.4.1
Author: Jonas Nordstrom (Modified by Ryan Tvenge)
Author URI: http://jonasnordstrom.se/
*/
/**
 * Copyright (c) 2012 Jonas Nordstrom. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 */
if (!class_exists("DeliciousCurator")) {
	class DeliciousCurator {

		// Max number of items
		protected $max_items;
		protected $delicious_user;
		protected $delicious_tag;
		protected $item_elements;
		protected $title_elements;

		// Constructor
		public function DeliciousCurator() {
			$this->max_items = get_option('delicious-curator-maxitems', '10');
			$this->delicious_user = get_option('delicious-curator-delicious-user', '10');
			$this->delicious_pass = get_option('delicious-curator-delicious-pass', '10');
			$this->delicious_tag = get_option('delicious-curator-delicious-tag', '10');
			$this->item_elements = array (
									'%TITLE%'               =>      'feed item title',
									'%LINK%'                =>      'link for the feed item',
									'%DATE%'                =>      'item publish date',
									'%NOTE%'                =>      'feed note',
			                		);
			$this->title_elements = array (
									'%DATE%'                =>      'post publish date'
									);
		}

		// initialization, setup localization
		public function init_delicious_curator() {
			// Set up localization
			$plugin_dir = basename(dirname(__FILE__));
			load_plugin_textdomain( 'deliciouscurator', 'wp-content/plugins/'. $plugin_dir.'/languages', $plugin_dir.'/languages' );
		}

		// Admin page for plugin
		function delicious_curator_admin_page() {
			// Handle updates
			if( isset( $_POST['action'] ) && $_POST[ 'action' ] == 'save' ) {
				check_admin_referer('delicious-curator-save-action', 'delicious-curator-save');
				update_option('delicious-curator-maxitems',       $_POST[ 'delicious-curator-maxitems' ]);
				update_option('delicious-curator-delicious-user', $_POST[ 'delicious-curator-delicious-user' ]);
				update_option('delicious-curator-delicious-pass', $_POST[ 'delicious-curator-delicious-pass' ]);
				update_option('delicious-curator-delicious-tag',  $_POST[ 'delicious-curator-delicious-tag' ]);
				update_option('delicious-curator-author',         $_POST[ 'delicious-curator-author' ]);
				update_option('delicious-curator-category',       $_POST[ 'delicious-curator-category' ]);
				update_option('delicious-curator-tags',           $_POST[ 'delicious-curator-tags' ]);
				update_option('delicious-curator-post-title',     $_POST[ 'delicious-curator-post-title' ]);
				update_option('delicious-curator-header',         $_POST[ 'delicious-curator-header' ]);
				update_option('delicious-curator-footer',         $_POST[ 'delicious-curator-footer' ]);
				update_option('delicious-curator-item',           $_POST[ 'delicious-curator-item' ]);
				?>
				<div class="updated"><p><strong><?php _e('Settings saved.', 'deliciouscurator' ); ?></strong></p></div>
				<?php

			}

			if( isset( $_POST['action'] ) && $_POST[ 'action' ] == 'run' ) {
				check_admin_referer('delicious-curator-run-action', 'delicious-curator-run');

				$items_posted = 0;

				$maxitems =      get_option('delicious-curator-maxitems');
				$delicious_user = get_option('delicious-curator-delicious-user');
				$delicious_pass = get_option('delicious-curator-delicious-pass');
				$delicious_tag =  get_option('delicious-curator-delicious-tag');
				$author =         get_option('delicious-curator-author');
				$category =       get_option('delicious-curator-category');
				$tags =           get_option('delicious-curator-tags');
				$post_title =     get_option('delicious-curator-post-title');
				$header =         get_option('delicious-curator-header');
				$footer =         get_option('delicious-curator-footer');
				$item_template =  get_option('delicious-curator-item');

				$api_url = 'https://' . $delicious_user . ':' . $delicious_pass . '@api.del.icio.us/v1/posts/all?red=api&results=' . $maxitems;

				//API link
				$api_data = simplexml_load_file($api_url, 'SimpleXMLElement', LIBXML_NOWARNING);

				//IF FEED is good
				if ( $api_data ) {

				    foreach ( $api_data as $item ) {

						$item_date = strtotime($item->attributes()->time);
						$new_item['entry_time'] = $item_date;
						$new_item["title"] = $item->attributes()->description;
						$new_item["link"] = $item->attributes()->href;
						$new_item["description"] = $item->attributes()->extended;

						$new_items[] = $new_item;
					}

				} else {
					echo '<div class="error"><p><strong>Couldn\'t get API feed. :(</strong></p></div>';
				}
				$prev_max_date = get_option('delicious-curator-prev-max-date');
				if (count($new_items) > 0) {
					$max_date = $prev_max_date;
					foreach ($new_items as $item) {

						$item_date = date(get_option("date_format"), $item["entry_time"]);

						if ($item["entry_time"] > $prev_max_date) {

							if( $item["entry_time"] > $max_date ) {
								$max_date = $item["entry_time"];
							}

							$import = html_entity_decode($item_template);
							$import = str_replace ( array_keys ( $this->item_elements ), array (
								$item["title"],
								$item["link"],
								$item_date,
								$item["description"],
								), $import );
							$post_content.= $import;
							$items_posted++;
						}
					}
					if ( $items_posted > 0 ) {
						$post_title = str_replace( array_keys ( $this->title_elements ), date(get_option("date_format")), $post_title);
						$my_post = array(
							'post_title' => $post_title,
							'post_content' => $header . $post_content . $footer,
							'post_status' => 'draft',
							'post_category' => array($category),
							'tags_input' => $tags,
							'post_author' => $author
							);
						$new_post_id = wp_insert_post( $my_post );
						update_option('delicious-curator-prev-max-date', $max_date);
					}
				}

				if ( !empty( $new_post_id ) ) {
					$message = $items_posted . " link(s) added to new post. <a href='" . get_edit_post_link($new_post_id) . "'>Edit</a>";
				} else {
					$message = sprintf(__("No new links found since %s. Nothing posted.", "deliciouscurator"), date(get_option("date_format"), $prev_max_date));
				}
				?>
				<div class="updated"><p><strong><?php echo  $message; ?></strong></p></div>
				<?php

			}

			// The form, with all names and a checkbox in front
			echo '<div class="wrap">';
			echo "<h2>" . __("Delicious Curator", "deliciouscurator") . "</h2>";
			?>
			<form name="delicious-curator-admin-form" method="post" action="">
				<input type="hidden" name="action" value="save" />
				<?php wp_nonce_field('delicious-curator-save-action', 'delicious-curator-save'); ?>
				<table class="delicious-curator-form-table">
					<tr>
						<td><?php _e("Max items", "deliciouscurator"); ?></td>
						<td><input type="text" id="delicious-curator-maxitems" name="delicious-curator-maxitems" value="<?php echo esc_html(stripslashes(get_option( 'delicious-curator-maxitems', '10' ))); ?>" /></td>
					</tr>
					<tr>
						<td><?php _e("Delicious User", "deliciouscurator"); ?></td>
						<td><input type="text" id="delicious-curator-delicious-user" name="delicious-curator-delicious-user" value="<?php echo esc_html(stripslashes(get_option( 'delicious-curator-delicious-user', 'USERNAME' ))); ?>" /></td>
					</tr>
					<tr>
						<td><?php _e("Delicious Password", "deliciouscurator"); ?></td>
						<td><input type="password" id="delicious-curator-delicious-pass" name="delicious-curator-delicious-pass" value="<?php echo esc_html(stripslashes(get_option( 'delicious-curator-delicious-pass' ))); ?>" /></td>
					</tr>
					<tr>
						<td><?php _e("Delicious Tag", "deliciouscurator"); ?></td>
						<td><input type="text" id="delicious-curator-delicious-tag" name="delicious-curator-delicious-tag" value="<?php echo esc_html(stripslashes(get_option( 'delicious-curator-delicious-tag', 'TAG or TAG+TAG' ))); ?>" /></td>
					</tr>
					<tr>
						<td><?php _e("Post title", "deliciouscurator"); ?></td>
						<td><input type="text" id="delicious-curator-post-title" name="delicious-curator-post-title" value="<?php echo esc_html( stripslashes(get_option( 'delicious-curator-post-title', 'My links %DATE%' ))); ?>" /></td>
					</tr>
					<tr>
						<td></td>
						<td>You can use these tags:<br/>
						<?php foreach ($this->title_elements as $tag => $desc) { echo "{$tag}: {$desc}<br/>"; } ?></td>
					</tr>
					<tr>
						<td><?php _e("Header", "deliciouscurator"); ?></td>
						<td><input type="text" id="delicious-curator-header" name="delicious-curator-header" value="<?php echo esc_html(stripslashes(get_option( 'delicious-curator-header', "<ol class=\"link-list\">" ))); ?>" /></td>
					</tr>
					<tr>
						<td><?php _e("Footer", "deliciouscurator"); ?></td>
						<td><input type="text" id="delicious-curator-footer" name="delicious-curator-footer" value="<?php echo esc_html(stripslashes(get_option( 'delicious-curator-footer', "</ol>" ))); ?>" /></td>
					</tr>
					<tr>
						<td><?php _e("Item", "deliciouscurator"); ?></td>
						<td><textarea cols="60" rows="3" id="delicious-curator-item" name="delicious-curator-item"><?php echo esc_html(stripslashes(get_option( 'delicious-curator-item', '<li><a href="%LINK%" title="%TITLE%">%TITLE%</a><br>%NOTE%</li>' ))); ?></textarea></td>
					</tr>
					<tr>
						<td></td>
						<td>You can use these tags:<br/>
						<?php foreach ($this->item_elements as $tag => $desc) { echo "{$tag}: {$desc}<br/>"; } ?></td>
					</tr>
					<tr>
						<td><?php _e("Author", "deliciouscurator"); ?></td>
						<td>
							<select name="delicious-curator-author" id="delicious-curator-author">
			                    <?php
		                        $all_users = get_users();
		                        foreach ($all_users as $u) {
									$selected = "";
									if ($u->ID == get_option( 'delicious-curator-author' )) $selected = ' selected="selected"';
									echo '<option value="'.$u->ID.'"'.$selected.'>'.$u->display_name.'</option>';
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td><?php _e("Category", "deliciouscurator"); ?></td>
						<td>
							<?php
							$dropdown_options = array(
														'show_option_all' => '',
														'hide_empty' => 0,
														'hierarchical' => 1,
					                            		'show_count' => 0,
														'depth' => 0,
														'orderby' => 'ID',
														'selected' => get_option( 'delicious-curator-category' ),
														'name' => 'delicious-curator-category');
							wp_dropdown_categories($dropdown_options);
							?>
						</td>
					</tr>
					<tr>
						<td><?php _e("Tags", "deliciouscurator"); ?></td>
						<td><input type="text" id="delicious-curator-tags" name="delicious-curator-tags" value="<?php echo stripslashes(get_option( 'delicious-curator-tags' )); ?>" /></td>
					</tr>
					<tr>
						<td colspan="2">
							<p class="submit">
								<input type="submit" name="Submit" value="<?php _e('Update options', 'deliciouscurator' ) ?>" />
							</p>
						</td>
					</tr>
				</table>
			</form>
			<form name="delicious-curator-run-form" method="post" action="">
				<input type="hidden" name="action" value="run" />
				<?php wp_nonce_field('delicious-curator-run-action', 'delicious-curator-run'); ?>
				<table class="delicious-curator-form-table">
					<tr>
						<td colspan="2">
							<p class="submit">
								<input type="submit" name="Submit" value="<?php _e('Run now', 'deliciouscurator' ) ?>" />
							</p>
						</td>
					</tr>

				</table>
			</form>

			</div>
			<?php
		}

		public function init_delicious_curator_admin() {
			add_options_page(__('Delicious Curator Settings', "deliciouscurator"), __('Delicious Curator', "deliciouscurator"), 'manage_options', basename(__FILE__), array(&$this, 'delicious_curator_admin_page') );
		}

	}
}

// Init class
if (class_exists("DeliciousCurator")) {
	$deliciouscurator = new DeliciousCurator();
}

// Hooks, Actions and Filters, oh my!
add_action( 'init', array(&$deliciouscurator, 'init_delicious_curator'));
add_action( 'admin_menu', array(&$deliciouscurator, 'init_delicious_curator_admin' ));

function _log() {
  if( WP_DEBUG === true ){
    $args = func_get_args();
    error_log(print_r($args, true));
  }
}

