<?php
/**
 * Plugin Name: WT Post of the Day
 * Version: 1.0.0
 * Plugin URI: https://nickgaultney.github.io/
 * Description: WT Post of the Day
 * Author: Nick Mark
 * Author URI: https://nickgaultney.github.io/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: wt-post-of-the-day
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Nick Mark
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-wt-post-of-the-day.php';
require_once 'includes/class-wt-post-of-the-day-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-wt-post-of-the-day-admin-api.php';
require_once 'includes/lib/class-wt-post-of-the-day-post-type.php';
require_once 'includes/lib/class-wt-post-of-the-day-taxonomy.php';

/**
 * Returns the main instance of WT_Post_of_the_Day to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WT_Post_of_the_Day
 */
function wt_post_of_the_day() {
	$instance = WT_Post_of_the_Day::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = WT_Post_of_the_Day_Settings::instance( $instance );
	}

	return $instance;
}

function wt_potd() {
    // Gets the DB row of today's PotD
    $current_potd = get_active_potd();
    // Gets the index of the next post in the cycle
    $next_potd_cycle_position = get_next_active_potd($current_potd);

    // Deactivates today's PotD by setting the "isActive" column to 0
    deactivate_potd();
    // Activates the new PotD by setting the "isActive" column to 1
    activate_potd($next_potd_cycle_position);

    cache_potd();
    do_action( 'qm/debug', 'Sending Emails....');
    all_emails();
}

/*
    Gets the DB row of today's PotD by finding the row where "isActive" is true
*/
function get_active_potd() {
    global $wpdb;
    $table_name = WT_Post_of_the_Day::table_name();
    return $wpdb->get_results( "SELECT * FROM $table_name WHERE isActive = 1" )[0];
}

/*
    Gets the index of the next post in the cycle, ensuring that it loops back
    to the beginning of the list if the current post is the last post. 
*/
function get_next_active_potd($potd) {
    global $wpdb;
    $table_name = WT_Post_of_the_Day::table_name();
    $table_size = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

    if ($table_size > $potd->cyclePosition) {
        return $potd->cyclePosition + 1;
    } else {
        return 1;
    }
}

/*
    Deactivates today's PotD by setting the "isActive" column to 0
*/
function deactivate_potd() {
    global $wpdb;
    $table_name = WT_Post_of_the_Day::table_name();
    $wpdb->update( $table_name, array( 'isActive' => 0 ), array( 'isActive' => 1 ) );
}

/*
    Activates today's PotD by setting the "isActive" column to 1
*/
function activate_potd($cyclePosition) {
    global $wpdb;
    $table_name = WT_Post_of_the_Day::table_name();
    $wpdb->update( $table_name, array( 'isActive' => 1 ), array( 'cyclePosition' => $cyclePosition ) );
}

/*
    Retrieves the PotD title and content and saves them into transient cache. 

    FIXME: Seems like a redundant DB lookup since it was already found in "wt_potd"
            during the "activate_potd"
*/
function cache_potd() {
    global $wpdb;
    $table_name = WT_Post_of_the_Day::table_name();
    $retrieve_data = $wpdb->get_results( "SELECT * FROM $table_name WHERE isActive = 1" );
    $title = $scripture = $message = $quote = $prayer = $url = '';

    if ( ! empty( $retrieve_data ) ) {
      $post_id = $retrieve_data[0]->postID;

      // Get post title and content
      $title = get_the_title( $post_id );

      // Get custom fields using get_post_meta
      $scripture = get_post_meta( $post_id, 'scripture', true );
      $message = get_post_meta( $post_id, 'message', true );
      $quote = get_post_meta( $post_id, 'quote', true );
      $prayer = get_post_meta( $post_id, 'prayer', true );
      $url = get_post_meta( $post_id, 'devotional_pdf_url', true );

    }

    set_transient('wt_potd_title', $title, 3600 * 24);
    set_transient('wt_potd_scripture', $scripture, 3600 * 24);
    set_transient('wt_potd_message', $message, 3600 * 24);
    set_transient('wt_potd_quote', $quote, 3600 * 24);
    set_transient('wt_potd_prayer', $prayer, 3600 * 24);
    set_transient('wt_potd_url', $url, 3600 * 24);

    return [$title, $scripture, $message, $quote, $prayer, $url];
}

