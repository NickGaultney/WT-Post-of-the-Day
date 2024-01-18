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

    $title = $retrieve_data[0]->title;
    $content = $retrieve_data[0]->content;

    set_transient('wt_potd_title', $title, 3600 * 24);
    set_transient('wt_potd_content', $content, 3600 * 24);

    return [$title, $content];
}

/*
    Loops through all email addresses in a Newsletter list and sends them the PotD
*/
function all_emails() {
    global $wpdb;
    $newsletter_list = "list_" . WT_Post_of_the_Day_Settings::get_list();
    $subscribers = $wpdb->get_results( "SELECT name, email FROM {$wpdb->prefix}newsletter WHERE {$newsletter_list} = 1", OBJECT );

    foreach ( $subscribers as $subscriber ) {
        email( $subscriber->email );
    }
}

/*
    Sends email to provided address with the PotD
*/
function email( $to ) {
    $subject = wt_title();
    $message = email_content();
    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail( $to, $subject, $message, $headers );
}


/*
    Retrieves the title of today's PotD title and caches it if not already in cache
*/
function wt_title() {
    $title = get_transient('wt_potd_title');

   if ($title === false) {
    [$title, $content] = cache_potd();
   }

   return $title;
}

/*
    Retrieves the title of today's PotD content and caches it if not already in cache
*/
function wt_content() {
    $content = get_transient('wt_potd_content');

   if ($content === false) {
    [$title, $content] = cache_potd();
   }

   return $content;
}

