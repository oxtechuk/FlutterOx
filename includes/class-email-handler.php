<?php
/**
 * Email utilities for Doken Ox Pro.
 *
 * @package Doken_Ox_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Doken_Ox_Email_Handler {

    /**
     * Send a generic email through wp_mail.
     *
     * @param string|array $to          Recipient(s).
     * @param string       $subject     Subject line.
     * @param string       $message     HTML body.
     * @param array        $headers     Additional headers.
     * @param array        $attachments Attachments.
     *
     * @return bool
     */
    public static function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        $defaults = array( 'Content-Type: text/html; charset=UTF-8' );
        $headers  = array_merge( $defaults, $headers );

        return wp_mail( $to, $subject, $message, $headers, $attachments );
    }

    /**
     * Dispatch password reset email.
     *
     * @param WP_User $user     User entity.
     * @param string  $reset_url Password reset URL/token.
     *
     * @return bool
     */
    public static function send_password_reset( WP_User $user, $reset_url ) {
        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] Password Reset Request', 'doken-ox-pro' ),
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
        );

        $message  = '<p>' . esc_html__( 'You requested a password reset.', 'doken-ox-pro' ) . '</p>';
        $message .= '<p><a href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Click here to reset your password', 'doken-ox-pro' ) . '</a></p>';

        return self::send( $user->user_email, $subject, $message );
    }

    /**
     * Send notification email to vendor/admin when needed.
     *
     * @param string $email   Recipient.
     * @param string $subject Subject.
     * @param string $body    Body.
     *
     * @return bool
     */
    public static function send_notification_email( $email, $subject, $body ) {
        return self::send( $email, $subject, wpautop( $body ) );
    }

    /**
     * Send OTP email.
     *
     * @param string $email Recipient.
     * @param string $otp   OTP code.
     *
     * @return bool
     */
    public static function send_otp_email( $email, $otp ) {
        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] Your OTP Code', 'doken-ox-pro' ),
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
        );

        $message  = '<p>' . esc_html__( 'Your One-Time Password (OTP) is:', 'doken-ox-pro' ) . '</p>';
        $message .= '<h2 style="background: #f4f4f4; padding: 10px; display: inline-block;">' . esc_html( $otp ) . '</h2>';
        $message .= '<p>' . esc_html__( 'This code is valid for 10 minutes.', 'doken-ox-pro' ) . '</p>';

        return self::send( $email, $subject, $message );
    }
}
