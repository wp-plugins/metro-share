<?php
/*
Plugin Name: Metro Share
Plugin URI: http://metronet.no
Description: Super fast and super customizable social sharing
Version: 0.5.8
Author: Metronet AS
Author URI: http://metronet.no
Text Domain: metro-share
*/


$metro_share = new Metro_Share;

/**
 * Metro Share class
 * 
 * @copyright Copyright (c), Metronet
 * @author Kaspars Dambis <kaspars@metronet.no>
 * @since 0.4
 */
class Metro_Share {

	var $settings = array();
	var $destinations = array();

	/*
	 * Class constructor
	 *
	 * @since 0.4
	 * @author Ryan Hellyer <ryanhellyer@gmail.com>
	 */
	public function __construct() {
		//delete_option( 'metroshare_settings' );
		$settings = array(
			'allposts' => 0,
			'prefix'   => '',
			'destinations' => array(
				'twitter' => array(
					'enabled'  => 'twitter',
					'message'  => '',
					'username' => '',
				),
				'facebook' => array(
					'app_id'  => '',
				),
				'email' => array(
					'enabled' => 'email',
					'message' => ''
				),
				'google-plus' => array(
					'enabled' => 'google-plus',
				),
				'linkedin' => array(
					'enabled' => 'linkedin',
				),
			),
		);
		add_option( 'metroshare_settings', $settings );

		// Grab settings
		$this->settings = get_option( 'metroshare_settings' );

		// Admin
		add_action( 'init',                  array( $this, 'load_settings' ) );
		add_action( 'admin_menu',            array( $this, 'register_admin_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Frontend
		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'metroshare',            array( $this, 'show_sharing_icons' ) );
		add_action( 'wp_footer',             array( $this, 'maybe_close_facebook_redirect' ) );
		add_action( 'the_content',           array( $this, 'show_the_content' ) );
	}

	/*
	 * Close Facebook ... 
	 *
	 * @since 0.4
	 * @author Kaspars Dambis <kaspars@metronet.no>
	 */
	public function maybe_close_facebook_redirect() {

		if ( ! isset( $_GET['metro-share'] ) ) {
			return;
		}

		if ( $_GET['metro-share'] == 'done' ) {
			echo '<script type="text/javascript">self.close();</script>';
		}

	}

	/*
	 * Load Javascript and CSS into admin
	 *
	 * @since 0.4
	 * @author Kaspars Dambis <kaspars@metronet.no>
	 */
	public function enqueue_admin_scripts( $page ) {

		if ( strstr( $page, 'metro-share' ) == -1 ) {
			return;
		}

		wp_enqueue_script( 'metroshare-admin-js', plugins_url( '/assets/metroshare-admin.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ) );
		wp_enqueue_style( 'metroshare-admin-css', plugins_url( '/assets/metroshare-admin.css', __FILE__ ) );
	}

	/*
	 * Load Javascript and CSS into frontend of site
	 *
	 * @since 0.4
	 * @author Kaspars Dambis <kaspars@metronet.no>
	 */
	public function enqueue_frontend_scripts() {
		wp_enqueue_style( 'metroshare-css', plugins_url( '/assets/metroshare.css', __FILE__ ) );
		wp_enqueue_script( 'metro-share', plugins_url( '/assets/metroshare.js', __FILE__ ), array( 'jquery' ), null, true );
	}

	/*
	 * Register admin page settings
	 *
	 * @since 0.4
	 * @author Kaspars Dambis <kaspars@metronet.no>
	 */
	public function register_admin_settings() {
		register_setting(
			'metroshare_settings',
			'metroshare_settings',
			array( $this, 'validate' )
		);
		add_submenu_page( 'options-general.php', __( 'Sharing Icon Settings', 'metro-share' ), __( 'Sharing Icons', 'metro-share' ), 'administrator', __FILE__, array( $this, 'settings_display' ) );
	}
	
	/*
	 * Sanitising each chunk of data submitted from the admin page
	 *
	 * @since 0.5
	 * @author Ryan Hellyer <ryanhellyer@gmail.com>
	 */
	public function sanitise_chunk( $input ) {

		// Strip @ characters from Twitter handles
		if ( 'twitter' == $input['enabled'] ) {
			$input['username'] = str_replace( '@', '', $input['username'] );
		}

		$output = array();
		if ( ! empty( $input['enabled'] ) ) {
			$output['enabled']  = sanitize_title( $input['enabled'] );
		}
		if ( isset( $input['message'] ) ) {
			$output['message']  = wp_kses( $input['message'], '', '' );
		}
		if ( isset( $input['username'] ) ) {
			$output['username'] = sanitize_user( $input['username'] );
		}
		if ( isset( $input['app_id'] ) ) {
			if ( is_numeric( $input['app_id'] ) ) {
				$output['app_id'] = $input['app_id'];
			} else {
				$output['app_id'] = '';
			}
		}
		return $output;
	}

	/*
	 * Validation/sanitisation of data inputted from admin page
	 *
	 * @since 0.5
	 * @author Ryan Hellyer <ryanhellyer@gmail.com>
	 */
	public function validate( $input ) {

		$output = array();
		if ( isset( $input['prefix'] ) ) {
			$output['prefix'] = wp_kses( $input['prefix'], '', '' );
		}
		if ( isset( $input['allposts'] ) ) {
			$output['allposts'] = (bool) $input['allposts'];
		} else {
			$output['allposts'] = false;
		}
		$destinations = $input['destinations'];

		foreach( $destinations as $destination => $value ) {
			$output['destinations'][$destination] = $this->sanitise_chunk( $destinations[$destination] );
		}

		return $output;
	}

	/*
	 * Load settings for admin pages
	 *
	 * @since 0.4
	 * @author Kaspars Dambis <kaspars@metronet.no>
	 */
	public function load_settings() {

		$this->destinations['twitter'] = array(
			'title' => 'Twitter',
			'action' => 'https://twitter.com/share',
			'fields' => array(
				'message' => array( 
					'type' => 'textarea',
					'label' => __( 'Default message:', 'metro-share' ),
					'help' => __( 'You can use the following tags: <code>{{title}}</code>, <code>{{link}}</code>, <code>{{post_title}}</code> and <code>{{shortlink}}</code>.', 'metro-share' )
				),
				'username' => array( 
					'type' => 'text',
					'label' => __( 'Your Twitter handle:', 'metro-share' ),
					'help' => __( 'This will be appended to the tweet automatically.', 'metro-share' )
				)
			),
			'hidden' => array(
				'url' => '{{link}}',
				'via' => '{{username}}',
				'text' => '{{message}}'
			)
		);

		// https://developers.facebook.com/docs/reference/dialogs/feed/
		$this->destinations['facebook'] = array(
			'title' => 'Facebook',
			'action' => 'https://www.facebook.com/dialog/feed',
			'fields' => array(
				'app_id' => array( 
					'type' => 'text',
					'label' => __( 'App ID:', 'metro-share' ),
					'help' => __( 'Facebook requires an Application ID which you can create at the <a href="https://developers.facebook.com/apps/">developers center</a>.', 'metro-share' )
				)
			),
			'hidden' => array(
				'app_id' => '{{app_id}}',
				'link' => '{{link}}',
				'redirect_uri' => '{{link}}?metroshare=done',
				'display' => 'popup'
			)
		);

		// https://developers.google.com/+/plugins/share/
		$this->destinations['google-plus'] = array(
			'title' => 'Google+',
			'description' => __( 'There is nothing to configure. Google+ automatically extracts all content meta data from the open graph tags on the page.', 'metro-share' ),
			'action' => 'https://plus.google.com/share',
			'hidden' => array(
				'url' => '{{link}}'
			)
		);

		// https://developer.linkedin.com/documents/share-linkedin
		$this->destinations['linkedin'] = array(
			'title' => 'LinkedIn',
			'description' => __( 'There is nothing to configure.', 'metro-share' ),
			'action' => 'http://www.linkedin.com/shareArticle',
			'fields' => array(),
			'hidden' => array(
				'title' => '{{post_title}}',
				'url' => '{{link}}',
				'mini' => true
			)			
		);

		$this->destinations['email'] = array(
			'title' => 'Email',
			'action' => 'http://api.addthis.com/oexchange/0.8/forward/email/offer',
			'fields' => array(
				'message' => array( 
					'type' => 'textarea',
					'label' => __( 'Default message:', 'metro-share' )
				),
			),
			'hidden' => array(
				'note' => '{{message}}',
				'url' => '{{link}}'
			)
		);

		$this->destinations = apply_filters( 'metroshare_destinations', $this->destinations );

		// Sort destinations according to user preferences
		if ( isset( $this->settings['destinations'] ) ) {
			$this->destinations = array_merge( $this->settings['destinations'], $this->destinations );
		}
		
	}

	/*
	 * Primary function used for displaying share icon via the_content() filter
	 *
	 * @since 0.5
	 * @author Ryan Hellyer <ryanhellyer@gmail.com>
	 * @param string   $content  The post content
	 * @global object  $post     The primary post object
	 * @return string
	 */
	public function show_the_content( $content = '' ) {
		global $post;

		if ( is_singular() && $post->ID == get_queried_object_id() || $this->settings['allposts'] == true ) {
			$icons = $this->get_sharing_icons();
			$content .= $icons;
		}

		// Finally, return the content
		return $content;
	}

	/*
	 * Primary function used for displaying share icon
	 *
	 * @since 0.5
	 * @author Ryan Hellyer <ryanhellyer@gmail.com>
	 */
	public function show_sharing_icons() {
		echo $this->get_sharing_icons();
	}

	/*
	 * Generate the sharing icons HTML
	 *
	 * @since 0.5
	 * @author Ryan Hellyer <ryanhellyer@gmail.com>
	 * @return string or null
	 */
	public function get_sharing_icons() {
		$items = array();
		$tabs = array();
		$post_id = get_the_ID();
		// Process each potential sharing destination
		foreach ( $this->settings['destinations'] as $d => $destination ) {

			if ( is_404() ) {
				$replace = array(
					'{{title}}'      => get_bloginfo( 'name' ),
					'{{post_title}}' => get_bloginfo( 'description' ),
					'{{link}}'       => home_url(),
					'{{shortlink}}'  => home_url(),
				);
			} else {
				$replace = array(
					'{{title}}'      => get_the_title(),
					'{{post_title}}' => get_the_title(),
					'{{link}}'       => get_permalink( $post_id ),
					'{{shortlink}}'  => wp_get_shortlink(),
				);
			}
			$replace = apply_filters( 'metroshare_tag', $replace );

			// Add custom destination settings fields to the replace variables
			if ( isset( $this->destinations[ $d ]['fields'] ) ) {
				foreach ( $this->destinations[ $d ]['fields'] as $field_name => $field_settings ) {
					if ( isset( $destination[ $field_name ] ) ) {
						$replace[ sprintf( '{{%s}}', $field_name ) ] = strtr( $destination[ $field_name ], $replace );
					}
				}
			}

			$hidden_fields = array();

			// Append hidden fields to the form
			if ( isset( $this->destinations[ $d ]['hidden'] ) ) {
				foreach ( $this->destinations[ $d ]['hidden'] as $field_name => $field_value ) {
					if ( ! empty( $field_value ) ) {
						$hidden_fields[ $field_name ] = urlencode( strtr( $field_value, $replace ) );
					}
				}
			}

			// If sharing destination is enabled, then display list item and link
			if ( isset( $destination['enabled'] ) ) {
				$href = add_query_arg( $hidden_fields, $this->destinations[ $d ]['action'] );
				$tabs[] = sprintf(
					apply_filters( 'metro-share-item', '<li class="metroshare-%s"><a rel="nofollow" href="%s"><span class="icon"></span>%s</a></li>' ),  
					$d,
					$href,
					esc_html( $this->destinations[ $d ]['title'] )
				);
			}

		}

		// Generate final HTML and add it to the main post content
		if ( ! empty( $tabs ) ) {
			$prefix = apply_filters( 'metro-share-prefix', $this->settings['prefix'] ); // Adding filter to allow users to edit the prefix text - useful for doing translations or changing the text based on which page you are on
			$share_html = sprintf( 
				'<div class="metroshare">
					<h4 class="share-prefix">%s</h4>
					<ul class="metro-tabs">%s</ul>
				</div>',
				esc_html( $prefix ),
				implode( '', $tabs )
			);
			return $share_html;
		}

	}

	/*
	 * Displays the admin page settings content
	 *
	 * @since 0.4
	 * @author Kaspars Dambis <kaspars@metronet.no>
	 */
	public function settings_display() {
		$network_settings = array();
		$enabled_settings = array(); 

		foreach ( $this->destinations as $n => $network ) {
			if ( ! isset( $this->settings['destinations'][ $n ]['enabled'] ) ) {
				$this->settings['destinations'][ $n ]['enabled'] = false;
			}
			$enabled_settings[ $n ] = sprintf( 
				'<li>
					<label>
						<input name="metroshare_settings[destinations][%s][enabled]" type="checkbox" value="%s" %s /> 
						%s
					</label>
				</li>',
				esc_attr( $n ),
				esc_attr( $n ),
				checked( $this->settings['destinations'][ $n ]['enabled'], esc_attr( $n ), false ),
				esc_html( $network['title'] )
			);

			$network_settings[ $n ] = sprintf( 
				'<tr id="destination-%s" class="metroshare-destination">
					<th>%s</th>
					<td>%s</td>
				</tr>',
				$n,
				$network['title'],
				implode( '', $this->network_settings_fields( $n ) )
			);

		}

		echo '<form action="options.php" method="post">';
		settings_fields( 'metroshare_settings' );
		echo '
		<style type="text/css">
		#icon-metronet {
			background:url(' . esc_url( plugins_url( '/assets/metronet-icon.png', __FILE__ ) ) . ') no-repeat;
		}
		</style>
		<div class="wrap metroshare-wrap">';
		screen_icon( 'metronet' );

		printf( 
				'
					<h2>%s</h2>
					
					<table class="form-table metroshare-global">
						<tr class="metroshare-prefix">
							<th>%s</th>
							<td>
								<input class="regular-text" type="text" name="metroshare_settings[prefix]" value="%s" />
							</td>
						</tr>
						<tr class="submit">
							<th>%s</th>
							<td>
								<p>
									<input type="checkbox" name="metroshare_settings[allposts]" value="1" %s />
								</p>
							</td>
						</tr>
						<tr class="metroshare-tabs">
							<th>%s</th>
							<td>
								<p>%s</p>
								<ul>%s</ul>
							</td>
						</tr>
						<tr class="submit">
							<th></th>
							<td>
								<p><input type="submit" class="button-primary" value="%s" /></p>
							</td>
						</tr>
					</table>

					<h3>%s</h3>
					<table class="form-table metroshare-destinations">
						%s
						<tr class="submit">
							<th></th>
							<td>
								<p><input type="submit" class="button-primary" value="%s" /></p>
							</td>
						</tr>
					</table>
				</div>',
				__( 'Sharing Icon Settings', 'metro-share' ),
				__( 'Invitation Text', 'metro-share' ),
				esc_attr( $this->settings['prefix'] ),
				__( 'Display in <strong>all</strong> post areas?', 'metro-share' ),
				checked( $this->settings['allposts'], true, false ),
				__( 'Sharing Destinations', 'metro-share' ),
				__( 'Select which sharing destinations you want to enable and use drag and drop to change their order:', 'metro-share' ),
				implode( '', $enabled_settings ),
				__( 'Update', 'metro-share' ),
				__( 'Destination Settings', 'metro-share' ),
				implode( '', $network_settings ),
				__( 'Update', 'metro-share' )
			);

		echo '</form>';
	}

	/*
	 * Adding fields to admin page
	 *
	 * @since 0.4
	 * @author Kaspars Dambis <kaspars@metronet.no>
	 * @todo Document @param here
	 * @return array
	 */
	public function network_settings_fields( $n ) {
		$fields = array();

		$inputs = array(
			'text' => '<input type="text" class="input-text" name="metroshare_settings[destinations][%1$s][%2$s]" value="%3$s" />',
			'textarea' => '<textarea class="input-textarea" name="metroshare_settings[destinations][%1$s][%2$s]">%3$s</textarea>'
		);

		if ( isset( $this->destinations[ $n ]['description'] ) )
			$fields['destination-desc'] = sprintf( '<p class="destination-desc">%s</p>', $this->destinations[ $n ]['description'] );

		if ( ! empty( $this->destinations[ $n ]['fields'] ) )
			foreach ( $this->destinations[ $n ]['fields'] as $f => $custom_field ) {

				if ( isset( $custom_field['help'] ) ) {
					$help = sprintf( '<span class="field-help">%s</span>', $custom_field['help'] );
				} else {
					$help = '';
				}

				$fields[ $f ] = sprintf( 
					'<p class="input-wrap field-%s">
						<label class="label-%s">
							<strong class="label">%s</strong>
							%s
						</label>
					</p>',
					esc_attr( $f ),
					esc_attr( $f ),
					esc_html( $custom_field['label'] ),
					sprintf( 
						$inputs[ $custom_field['type'] ], 
						esc_attr( $n ), 
						esc_attr( $f ), 
						esc_attr( $this->settings['destinations'][ $n ][ $f ] ) 
					) . $help
				);
			}

		return apply_filters( 'metroshare-settings-fields', $fields, $n );
	}

}
