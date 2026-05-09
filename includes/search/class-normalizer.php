<?php
defined( 'ABSPATH' ) || exit;

class TKR_Normalizer {

    public static function normalize( string $input ): string {
        $s = mb_strtolower( trim( $input ), 'UTF-8' );

        $s = str_replace( [ 'ä', 'Ä' ], 'ae', $s );
        $s = str_replace( [ 'ö', 'Ö' ], 'oe', $s );
        $s = str_replace( [ 'ü', 'Ü' ], 'ue', $s );
        $s = str_replace( 'ß', 'ss', $s );

        $s = preg_replace( '/[^a-z0-9\s]/u', ' ', $s );
        $s = preg_replace( '/\s+/', ' ', $s );

        return trim( $s );
    }
}
