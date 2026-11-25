<?php
class EFC_Stats {

    public static function init() {
        // nothing
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'efc_stats';
    }

    public static function log( $lang, $question, $matched, $matched_q = '', $category = '' ) {
        global $wpdb;
        $wpdb->insert(
            self::table(),
            array(
                'created_at'  => current_time( 'mysql' ),
                'lang'         => sanitize_text_field( $lang ),
                'question'     => sanitize_text_field( $question ),
                'matched'      => $matched ? 1 : 0,
                'matched_q'    => sanitize_text_field( $matched_q ),
                'category'     => sanitize_text_field( $category ),
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    public static function top_questions( $limit = 50 ) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT question AS q, COUNT(*) AS cnt, MAX(created_at) AS last_at, lang
             FROM {$table} WHERE matched=1 GROUP BY question, lang ORDER BY cnt DESC LIMIT %d",
            $limit
        ), ARRAY_A );
    }

    public static function unmatched( $limit = 50 ) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT question AS q, COUNT(*) AS cnt, MAX(created_at) AS last_at, lang
             FROM {$table} WHERE matched=0 GROUP BY question, lang ORDER BY cnt DESC LIMIT %d",
            $limit
        ), ARRAY_A );
    }

    public static function export_csv( $type = 'agg' ) {
        global $wpdb;
        $table = self::table();
        if ( $type === 'raw' ) {
            $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
            $header = array( 'id', 'created_at', 'lang', 'question', 'matched', 'matched_q', 'category' );
        } else {
            $rows = $wpdb->get_results(
                "SELECT question, lang, matched, matched_q, category, COUNT(*) AS count, MAX(created_at) AS last_at
                 FROM {$table} GROUP BY question, lang, matched, matched_q, category ORDER BY count DESC",
                ARRAY_A
            );
            $header = array( 'question', 'lang', 'matched', 'matched_q', 'category', 'count', 'last_at' );
        }
        $out = fopen( 'php://temp', 'r+' );
        // BOM for Excel
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, $header, ';' );
        foreach ( $rows as $r ) {
            fputcsv( $out, array_values( $r ), ';' );
        }
        rewind( $out );
        $csv = stream_get_contents( $out );
        fclose( $out );
        return $csv;
    }

    public static function clear() {
        global $wpdb;
        $wpdb->query( 'TRUNCATE TABLE ' . self::table() );
    }
}