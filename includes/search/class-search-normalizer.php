<?php
defined('ABSPATH') || exit;

class TKR_Search_Normalizer {
    public static function normalize($value) {
        $value = strtolower(wp_strip_all_tags((string) $value));
        $map = array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss');
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9\s-]/u', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
