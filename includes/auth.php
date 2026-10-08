<?php
if (!defined('ABSPATH'))
    exit;

require_once DOKEN_OX_PRO_PATH . 'includes/helpers/response-helpers.php';

class Doken_Ox_Auth
{

    /**
     * ---------------------------------------------------
     *  Login
     *  POST /auth/login
     * ---------------------------------------------------
     */
    public static function login($request)
    {
        $email = sanitize_email($request->get_param('email'));
        $password = sanitize_text_field($request->get_param('password'));
        $role = sanitize_text_field($request->get_param('role'));

        if (empty($email) || empty($password) || empty($role)) {
            return dox_response(false, __('Email, password, and role are required.', 'doken-ox-pro'));
        }

        if (!in_array($role, ['customer', 'vendor'])) {
            return dox_response(false, __('Invalid role specified.', 'doken-ox-pro'));
        }

        $user = get_user_by('email', $email);

        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            return dox_response(false, __('Invalid email or password.', 'doken-ox-pro'));
        }

        $user_roles = (array) $user->roles;
        $is_vendor = in_array('vendor', $user_roles) || in_array('seller', $user_roles) || in_array('administrator', $user_roles) || in_array('dokan_vendor', $user_roles);

        if ($role === 'vendor' && !$is_vendor) {
            return dox_response(false, __('This account is not a vendor.', 'doken-ox-pro'), array(), array(), 403);
        }

        if ($role === 'customer' && $is_vendor) {
            return dox_response(false, __('This account is registered as a vendor.', 'doken-ox-pro'), array(), array(), 403);
        }

        $user_type = $is_vendor ? 'vendor' : 'customer';

        $tokens = Doken_Ox_JWT_Handler::generate_token($user->ID, $user->roles[0] ?? 'customer');

        $data = array(
            'token' => $tokens['token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'user' => dox_format_user($user),
            'user_type' => $user_type,
        );

        return dox_response(true, __('Logged in successfully.', 'doken-ox-pro'), $data);
    }

