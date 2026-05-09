<?php
defined( 'ABSPATH' ) || exit;

class TKR_Normalizer {

    private static array $umlaut_map = [
        'ae' => ['ä', 'Ä'],
        'oe' => ['ö', 'Ö'],
        'ue' => ['ü', 'Ü'],
        'ss' => ['ß'],
    ];

    public static function normalize( string $input ): string {
        $s = mb_strtolower( trim( $input ), 'UTF-8' );

        // Replace German umlauts with ASCII equivalents for matching
        $s = str_replace( ['ä','Ä'], 'ae', $s );
        $s = str_replace( ['ö','Ö'], 'oe', $s );
        $s = str_replace( ['ü','Ü'], 'ue', $s );
        $s = str_replace( 'ß', 'ss', $s );

        // Remove characters that are not letters, digits, or spaces
        $s = preg_replace( '/[^a-z0-9\s]/u', ' ', $s );

        // Collapse multiple spaces
        $s = preg_replace( '/\s+/', ' ', $s );

        return trim( $s );
    }
}
