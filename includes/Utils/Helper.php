<?php
namespace KG_Core\Utils;

class Helper {

    /**
     * Debugging helper to log data to debug.log
     * Usage: \KG_Core\Utils\Helper::log($data);
     */
    public static function log( $data ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_array( $data ) || is_object( $data ) ) {
                error_log( print_r( $data, true ) );
            } else {
                error_log( $data );
            }
        }
    }
    
    /**
     * Example: Get Current User IP
     */
    public static function get_client_ip() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}