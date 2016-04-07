<?php 
/**
 *
 * @link              http://www.geekyposh.com
 * @since             1.0.0
 * @package           Improved_Comment_Reply_Notification
 *
 * @wordpress-plugin
 * Plugin Name:       Improved Comment Reply Notification
 * Plugin URI:        http://example.com/plugin-name-uri/
 * Description:       Based on the original comment reply notification (https://www.nosegraze.com/comment-interaction/) and 
 *					  Nose Graze's version (https://www.nosegraze.com/comment-interaction/)
 * Version:           1.0.0
 * Author:            Jenny Wu
 * Author URI:        http://www.geekyposh.com/
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl.txt
 * Text Domain:       improved-comment-reply-notification
 */

class GP_Comment_Reply {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 */
	public function __construct() {
		$this->plugin_name = 'improved-comment-reply-notification';
		$this->version = '1.0.0';

		add_action('wp_insert_comment', array( $this, 'comment_notification' ), 99, 2 );
		add_action('comment_post', array($this,'save_mail_reply'));
		add_action('comment_form_after_fields', array($this,'add_reply_id_form_field'),99, 2);
		add_action('wp_set_comment_status', array( $this, 'comment_status_changed' ), 99, 2 );
		add_action('add_meta_boxes_comment', array( $this, 'extend_comment_add_mail_status' ) );
	}

	/**
	 * When a comment gets published, this checks to see if an email should
	 * be sent. If so, it fires the send_email function.
	 *
	 * @param int    $comment_id
	 * @param object $comment_object
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function comment_notification($comment_id, $comment_object) {
		// This comment is not approved or it's not a reply to a parent comment - do not email.
		if ( $comment_object->comment_approved != 1 || $comment_object->comment_parent < 1 ) {
			return;
		}

		// If someone is replying to themselves, don't send an email.
		$comment_parent = get_comment($comment_object->comment_parent);
		if ( $comment_parent->comment_author_email == $comment_object->comment_author_email ) {
			return;
		}

		//if the user opted out of receiving comment reply notifications, don't send an email
		$comment_mail_notify = get_comment_meta($comment_parent->comment_ID, 'comment_mail_notify', true);
		if ($comment_mail_notify != '1') {
			return;
		}

		// Let's send the email in all other scenarios.
		$this->send_email( $comment_id, $comment_object, $comment_parent );
	}

	/**
	 * Triggers when the status of a comment gets changed (like if we approve
	 * it later). This also determines if an email should be sent, and if so,
	 * it calls our comment_notification() method.
	 *
	 * @param int    $comment_id
	 * @param string $comment_status
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function comment_status_changed( $comment_id, $comment_status ) {
		$comment_object = get_comment( $comment_id );
		if ( $comment_status == 'approve' ) {
			$this->comment_notification( $comment_object->comment_ID, $comment_object );
		}
	}

	/**
	 * Crafts the comment reply message and sends the email.
	 *
	 * @param int    $comment_id
	 * @param object $comment_object
	 * @param object $comment_parent
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function send_email( $comment_id, $comment_object, $comment_parent ) {
		$recipient = $comment_parent->comment_author_email;
		$subject   = 'Your comment at Geeky Posh has a new reply!';

		ob_start();

		?>
		<p>Hi there love! There's a new reply to your comment over on Geeky Posh. As a reminder, here's your
			original comment on the post <strong><a href="<?php echo get_permalink( $comment_parent->comment_post_ID ); ?>"><?php echo get_the_title( $comment_parent->comment_post_ID ); ?></a></strong>:
		</p>
		<blockquote><?php echo $comment_parent->comment_content; ?></blockquote>
		<p>And here's the new reply from <strong><?php echo $comment_object->comment_author; ?></strong>:</p>
		<blockquote><?php echo $comment_object->comment_content; ?></blockquote>
		<p>You can read and reply to the comment here: <a href="<?php echo get_comment_link( $comment_object ); ?>"><?php echo get_comment_link( $comment_object ); ?></a>
		</p>
		<p>Thank you for commenting and let's keep the conversation going! :) </p>
		<br>
		<p><strong>This email was sent automatically. Please don't reply to this email.</strong></p>
		<?php

		$message = ob_get_clean();

		$headers = array('Content-Type: text/html; charset=UTF-8');
		wp_mail( $recipient, $subject, $message, $headers );
	}

	/**
	 * Saves the commentor's reply notification settings in the wp_commentmeta table
	 *
	 * @param int    $comment_id
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function save_mail_reply($comment_id){
		if ( isset( $_POST['comment_mail_notify'] ) ){
			add_comment_meta( $comment_id, 'comment_mail_notify', $_POST['comment_mail_notify'] );
		}else{
			add_comment_meta( $comment_id, 'comment_mail_notify', '0' );
		}	
	}

	/**
	 * Adds a little checkbox to the comment form for users to opt-out of comment reply notifications.
	 * Default option is to receive these notifications (box is checked).
	 *
	 * @param int    $comment_id
	 *
	 * @access public
	 * @since  1.0.0
	 * @return void
	 */
	public function add_reply_id_form_field($comment_id){
		echo '<p><input type="checkbox" name="comment_mail_notify" id="comment_mail_notify" checked="checked" value="1" /><label for="comment_mail_notify">Notify me of follow-up comments via e-mail</label></p>';
	}
}

new GP_Comment_Reply();
?>