/*
    Loops through all email addresses in a Newsletter list and sends them the PotD
*/
function all_emails() {
    global $wpdb;
    $newsletter_list = "list_" . WT_Post_of_the_Day_Settings::get_list();
    $subscribers = $wpdb->get_results( "SELECT name, email, id FROM {$wpdb->prefix}newsletter WHERE {$newsletter_list} = 1", OBJECT );

    foreach ( $subscribers as $subscriber ) {
        email( $subscriber );
    }
}

/*
    Sends email to provided address with the PotD
*/
function email( $subscriber ) {
    $subject = wt_title();
    $unsubscribe_url = wt_tnp_unsubscribe_url($subscriber->id);
    $message = email_content($unsubscribe_url);
    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail( $subscriber->email, $subject, $message, $headers );
}


/*
    Retrieves the title of today's PotD title and caches it if not already in cache
*/
function wt_title() {
    $title = get_transient('wt_potd_title');

   if ($title === false) {
    [$title, $scripture, $message, $quote, $prayer, $url] = cache_potd();
   }

   return $title;
}

/*
    Retrieves the content of today's PotD content and caches it if not already in cache
*/
function wt_content() {
    $scripture = get_transient('wt_potd_scripture');
    $message = get_transient('wt_potd_message');
    $quote = get_transient('wt_potd_quote');
    $prayer = get_transient('wt_potd_prayer');
    $url = get_transient('wt_potd_url');

    if ( !($scripture && $message && $quote && $prayer && $url) ) {
      [$title, $scripture, $message, $quote, $prayer, $url] = cache_potd();
    }

    $content = array(
      'scripture' => $scripture,
      'message'   => $message,
      'quote'     => $quote,
      'prayer'    => $prayer,
      'url'       => $url,
    );

    #TODO make sure content is used properlly
   return $content;
}

function wt_tnp_unsubscribe_url( $user_id ) {
  $url = home_url();

  // Check if the Newsletter plugin is installed
  if ( class_exists( 'Newsletter' ) ) {
      // The Newsletter class exists, so the plugin is installed

      // Get the instance if available
      $newsletter = method_exists( 'Newsletter', 'instance' ) ? Newsletter::instance() : null;

      if ( $newsletter ) {
          // The Newsletter instance is available, so the plugin is active
        $subscriber = $newsletter->get_user(user_id);
        $url = home_url() . '?na=uc&k=' . $subscriber->id . '-' . $subscriber->token;
          // Proceed with using the $newsletter instance
          // ...

      } else {
          // The Newsletter instance is not available
          // Handle the case where the plugin is not active
      }

  } else {
      // The Newsletter class does not exist
      // Handle the case where the plugin is not installed
  }

  return $url;
}

