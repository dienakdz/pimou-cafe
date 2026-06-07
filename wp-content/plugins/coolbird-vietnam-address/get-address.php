<?php
if (! defined('ABSPATH')) {
    exit;
}

$matp = '';
$maqh = '';

if (isset($_POST['matp'])) {
    $matp = sanitize_text_field(wp_unslash($_POST['matp']));
    $matp = preg_replace('/[^A-Za-z0-9_-]/', '', $matp);
}

if (isset($_POST['maqh'])) {
    $maqh = sanitize_text_field(wp_unslash($_POST['maqh']));
    $maqh = preg_replace('/\D+/', '', $maqh);
}

function coolviad_search_in_array($array, $key, $value)
{
    $results = array();

    if (is_array($array)) {
        if (isset($array[$key]) && $array[$key] == $value) {
            $results[] = $array;
        } elseif (isset($array[$key]) && is_serialized($array[$key]) && in_array($value, maybe_unserialize($array[$key]), true)) {
            $results[] = $array;
        }
        foreach ($array as $subarray) {
            $results = array_merge($results, coolviad_search_in_array($subarray, $key, $value));
        }
    }

    return $results;
}

function coolviad_natorder($a, $b)
{
    return strnatcasecmp($a['name'], $b['name']);
}

$result = array('success' => false);

if ($matp !== '') {
    include 'cities/districts.php';
    $quan = coolviad_search_in_array($quan_huyen, 'matp', $matp);
    usort($quan, 'coolviad_natorder');
    if ($quan) {
        $result = array(
            'success' => true,
            'data' => $quan
        );
    }
}

if ($maqh !== '') {
    include 'cities/wards.php';
    $id_xa = sprintf('%05d', (int) $maqh);
    $xa = coolviad_search_in_array($xa_phuong_thitran, 'maqh', $id_xa);
    usort($xa, 'coolviad_natorder');
    if ($xa) {
        $result = array(
            'success' => true,
            'data' => $xa
        );
    }
}

wp_send_json($result);
