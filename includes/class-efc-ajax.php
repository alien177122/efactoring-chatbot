<?php
class EFC_Ajax {

    public static function init() {
        add_action( 'wp_ajax_efc_log', array( __CLASS__, 'log' ) );
        add_action( 'wp_ajax_nopriv_efc_log', array( __CLASS__, 'log' ) );
        add_action( 'wp_ajax_efc_contact', array( __CLASS__, 'contact' ) );
        add_action( 'wp_ajax_nopriv_efc_contact', array( __CLASS__, 'contact' ) );
    }

    private static function check_nonce() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'efc_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Неверный токен.' ), 403 );
            exit;
        }
    }

    public static function log() {
        self::check_nonce();
        $lang = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : 'ru';
        $question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
        $matched = isset( $_POST['matched'] ) ? (int) $_POST['matched'] : 0;
        $matched_q = isset( $_POST['matched_q'] ) ? sanitize_text_field( wp_unslash( $_POST['matched_q'] ) ) : '';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        EFC_Stats::log( $lang, $question, $matched, $matched_q, $category );
        wp_send_json_success( array( 'ok' => true ) );
    }

    public static function contact() {
        self::check_nonce();
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $contact = isset( $_POST['contact'] ) ? sanitize_text_field( wp_unslash( $_POST['contact'] ) ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $history = isset( $_POST['history'] ) ? sanitize_textarea_field( wp_unslash( $_POST['history'] ) ) : '';

        $to = efc_get_option( 'manager_email', get_option( 'admin_email' ) );
        $subject = 'Обращение из чат-бота сайта';
        $body = "Имя: {$name}\nКонтакт: {$contact}\n\nСообщение:\n{$message}\n\n--- История диалога ---\n{$history}";
        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        $sent = wp_mail( $to, $subject, $body, $headers );

        // Telegram (optional)
        $token = efc_get_option( 'telegram_token', '' );
        $chat = efc_get_option( 'telegram_chat', '' );
        if ( $token && $chat ) {
            $tg_text = "Чат-бот: новое обращение\nИмя: {$name}\nКонтакт: {$contact}\nСообщение: {$message}";
            wp_remote_post( "https://api.telegram.org/bot{$token}/sendMessage", array(
                'body' => array( 'chat_id' => $chat, 'text' => $tg_text ),
                'timeout' => 10,
            ) );
        }

        if ( $sent ) {
            wp_send_json_success( array( 'message' => 'Отправлено.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Не удалось отправить. Попробуйте позже.' ), 500 );
        }
    }
}