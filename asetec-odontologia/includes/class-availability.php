<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Availability {
    public static function get_slots_for_date( string $ymd ): array {
        $tz = ASETEC_ODO_H::tz();
        $today = new DateTime( 'now', $tz );
        $date  = ASETEC_ODO_H::to_dt( $ymd . ' 00:00:00' );
        if ( ! $date ) return [];

        $blocked = ASETEC_ODO_H::opt('blocked_dates', []);
        if ( in_array( $ymd, $blocked, true ) ) return [];

        $min_hours = intval( ASETEC_ODO_H::opt('min_hours_notice', 2 ) );
        $min_dt    = (clone $today)->modify("+{$min_hours} hours");

        $w = intval( $date->format('N') );
        $schedule = ASETEC_ODO_H::opt('schedule', []);
        $tramos   = $schedule[ strval($w) ] ?? [];

        $slots = [];
        foreach ( $tramos as $tr ) {
            $duration = max(5, intval( $tr['duration'] ?? 40 ));
            $start    = ASETEC_ODO_H::to_dt( $ymd . ' ' . ($tr['start'] ?? '08:00') );
            $end      = ASETEC_ODO_H::to_dt( $ymd . ' ' . ($tr['end'] ?? '17:00') );
            if ( ! $start || ! $end || $end <= $start ) continue;

            $cursor = clone $start;
            while ( $cursor < $end ) {
                $s = clone $cursor;
                $f = (clone $s)->modify("+{$duration} minutes");
                if ( $f > $end ) break;

                if ( $s < $min_dt ) { $cursor = $f; continue; }
                if ( self::slot_overlaps_appointments( $s, $f ) ) { $cursor = $f; continue; }

                $slots[] = [
                    'start' => ASETEC_ODO_H::fmt($s, 'Y-m-d H:i'),
                    'end'   => ASETEC_ODO_H::fmt($f, 'Y-m-d H:i'),
                    'duration' => $duration,
                ];
                $cursor = $f;
            }
        }
        return $slots;
    }

    public static function slot_overlaps_appointments( DateTime $s, DateTime $f ): bool {
        $q = new WP_Query( [
            'post_type' => 'cita_odontologia',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => 'estado', 'value' => [ 'pendiente', 'aprobada' ], 'compare' => 'IN' ],
                [ 'key' => 'fecha_hora_inicio', 'compare' => '<', 'value' => ASETEC_ODO_H::fmt($f) ],
                [ 'key' => 'fecha_hora_fin',    'compare' => '>', 'value' => ASETEC_ODO_H::fmt($s) ],
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ] );
        return $q->have_posts();
    }
}
