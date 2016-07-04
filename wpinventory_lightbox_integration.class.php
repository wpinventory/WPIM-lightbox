<?php

// No direct access allowed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiny class just for internationalization purposes.
 */
abstract class WPIMLightboxCore extends WPIMCore {

	const LANG = 'wpinventory_lightbox';

	/**
	 * Abstraction of the WP language function.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public static function __( $text ) {
		return __( $text, self::LANG );
	}

	/**
	 * Abstraction of the WP language function (echo)
	 *
	 * @param string $text
	 */
	public static function _e( $text ) {
		echo self::__( $text );
	}
}

/**
 * Class WPIMBreadCrumbIntegration
 */
Class WPIMLightboxIntegration extends WPIMLightboxCore {

	/*
	 * Which lightbox are we using?
	 * Only matters in some instances.
	 * Currently, either NULL (not set), '' (not simple lightbox) or 'simple_lightbox'
	 */
	private static $type_of_lightbox = NULL;

	private static $config_key_name_field = 'lightbox_settings';

	private static $lightbox_settings = array();

	/**
	 * First call.  Continues only if Inventory Manager is Installed
	 */
	public static function start() {
		add_filter( 'wpim_default_config', array( __CLASS__, 'wpim_default_config' ) );
		add_action( 'wpim_edit_settings_media', array( __CLASS__, 'wpim_edit_settings' ) );
		add_action( 'init', array( __CLASS__, 'init' ) );
		// Only set up the front-end hooks if we're not in the admin dashboard
		if ( ! is_admin() ) {
			add_filter( 'wpim_image_link_attributes', array( __CLASS__, 'wpim_image_link_attributes' ), 10, 2 );

			/**
			 * MAKE SETTINGS:
			 * 1. Adjust open image size
			 * 2. Adjust image title
			 * 3. Adjust image alt
			 */
			// NEW FILTERS:
			add_filter( 'wim_open_image_size', array( __CLASS__, 'wim_open_image_size' ), 10, 2 );
			add_filter( 'wpim_image_title', array( __CLASS__, 'wpim_image_title' ), 10, 2 );
			add_filter( 'wpim_image_alt', array( __CLASS__, 'wpim_image_alt' ), 10, 2 );
			add_filter( 'wpim_image_tags', array( __CLASS__, 'wpim_image_tags' ) );
		}
	}

	/**
	 * WordPress init action.
	 * Sets up internationalization
	 */
	public static function init() {
		// Enable internationalization
		if ( ! load_plugin_textdomain( 'wpinventory_lightbox', FALSE, '/wp-content/languages/' ) ) {
			load_plugin_textdomain( 'wpinventory_lightbox', FALSE, basename( dirname( __FILE__ ) ) . "/languages/" );

			self::load_settings();
		}
	}

	/**
	 * Adds the breadcrumb name field to the default config
	 *
	 * @param array $default
	 *
	 * @return array
	 */
	public static function wpim_default_config( $default ) {
		$default[ self::$config_key_name_field ] = array(
			'open_in_lightbox'       => 1,
			'adjust_open_image_size' => 'large',
			'image_title'            => 0,
			'image_alt'              => 0
		);

		return $default;
	}

	/**
	 * Displays the WPIM Admin Settings (selecting the breadcrumb name field)
	 */
	public static function wpim_edit_settings() {
		self::load_settings();

		$open_in_lightbox       = self::get_setting( 'open_in_lightbox' );
		$adjust_open_image_size = self::get_setting( 'adjust_open_image_size' );
		$image_title            = self::get_setting( 'image_title' );
		$image_alt              = self::get_setting( 'image_alt' );

		$size_array = array(
			''          => self::__( 'Do Not Adjust' ),
			'thumbnail' => self::__( 'Thumbnail' ),
			'medium'    => self::__( 'Medium' ),
			'large'     => self::__( 'Large' ),
			'full'      => self::__( 'Full' )
		);

		$intermediate_sizes = get_intermediate_image_sizes();
		$intermediate_sizes = array_diff( array_values( $intermediate_sizes ), array_keys( $size_array ) );

		foreach ( (array) $intermediate_sizes AS $size ) {
			$size_array[ $size ] = ucwords( str_ireplace( array( '_', '-' ), ' ', $size ) );
		}

		echo '<tr class="subtab"><th colspan="2"><h4>' . self::__( 'Lightbox Integration' ) . '</h4></th>';
		echo '</tr>';
		echo '<tr><th>' . self::__( 'Open Inventory Images in Lightbox' ) . '</th>';
		echo '<td>' . WPIMAdmin::dropdown_yesno( self::$config_key_name_field . '[open_in_lightbox]', $open_in_lightbox ) . '</td>';
		echo '</tr>';
		echo '<tr><th>' . self::__( 'Lightbox Image Size' ) . '</th>';
		echo '<td>';
		echo WPIMAdmin::dropdown_array( self::$config_key_name_field . '[adjust_open_image_size]', $adjust_open_image_size, $size_array );
		echo '</tr>';

		echo '<tr><th>' . self::__( 'Adjust Image Title' ) . '</th>';
		echo '<td>';
		echo '<input type="text" name="' . self::$config_key_name_field . '[image_title]" class="widefat" value="' . $image_title . '">';
		echo '<p class="description">' . self::__( 'Use any fields you like, in shortcode-format.  Example: [name] - [description] by [manufacturer]' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'Adjust Image Alt' ) . '</th>';
		echo '<td>';
		echo '<input type="text" name="' . self::$config_key_name_field . '[image_alt]" class="widefat" value="' . $image_alt . '">';
		echo '<p class="description">' . self::__( 'Use any fields you like, in shortcode-format.  Example: [name] - [description] by [manufacturer]' ) . '</p>';
		echo '</td>';
		echo '</tr>';

	}

	/**
	 * Hooks into the "open image size" filter.
	 * Adjusts the size based on the setting in the WP Inventory Settings.
	 *
	 * @param string $open_size
	 * @param string $size
	 *
	 * @return string
	 */
	public static function wim_open_image_size( $open_size, $size ) {
		if ( self::$lightbox_settings['adjust_open_image_size'] ) {
			$open_size = self::$lightbox_settings['adjust_open_image_size'];
		}

		return $open_size;
	}

	/**
	 * Hooks into the "image title" filter.
	 * Allows editing of the image title tag via shortcodes.  Set in the WP Inventory Settings interface.
	 *
	 * @param string $title
	 * @param int $is_single
	 *
	 * @return string
	 */
	public static function wpim_image_title( $title, $is_single ) {
		if ( $is_single ) {
			if ( self::$lightbox_settings['image_title'] ) {
				$title = self::parse_title_setting( 'image_title' );
			}
		}

		return $title;
	}

	/**
	 * Hooks into the "image alt" filter.
	 * Allows editing of the image alt tag via shortcodes.  Set in the WP Inventory Settings interface.
	 *
	 * @param string $alt
	 * @param int $is_single
	 *
	 * @return string
	 */
	public static function wpim_image_alt( $alt, $is_single ) {
		if ( $is_single ) {
			if ( self::$lightbox_settings['image_alt'] ) {
				$alt = self::parse_title_setting( 'image_alt' );
			}
		}

		return $alt;
	}

	/**
	 * Hooks into the "image attributes" filter.
	 * Allows editing of the image attributes.
	 * Used to turn on the "lightbox" effect for WP Lightbox 2 by adding rel="lightbox" to the images.
	 *
	 * @param string $attributes
	 * @param int $is_single
	 *
	 * @return string
	 */
	public static function wpim_image_link_attributes( $attributes, $is_single ) {
		if ( $is_single ) {
			if ( self::get_setting( 'open_in_lightbox' ) ) {
				if ( FALSE === stripos( $attributes, 'rel=' ) ) {
					$attributes = trim( $attributes . ' rel="lightbox"' );
				} else {
					preg_match( '/rel=["\'](.*?)["\']/i', $attributes, $rel );
					if ( isset( $rel[1] ) && FALSE === stripos( $rel[1], 'lightbox' ) ) {
						$attributes = str_ireplace( $rel[0], 'rel="lightbox ' . $rel[1] . '"', $attributes );
					}
				}
			}
		}

		return $attributes;
	}

	/**
	 * If it's the Simple Lightbox plugin, we have to "apply_filters('the_content')" to the image tags for them to get picked up.
	 *
	 * @param string $tags
	 *
	 * @return string
	 */
	public static function wpim_image_tags( $tags ) {
		// Store which kind of lightbox for efficiency.
		if ( NULL === self::$type_of_lightbox ) {
			self::$type_of_lightbox = '';
			if ( class_exists( 'SLB_Base' ) ) {
				self::$type_of_lightbox = 'simple lightbox';
			}
		}

		if ( self::get_setting( 'open_in_lightbox' ) && 'simple lightbox' == self::$type_of_lightbox ) {
			return apply_filters( 'the_content', $tags );
		}

		return $tags;
	}

	public static function parse_title_setting( $setting ) {
		$string = self::$lightbox_settings[ $setting ];

		preg_match_all( '/\[(.*?)\]/', $string, $tags );

		if ( $tags[1] ) {
			foreach ( $tags[1] AS $index => $slug ) {
				$value = wpinventory_get_field( strtolower( $slug ) );
				if ( NULL === $value ) {
					// Do something to alert the user the field wasn't found
					$value = '(field ' . $tags[0][ $index ] . ' not found)';
				}

				$string = str_ireplace( $tags[0][ $index ], $value, $string );
				echo '<br>' . $slug . ' = ' . $value;
			}
		}

		return $string;
	}

	/**
	 * Load the configuration settings (field to display in breadcrumb trail)
	 */
	private static function load_settings() {
		if ( ! self::$lightbox_settings ) {
			self::$lightbox_settings = wpinventory_get_config( self::$config_key_name_field );
		}
	}

	private static function get_setting( $key ) {
		return ( isset( self::$lightbox_settings[ $key ] ) ) ? self::$lightbox_settings[ $key ] : NULL;
	}
}

WPIMLightboxIntegration::start();