    /**
     * ---------------------------------------------------
     *  Register
     *  POST /auth/register
     * ---------------------------------------------------
     */
    public static function register($request)
    {
        $email = sanitize_email($request['email']);
        $password = sanitize_text_field($request['password']);
        $name = sanitize_text_field($request['name']);
        $role = sanitize_text_field($request['role']);
        $phone = sanitize_text_field($request['phone']);

        if (!in_array($role, ['customer', 'vendor'])) {
            $role = 'customer';
        }

        if (email_exists($email)) {
            return dox_response(false, __('Email already exists.', 'doken-ox-pro'));
        }

        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            return dox_response(false, __('Could not create user.', 'doken-ox-pro'));
        }

        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $name,
        ));

        update_user_meta($user_id, 'phone', $phone);

        // Assign Role (customer | vendor)
        $user = new WP_User($user_id);
        $user->set_role($role);

        // إذا role = vendor → نعمل متجر Dokan تلقائي
        if ($role == 'vendor' && function_exists('dokan')) {
            dokan()->vendor->create($user_id);
        }

        $user_object = get_user_by('id', $user_id);
        $tokens = Doken_Ox_JWT_Handler::generate_token($user_id, $user_object->roles[0] ?? $role);

        return dox_response(true, __('Account created successfully.', 'doken-ox-pro'), array(
            'token' => $tokens['token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'user' => dox_format_user($user_object),
        ));
    }

    /**
     * ---------------------------------------------------
     *  Me (Protected)
     *  GET /auth/me
     * ---------------------------------------------------
     */
    public static function me($request)
    {
        $uid = Doken_Ox_JWT_Handler::get_user_id_from_request();

        if (is_wp_error($uid) || !$uid) {
            return dox_response(false, __('Invalid token.', 'doken-ox-pro'), array(), array(), 401);
        }

        $user = get_user_by('id', $uid);

        return dox_response(true, '', array(
            'user' => dox_format_user($user),
        ));
    }

    /**
     * ---------------------------------------------------
     *  Update Profile (Protected)
     *  PUT /auth/profile
     * ---------------------------------------------------
     */
    public static function update_profile($request)
    {
        $uid = Doken_Ox_JWT_Handler::get_user_id_from_request();

        if (is_wp_error($uid) || !$uid) {
            return dox_response(false, __('Invalid token.', 'doken-ox-pro'), array(), array(), 401);
        }

        $name = sanitize_text_field($request['name']);
        $phone = sanitize_text_field($request['phone']);
        $avatar = $request['avatar'];

        $update = array(
            'ID' => $uid,
            'display_name' => $name,
        );

        wp_update_user($update);
        update_user_meta($uid, 'phone', $phone);

        // هل رفع Avatar Base64؟
        if (!empty($avatar)) {
            self::upload_avatar($uid, $avatar);
        }

        return dox_response(true, __('Profile updated successfully.', 'doken-ox-pro'), array(
            'user' => dox_format_user(get_user_by('id', $uid)),
        ));
    }

    /**
     * ---------------------------------------------------
     *  Logout
     *  POST /auth/logout
     * ---------------------------------------------------
     */
    public static function logout($request)
    {

        $token = Doken_Ox_JWT_Handler::get_token_from_headers();

        if (empty($token)) {
            return dox_response(true, __('Logged out successfully.', 'doken-ox-pro'));
        }

        Doken_Ox_JWT_Handler::invalidate_token($token);

        return dox_response(true, __('Logged out successfully.', 'doken-ox-pro'));
    }

    /**
     * Refresh token endpoint.
     */
    public static function refresh($request)
    {
        $refresh = sanitize_text_field($request->get_param('refresh_token'));

        if (empty($refresh)) {
            return dox_response(false, __('refresh_token is required.', 'doken-ox-pro'));
        }

        $tokens = Doken_Ox_JWT_Handler::refresh_access_token($refresh);

        if (is_wp_error($tokens)) {
            return dox_response(false, $tokens->get_error_message(), array(), array(), $tokens->get_error_data()['status'] ?? 400);
        }

        return dox_response(true, __('Token refreshed.', 'doken-ox-pro'), $tokens);
    }

    /**
     * Forgot password endpoint.
     */
    public static function forgot_password($request)
    {
        $email = sanitize_email($request->get_param('email'));

        if (empty($email)) {
            return dox_response(false, __('Email is required.', 'doken-ox-pro'));
        }

        $user = get_user_by('email', $email);

        if (!$user) {
            return dox_response(false, __('User not found.', 'doken-ox-pro'));
        }

        $reset_key = get_password_reset_key($user);
        if (is_wp_error($reset_key)) {
            return dox_response(false, $reset_key->get_error_message(), array(), array(), 400);
        }
        $reset_url = add_query_arg(
            array(
                'action' => 'rp',
                'key' => $reset_key,
                'login' => rawurlencode($user->user_login),
            ),
            wp_login_url()
        );

        Doken_Ox_Email_Handler::send_password_reset($user, $reset_url);

        return dox_response(true, __('Password reset email sent.', 'doken-ox-pro'));
    }

    /**
     * Reset password endpoint.
     */
    public static function reset_password($request)
    {
        $key = sanitize_text_field($request->get_param('key'));
        $login = sanitize_text_field($request->get_param('login'));
        $password = $request->get_param('password');

        if (empty($key) || empty($login) || empty($password)) {
            return dox_response(false, __('Missing reset data.', 'doken-ox-pro'));
        }

        $user = check_password_reset_key($key, $login);

        if (is_wp_error($user)) {
            return dox_response(false, $user->get_error_message(), array(), array(), 400);
        }

        reset_password($user, $password);

        return dox_response(true, __('Password updated successfully.', 'doken-ox-pro'));
    }


    /*************************************************
     * Helper Functions
     *************************************************/

    /**
     * رفع صورة Avatar (Base64) → WordPress Media Library under /custom-photos/
     */
    public static function upload_avatar($user_id, $base64)
    {
        // Strip data URI prefix if present  e.g. "data:image/png;base64,..."
        $mime_type = 'image/png';
        if (strpos($base64, 'base64,') !== false) {
            $parts = explode('base64,', $base64);
            // Parse mime from header e.g. "data:image/jpeg;base64,"
            if (strpos($parts[0], 'jpeg') !== false || strpos($parts[0], 'jpg') !== false) {
                $mime_type = 'image/jpeg';
            } elseif (strpos($parts[0], 'gif') !== false) {
                $mime_type = 'image/gif';
            } elseif (strpos($parts[0], 'webp') !== false) {
                $mime_type = 'image/webp';
            }
            $base64 = $parts[1];
        }

        $ext_map = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );
        $ext = $ext_map[$mime_type] ?? 'png';

        $decoded = base64_decode($base64);
        if (empty($decoded)) {
            return false;
        }

        // ── Create / use the "custom-photos" subfolder ──────────────────────
        $upload_dir   = wp_upload_dir();
        $custom_dir   = $upload_dir['basedir'] . '/custom-photos';
        $custom_url   = $upload_dir['baseurl'] . '/custom-photos';

        if (!file_exists($custom_dir)) {
            wp_mkdir_p($custom_dir);
        }

        $filename  = 'avatar-' . $user_id . '-' . time() . '.' . $ext;
        $file_path = $custom_dir . '/' . $filename;
        $file_url  = $custom_url . '/' . $filename;

        // Write file to disk
        if (file_put_contents($file_path, $decoded) === false) {
            return false;
        }

        // ── Register as WordPress Media Library attachment ───────────────────
        $filetype   = wp_check_filetype($filename, null);
        $attachment = array(
            'guid'           => $file_url,
            'post_mime_type' => $filetype['type'],
            'post_title'     => 'avatar-' . $user_id,
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        // Load required WP functions for attachment generation
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attach_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attach_id)) {
            return false;
        }

        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // ── Delete the old avatar attachment if it exists ───────────────────
        $old_attach_id = get_user_meta($user_id, 'dox_avatar_id', true);
        if ($old_attach_id) {
            wp_delete_attachment((int) $old_attach_id, true);
        }

        // ── Store attachment ID and URL in user meta ─────────────────────────
        update_user_meta($user_id, 'dox_avatar_id', $attach_id);
        update_user_meta($user_id, 'dox_avatar', $file_url);

        return $file_url;
    }

    /**
     * ---------------------------------------------------
     *  Send OTP
     *  POST /auth/send-otp
     * ---------------------------------------------------
     */
    public static function send_otp($request)
    {
        $email = sanitize_email($request->get_param('email'));

        if (empty($email) || !is_email($email)) {
            return dox_response(false, __('Invalid email address.', 'doken-ox-pro'));
        }

        // Generate 6 digit OTP
        $otp = rand(100000, 999999);

        // Store in transient for 10 minutes (600 seconds)
        // We use a hash of the email to be safe
        set_transient('dox_otp_' . md5($email), $otp, 600);

        // Send Email
        $sent = Doken_Ox_Email_Handler::send_otp_email($email, $otp);

        if (!$sent) {
            return dox_response(false, __('Failed to send OTP email.', 'doken-ox-pro'), array(), array(), 500);
        }

        return dox_response(true, __('OTP sent successfully.', 'doken-ox-pro'));
    }

    /**
     * ---------------------------------------------------
     *  Verify OTP
     *  POST /auth/verify-otp
     * ---------------------------------------------------
     */
    public static function verify_otp($request)
    {
        $email = sanitize_email($request->get_param('email'));
        $otp = sanitize_text_field($request->get_param('otp'));
        $role = sanitize_text_field($request->get_param('role'));

        if (empty($email) || empty($otp) || empty($role)) {
            return dox_response(false, __('Email, OTP, and role are required.', 'doken-ox-pro'));
        }

        if (!in_array($role, ['customer', 'vendor'])) {
            return dox_response(false, __('Invalid role specified.', 'doken-ox-pro'));
        }

        $stored_otp = get_transient('dox_otp_' . md5($email));

        if (!$stored_otp || $stored_otp != $otp) {
            return dox_response(false, __('Invalid or expired OTP.', 'doken-ox-pro'), array(), array(), 400);
        }

        delete_transient('dox_otp_' . md5($email));

        $user = get_user_by('email', $email);

        if ($user) {
            $user_roles = (array) $user->roles;
            $is_vendor = in_array('vendor', $user_roles) || in_array('seller', $user_roles) || in_array('administrator', $user_roles);

            if ($role === 'vendor' && !$is_vendor) {
                return dox_response(false, __('This account is not a vendor.', 'doken-ox-pro'), array(), array(), 403);
            }

            if ($role === 'customer' && $is_vendor) {
                return dox_response(false, __('This account is registered as a vendor.', 'doken-ox-pro'), array(), array(), 403);
            }

            $user_type = $is_vendor ? 'vendor' : 'customer';

            $tokens = Doken_Ox_JWT_Handler::generate_token($user->ID, $user->roles[0] ?? 'customer');

            $data = array(
                'token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $tokens['expires_in'],
                'user' => dox_format_user($user),
                'user_type' => $user_type,
                'is_new_user' => false
            );

            return dox_response(true, __('Logged in successfully.', 'doken-ox-pro'), $data);
        } else {
            return dox_response(true, __('OTP verified.', 'doken-ox-pro'), array('is_new_user' => true));
        }
    }
}
