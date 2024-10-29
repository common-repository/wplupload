<?php
/*
Plugin Name: WPlupload
Plugin URI: 
Description: Use Plupload as media upload backend
Version: 1.1
Author: Davit Barbakadze
Author URI: http://plupload.com 
*/

if ( !class_exists('WPlupload') ) :

require_once(dirname(__FILE__) . '/i8/class.Plugino.php');
class WPlupload extends WPL_Plugino {
	
	var $prefix = 'wpl_';
	
	var $pages = array(
			array(
				'parent' => 'options',
				'handle' => 'options_form',
				'page_title' => 'Plupload'
			)
		);
		
	var $options = array(
		'tmp_dir' => false,
		'file_data_name' => 'async-upload',
		'exec_time' => 0, // max execution time of upload handler in secs
		
		'runtimes' => array(
			'type' => 'text',
			'label' => "Runtimes:",
			'desc' => "runtimes will be tried in specified order <br/><em>separate runtimes with commas</em>",
			'class' => 'regular-text',
			'value' => 'html5,flash,silverlight,gears,browserplus,html4'
		),
		'max_file_size' => array(
			'type' => 'text',
			'label' => "Maximum allowed filesize:",
			'desc' => "allowed suffixes: <b>kb, mb, gb (e.g. 200mb)</b> <br/><em>by default there's no limit</em>",
			'class' => 'small-text'
		),
		'chunk_size' => array(
			'type' => 'text',
			'label' => "Chunk size:",
			'desc' => "allowed suffixes: <b>kb, mb, gb</b>; files will be split to chunks, if possible, and reconstructed on server <br/><em>this way one might for example overcome server limitation on maximum file upload size</em>",
			'class' => 'small-text',
			'value' => '200kb'
		),
		'resize' => array(
			'type' => 'resize',
			'label' => "Resize on client-side:",
			'desc' => "if possible resize image on client-side, before uploading to a server",
			'value' => array(
				'on' => false,
				'width' => 800,
				'height' => 600,
				'quality' => 90
			)
		)
	);
	
	function __construct()
	{	
		parent::__construct(__FILE__);	
		
		// temp dir may eventually become different, so we store it as an option 
		// and change only if current one is not writable
		if (!$this->o('tmp_dir') || is_writable($this->o('tmp_dir')))
		{
			if ($tmp_dir = $this->prepare_tmpdir())
				$this->o('tmp_dir', $tmp_dir);
			else
				$this->warn('<b>WPlupload</b> requires writable directory for storing temporary files. Make sure that at least WordPress <b>uploads/</b> directory is writable.');
		}
		
		// set to 5 minutes by default
		if (!$this->o('exec_time'))
			$this->o('exec_time', 5 * 60);
		
		add_action('pre-upload-ui', create_function('', 'ob_start();'));
		add_action('post-upload-ui', array($this, 'insert_plupload_form'));
	}
	
	function on_activate()
	{
		wp_schedule_event(time(), 'daily', 'wpl_cleanup');	
	}
	
