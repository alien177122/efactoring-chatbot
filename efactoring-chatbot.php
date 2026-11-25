<?php
/**
 * Plugin Name: Efactoring Site Chatbot
 * Plugin URI:  https://efactoring.kz
 * Description: Чат-бот для сайта: ответы на общие вопросы про факторинг (RU/KK). Источник знаний — таблица вопросов и ответов (CSV/XLSX), загружаемая заказчиком.
 * Version:     1.0.0
 * Author:      Efactoring
 * License:     GPL-2.0-or-later
 * Text Domain: efactoring-chatbot
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EFC_VERSION', '1.0.0' );
define( 'EFC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EFC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EFC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once EFC_PLUGIN_DIR . 'includes/class-efc-data.php';
require_once EFC_PLUGIN_DIR . 'includes/class-efc-stats.php';
require_once EFC_PLUGIN_DIR . 'includes/class-efc-ajax.php';
require_once EFC_PLUGIN_DIR . 'includes/class-efc-admin.php';

register_activation_hook( __FILE__, array( 'EFC_Data', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EFC_Data', 'deactivate' ) );

add_action( 'plugins_loaded', 'efc_init' );
function efc_init() {
    EFC_Stats::init();
    EFC_Ajax::init();
    if ( is_admin() ) {
        EFC_Admin::init();
    }
}

add_action( 'wp_enqueue_scripts', 'efc_enqueue_frontend' );
function efc_enqueue_frontend() {
    if ( ! efc_is_enabled() ) {
        return;
    }
    wp_enqueue_style( 'efc-chatbot', EFC_PLUGIN_URL . 'assets/css/chatbot.css', array(), EFC_VERSION );
    wp_enqueue_script( 'efc-chatbot', EFC_PLUGIN_URL . 'assets/js/chatbot.js', array(), EFC_VERSION, true );

    $upload = wp_upload_dir();
    $qa_url = $upload['baseurl'] . '/efactoring-chatbot/qa.json';
    $qa_path = $upload['basedir'] . '/efactoring-chatbot/qa.json';

    $config = array(
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'efc_nonce' ),
        'qaUrl'         => file_exists( $qa_path ) ? $qa_url : '',
        'lang'          => efc_detect_lang(),
        'title'         => efc_get_option( 'title', 'Чат-бот' ),
        'greeting'      => array(
            'ru' => efc_get_option( 'greeting_ru', 'Здравствуйте! Задайте вопрос о факторинге.' ),
            'kk' => efc_get_option( 'greeting_kk', 'Сәлеметсіз бе! Факторинг туралы сұрақ қойыңыз.' ),
        ),
        'placeholder'   => array(
            'ru' => efc_get_option( 'placeholder_ru', 'Введите вопрос...' ),
            'kk' => efc_get_option( 'placeholder_kk', 'Сұрағыңызды жазыңыз...' ),
        ),
        'managerBtn'    => array(
            'ru' => efc_get_option( 'manager_btn_ru', 'Связаться с менеджером' ),
            'kk' => efc_get_option( 'manager_btn_kk', 'Менеджерге хабарласу' ),
        ),
        'noAnswer'      => array(
            'ru' => efc_get_option( 'no_answer_ru', 'По этому вопросу точного ответа нет. Свяжитесь с менеджером.' ),
            'kk' => efc_get_option( 'no_answer_kk', 'Бұл сұрақ бойынша нақты жауап жоқ. Менеджерге хабарласыңыз.' ),
        ),
        'quickCount'    => (int) efc_get_option( 'quick_count', 6 ),
        'primaryColor'  => efc_get_option( 'primary_color', '#1f6feb' ),
        'logoUrl'       => efc_get_option( 'logo_url', '' ),
        'position'      => efc_get_option( 'position', 'right' ),
        'autoOpen'      => (bool) efc_get_option( 'auto_open', false ),
    );

    wp_localize_script( 'efc-chatbot', 'EFC_CONFIG', $config );
}

add_action( 'wp_footer', 'efc_render_widget_root' );
function efc_render_widget_root() {
    if ( ! efc_is_enabled() ) {
        return;
    }
    echo '<div id="efc-chatbot-root"></div>';
}

add_shortcode( 'efactoring_chatbot', 'efc_shortcode' );
function efc_shortcode( $atts ) {
    if ( ! efc_is_enabled() ) {
        return '';
    }
    return '<div id="efc-chatbot-root"></div>';
}

function efc_is_enabled() {
    return (bool) efc_get_option( 'enabled', true );
}

function efc_get_option( $key, $default = '' ) {
    $opts = get_option( 'efc_settings', array() );
    if ( is_array( $opts ) && array_key_exists( $key, $opts ) ) {
        return $opts[ $key ];
    }
    return $default;
}

function efc_update_option( $key, $value ) {
    $opts = get_option( 'efc_settings', array() );
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }
    $opts[ $key ] = $value;
    update_option( 'efc_settings', $opts );
}

function efc_detect_lang() {
    $lang = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ), 0, 2 ) : 'ru';
    // Prefer page language
    if ( function_exists( 'get_locale' ) ) {
        $loc = get_locale();
        if ( strpos( $loc, 'kk' ) === 0 || strpos( $loc, 'kz' ) === 0 ) {
            return 'kk';
        }
    }
    if ( $lang === 'kk' || $lang === 'kz' ) {
        return 'kk';
    }
    return 'ru';
}