<?php
/*
Plugin Name: Comment Author Checklist
Plugin URI: http://sillybean.net/code/comment-author-checklist
Description: Creates a template tag that generates a list of registered users and crosses off the names of those who have commented on a post.
Version: 2.0
Author: Stephanie Leary
Author URI: http://sillybean.net/

TODO
* line 312+
* make the checkbox in the comment form save as comment meta
* fix update_comment_author_checklist to use the comment meta

Changelog:
2.0 (January 2010)
	Wall of Shame widget identifies users who have not commented on the current submission
	Checklist history tag prints checklists in an archive format
	Commenter list now includes only users who were registered when the post was published
		(i.e.) new users are not held accountable for old posts
	Rewritten to use the settings API
1.04 (June 11, 2008)
	Changes to admin page
1.03 (April 4, 2008)
	Switched from text input to checkboxes for category selection
	Options are now removed from the database when the plugin is deactivated
1.02 (April 1, 2008)
	Bug fix AGAIN to get multiple categories working correctly
	Added the * option for all categories
	Added support for attributes in heading HTML
1.01 (March 31, 2008)
	Bug fix to get the "ignore admin user" checkbox working correctly.
1.0 (March 30, 2008)
	First release

Copyright 2010  Stephanie Leary  (email : steph@sillybean.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action('admin_menu', 'comment_author_checklist_add_pages');
add_action('admin_head', 'comment_author_checklist_css');
add_action('comment_post', 'add_comment_author_check');
add_action('comment_form', 'show_comment_checklist_checkbox');
add_action('save_post', 'set_comment_checklist_authors');
add_action('comment_post', 'update_comment_author_checklist');
//call register settings function
add_action('admin_init', 'comment_author_checklist_settings');

function comment_author_checklist_settings() {
	//register our settings
	register_setting( 'comment_author_checklist', 'comment_author_checklist' );
	register_setting( 'comment_author_checklist', 'comment_author_checklist_categories' );
}

function comment_author_checklist_add_pages() {
    // Add a new submenu under Options:
	add_options_page('Comment Checklist', 'Comment Checklist', 8, 'comment-author-checklist', 'comment_checklist_options');
}

// what to do when the plugin is deactivated
function unset_comment_checklist_options() {
	delete_option('comment_author_checklist');
	delete_option('comment_author_checklist_categories');
}
register_deactivation_hook(__FILE__, 'unset_comment_checklist_options');

// upgrade from old options when activated
function comment_author_checklist_convert_options() {
	$options = get_option('comment_author_checklist');
	if (is_array('comment_author_checklist')) {
		$cats = get_option('comment_author_checklist_categories');
		$options['ignore_admin'] = get_option('comment_author_checklist_ignore_admin');
			// convert from strings to ints
				if ($options['ignore_admin'] == 'yes') $options['ignore_admin'] = 1;
				else $options['ignore_admin'] = 0;
			// convert from ints to strings
			switch ($options['min_level']) {
				case 0: $options['min_level'] = 'subscriber'; break;
				case 1: $options['min_level'] = 'contributor'; break;
				case 2: $options['min_level'] = 'author'; break;
				case 5: $options['min_level'] = 'editor'; break;
				case 8: $options['min_level'] = 'administrator'; break;
				default: break;
			}
		$options['sort'] = get_option('comment_author_checklist_sort');
		$options['min_level'] = get_option('comment_author_checklist_min_level');
		$options['heading'] = get_option('comment_author_checklist_list_heading');
		// new option defaults
		$options['show'] = 'display_name';
		$options['checkbox'] = "This is my critique. Cross me off the list!";
	}
	else {
		// set defaults
		$cats = array();
		$options['ignore_admin'] = 0;
		$options['sort'] = 'user_id';
		$options['min_level'] = 'subscriber';
		$options['heading'] = '<h3>Checklist</h3>';
		$options['show'] = 'display_name';
		$options['checkbox'] = "This is my critique. Cross me off the list!";
	}
	add_option('comment_author_checklist_categories', $cats, '', 'yes');
	add_option('comment_author_checklist', $options, '', 'yes');
	
	delete_option('comment_author_checklist_categories');
	delete_option('comment_author_checklist_ignore_admin');
	delete_option('comment_author_checklist_sort');
	delete_option('comment_author_checklist_min_level');
	delete_option('comment_author_checklist_list_heading');
}
register_activation_hook(__FILE__, 'comment_author_checklist_convert_options');

function comment_author_checklist_css() {
		echo "<style type=\"text/css\">\n";
	 	echo "#list_categories li { list-style: none; }\n";	
		echo "#list_categories { margin-left: 0; padding-left: 0; }\n";	
		echo "</style>";
}

// displays the options page content
function comment_checklist_options() { ?>
    <div class="wrap">
		<h2>Post Signature</h2>
		<form method="post" action="options.php">
			<?php settings_fields('comment_author_checklist');
			$options = get_option('comment_author_checklist');
			$cats = get_option('comment_author_checklist_categories');
?>
	
        <h2><?php _e( 'Comment Author Checklist Options'); ?></h2>
        <h3><?php _e("Authors to show in the checklist:"); ?></h3>       
		<p>
          <label>
          <?php _e("Sort Authors by: "); ?>
          <select name="comment_author_checklist[sort]" id="sort">
            <option value="user_id" <?php selected("user_id", $options['sort']); ?>>
            	<?php _e('User ID'); ?></option>
            <option value="display_name" <?php selected("display_name", $options['sort']); ?>>
            	<?php _e('Display Name'); ?></option>
            <option value="user_firstname" <?php selected("user_firstname", $options['sort']); ?>>
            	<?php _e('First Name'); ?></option>
            <option value="user_lastname" <?php selected("user_lastname", $options['sort']); ?>>
            	<?php _e('Last Name'); ?></option>
            <option value="user_login" <?php selected("user_login", $options['sort']); ?>>
            	<?php _e('Login'); ?></option>
          </select>
          </label>
        </p>
		<p>
         <label>
         <?php _e("Show: "); ?>
         <select name="comment_author_checklist[show]" id="sort">
           <option value="display_name" <?php selected("display_name", $options['show']); ?>>
           	<?php _e('Display Name'); ?></option>
           <option value="user_firstname" <?php selected("user_firstname", $options['show']); ?>>
           	<?php _e('First Name'); ?></option>
           <option value="user_lastname" <?php selected("user_lastname", $options['show']); ?>>
           	<?php _e('Last Name'); ?></option>
		<option value="user_lastname" <?php selected("user_lastname", $options['show']); ?>>
           	<?php _e('First Name Last Name'); ?></option>
          	<option value="first_last" <?php selected("first_last", $options['show']); ?>>
           	<?php _e('Last Name, First Name'); ?></option>
 		<option value="last_comma_first" <?php selected("last_comma_first", $options['show']); ?>>
           	<?php _e('Login'); ?></option>
         </select>
         </label>
       </p>
        <p>
          <label>
          <?php _e("Minimum Author Level to Display: "); ?>
          <select name="comment_author_checklist[min_level]" id="min_level">
            <option value="administrator" <?php selected('administrator', $options['min_level']); ?>>
				<?php _e('Admins', 'comment-author-checklist'); ?></option>
			<option value="editor" <?php selected('editor', $options['min_level']); ?>>
				<?php _e('Editors', 'comment-author-checklist'); ?></option>
			<option value="author" <?php selected('author', $options['min_level']); ?>>
				<?php _e('Authors', 'comment-author-checklist'); ?></option>
			<option value="contributor" <?php selected('contributor', $options['min_level']); ?>>
				<?php _e('Contributors', 'comment-author-checklist'); ?></option>
			<option value="subscriber" <?php selected('subscriber', $options['min_level']); ?>>
				<?php _e('Subscribers', 'comment-author-checklist'); ?></option>
          </select>
          </label>
        </p>
        <p>
          <label>
          <input type="checkbox" name="comment_author_checklist[ignore_admin]" id="ignore_admin" value="1" 
				<?php checked($options['ignore_admin']); ?> /> <?php _e("Ignore 'admin' user"); ?>
          </label>
        </p>

        <h3><?php _e("Show the checklist in the following categories:"); ?></h3>
<?php $walker = new Comment_Author_Walker_Category_Checklist; ?>
        <ul id="list_categories"><?php wp_category_checklist(0, false, $cats, 0, $walker, false); ?></ul>

        <h3 style="clear: both;"><?php _e("Display Options: "); ?></h3>
        <p>
          <label>
          <?php _e("Heading for the Checklist: "); ?>
          <input type="text" name="comment_author_checklist[heading]" id="heading" value="<?php echo(stripslashes(stripslashes($options['heading']))); ?>" style="width: 30em;" />
          </label>
          <br />
          <small>
          <?php _e("Include HTML tags, if desired (example: &lt;h2&gt;Checklist&lt;/h2&gt;)"); ?>
          </small></p>
        <p>
          <label>
          <?php _e("Checkbox for comment authors: "); ?>
          <input type="text" name="comment_author_checklist[checkbox]" id="checkbox" value="<?php echo(stripslashes(stripslashes($options['checkbox']))); ?>" style="width: 30em;" />
          </label>
          <br />
          <small>
          <?php _e('Include HTML tags, if desired (example: &lt;small&gt;"This is my critique. Cross me off the list!"&lt;/small&gt;)'); ?>
          </small></p>
        	<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
        <p><?php _e("Once you have saved these options, add <code>&lt;?php if (function_exists(show_comment_author_checklist)) { show_comment_author_checklist(); } ?&gt;</code> to your post template where you would like the checklist to appear."); ?></p>
      </form>
    </div>
<?php } // end function comment_checklist_options() 

function add_comment_author_check($cid) {
	global $post, $wpdb;
	$cid = (int)$cid;
	$meta_key = 'current_users';
	$comment = get_comment($cid);
	$post = get_post($comment->comment_post_ID);
	
	$options = get_option('comment_author_checklist');
	
	if ( $comment->comment_approved == '1' && $comment->comment_type == '' ) {
		// Comment has been approved and isn't a trackback or a pingback
		if (in_category($options['show_in_categories'], $post)) {
			$user_level = get_usermeta('$user->ID', 'wp_user_level');
			$admin_passed = true;
			if (($user->user_login == 'admin') && ($ignore_admin))   //ignores admin user
				$admin_passed = false;
			if (($user_level >= $min_level) && ($admin_passed)) :
				$commenters = get_post_meta($post->ID, $meta_key, false); // get existing list
				$commenters[$comment->user_id] = 1;
				$oldlist = get_post_meta($post->ID, 'current_users', 0);
				update_post_meta($post->ID, $meta_key, $commenters, $oldlist); // update meta table
			endif; // end user_level test
		}
	}
}

function show_comment_checklist_checkbox() {
	$options = get_option('comment_author_checklist'); ?>
	<p class="comment-author-checklist-checkbox">
	<input type="checkbox" name="submit-critique" id="submit-critique" value="1" />
	<label for="submit-critique"><?php echo $options['checkbox']; ?></label>
	</p>
<?php
}

// prints the checklist (new template tag)
function show_comment_author_checklist() {
	global $post;
	
	// Read in existing option value from database
	$options = get_option('comment_author_checklist');
	$cats = get_option('comment_author_checklist_categories');
	
	if (in_category($cats) && (!empty($options['checkbox']))) {	
		$list = '';
		$commenters = get_post_meta($post->ID, 'current_users', 0); // get existing list
		$ids = array_keys($commenters[0]);
		echo "<pre>";
		print_r($commenters);
		print_r($ids);
		echo "</pre>";
//*
		foreach ($ids as $user) { // start users'loop
			$user_info = get_userdata($user);
			if ($commenters[$user]) {
				$list .= "\n<li><del>".$user_info->$options['show']."</del></li>"; 
			}
			else {
				$list .= "\n<li>".$user_info->$options['show']."</li>";	// else they haven't commented yet
			} 			
		} // end of the users' profile 'loop'
		/**/
	echo stripslashes(stripslashes(htmlspecialchars_decode($options['heading']))).
		"\n<ul class=\"comment-author-checklist\">\n".$list."\n</ul>";
	} // end if
} 

