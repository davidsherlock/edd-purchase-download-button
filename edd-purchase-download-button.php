<?php
/**
 * Plugin Name:     Easy Digital Downloads - Purchase Download Button
 * Plugin URI:      https://sellcomet.com/downloads/purchase-download-button
 * Description:     Automatically add a "Download" button instead of "Add To Cart" on purchased downloads.
 * Version:         1.0.2
 * Author:          Sell Comet
 * Author URI:      https://sellcomet.com
 * Text Domain:     edd-purchase-download-button
 * Domain Path:     languages
 *
 * @package         EDD\Purchase_Download_Button
 * @author          Sell Comet
 * @copyright       Copyright (c) Sell Comet
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'EDD_Purchase_Download_Button' ) ) {

    /**
     * Main EDD_Purchase_Download_Button class
     *
     * @since       1.0.0
     */
    class EDD_Purchase_Download_Button {

        /**
         * @var         EDD_Purchase_Download_Button $instance The one true EDD_Purchase_Download_Button
         * @since       1.0.0
         */
        private static $instance;


        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Purchase_Download_Button
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new EDD_Purchase_Download_Button();
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
            define( 'EDD_PURCHASE_DOWNLOAD_BUTTON_VER', '1.0.2' );

            // Plugin path
            define( 'EDD_PURCHASE_DOWNLOAD_BUTTON_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'EDD_PURCHASE_DOWNLOAD_BUTTON_URL', plugin_dir_url( __FILE__ ) );
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
            add_filter( 'edd_purchase_download_form', array( $this, 'purchase_download_button' ), 10, 2 );
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
            $lang_dir = EDD_PURCHASE_DOWNLOAD_BUTTON_DIR . '/languages/';
            $lang_dir = apply_filters( 'edd_purchased_download_button_languages_directory', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'edd-purchase-download-button' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'edd-purchase-download-button', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-purchase-download-button/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-purchase-download-button/ folder
                load_textdomain( 'edd-purchase-download-button', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-purchase-download-button/languages/ folder
                load_textdomain( 'edd-purchase-download-button', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-purchase-download-button', false, $lang_dir );
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
          $settings['button_text']['free_download_text']['id'] = 'edd_purchase_download_button_text';
          $settings['button_text']['free_download_text']['name'] = sprintf( __( '%s Text', 'edd-purchase-download-button' ), edd_get_label_singular() );
          $settings['button_text']['free_download_text']['desc'] = sprintf( __( 'Text shown on the purchased %s.', 'edd-purchase-download-button' ), edd_get_label_plural( true ) );
          $settings['button_text']['free_download_text']['type'] = 'text';
          $settings['button_text']['free_download_text']['std'] = __( 'Download', 'edd-purchase-download-button' );

          $settings['button_text']['free_download_bundle_title']['id'] = 'edd_purchase_download_bundle_title';
          $settings['button_text']['free_download_bundle_title']['desc'] = esc_html__( 'Use Item names for Bundles.', 'edd-purchase-download-button' ) ;
          $settings['button_text']['free_download_bundle_title']['type'] = 'checkbox';
          $settings['button_text']['free_download_bundle_title']['std'] = false;

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
        public function purchase_download_button( $purchase_form, $args ) {
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
                
                $price_id = '';
                
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
                    $is_bundle = true;
                } else {
                    $download_ids[] = $download_id;
                    $is_bundle = false;
                }

                $text               = isset( $edd_options['edd_purchase_download_button_text'] ) ? $edd_options['edd_purchase_download_button_text'] : 'Download';
                $use_bundle_title   = isset( $edd_options['edd_purchase_download_bundle_title'] ) ? $edd_options['edd_purchase_download_bundle_title'] : false;
                $style              = isset( $edd_options['button_style'] ) ? $edd_options['button_style'] : 'button';
                $color              = isset( $edd_options['checkout_color'] ) ? $edd_options['checkout_color'] : 'blue';

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

                            if ($use_bundle_title && $is_bundle) {
                                $text = get_the_title($item);
                            } else {
                                $text = __( $text, 'edd-purchase-download-button' );
                            }

                            $text = apply_filters( 'edd_purchase_download_button_label', $text, $item, $is_bundle );

                            $new_purchase_form .= '<a href="' . $file_url . '" class="edd-purchase-download-button ' . $style . ' ' . $color . ' edd-submit"><span class="edd-purchased-download-label">' . $text . '</span></a>';
                        }
                    }
                    // As long as we ended up with links to show, use them.
                    if ( ! empty( $new_purchase_form ) ) {
                        $purchase_form = '<div class="edd_purchase_submit_wrapper">' . $new_purchase_form . '</div>';
                    }
                }
            }

            return apply_filters( 'edd_purchase_download_button', $purchase_form, $args );
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

        	$purchases = edd_get_payments( apply_filters( 'edd_purchase_download_button_get_purchase_args', $args ) );

        	// No purchases
        	if ( ! $purchases )
        		return false;

        	return $purchases;
        }
    }
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_Purchase_Download_Button
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Purchase_Download_Button The one true EDD_Purchase_Download_Button
 */
function edd_purchase_download_button() {
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

    return EDD_Purchase_Download_Button::instance();
  }
}
add_action( 'plugins_loaded', 'edd_purchase_download_button' );