	function on_deactivate()
	{
		wp_clear_scheduled_hook('wpl_cleanup');
	}
	
	
	function a__wpl_cleanup()
	{
		global $wpdb;
		$now = time();
		
		// first remove stuck transients and corresponding files if possible
		if ($transients = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_wpl_%' AND option_value < $now")) 
		{
			foreach ($transients as $name) {
				$token = str_replace('_transient_timeout_', '', $name);
				$tmp_file = get_transient($token);
				if (file_exists($tmp_file))
					@unlink($tmp_file);
				delete_transient($token);
			}
		}
		
		// remove stuck tmp files
		if ($this->o('tmp_dir')) {
			$files = scandir($this->o('tmp_dir'));
			foreach ($files as $file) {
				if (substr($file, -4) == '.tmp' && $now - filemtime($file)	> 24 * 60 * 60) // older then day
					@unlink($file);
			}
		}
	}
	
	
	function options_field_resize($name, &$o)
	{		
		extract($o);
		?><input id="wpl-resize-switcher" type="checkbox" name="<?php echo $this->options_handle; ?>[<?php echo $name; ?>][value][on]" value="1" <?php if ($value['on']) echo 'checked="checked"'; ?> /> <?php echo $desc;
		?><br />
        <div id="wpl-resize-options">
        	Width: <input type="text" class="small-text" name="<?php echo $this->options_handle; ?>[<?php echo $name; ?>][value][width]" value="<?php echo $value['width']; ?>" />px, &nbsp;Height: <input type="text" class="small-text" name="<?php echo $this->options_handle; ?>[<?php echo $name; ?>][value][height]" value="<?php echo $value['height']; ?>" />px, &nbsp;Quality (for JPEG):<input type="text" class="small-text" name="<?php echo $this->options_handle; ?>[<?php echo $name; ?>][value][quality]" value="<?php echo $value['quality']; ?>" /> </br />
        </div>
        <script>
		//<![CDATA[
		(function($) {
			function enableResize(chk) {
				if (chk.checked) {
					$('#wpl-resize-options input').removeAttr('disabled');
				} else {
					$('#wpl-resize-options input').attr('disabled', 'disabled');
				}	
			}
			
			$('#wpl-resize-switcher').click(function() {
				enableResize(this);
			});
			
			enableResize($('#wpl-resize-switcher')[0]);		
			
		}(jQuery));
		 //]]>
		</script>
        
        <?php
	}
	
	
	protected function prepare_tmpdir()
	{		
		if ( defined('WP_TEMP_DIR') )
			return WP_TEMP_DIR;
	
		if  ( function_exists('sys_get_temp_dir') ) {
			$temp = sys_get_temp_dir();
			if ( @is_writable($temp) )
				return $temp;
		}
	
		$temp = ini_get('upload_tmp_dir');
		if ( is_dir($temp) && @is_writable($temp) )
			return $temp;
	
		$temp = '/tmp/';
		if ( is_dir($temp) && @is_writable($temp) )
			return $temp;
		
		// if no other choice, create temp dir in uploads/ and store there
		if (@is_writable($this->upload_path)) {
			$temp = $this->upload_path . '/wpl_temp/';
			if (@mkdir($temp))
				return $temp;	
		}
			
		return false;
	}
	
	
	protected function prepare_tempfile($name, $delete_transient = false)
	{
		global $user_ID;
		
		$token = $this->prefix . $user_ID . "_" . md5($name);
		
		if (!$tmp_file = get_transient($token))
			$tmp_file =  $this->o('tmp_dir') . '/' . uniqid($this->prefix, true) . '.tmp';	
		
		if ($delete_transient)	
			delete_transient($token);
		else
			set_transient($token, $tmp_file, $this->o('exec_time'));
			
		return $tmp_file;
	}
	
	
	function a__admin_print_scripts()
	{
		wp_deregister_script('swfupload-all');
		wp_deregister_script('swfupload-handlers');
		wp_enqueue_script('swfupload-handlers', get_option('siteurl') . "/wp-includes/js/swfupload/handlers.js", array('jquery'), '2201-20100523');
		wp_enqueue_script('browserplus', 'http://bp.yahooapis.com/2.4.21/browserplus-min.js');
		wp_enqueue_script('plupload', $this->url . '/js/plupload.full.js', array('browserplus'));
		wp_enqueue_script('wplupload', $this->url . '/js/wplupload.js', array('plupload', 'jquery'));	
	}
	
	
	function a__admin_print_styles()
	{
		wp_enqueue_style('wplupload', $this->url . '/css/wplupload.css');	
	}
	
	
	function insert_plupload_form()
	{
		ob_clean();
				
		global $type, $tab, $pagenow;
		
		// If Mac and mod_security, no Flash. :(
		$flash = true;
		if ( false !== stripos($_SERVER['HTTP_USER_AGENT'], 'mac') && apache_mod_loaded('mod_security') )
			$flash = false;
	
		$flash = apply_filters('flash_uploader', $flash);
		$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
	
		$upload_size_unit = $max_upload_size =  wp_max_upload_size();
		$sizes = array( 'KB', 'MB', 'GB' );
		for ( $u = -1; $upload_size_unit > 1024 && $u < count( $sizes ) - 1; $u++ )
			$upload_size_unit /= 1024;
		if ( $u < 0 ) {
			$upload_size_unit = 0;
			$u = 0;
		} else {
			$upload_size_unit = (int) $upload_size_unit;
		}
		
		// Define acceptable file types
		$file_types = apply_filters('upload_file_glob', '');
	
		// Check quota for this blog if multisite
		if ( is_multisite() && !is_upload_space_available() ) {
			echo '<p>' . sprintf( __( 'Sorry, you have filled your storage quota (%s MB).' ), get_space_allowed() ) . '</p>';
			return;
		}	
		
		// Set the post params, which SWFUpload will post back with the file, and pass
		// them through a filter.
		$post_params = array(
			"auth_cookie" => (is_ssl() ? $_COOKIE[SECURE_AUTH_COOKIE] : $_COOKIE[AUTH_COOKIE]),
			"logged_in_cookie" => $_COOKIE[LOGGED_IN_COOKIE],
			"_wpnonce" => wp_create_nonce('media-form'),
			"action" => "wpl_upload"
		);
		$post_params = apply_filters( 'plupload_post_params', $post_params );
		$p = array();
		foreach ( $post_params as $param => $val )
			$p[] = "\t\t'$param' : '$val'";
		$post_params_str = implode( ", \n", $p );
		
		$this->_e_l10n();

		
		?><div id="plupload-ui" style="position:relative;display:none;">
        	<div id="wpl-logo">
            	<a href="http://plupload.com" title="Try Plupload" target="_blank"> </a>
            </div>
            <div>
                <?php _e( 'Choose files to upload' ); ?>
                <span><input id="select-files" type="button" value="<?php esc_attr_e('Select Files'); ?>" class="button" /></span>
                <span><input id="cancel-upload" disabled="disabled" type="button" value="<?php esc_attr_e('Cancel Upload'); ?>" class="button" /></span>
            </div>
            <p class="howto"><?php _e('After a file has been uploaded, you can add titles and descriptions.'); ?></p>
        </div>
        
        <div id="html-upload-ui">
		<?php //do_action('pre-html-upload-ui'); ?>
            <p id="async-upload-wrap">
            <label class="screen-reader-text" for="async-upload"><?php _e('Upload'); ?></label>
            <input type="file" name="async-upload" id="async-upload" /> <input type="submit" class="button" name="html-upload" value="<?php esc_attr_e('Upload'); ?>" /> <a href="#" onClick="try{top.tb_remove();}catch(e){}; return false;"><?php _e('Cancel'); ?></a>
            </p>
            <div class="clear"></div>
            <p class="media-upload-size"><?php printf( __( 'Maximum upload file size: %d%s' ), $upload_size_unit, $sizes[$u] ); ?></p>
            <?php if ( is_lighttpd_before_150() ): ?>
            <p><?php _e('If you want to use all capabilities of the uploader, like uploading multiple files at once, please upgrade to lighttpd 1.5.'); ?></p>
            <?php endif;?>
        <?php //do_action('post-html-upload-ui', $flash); ?>
        </div>
        
        <script type="text/javascript">
        //<![CDATA[
		var wplupload;
		
		jQuery('#plupload-ui').show();
		jQuery('#html-upload-ui').hide();
        
		(function($) {
			setTimeout(function() {		
				wplupload = jQuery('#select-files').wplupload({
					runtimes : '<?php echo $this->o('runtimes'); ?>',
					url : ajaxurl,
					container: 'plupload-ui',
					browse_button : 'select-files',
					file_data_name : 'async-upload',
					flash_swf_url : '<?php echo $this->url; ?>/js/plupload.flash.swf',
					silverlight_xap_url : '<?php echo $this->url; ?>/js/plupload.silverlight.xap',
					<?php if (trim($this->o('max_file_size')) !== '') echo "max_file_size: '{$this->o('max_file_size')}',"; ?>
					<?php $resize = $this->o('resize'); 
					if ($resize['on']) {
						echo "resize:{ width:{$resize['width']},height:{$resize['height']},quality:{$resize['quality']} },"; 
					} ?>
					multipart_params : <?php echo json_encode($post_params); ?>,
					multipart: true,
					chunk_size: '<?php echo $this->o('chunk_size'); ?>',
					<?php if (trim($file_types) !== '')
						echo "filters: [{ title : 'Allowed file types', extensions: '$file_types' }],"; ?>
					drop_element: 'plupload-ui'
				});
			}, 200);
		}(jQuery));
		        
        //]]>
        </script><?php
	}
	
	
	function a__wp_ajax_wpl_upload()
	{
		@set_time_limit($this->o('exec_time'));
		
		// Uncomment this one to fake upload time
		// usleep(5000);
		
		nocache_headers();
		
		check_admin_referer('media-form');
		
		if (!current_user_can('upload_files'))
			$this->json(array('OK' => 0, 'info' => 'Forbidden'));
		

		// Get parameters
		$chunk = is_numeric($_REQUEST['chunk']) ? $_REQUEST['chunk'] : 0;
		$chunks = is_numeric($_REQUEST['chunks']) ? $_REQUEST['chunks'] : 0;
		$name = (isset($_REQUEST['name']) ? $_REQUEST['name'] : '');
		
		$tmp_path = $this->prepare_tempfile($name);
		
		// clean up a bit
		//$name = preg_replace('/[^\w\._~\-]+/', '', $name);
		
		// Look for the content type header
		if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
			$content_type = $_SERVER["HTTP_CONTENT_TYPE"];
				
		if (isset($_SERVER["CONTENT_TYPE"]))
			$content_type = $_SERVER["CONTENT_TYPE"];

		// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
		if (strpos($content_type, "multipart") !== false) 
		{
			if (!empty($_FILES))
				$uploaded = $_FILES[$this->o('file_data_name')]['tmp_name'];	
			
			if ($uploaded && @is_uploaded_file($uploaded)) 
			{
				// Open temp file
				$out = fopen($tmp_path, $chunk == 0 ? "wb" : "ab");
				if ($out) {
					// Read binary input stream and append it to temp file
					$in = fopen($uploaded, "rb");
		
					if ($in) {
						while ($buff = fread($in, 4096))
							fwrite($out, $buff);
					} else
						$this->json(array('OK' => 0, "code" => 101, "message" => "Failed to open input stream."));
						
					fclose($in);
					fclose($out);
					@unlink($uploaded);
					
				} else
					$this->json(array('OK' => 0, "code" => 102, "message" => "Failed to open output stream."));
			} else
				$this->json(array('OK' => 0, "code" => 103, "message" => "Failed to move uploaded file."));
				
		} else {
			// Open temp file
			$out = fopen($tmp_path, $chunk == 0 ? "wb" : "ab");
			if ($out) {
				// Read binary input stream and append it to temp file
				$in = fopen("php://input", "rb");
		
				if ($in) {
					while ($buff = fread($in, 4096))
						fwrite($out, $buff);
				} else
					$this->json(array('OK' => 0, "code" => 101, "message" => "Failed to open input stream."));
		
				fclose($in);
				fclose($out);
								
			} else
				$this->json(array('OK' => 0, "code" => 102, "message" => "Failed to open output stream."));
		}
		
		// generate file verification nonce (wp_create_nonce includes user_ID on it's own)
		$nonce = wp_create_nonce(md5($tmp_path . $name . filesize($tmp_path)));
		
		$this->json(array('OK' => 1, 'nonce' => $nonce));
	}
	
	
	function a__wp_ajax_wpl_handle_upload()
	{
		nocache_headers();		
		
		if (!current_user_can('upload_files'))
			$this->json(array('OK' => 0, 'info' => 'Forbidden'));
		

		$name = (isset($_REQUEST['name']) ? $_REQUEST['name'] : '');
		
		$tmp_path = $this->prepare_tempfile($name, true);
		
		// verify file
		$action = md5($tmp_path . $name . filesize($tmp_path));
		if (!wp_verify_nonce($_REQUEST['nonce'], $action))
			die('Wrong file.');
		
		// clean up a bit
		$name = preg_replace('/[^\w\._~\-]+/', '', $name);
		
		// prepare customized $_FILES array
		$_FILES[$this->o('file_data_name')] = array(
			'tmp_name' => $tmp_path,
			'name' => $name,
			'size' => filesize($tmp_path),
			'error' => 0
		);
									
		$id = $this->media_handle_upload(
			$this->o('file_data_name'), 
			$_REQUEST['post_id'], 
			array(), 
			array('test_form' => false, 'test_upload' => false) // disable is_uploaded() check, 'cause it will fail
		);
		
		
		if (is_wp_error($id)) {
			echo '<div class="error-div"><a class="dismiss" href="#" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">' . __('Dismiss') . '</a><strong>' . sprintf(__('&#8220;%s&#8221; has failed to upload due to an error'), esc_html($name) ) . '</strong><br />' .
	esc_html($id->get_error_message()) . '</div>';
			die;
		} else {
			if ( 2 == $_REQUEST['fetch'] ) {
				add_filter('attachment_fields_to_edit', 'media_single_attachment_fields_to_edit', 10, 2);
				echo get_media_item($id, array( 'send' => false, 'delete' => true ));
			} else {
				add_filter('attachment_fields_to_edit', 'media_post_single_attachment_fields_to_edit', 10, 2);
				echo get_media_item($id);
			}
			exit;
		}
	}
	
	
	function _e_l10n()
	{
		$l10n = array(
			'queue_limit_exceeded' => __('You have attempted to queue too many files.'),
			'file_exceeds_size_limit' => __('This file exceeds the maximum upload size for this site.'),
			'zero_byte_file' => __('This file is empty. Please try another.'),
			'invalid_filetype' => __('This file type is not allowed. Please try another.'),
			'default_error' => __('An error occurred in the upload. Please try again later.'),
			'missing_upload_url' => __('There was a configuration error. Please contact the server administrator.'),
			'upload_limit_exceeded' => __('You may only upload 1 file.'),
			'http_error' => __('HTTP error.'),
			'upload_failed' => __('Upload failed.'),
			'io_error' => __('IO error.'),
			'security_error' => __('Security error.'),
			'file_cancelled' => __('File canceled.'),
			'upload_stopped' => __('Upload stopped.'),
			'dismiss' => __('Dismiss'),
			'crunching' => __('Crunching&hellip;'),
			'deleted' => __('moved to the trash.'),
			'error_uploading' => __('&#8220;%s&#8221; has failed to upload due to an error'),
			'cancel_upload' => __('Cancel upload'),
			'dismiss' => __('Dismiss')		
		);
		
		?>
		<script>
			var swfuploadL10n = <?php echo json_encode($l10n); ?>;
        </script>
        <?php
	}
	
	
	/**
	 * The only reason why it is here, is that we need to call $this->wp_handle_upload()
	 *
	 * {@internal Missing Short Description}}
	 *
	 * This handles the file upload POST itself, creating the attachment post.
	 *
	 * @since unknown
	 *
	 * @param string $file_id Index into the {@link $_FILES} array of the upload
	 * @param int $post_id The post ID the media is associated with
	 * @param array $post_data allows you to overwrite some of the attachment
	 * @param array $overrides allows you to override the {@link wp_handle_upload()} behavior
	 * @return int the ID of the attachment
	 */
	function media_handle_upload($file_id, $post_id, $post_data = array(), $overrides = array( 'test_form' => false )) {
	
		$time = current_time('mysql');
		if ( $post = get_post($post_id) ) {
			if ( substr( $post->post_date, 0, 4 ) > 0 )
				$time = $post->post_date;
		}
	
		$name = $_FILES[$file_id]['name'];
		$file = $this->wp_handle_upload($_FILES[$file_id], $overrides, $time);
	
		if ( isset($file['error']) )
			return new WP_Error( 'upload_error', $file['error'] );
	
		$name_parts = pathinfo($name);
		$name = trim( substr( $name, 0, -(1 + strlen($name_parts['extension'])) ) );
	
		$url = $file['url'];
		$type = $file['type'];
		$file = $file['file'];
		$title = $name;
		$content = '';
	
		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata($file) ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) )
				$title = $image_meta['title'];
			if ( trim( $image_meta['caption'] ) )
				$content = $image_meta['caption'];
		}
	
		// Construct the attachment array
		$attachment = array_merge( array(
			'post_mime_type' => $type,
			'guid' => $url,
			'post_parent' => $post_id,
			'post_title' => $title,
			'post_content' => $content,
		), $post_data );
	
		// Save the data
		$id = wp_insert_attachment($attachment, $file, $post_id);
		if ( !is_wp_error($id) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		}
	
		return $id;
	}
	
	
	/**
	 * @see http://lists.automattic.com/pipermail/wp-hackers/2011-January/037447.html
	 *
	 * {@internal Missing Short Description}}
	 *
	 * @since unknown
	 *
	 * @param array $file Reference to a single element of $_FILES. Call the function once for each uploaded file.
	 * @param array $overrides Optional. An associative array of names=>values to override default variables with extract( $overrides, EXTR_OVERWRITE ).
	 * @return array On success, returns an associative array of file attributes. On failure, returns $overrides['upload_error_handler'](&$file, $message ) or array( 'error'=>$message ).
	 */
	function wp_handle_upload( &$file, $overrides = false, $time = null ) {
		// The default error handler.
		if ( ! function_exists( 'wp_handle_upload_error' ) ) {
			function wp_handle_upload_error( &$file, $message ) {
				return array( 'error'=>$message );
			}
		}
	
		$file = apply_filters( 'wp_handle_upload_prefilter', $file );
	
		// You may define your own function and pass the name in $overrides['upload_error_handler']
		$upload_error_handler = 'wp_handle_upload_error';
	
		// You may have had one or more 'wp_handle_upload_prefilter' functions error out the file.  Handle that gracefully.
		if ( isset( $file['error'] ) && !is_numeric( $file['error'] ) && $file['error'] )
			return $upload_error_handler( $file, $file['error'] );
	
		// You may define your own function and pass the name in $overrides['unique_filename_callback']
		$unique_filename_callback = null;
	
		// $_POST['action'] must be set and its value must equal $overrides['action'] or this:
		$action = 'wp_handle_upload';
	
		// Courtesy of php.net, the strings that describe the error indicated in $_FILES[{form field}]['error'].
		$upload_error_strings = array( false,
			__( "The uploaded file exceeds the <code>upload_max_filesize</code> directive in <code>php.ini</code>." ),
			__( "The uploaded file exceeds the <em>MAX_FILE_SIZE</em> directive that was specified in the HTML form." ),
			__( "The uploaded file was only partially uploaded." ),
			__( "No file was uploaded." ),
			'',
			__( "Missing a temporary folder." ),
			__( "Failed to write file to disk." ),
			__( "File upload stopped by extension." ));
	
		// All tests are on by default. Most can be turned off by $override[{test_name}] = false;
		$test_form = true;
		$test_size = true;
		$test_upload = true;
	
		// If you override this, you must provide $ext and $type!!!!
		$test_type = true;
		$mimes = false;
	
		// Install user overrides. Did we mention that this voids your warranty?
		if ( is_array( $overrides ) )
			extract( $overrides, EXTR_OVERWRITE );
	
		// A correct form post will pass this test.
		if ( $test_form && (!isset( $_POST['action'] ) || ($_POST['action'] != $action ) ) )
			return call_user_func($upload_error_handler, $file, __( 'Invalid form submission.' ));
	
		// A successful upload will pass this test. It makes no sense to override this one.
		if ( $file['error'] > 0 )
			return call_user_func($upload_error_handler, $file, $upload_error_strings[$file['error']] );
	
		// A non-empty file will pass this test.
		if ( $test_size && !($file['size'] > 0 ) ) {
			if ( is_multisite() )
				$error_msg = __( 'File is empty. Please upload something more substantial.' );
			else
				$error_msg = __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.' );
			return call_user_func($upload_error_handler, $file, $error_msg);
		}
	
		// A properly uploaded file will pass this test. There should be no reason to override this one.
		if ( $test_upload && ! @ is_uploaded_file( $file['tmp_name'] ) )
			return call_user_func($upload_error_handler, $file, __( 'Specified file failed upload test.' ));
	
		// A correct MIME type will pass this test. Override $mimes or use the upload_mimes filter.
		if ( $test_type ) {
			$wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
	
			extract( $wp_filetype );
	
			// Check to see if wp_check_filetype_and_ext() determined the filename was incorrect
			if ( $proper_filename )
				$file['name'] = $proper_filename;
	
			if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) )
				return call_user_func($upload_error_handler, $file, __( 'File type does not meet security guidelines. Try another.' ));
	
			if ( !$ext )
				$ext = ltrim(strrchr($file['name'], '.'), '.');
	
			if ( !$type )
				$type = $file['type'];
		} else {
			$type = '';
		}
	
		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( ! ( ( $uploads = wp_upload_dir($time) ) && false === $uploads['error'] ) )
			return call_user_func($upload_error_handler, $file, $uploads['error'] );
	
		$filename = wp_unique_filename( $uploads['path'], $file['name'], $unique_filename_callback );
	
		// Move the file to the uploads dir
		$new_file = $uploads['path'] . "/$filename";
		if ( false === @ rename( $file['tmp_name'], $new_file ) )
			return $upload_error_handler( $file, sprintf( __('The uploaded file could not be moved to %s.' ), $uploads['path'] ) );
			
		// Delete temp file
		@unlink($file['tmp_name']);
	
		// Set correct file permissions
		$stat = stat( dirname( $new_file ));
		$perms = $stat['mode'] & 0000666;
		@ chmod( $new_file, $perms );
	
		// Compute the URL
		$url = $uploads['url'] . "/$filename";
	
		if ( is_multisite() )
			delete_transient( 'dirsize_cache' );
	
		return apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ), 'upload' );
	}
	
}
new WPlupload;

endif;

?>