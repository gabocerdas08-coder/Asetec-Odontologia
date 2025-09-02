<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_H {
    public static function opt( $key, $default = null ) {
        $opts = get_option( 'asetec_odo_settings', [] );
        return $opts[ $key ] ?? $default;
    }
    public static function update_opts( $arr ) {
        $curr = get_option( 'asetec_odo_settings', [] );
        update_option( 'asetec_odo_settings', array_merge( $curr, $arr ) );
    }
    public static function tz() { return wp_timezone(); }
    public static function to_dt( $str ) {
        try { return new DateTime( $str, self::tz() ); } catch ( Exception $e ) { return null; }
    }
    public static function fmt( DateTime $dt, $fmt = 'Y-m-d H:i:s' ) {
        $dt->setTimezone( self::tz() );
        return $dt->format( $fmt );
    }
    public static function sanitize_bool( $v ) { return (bool) intval( $v ); }
}
