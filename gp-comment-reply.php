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
 * Description:       Based on the original comment reply notification (https://www.nosegraze.com/comment-interaction/) and Nose Graze's version (https://www.nosegraze.com/comment-interaction/)
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

	 	add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
	 	add_action('wp_insert_comment', array( $this, 'comment_notification' ), 99, 2 );
	 	add_action('comment_post', array($this,'save_mail_reply'));
	 	add_action('comment_form', array($this,'add_reply_id_form_field'),99, 2);
	 	add_action('wp_set_comment_status', array( $this, 'comment_status_changed' ), 99, 2 );
	 	//add_action('add_meta_boxes_comment', array( $this, 'extend_comment_add_mail_status' ) );
	 }

	/**
	 * When a comment gets published, this checks to see if an email should be sent. If so, it fires the send_email function.
	 *
	 * @since  1.0.0
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
	 * Triggers when the status of a comment gets changed (like if we approve it later). This also determines if an email should be sent, and if so, it calls our comment_notification() method.
	 *
	 * @since  1.0.0
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
	 * @since  1.0.0
	 */
	public function send_email( $comment_id, $comment_object, $comment_parent ) {
		$email_options = get_option('gp_email_options');
		$recipient = $comment_parent->comment_author.' <'.$comment_parent->comment_author_email.'>';
		$subject   = $email_options['email_subject'];
		$shortcode_replace = array( 
			'[BLOGNAME]' => get_option('blogname'), 
			'[POSTNAME]' => get_the_title( $comment_parent->comment_post_ID ), 
			'[POSTLINK]' => get_permalink( $comment_parent->comment_post_ID ),
			'[COMMENTAUTHOR]' => $comment_parent->comment_author,
			'[ORIGINALCOMMENT]' => $comment_parent->comment_content ,
			'[REPLYCOMMENT]' => $comment_object->comment_content ,
			'[REPLYCOMMENTAUTHOR]' => $comment_object->comment_author ,
			'[COMMENTLINK]' => get_comment_link( $comment_object )
		);
		$original_content = $email_options['email_content']; 
		ob_start();
		echo $this->strReplaceAssoc($shortcode_replace,$original_content); 

		$message = ob_get_clean();

		$headers = array('From: '.get_option('blogname').' <'.get_option('admin_email').'>', 'Content-Type: text/html; charset=UTF-8');
		wp_mail( $recipient, $subject, $message, $headers );
	}
	public function strReplaceAssoc(array $replace, $subject) { 
   		return str_replace(array_keys($replace), array_values($replace), $subject);    
	} 
	/**
	 * Adds a little checkbox to the comment form for users to opt-out of comment reply notifications. Default option is to receive these notifications (box is checked).
	 *
	 * @since  1.0.0
	 */
	public function add_reply_id_form_field($comment_id){
		echo '<p><input type="checkbox" name="gp_comment_mail_notify" id="gp_comment_mail_notify" checked="checked" value="1" /><label for="gp_comment_mail_notify">Notify me of follow-up comments via e-mail</label></p>';
	}
	public function save_mail_reply($comment_id){
		if ( isset( $_POST['gp_comment_mail_notify'] ) ){
			add_comment_meta( $comment_id, 'comment_mail_notify', $_POST['gp_comment_mail_notify'] );
		}else{
			add_comment_meta( $comment_id, 'comment_mail_notify', '0' );
		}	
	}

	/**
	 * Adds a settings page for the plugin - woohoo!
	 *
	 * @since  1.0.0
	 */
	public function add_plugin_page(){
		add_options_page(
			'Improved Comment Reply Notifications Settings',
			'Improved Comment Reply Notifications', 
			'manage_options', 
			'improved_comment_reply_notification',
			array($this,'create_admin_page')
		);
	}
	public function  create_admin_page() {
		$this->options = get_option('gp_email_options');
        ?>
        <div class="wrap">
            <h2>Improved Comment Reply Notifications</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'gp_email_option_group' );   
                do_settings_sections( 'gp-email-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
	}
	public function page_init() {        
        register_setting(
            'gp_email_option_group', // Option group
            'gp_email_options', // Option name
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'email_section_id', // ID
            'Email Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'gp-email-setting-admin' // Page
        );  

        add_settings_field(
            'email_subject', // ID
            'Email Subject', // Title 
            array( $this, 'email_subject_callback' ), // Callback
            'gp-email-setting-admin', // Page
            'email_section_id' // Section           
        );      

        add_settings_field(
            'email_content', 
            'Email Content', 
            array( $this, 'email_content_callback' ), 
            'gp-email-setting-admin', 
            'email_section_id'
        );      
    }
    public function sanitize( $input ){
        $new_input = array();
        if( isset( $input['email_subject'] ) )
            $new_input['email_subject'] = sanitize_text_field( $input['email_subject'] );

        if( isset( $input['email_content'] ) )
            $new_input['email_content'] = $input['email_content'];

        return $new_input;
    }
    public function print_section_info(){
        echo "<p>You can customize the subject and content of your comment reply notifications below. The following shortcodes may be used in your email content:</p>";
        echo "<ul>";
        echo "<li><strong>[BLOGNAME]</strong> - displays the name of your blog as entered in the WordPress settings page.</li>";
        echo "<li><strong>[POSTNAME]</strong> - displays the name of the post the user commented.</li>";
        echo "<li><strong>[POSTLINK]</strong> - gets the url of the post the user commented </li>";
        echo "<li><strong>[COMMENTAUTHOR]</strong> - displays the user's (original comment author) name.</li>";
        echo "<li><strong>[ORIGINALCOMMENT]</strong> - displays the original comment the user posted.</li>";
        echo "<li><strong>[REPLYCOMMENT]</strong> - displays the response to the original comment.</li>";
        echo "<li><strong>[REPLYCOMMENTAUTHOR]</strong> - displays the name of the person who responded.</li>";
        echo "<li><strong>[COMMENTLINK]</strong> - gets the url of the comment response</li>";
        echo "</ul>";
        echo "<p>You can also use any html like &lt;strong&gt; &lt;em&gt; &lt;a&gt; &lt;p&gt;:</p>";
    }
    public function email_subject_callback(){
        printf(
            '<input type="text" id="email_subject" name="gp_email_options[email_subject]" value="%s" size="100"/>',
            isset( $this->options['email_subject'] ) ? esc_attr( $this->options['email_subject']) : ''
        );
    }
    public function email_content_callback(){
        printf(
            '<textarea id="email_content" name="gp_email_options[email_content]" value="" rows="15" cols="100"/>%s</textarea>',
            isset( $this->options['email_content'] ) ? esc_attr( $this->options['email_content']) : ''
        );
    }
}

new GP_Comment_Reply();
?>