function set_comment_checklist_authors($id) {
//delete_post_meta($id, 'current_users');
	$users = array();
	$blogusers = get_users_of_blog(); //get all registered users
    foreach ($blogusers as $bloguser) {
		$users[$bloguser->user_id] = 0; // no one has commented yet; all are false
	}
	update_post_meta($id, 'current_users', $users); // update meta table
}

function update_comment_author_checklist($commentid, $status) {
	if ($status == 1) { // it's approved and not spam
		// how do we get the value from the checkbox in the comment form?
		// should it have been added in some other way?
		// to do here:
		// grab the checkbox from the comment meta.
		// if the comment author is in the original list from the post meta (comment parent?), update the post meta array
		// OR 
		// simplify the post meta array to store the current authors only, and then loop through the comments looking for this meta value
		// then compare the arrays to generate the checklist
	}
}

// Custom Walker, so we can change the class on the checkboxes (this is really silly)
class Comment_Author_Walker_Category_Checklist extends Walker {
      var $tree_type = 'category';
      var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this
  
  	function start_lvl(&$output, $depth, $args) {
          $indent = str_repeat("\t", $depth);
          $output .= "$indent<ul class='children'>\n";
      }
  
  	function end_lvl(&$output, $depth, $args) {
          $indent = str_repeat("\t", $depth);
          $output .= "$indent</ul>\n";
      }
  
  	function start_el(&$output, $category, $depth, $args) {
          extract($args);
  
         $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
          $output .= "\n<li id='category-$category->term_id'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="comment_author_checklist_categories[]" id="in-category-' . $category->term_id . '"' . (in_array( $category->term_id, $selected_cats ) ? ' checked="checked"' : "" ) . '/> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
      }
  
  	function end_el(&$output, $category, $depth, $args) {
          $output .= "</li>\n";
      }
  }


// i18n
$plugin_dir = basename(dirname(__FILE__)). '/languages';
load_plugin_textdomain( 'comment_author_checklist', 'wp-content/plugins/' . $plugin_dir, $plugin_dir );
?>
