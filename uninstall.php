<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
// Data tables are intentionally retained by default to prevent accidental loss of imported GOT master data.
delete_option('tkr_version');