/*
    The styling and content of the email body
*/
function email_content( $unsubscribe_url ) {
    $hp_body = "<!DOCTYPE html>

    <html lang='en' xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:v='urn:schemas-microsoft-com:vml'>
    <head>
    <title></title>
    <meta content='text/html; charset=utf-8' http-equiv='Content-Type'/>
    <meta content='width=device-width, initial-scale=1.0' name='viewport'/><!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch><o:AllowPNG/></o:OfficeDocumentSettings></xml><![endif]-->
    <style>
        * {
          box-sizing: border-box;
        }

        body {
          margin: 0;
          padding: 0;
        }

        a[x-apple-data-detectors] {
          color: inherit !important;
          text-decoration: inherit !important;
        }

        #MessageViewBody a {
          color: inherit;
          text-decoration: none;
        }

        p {
          line-height: inherit
        }

        .desktop_hide,
        .desktop_hide table {
          mso-hide: all;
          display: none;
          max-height: 0px;
          overflow: hidden;
        }

        .image_block img+div {
          display: none;
        }

        .menu_block.desktop_hide .menu-links span {
          mso-hide: all;
        }

        @media (max-width:768px) {
          .desktop_hide table.icons-inner {
            display: inline-block !important;
          }

          .icons-inner {
            text-align: center;
          }

          .icons-inner td {
            margin: 0 auto;
          }

          .mobile_hide {
            display: none;
          }

          .row-content {
            width: 100% !important;
          }

          .stack .column {
            width: 100%;
            display: block;
          }

          .mobile_hide {
            min-height: 0;
            max-height: 0;
            max-width: 0;
            overflow: hidden;
            font-size: 0px;
          }

          .desktop_hide,
          .desktop_hide table {
            display: table !important;
            max-height: none !important;
          }
        }
      </style>
    </head>
    <body style='background-color: #3f3f3f; margin: 0; padding: 0; -webkit-text-size-adjust: none; text-size-adjust: none;'>
    <table border='0' cellpadding='0' cellspacing='0' class='nl-container' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #3f3f3f;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row row-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row-content stack' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 800px; margin: 0 auto;' width='800'>
    <tbody>
    <tr>
    <td class='column column-1' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;' width='100%'>
    <table border='0' cellpadding='10' cellspacing='0' class='heading_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <h1 style='margin: 0; color: #ffffff; direction: ltr; font-family: Arial, Helvetica, sans-serif; font-size: 38px; font-weight: 700; letter-spacing: normal; line-height: 120%; text-align: center; margin-top: 0; margin-bottom: 0; mso-line-height-alt: 45.6px;'><span class='tinyMce-placeholder'>Traveling Prayer Partners</span></h1>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row row-2' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row-content stack' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-bottom: 10px solid transparent; border-left: 10px solid transparent; border-radius: 0; border-right: 10px solid transparent; border-top: 10px solid transparent; color: #000000; width: 800px; margin: 0 auto;' width='800'>
    <tbody>
    <tr>
    <td class='column column-1' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #222222; padding-bottom: 20px; padding-left: 20px; padding-right: 20px; padding-top: 20px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;' width='100%'>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 14.399999999999999px; color: #ffffff; line-height: 1.2;'>
    <p style='margin: 0; font-size: 16px; text-align: left; mso-line-height-alt: 19.2px;'><span style='font-size:20px;'><em><strong>" . wt_title() . "</strong></em></span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='divider_block block-2' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <div align='left' class='alignment'>
    <table border='0' cellpadding='0' cellspacing='0' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='33%'>
    <tr>
    <td class='divider_inner' style='font-size: 1px; line-height: 1px; border-top: 5px solid #CDA95B;'><span> </span></td>
    </tr>
    </table>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-3' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 21.6px; color: #ffffff; line-height: 1.8;'>
    <p style='margin: 0; font-size: 16px; mso-line-height-alt: 21.6px;'> </p>
    <p style='margin: 0; font-size: 16px; mso-line-height-alt: 28.8px;'><span style='font-size:16px;'>" . wt_content()['scripture'] . "</span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row row-3' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row-content stack' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-bottom: 10px solid transparent; border-left: 10px solid transparent; border-radius: 0; border-right: 10px solid transparent; border-top: 10px solid transparent; color: #000000; width: 800px; margin: 0 auto;' width='800'>
    <tbody>
    <tr>
    <td class='column column-1' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #222222; padding-bottom: 20px; padding-left: 20px; padding-right: 20px; padding-top: 20px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;' width='100%'>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 14.399999999999999px; color: #ffffff; line-height: 1.2;'>
    <p style='margin: 0; font-size: 16px; text-align: left; mso-line-height-alt: 19.2px;'><span style='font-size:20px;'><em><strong>Message</strong></em></span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='divider_block block-2' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <div align='left' class='alignment'>
    <table border='0' cellpadding='0' cellspacing='0' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='33%'>
    <tr>
    <td class='divider_inner' style='font-size: 1px; line-height: 1px; border-top: 5px solid #CDA95B;'><span> </span></td>
    </tr>
    </table>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-3' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 18px; color: #ffffff; line-height: 1.5;'>
    <p style='margin: 0; mso-line-height-alt: 24px;'><span style='font-size:16px;'>" . wt_content()['message'] . "</span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row row-4' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row-content stack' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-radius: 0; color: #000000; width: 800px; margin: 0 auto;' width='800'>
    <tbody>
    <tr>
    <td class='column column-1' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #222222; border-bottom: 10px solid #3F3F3F; border-left: 10px solid #3F3F3F; border-right: 10px solid #3F3F3F; border-top: 10px solid #3F3F3F; padding-bottom: 20px; padding-left: 20px; padding-right: 20px; padding-top: 20px; vertical-align: top;' width='50%'>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 14.399999999999999px; color: #ffffff; line-height: 1.2;'>
    <p style='margin: 0; font-size: 16px; text-align: left; mso-line-height-alt: 19.2px;'><span style='font-size:20px;'><em><strong>Today's Quote</strong></em></span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='divider_block block-2' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <div align='left' class='alignment'>
    <table border='0' cellpadding='0' cellspacing='0' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='33%'>
    <tr>
    <td class='divider_inner' style='font-size: 1px; line-height: 1px; border-top: 5px solid #CDA95B;'><span> </span></td>
    </tr>
    </table>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-3' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 18px; color: #ffffff; line-height: 1.5;'>
    <p style='margin: 0; font-size: 16px; mso-line-height-alt: 18px;'> </p>
    <p style='margin: 0; font-size: 16px; mso-line-height-alt: 24px;'><span style='font-size:16px;'>" . wt_content()['quote'] . "</span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    </td>
    <td class='column column-2' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #222222; border-bottom: 10px solid #3F3F3F; border-left: 10px solid #3F3F3F; border-right: 10px solid #3F3F3F; border-top: 10px solid #3F3F3F; padding-bottom: 20px; padding-left: 20px; padding-right: 20px; padding-top: 20px; vertical-align: top;' width='50%'>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 14.399999999999999px; color: #ffffff; line-height: 1.2;'>
    <p style='margin: 0; font-size: 16px; text-align: left; mso-line-height-alt: 19.2px;'><span style='font-size:20px;'><em><strong>Today's Prayer</strong></em></span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='divider_block block-2' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <div align='left' class='alignment'>
    <table border='0' cellpadding='0' cellspacing='0' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='33%'>
    <tr>
    <td class='divider_inner' style='font-size: 1px; line-height: 1px; border-top: 5px solid #CDA95B;'><span> </span></td>
    </tr>
    </table>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='10' cellspacing='0' class='text_block block-3' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;' width='100%'>
    <tr>
    <td class='pad'>
    <div style='font-family: sans-serif'>
    <div class=' style='font-size: 12px; font-family: Arial, Helvetica, sans-serif; mso-line-height-alt: 18px; color: #ffffff; line-height: 1.5;'>
    <p style='margin: 0; font-size: 16px; mso-line-height-alt: 18px;'> </p>
    <p style='margin: 0; font-size: 16px; mso-line-height-alt: 24px;'><span style='font-size:16px;'>" . wt_content()['prayer'] . "</span></p>
    </div>
    </div>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row row-5' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row-content stack' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 800px; margin: 0 auto;' width='800'>
    <tbody>
    <tr>
    <td class='column column-1' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;' width='100%'>
    <table border='0' cellpadding='10' cellspacing='0' class='button_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <div align='left' class='alignment'><!--[if mso]>
    <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' xmlns:w='urn:schemas-microsoft-com:office:word' href='https://him-powered.com' style='height:42px;width:191px;v-text-anchor:middle;' arcsize='10%' stroke='false' fillcolor='#5b7fcd'>
    <w:anchorlock/>
    <v:textbox inset='0px,0px,0px,0px'>
    <center style='color:#ffffff; font-family:Arial, sans-serif; font-size:16px'>
    <![endif]--><a href='" . wt_content()['url'] . "' style='text-decoration:none;display:inline-block;color:#ffffff;background-color:#5b7fcd;border-radius:4px;width:auto;border-top:0px solid transparent;font-weight:400;border-right:0px solid transparent;border-bottom:0px solid transparent;border-left:0px solid transparent;padding-top:5px;padding-bottom:5px;font-family:Arial, Helvetica, sans-serif;font-size:16px;text-align:center;mso-border-alt:none;word-break:keep-all;' target='_blank'><span style='padding-left:20px;padding-right:20px;font-size:16px;display:inline-block;letter-spacing:normal;'><span style='word-break: break-word; line-height: 32px;'>Download Devotional</span></span></a><!--[if mso]></center></v:textbox></v:roundrect><![endif]--></div>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row row-6' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row-content stack' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 800px; margin: 0 auto;' width='800'>
    <tbody>
    <tr>
    <td class='column column-1' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;' width='100%'>
    <table border='0' cellpadding='10' cellspacing='0' class='divider_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <div align='center' class='alignment'>
    <table border='0' cellpadding='0' cellspacing='0' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='divider_inner' style='font-size: 1px; line-height: 1px; border-top: 2px solid #CDA95B;'><span> </span></td>
    </tr>
    </table>
    </div>
    </td>
    </tr>
    </table>
    <table border='0' cellpadding='20' cellspacing='0' class='menu_block block-2' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad'>
    <table border='0' cellpadding='0' cellspacing='0' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='alignment' style='text-align:center;font-size:0px;'>
    <div class='menu-links'><!--[if mso]><table role='presentation' border='0' cellpadding='0' cellspacing='0' align='center' style='><tr style='text-align:center;'><![endif]--><!--[if mso]><td style='padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px'><![endif]--><a href='" . $unsubscribe_url . "' style='mso-hide:false;padding-top:20px;padding-bottom:20px;padding-left:20px;padding-right:20px;display:inline-block;color:#ffffff;font-family:Arial, Helvetica, sans-serif;font-size:16px;text-decoration:none;letter-spacing:normal;' target='_self'>Unsubscribe</a><!--[if mso]></td><td><![endif]--><span class='sep' style='font-size:16px;font-family:Arial, Helvetica, sans-serif;color:#ffffff;'>|</span><!--[if mso]></td><![endif]--><!--[if mso]><td style='padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px'><![endif]--><a href='https://him-powered.com' style='mso-hide:false;padding-top:20px;padding-bottom:20px;padding-left:20px;padding-right:20px;display:inline-block;color:#ffffff;font-family:Arial, Helvetica, sans-serif;font-size:16px;text-decoration:none;letter-spacing:normal;' target='_self'>View Online</a><!--[if mso]></td><![endif]--><!--[if mso]></tr></table><![endif]--></div>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row row-7' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #ffffff;' width='100%'>
    <tbody>
    <tr>
    <td>
    <table align='center' border='0' cellpadding='0' cellspacing='0' class='row-content stack' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 800px; margin: 0 auto;' width='800'>
    <tbody>
    <tr>
    <td class='column column-1' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;' width='100%'>
    <table border='0' cellpadding='0' cellspacing='0' class='icons_block block-1' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='pad' style='vertical-align: middle; color: #1e0e4b; font-family: 'Inter', sans-serif; font-size: 15px; padding-bottom: 5px; padding-top: 5px; text-align: center;'>
    <table cellpadding='0' cellspacing='0' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt;' width='100%'>
    <tr>
    <td class='alignment' style='vertical-align: middle; text-align: center;'><!--[if vml]><table align='center' cellpadding='0' cellspacing='0' role='presentation' style='display:inline-block;padding-left:0px;padding-right:0px;mso-table-lspace: 0pt;mso-table-rspace: 0pt;'><![endif]-->
    <!--[if !vml]><!-->
    <table cellpadding='0' cellspacing='0' class='icons-inner' role='presentation' style='mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block; margin-right: -4px; padding-left: 0px; padding-right: 0px;'><!--<![endif]-->
    <tr>
    <td style='vertical-align: middle; text-align: center; padding-top: 5px; padding-bottom: 5px; padding-left: 5px; padding-right: 6px;'><a href='http://designedwithbeefree.com/' style='text-decoration: none;' target='_blank'><img align='center' alt='Beefree Logo' class='icon' height='32' src='images/Beefree-logo.png' style='display: block; height: auto; margin: 0 auto; border: 0;' width='34'/></a></td>
    <td style='font-family: 'Inter', sans-serif; font-size: 15px; font-weight: undefined; color: #1e0e4b; vertical-align: middle; letter-spacing: undefined; text-align: center;'><a href='http://designedwithbeefree.com/' style='color: #1e0e4b; text-decoration: none;' target='_blank'>Designed with Beefree</a></td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table><!-- End -->
    </body>
    </html>";

    return $hp_body;
}

add_action( 'wt-title', 'wt_title' );
add_action( 'wt-content', 'wt_content' );
add_action( 'wt_potd', 'wt_potd');

add_shortcode( 'wt-title', 'wt_title' );
add_shortcode( 'wt-content', 'wt_content' );
wt_post_of_the_day();