<?php
/**
 * Plugin Name:     Easy Digital Downloads - Purchased Download Button
 * Plugin URI:      https://sellcomet.com/downloads/purchased-download-button
 * Description:     Automatically add a "Download" button instead of "Add To Cart" on purchased downloads.
 * Version:         1.0.1
 * Author:          Sell Comet
 * Author URI:      https://sellcomet.com
 * Text Domain:     edd-purchased-download-button
 * Domain Path:     languages
 *
 * @package         EDD\Purchased_Download_Button
 * @author          Sell Comet
 * @copyright       Copyright (c) Sell Comet
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'EDD_Purchased_Download_Button' ) ) {

    /**
     * Main EDD_Purchased_Download_Button class
     *
     * @since       1.0.0
     */
    class EDD_Purchased_Download_Button {

        /**
         * @var         EDD_Purchased_Download_Button $instance The one true EDD_Purchased_Download_Button
         * @since       1.0.0
         */
        private static $instance;


        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Purchased_Download_Button
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new EDD_Purchased_Download_Button();
                self::$instance->setup_constants();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin version
            define( 'EDD_PURCHASED_DOWNLOAD_BUTTON_VER', '1.0.1' );

            // Plugin path
            define( 'EDD_PURCHASED_DOWNLOAD_BUTTON_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'EDD_PURCHASED_DOWNLOAD_BUTTON_URL', plugin_dir_url( __FILE__ ) );
        }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {
            if ( is_admin() ) {
              // Register settings
              add_filter( 'edd_settings_misc', array( $this, 'settings' ), 1 );
            }

            // Register a "Download" button instead of "Add To Cart" on purchased downloads.
            add_filter( 'edd_purchase_download_form', array( $this, 'purchased_download_button' ), 10, 2 );
        }


        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = EDD_PURCHASED_DOWNLOAD_BUTTON_DIR . '/languages/';
            $lang_dir = apply_filters( 'edd_purchased_download_button_languages_directory', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'edd-purchased-download-button' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'edd-purchased-download-button', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-purchased-download-button/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-purchased-download-button/ folder
                load_textdomain( 'edd-purchased-download-button', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-purchased-download-button/languages/ folder
                load_textdomain( 'edd-purchased-download-button', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-purchased-download-button', false, $lang_dir );
            }
        }


        /**
         * Add settings
         *
         * @access      public
         * @since       1.0.0
         * @param       array $settings The existing EDD settings array
         * @return      array The modified EDD settings array
         */
        public function settings( $settings ) {
          $settings['button_text']['free_download_text']['id'] = 'edd_purchased_download_button_text';
          $settings['button_text']['free_download_text']['name'] = sprintf( __( '%s Text', 'edd-purchased-download-button' ), edd_get_label_singular() );
          $settings['button_text']['free_download_text']['desc'] = sprintf( __( 'Text shown on the purchased %s.', 'edd-purchased-download-button' ), edd_get_label_plural( true ) );
          $settings['button_text']['free_download_text']['type'] = 'text';
          $settings['button_text']['free_download_text']['std'] = __( 'Download', 'edd-purchased-download-button' );

          return $settings;
        }


        /**
         * Download button on purchased downloads
         *
         * @access      public
         * @since       1.0.0
         * @param       string $purchase_form EDD original purchase form
         * @param       array $args Purchase form args (contains the download ID)
         * @return      string $purchase_form
         */
        public function purchased_download_button( $purchase_form, $args ) {
            global $edd_options;

            // Bail if user is not logged in
            if ( ! is_user_logged_in() ) {
                return $purchase_form;
            }

            $download_id = (string) $args['download_id'];

            $current_user_id = get_current_user_id();

            // If the user has purchased this item, itterate through their purchases to get the specific purchase data and pull out the key and email associated with it.
            // This is necessary for the generation of the download link
            if ( edd_has_user_purchased( $current_user_id, $download_id, $variable_price_id = null ) ) {
                $user_purchases = $this->get_users_purchases( $current_user_id, -1, false, 'complete' );

                foreach ( $user_purchases as $purchase ) {
                    $cart_items = edd_get_payment_meta_cart_details( $purchase->ID );
                    $item_ids = wp_list_pluck( $cart_items, 'id' );

                    if ( in_array( $download_id, $item_ids ) ) {
                        $email = edd_get_payment_user_email( $purchase->ID );
                        $payment_key = edd_get_payment_key( $purchase->ID );
                        $payment_id = $purchase->ID;
                    }

                    // Variable priced downloads
                    foreach ( $cart_items as $item ) {
                      if ( edd_has_variable_prices( $download_id ) ) {
                        $price_id = isset( $item['item_number']['options']['price_id'] ) ? $item['item_number']['options']['price_id'] : null;
                      }
                    }
                }

                $download_ids = array();

                if ( edd_is_bundled_product( $download_id ) ) {
                    $download_ids = edd_get_bundled_products( $download_id );
                } else {
                    $download_ids[] = $download_id;
                }

                $text  = isset( $edd_options['edd_purchased_download_button_text'] ) ? $edd_options['edd_purchased_download_button_text'] : 'Download';
                $style = isset( $edd_options['button_style'] ) ? $edd_options['button_style'] : 'button';
                $color = isset( $edd_options['checkout_color'] ) ? $edd_options['checkout_color'] : 'blue';

                $new_purchase_form = '';

                foreach ( $download_ids as $item ) {
                    // Attempt to get the file data associated with this download
                    $download_data = edd_get_download_files( $item, $price_id );

                    if ( $download_data ) {
                        foreach ( $download_data as $filekey => $file ) {

                            // Skip the file if we have hit the download limit for the download/purchase
                            if ( edd_is_file_at_download_limit( $download_id, $payment_id, $filekey, $price_id ) ) {
                              continue;
                            }

                            // Generate the file URL and then make a link to it
                            $file_url = edd_get_download_file_url( $payment_key, $email, $filekey, $item, $price_id );
                            $new_purchase_form .= '<a href="' . $file_url . '" class="edd-purchased-download-button ' . $style . ' ' . $color . ' edd-submit"><span class="edd-purchased-download-label">' . __( $text, 'edd-purchased-download-button' ) . '</span></a>';
                        }
                    }
                    // As long as we ended up with links to show, use them.
                    if ( ! empty( $new_purchase_form ) ) {
                        $purchase_form = '<div class="edd_purchase_submit_wrapper">' . $new_purchase_form . '</div>';
                    }
                }
            }

            return apply_filters( 'edd_purchases_download_button', $purchase_form, $args );
        }


        /**
         * Get Users Purchases
         *
         * Retrieves a list of all purchases by a specific user.
         *
         * @since  1.0.0
         *
         * @param int $user User ID or email address
         * @param int $number Number of purchases to retrieve
         * @param bool pagination
         * @param string|array $status Either an array of statuses, a single status as a string literal or a comma separated list of statues
         *
         * @return bool|object List of all user purchases
         */
        function get_users_purchases( $user = 0, $number = 20, $pagination = false, $status = 'complete' ) {
        	if ( empty( $user ) ) {
        		$user = get_current_user_id();
        	}

        	if ( 0 === $user ) {
        		return false;
        	}

        	if ( is_string( $status ) ) {
        		if ( strpos( $status, ',' ) ) {
        			$status = explode( ',', $status );
        		} else {
        			$status = $status === 'complete' ? 'publish' : $status;
        			$status = array( $status );
        		}

        	}

        	if ( is_array( $status ) ) {
        		$status = array_unique( $status );
        	}

        	if ( $pagination ) {
        		if ( get_query_var( 'paged' ) )
        			$paged = get_query_var('paged');
        		else if ( get_query_var( 'page' ) )
        			$paged = get_query_var( 'page' );
        		else
        			$paged = 1;
        	}

        	$args = array(
        		'user'    => $user,
        		'number'  => $number,
        		'status'  => $status,
        		'orderby' => 'date',
        		'order' 	=> 'asc',
        	);

        	if ( $pagination ) {

        		$args['page'] = $paged;

        	} else {

        		$args['nopaging'] = true;

        	}

        	$by_user_id = is_numeric( $user ) ? true : false;
        	$customer   = new EDD_Customer( $user, $by_user_id );

        	if( ! empty( $customer->payment_ids ) ) {

        		unset( $args['user'] );
        		$args['post__in'] = array_map( 'absint', explode( ',', $customer->payment_ids ) );

        	}

        	$purchases = edd_get_payments( apply_filters( 'edd_purchased_download_button_get_users_purchases_args', $args ) );

        	// No purchases
        	if ( ! $purchases )
        		return false;

        	return $purchases;
        }
    }
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_Purchased_Download_Button
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Purchased_Download_Button The one true EDD_Purchased_Download_Button
 */
function edd_purchased_download_button() {
  if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
    if ( ! class_exists( 'EDD_Extension_Activation' ) ) {
        require_once 'includes/class-activation.php';
    }

  // Easy Digital Downloads activation
  if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
    $edd_activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
    $edd_activation = $edd_activation->run();
  }

  } else {

    return EDD_Purchased_Download_Button::instance();
  }
}
add_action( 'plugins_loaded', 'edd_purchased_download_button' );