/*
    The styling and content of the email body
*/
function email_content() {
    $body = "<!DOCTYPE html>
    <html xmlns='https://www.w3.org/1999/xhtml' xmlns:o='urn:schemas-microsoft-com:office:office'>
      <head>
        <title>{email_subject}</title>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
        <meta name='format-detection' content='address=no'>
        <meta name='format-detection' content='telephone=no'>
        <meta name='format-detection' content='email=no'>
        <meta name='x-apple-disable-message-reformatting'>
        <!--[if gte mso 9]>
        <xml>
          <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
          </o:OfficeDocumentSettings>
        </xml>
        <![endif]-->
        <style type='text/css'>
          #outlook a{padding:0;}
          .ReadMsgBody{width:100%;} .ExternalClass{width:100%;}
          .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {line-height: 100%;}
          body { margin: 0; padding: 0; height: 100%!important; width: 100%!important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; mso-line-height-rule: exactly;}
          table,td { border-collapse: collapse !important; mso-table-lspace: 0pt; mso-table-rspace: 0pt;}
          img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; max-width: 100%!important; -ms-interpolation-mode: bicubic;}
          img.aligncenter { display: block; margin: 0 auto;}
          @media screen and (max-width: 525px) {
          .pt-1, .padding-top-15 { padding-top: 15px!important; }
          .pb-1, .padding-bottom-15 { padding-bottom: 15px!important; }
          .responsive { width:100%!important; }
          table.responsive { width:100%!important; float: none; display: table; padding-left: 0; padding-right: 0; }
          table[class='responsive'] { width:100%!important; float: none; display: table; padding-left: 0; padding-right: 0; }
          img { max-width: 100%!important }
          img[class='responsive'] { max-width: 100%!important; }
          /* 'width: auto' restores the natural dimensions forced with attributes for Outlook */
          .fluid { max-width: 100%!important; width: auto; }
          img[class='fluid'] { max-width: 100%!important; width: auto; }
          .block { display: block; }
          td[class='responsive']{width:100%!important; max-width: 100%!important; display: block; padding-left: 0 !important; padding-right: 0!important; float: none; }
          td.responsive { width:100%!important; max-width: 100%!important; display: block; padding-left: 0 !important; padding-right: 0!important; float: none; }
          td[class='section-padding-bottom-image']{
          padding: 50px 15px 0 15px !important;
          }
          .max-width-100 { max-width: 100%!important; }
          /* Obsolete */
          .tnp-grid-column {
          max-width: 100%!important;
          }
          }
          /* Text */
          /* Last posts */
          @media (max-width: 525px) {
          .posts-1-column {
          width: 100%!important;
          }
          .posts-1-image {
          width: 100%!important;
          display: block;
          }
          }
          /* Html */
          .html-td-global p {
          font-family: Helvetica, Arial, sans-serif;
          font-size: 16px;
          }
        </style>
      </head>
      <body style='margin: 0; padding: 0; line-height: normal; word-spacing: normal;' dir='ltr'>
        <table cellpadding='0' cellspacing='0' border='0' width='100%'>
          <tr>
            <td bgcolor='#ffffff' valign='top'>
              <!-- tnp -->
              <table style='border-collapse: collapse; width: 100%;' class='tnpc-row tnpc-row-block' data-id='header' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                <tbody>
                  <tr>
                    <td style='padding: 0;' class='edit-block' align='center'>
                      <!--[if mso | IE]>
                      <table role='presentation' border='0' cellpadding='0' align='center' cellspacing='0' width='600'>
                        <tr>
                          <td width='600' style='vertical-align:top;width:600px;'>
                            <![endif]-->
                            <table type='options' data-json='eyJibG9ja19wYWRkaW5nX3RvcCI6MTUsImJsb2NrX3BhZGRpbmdfYm90dG9tIjoxNSwiYmxvY2tfcGFkZGluZ19yaWdodCI6MTUsImJsb2NrX3BhZGRpbmdfbGVmdCI6MTUsImJsb2NrX2JhY2tncm91bmQiOiIiLCJibG9ja19iYWNrZ3JvdW5kXzIiOiIiLCJibG9ja193aWR0aCI6NjAwLCJibG9ja19hbGlnbiI6ImNlbnRlciIsImZvbnRfZmFtaWx5IjoiIiwiZm9udF9zaXplIjoiIiwiZm9udF9jb2xvciI6IiIsImZvbnRfd2VpZ2h0IjoiIiwibG9nb193aWR0aCI6IjEyMCIsImxheW91dCI6IiIsImlubGluZV9lZGl0cyI6IiIsImJsb2NrX2lkIjoiaGVhZGVyIn0=' class='tnpc-block-content' style='width: 100%!important; max-width: 600px!important' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                              <tbody>
                                <tr>
                                  <td style='text-align: center; width: 100% !important; line-height: normal !important; letter-spacing: normal; padding-top: 15px; padding-left: 15px; padding-right: 15px; padding-bottom: 15px; background-color: #ffffff;' width='100%' bgcolor='#ffffff' align='center'>
                                    <table style='margin: 0; border-collapse: collapse;' width='100%' cellspacing='0' cellpadding='0' border='0'>
                                      <tbody>
                                        <tr>
                                          <td style='padding: 10px' width='50%' align='center'>
                                            <a href='https://nickmark.info' target='_blank' style='font-size: 19px;font-family: Verdana, Geneva, sans-serif;font-weight: normal;color: #222222; text-decoration: none; line-height: normal;'>
                                            " . WT_Post_of_the_Day_Settings::get_title() . "            </a>
                                            <div style='font-size: 14px;font-family: Verdana, Geneva, sans-serif;font-weight: normal;color: #222222; text-decoration: none; line-height: normal; padding: 10px;'>" . WT_Post_of_the_Day_Settings::get_subtitle() . "</div>
                                          </td>
                                        </tr>
                                      </tbody>
                                    </table>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                            <!--[if mso | IE]>
                          </td>
                        </tr>
                      </table>
                      <![endif]-->
                    </td>
                  </tr>
                </tbody>
              </table>
              <table style='border-collapse: collapse; width: 100%;' class='tnpc-row tnpc-row-block' data-id='heading' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                <tbody>
                  <tr>
                    <td style='padding: 0;' class='edit-block' align='center'>
                      <!--[if mso | IE]>
                      <table role='presentation' border='0' cellpadding='0' align='center' cellspacing='0' width='600'>
                        <tr>
                          <td width='600' style='vertical-align:top;width:600px;'>
                            <![endif]-->
                            <table type='options' data-json='eyJibG9ja19wYWRkaW5nX3RvcCI6MTUsImJsb2NrX3BhZGRpbmdfYm90dG9tIjoxNSwiYmxvY2tfcGFkZGluZ19yaWdodCI6MTUsImJsb2NrX3BhZGRpbmdfbGVmdCI6MTUsImJsb2NrX2JhY2tncm91bmQiOiIiLCJibG9ja19iYWNrZ3JvdW5kXzIiOiIiLCJibG9ja193aWR0aCI6NjAwLCJibG9ja19hbGlnbiI6ImNlbnRlciIsInRleHQiOiJUaGUgZGF5IGhhcyBjb21lISIsImFsaWduIjoiY2VudGVyIiwiZm9udF9mYW1pbHkiOiIiLCJmb250X3NpemUiOiIzNiIsImZvbnRfY29sb3IiOiIiLCJmb250X3dlaWdodCI6ImJvbGQiLCJibG9ja19pZCI6ImhlYWRpbmcifQ==' class='tnpc-block-content' style='width: 100%!important; max-width: 600px!important' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                              <tbody>
                                <tr>
                                  <td style='text-align: center; width: 100% !important; line-height: normal !important; letter-spacing: normal; padding-top: 15px; padding-left: 15px; padding-right: 15px; padding-bottom: 15px; background-color: #FFFFFF;' width='100%' bgcolor='#FFFFFF' align='center'>
                                    <table width='100%' cellspacing='0' cellpadding='0' border='0'>
                                      <tbody>
                                        <tr>
                                          <td style='font-size: 36px;font-family: Verdana, Geneva, sans-serif;font-weight: bold;color: #222222; padding: 0; line-height: normal !important; letter-spacing: normal;' valign='middle' align='center'>" .
                                            wt_title() . "
                                          </td>
                                        </tr>
                                      </tbody>
                                    </table>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                            <!--[if mso | IE]>
                          </td>
                        </tr>
                      </table>
                      <![endif]-->
                    </td>
                  </tr>
                </tbody>
              </table>
              <table style='border-collapse: collapse; width: 100%;' class='tnpc-row tnpc-row-block' data-id='text' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                <tbody>
                  <tr>
                    <td style='padding: 0;' class='edit-block' align='center'>
                      <!--[if mso | IE]>
                      <table role='presentation' border='0' cellpadding='0' align='center' cellspacing='0' width='600'>
                        <tr>
                          <td width='600' style='vertical-align:top;width:600px;'>
                            <![endif]-->
                            <table type='options' data-json='eyJibG9ja19wYWRkaW5nX3RvcCI6MjAsImJsb2NrX3BhZGRpbmdfYm90dG9tIjoyMCwiYmxvY2tfcGFkZGluZ19yaWdodCI6MzAsImJsb2NrX3BhZGRpbmdfbGVmdCI6MzAsImJsb2NrX2JhY2tncm91bmQiOiIiLCJibG9ja19iYWNrZ3JvdW5kXzIiOiIiLCJibG9ja193aWR0aCI6NjAwLCJibG9ja19hbGlnbiI6ImNlbnRlciIsImh0bWwiOiJcdTAwM0NwIHN0eWxlPVwidGV4dC1hbGlnbjogY2VudGVyXCJcdTAwM0VcdTAwM0NzcGFuIHN0eWxlPVwiZm9udC1zaXplOiAxNnB4O2ZvbnQtZmFtaWx5OiBhcmlhbCxoZWx2ZXRpY2Esc2Fucy1zZXJpZlwiXHUwMDNFV2UgYXJlIGluY3JlZGlibHkgZXhjaXRlZCB0byBzaGFyZSB3aXRoIHlvdSBzb21lIGJpZyBuZXdzOiBvdXIgW25ldyBzZXJ2aWNlXSBpcyBmaW5hbGx5IGhlcmUgZm9yIHlvdSFcdTAwM0NcL3NwYW5cdTAwM0VcdTAwM0NcL3BcdTAwM0UiLCJmb250X2ZhbWlseSI6IiIsImZvbnRfc2l6ZSI6IiIsImZvbnRfY29sb3IiOiIiLCJibG9ja19pZCI6InRleHQifQ==' class='tnpc-block-content' style='width: 100%!important; max-width: 600px!important' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                              <tbody>
                                <tr>
                                  <td style='text-align: center; width: 100% !important; line-height: normal !important; letter-spacing: normal; padding-top: 20px; padding-left: 30px; padding-right: 30px; padding-bottom: 20px; background-color: #FFFFFF;' width='100%' bgcolor='#FFFFFF' align='center'>
                                    <table style='width: 100%!important' width='100%' cellspacing='0' cellpadding='0' border='0'>
                                      <tbody>
                                        <tr>
                                          <td style='font-family: Verdana, Geneva, sans-serif; font-size: 16px; font-weight: normal; color: #222222; line-height: 1.5;' width='100%' valign='top' align='left'>
                                            <p style='text-align: center'><span style='font-size: 16px;font-family: arial,helvetica,sans-serif'>
                                            " . wt_content() . "</p>
                                          </td>
                                        </tr>
                                      </tbody>
                                    </table>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                            <!--[if mso | IE]>
                          </td>
                        </tr>
                      </table>
                      <![endif]-->
                    </td>
                  </tr>
                </tbody>
              </table>
              <table style='border-collapse: collapse; width: 100%;' class='tnpc-row tnpc-row-block' data-id='image' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                <tbody>
                  <tr>
                    <td style='padding: 0;' class='edit-block' align='center'>
                      <!--[if mso | IE]>
                      <table role='presentation' border='0' cellpadding='0' align='center' cellspacing='0' width='600'>
                        <tr>
                          <td width='600' style='vertical-align:top;width:600px;'>
                            <![endif]-->
                            <table type='options' data-json='eyJibG9ja19wYWRkaW5nX3RvcCI6MTUsImJsb2NrX3BhZGRpbmdfYm90dG9tIjoxNSwiYmxvY2tfcGFkZGluZ19yaWdodCI6MCwiYmxvY2tfcGFkZGluZ19sZWZ0IjowLCJibG9ja19iYWNrZ3JvdW5kIjoiIiwiYmxvY2tfYmFja2dyb3VuZF8yIjoiIiwiYmxvY2tfd2lkdGgiOjYwMCwiYmxvY2tfYWxpZ24iOiJjZW50ZXIiLCJpbWFnZSI6eyJpZCI6IjAiLCJ1cmwiOiIifSwiaW1hZ2UtYWx0IjoiIiwidXJsIjoiIiwid2lkdGgiOiIwIiwiYWxpZ24iOiJjZW50ZXIiLCJpbmxpbmVfZWRpdHMiOiIiLCJwbGFjZWhvbGRlciI6Imh0dHBzOlwvXC9uaWNrbWFyay5pbmZvXC93cC1jb250ZW50XC9wbHVnaW5zXC9uZXdzbGV0dGVyXC9lbWFpbHNcL3ByZXNldHNcL2Fubm91bmNlbWVudFwvaW1hZ2VzXC9hbm5vdW5jZW1lbnQuanBnIiwiaW1hZ2UtdXJsIjoiIiwiYmxvY2tfaWQiOiJpbWFnZSJ9' class='tnpc-block-content' style='width: 100%!important; max-width: 600px!important' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                              <tbody>
                                <tr>
                                  <td style='text-align: center; width: 100% !important; line-height: normal !important; letter-spacing: normal; padding-top: 15px; padding-left: 0px; padding-right: 0px; padding-bottom: 15px; background-color: #ffffff;' width='100%' bgcolor='#ffffff' align='center'>
                                    <table width='100%'>
                                      <tbody>
                                        <tr>
                                          <td align='center'><img src='" . WT_Post_of_the_Day_Settings::get_image() . "' alt=' style='display: block; max-width: 600px !important; width: 100%; padding: 0; border: 0; font-size: 12px' width='600' height='250' border='0'></td>
                                        </tr>
                                      </tbody>
                                    </table>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                            <!--[if mso | IE]>
                          </td>
                        </tr>
                      </table>
                      <![endif]-->
                    </td>
                  </tr>
                </tbody>
              </table>
              <table style='border-collapse: collapse; width: 100%;' class='tnpc-row tnpc-row-block' data-id='footer' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                <tbody>
                  <tr>
                    <td style='padding: 0;' class='edit-block' align='center'>
                      <!--[if mso | IE]>
                      <table role='presentation' border='0' cellpadding='0' align='center' cellspacing='0' width='600'>
                        <tr>
                          <td width='600' style='vertical-align:top;width:600px;'>
                            <![endif]-->
                            <table type='options' data-json='eyJibG9ja19wYWRkaW5nX3RvcCI6MTUsImJsb2NrX3BhZGRpbmdfYm90dG9tIjoxNSwiYmxvY2tfcGFkZGluZ19yaWdodCI6MTUsImJsb2NrX3BhZGRpbmdfbGVmdCI6MTUsImJsb2NrX2JhY2tncm91bmQiOiIiLCJibG9ja19iYWNrZ3JvdW5kXzIiOiIiLCJibG9ja193aWR0aCI6NjAwLCJibG9ja19hbGlnbiI6ImNlbnRlciIsInZpZXciOiJWaWV3IG9ubGluZSIsInZpZXdfZW5hYmxlZCI6MSwicHJvZmlsZSI6Ik1hbmFnZSB5b3VyIHN1YnNjcmlwdGlvbiIsInByb2ZpbGVfZW5hYmxlZCI6MSwidW5zdWJzY3JpYmUiOiJVbnN1YnNjcmliZSIsInVuc3Vic2NyaWJlX2VuYWJsZWQiOjEsImZvbnRfZmFtaWx5IjoiIiwiZm9udF9zaXplIjoiIiwiZm9udF9jb2xvciI6IiIsImZvbnRfd2VpZ2h0IjoiIiwidXJsIjoicHJvZmlsZSIsImJsb2NrX2lkIjoiZm9vdGVyIn0=' class='tnpc-block-content' style='width: 100%!important; max-width: 600px!important' width='100%' cellspacing='0' cellpadding='0' border='0' align='center'>
                              <tbody>
                                <tr>
                                  <td style='text-align: center; width: 100% !important; line-height: normal !important; letter-spacing: normal; padding-top: 15px; padding-left: 15px; padding-right: 15px; padding-bottom: 15px; background-color: #FFFFFF;' width='100%' bgcolor='#FFFFFF' align='center'><a style='font-size: 13px;font-family: Verdana, Geneva, sans-serif;font-weight: normal;color: #222222; text-decoration: none; line-height: normal;' href='{unsubscription_url}' target='_blank'>Unsubscribe</a><span style='font-size: 13px;font-family: Verdana, Geneva, sans-serif;font-weight: normal;color: #222222; text-decoration: none; line-height: normal;'>   |   </span><a style='font-size: 13px;font-family: Verdana, Geneva, sans-serif;font-weight: normal;color: #222222; text-decoration: none; line-height: normal;' href='{profile_url}' target='_blank'>Manage your subscription</a><span style='font-size: 13px;font-family: Verdana, Geneva, sans-serif;font-weight: normal;color: #222222; text-decoration: none; line-height: normal;'>   |   </span><a style='font-size: 13px;font-family: Verdana, Geneva, sans-serif;font-weight: normal;color: #222222; text-decoration: none; line-height: normal;' href='{email_url}' target='_blank'>View online</a></td>
                                </tr>
                              </tbody>
                            </table>
                            <!--[if mso | IE]>
                          </td>
                        </tr>
                      </table>
                      <![endif]-->
                    </td>
                  </tr>
                </tbody>
              </table>
              <!-- /tnp -->
            </td>
          </tr>
        </table>
      </body>
    </html>";

    return $body;
}

add_action( 'wt-title', 'wt_title' );
add_action( 'wt-content', 'wt_content' );
add_action( 'wt_potd', 'wt_potd');

add_shortcode( 'wt-title', 'wt_title' );
add_shortcode( 'wt-content', 'wt_content' );
wt_post_of_the_day();