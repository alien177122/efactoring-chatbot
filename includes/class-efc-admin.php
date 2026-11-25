<?php
class EFC_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_post_efc_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'admin_post_efc_upload_qa', array( __CLASS__, 'upload_qa' ) );
        add_action( 'admin_post_efc_export_stats', array( __CLASS__, 'export_stats' ) );
        add_action( 'admin_post_efc_clear_stats', array( __CLASS__, 'clear_stats' ) );
        add_action( 'admin_post_efc_download_qa', array( __CLASS__, 'download_qa' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function menu() {
        add_menu_page(
            'Чат-бот факторинга',
            'Чат-бот',
            'manage_options',
            'efc-chatbot',
            array( __CLASS__, 'render_page' ),
            'dashicons-format-chat',
            58
        );
    }

    public static function register_settings() {
        // settings stored manually
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'efc-chatbot' ) === false ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    private static function tabs() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
        return $tab;
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Доступ запрещён.' );
        }
        $tab = self::tabs();
        $qa_count = count( EFC_Data::load_qa() );
        ?>
        <div class="wrap">
            <h1>Чат-бот для сайта (факторинг)</h1>
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=efc-chatbot&tab=settings' ) ); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">Настройки</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=efc-chatbot&tab=knowledge' ) ); ?>" class="nav-tab <?php echo $tab === 'knowledge' ? 'nav-tab-active' : ''; ?>">База знаний (<?php echo (int) $qa_count; ?>)</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=efc-chatbot&tab=stats' ) ); ?>" class="nav-tab <?php echo $tab === 'stats' ? 'nav-tab-active' : ''; ?>">Статистика</a>
            </nav>
            <div style="margin-top:16px;">
            <?php
            if ( $tab === 'knowledge' ) {
                self::render_knowledge();
            } elseif ( $tab === 'stats' ) {
                self::render_stats();
            } else {
                self::render_settings();
            }
            ?>
            </div>
        </div>
        <?php
    }

    private static function render_settings() {
        $o = get_option( 'efc_settings', array() );
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="efc_save_settings">
            <?php wp_nonce_field( 'efc_save_settings' ); ?>
            <table class="form-table">
                <tr><th>Включить виджет</th><td><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $o['enabled'] ) ); ?>></td></tr>
                <tr><th>Название</th><td><input type="text" name="title" value="<?php echo esc_attr( $o['title'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Приветствие (RU)</th><td><textarea name="greeting_ru" class="large-text" rows="2"><?php echo esc_textarea( $o['greeting_ru'] ?? '' ); ?></textarea></td></tr>
                <tr><th>Приветствие (KK)</th><td><textarea name="greeting_kk" class="large-text" rows="2"><?php echo esc_textarea( $o['greeting_kk'] ?? '' ); ?></textarea></td></tr>
                <tr><th>Плейсхолдер (RU)</th><td><input type="text" name="placeholder_ru" value="<?php echo esc_attr( $o['placeholder_ru'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Плейсхолдер (KK)</th><td><input type="text" name="placeholder_kk" value="<?php echo esc_attr( $o['placeholder_kk'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Кнопка менеджера (RU)</th><td><input type="text" name="manager_btn_ru" value="<?php echo esc_attr( $o['manager_btn_ru'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Кнопка менеджера (KK)</th><td><input type="text" name="manager_btn_kk" value="<?php echo esc_attr( $o['manager_btn_kk'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Нет ответа (RU)</th><td><textarea name="no_answer_ru" class="large-text" rows="2"><?php echo esc_textarea( $o['no_answer_ru'] ?? '' ); ?></textarea></td></tr>
                <tr><th>Нет ответа (KK)</th><td><textarea name="no_answer_kk" class="large-text" rows="2"><?php echo esc_textarea( $o['no_answer_kk'] ?? '' ); ?></textarea></td></tr>
                <tr><th>Кол-во быстрых подсказок</th><td><input type="number" name="quick_count" value="<?php echo esc_attr( $o['quick_count'] ?? 6 ); ?>" min="0" max="12"></td></tr>
                <tr><th>Основной цвет</th><td><input type="text" name="primary_color" value="<?php echo esc_attr( $o['primary_color'] ?? '#1f6feb' ); ?>" class="efc-color"></td></tr>
                <tr><th>URL логотипа</th><td><input type="url" name="logo_url" value="<?php echo esc_attr( $o['logo_url'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Позиция</th><td>
                    <select name="position">
                        <option value="right" <?php selected( ( $o['position'] ?? 'right' ), 'right' ); ?>>Справа</option>
                        <option value="left" <?php selected( ( $o['position'] ?? 'right' ), 'left' ); ?>>Слева</option>
                    </select>
                </td></tr>
                <tr><th>Email менеджера</th><td><input type="email" name="manager_email" value="<?php echo esc_attr( $o['manager_email'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Telegram bot token (опц.)</th><td><input type="text" name="telegram_token" value="<?php echo esc_attr( $o['telegram_token'] ?? '' ); ?>" class="regular-text"></td></tr>
                <tr><th>Telegram chat id (опц.)</th><td><input type="text" name="telegram_chat" value="<?php echo esc_attr( $o['telegram_chat'] ?? '' ); ?>" class="regular-text"></td></tr>
            </table>
            <?php submit_button( 'Сохранить' ); ?>
        </form>
        <script>
        jQuery(function($){ $('.efc-color').wpColorPicker(); });
        </script>
        <?php
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'efc_save_settings' );
        $o = get_option( 'efc_settings', array() );
        $fields = array( 'title','greeting_ru','greeting_kk','placeholder_ru','placeholder_kk','manager_btn_ru','manager_btn_kk','no_answer_ru','no_answer_kk','primary_color','logo_url','position','manager_email','telegram_token','telegram_chat' );
        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                if ( strpos( $f, 'email' ) !== false ) {
                    $o[ $f ] = sanitize_email( wp_unslash( $_POST[ $f ] ) );
                } elseif ( in_array( $f, array( 'no_answer_ru','no_answer_kk','greeting_ru','greeting_kk' ), true ) ) {
                    $o[ $f ] = sanitize_textarea_field( wp_unslash( $_POST[ $f ] ) );
                } else {
                    $o[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
                }
            }
        }
        $o['enabled'] = isset( $_POST['enabled'] );
        $o['quick_count'] = isset( $_POST['quick_count'] ) ? (int) $_POST['quick_count'] : 6;
        update_option( 'efc_settings', $o );
        wp_redirect( admin_url( 'admin.php?page=efc-chatbot&tab=settings&updated=1' ) );
        exit;
    }

    private static function render_knowledge() {
        $items = EFC_Data::load_qa();
        $upload = wp_upload_dir();
        $file = $upload['basedir'] . '/efactoring-chatbot/qa-source.xlsx';
        $has_source = file_exists( $upload['basedir'] . '/efactoring-chatbot/qa-source.csv' ) || file_exists( $upload['basedir'] . '/efactoring-chatbot/qa-source.xlsx' );
        ?>
        <h2>База знаний</h2>
        <p>Загружено пар вопрос–ответ: <strong><?php echo count( $items ); ?></strong></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="efc_upload_qa">
            <?php wp_nonce_field( 'efc_upload_qa' ); ?>
            <input type="file" name="qa_file" accept=".csv,.xlsx" required>
            <?php submit_button( 'Загрузить', 'primary', 'submit', false ); ?>
        </form>
        <p style="margin-top:12px;">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                <input type="hidden" name="action" value="efc_download_qa">
                <?php wp_nonce_field( 'efc_download_qa' ); ?>
                <button class="button">Скачать текущую базу (JSON)</button>
            </form>
        </p>
        <h3>Превью (первые 10)</h3>
        <table class="widefat striped">
            <thead><tr><th>Вопрос</th><th>Категория</th></tr></thead>
            <tbody>
            <?php foreach ( array_slice( $items, 0, 10 ) as $it ) : ?>
                <tr><td><?php echo esc_html( $it['q'] ); ?></td><td><?php echo esc_html( $it['category'] ); ?></td></tr>
            <?php endforeach; ?>
            <?php if ( empty( $items ) ) : ?>
                <tr><td colspan="2">База пуста. Загрузите CSV/XLSX.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:12px;color:#555;">Формат: колонки «вопрос», «ответ на русском», «ответ на казахском», «категория». Разделитель CSV — запятая или точка с запятой, кодировка UTF-8.</p>
        <?php
    }

    public static function upload_qa() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'efc_upload_qa' );
        if ( empty( $_FILES['qa_file'] ) ) {
            wp_die( 'Файл не передан.' );
        }
        $file = $_FILES['qa_file'];
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            wp_die( 'Ошибка загрузки.' );
        }
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
            wp_die( 'Допустимы только CSV и XLSX.' );
        }
        $items = EFC_Data::parse_file( $file['tmp_name'], $file['name'] );
        if ( is_wp_error( $items ) ) {
            wp_die( esc_html( $items->get_error_message() ) );
        }
        EFC_Data::save_qa( $items );
        // save source copy
        $upload = wp_upload_dir();
        $dir = $upload['basedir'] . '/efactoring-chatbot';
        if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
        $dest = $dir . '/qa-source.' . $ext;
        move_uploaded_file( $file['tmp_name'], $dest );
        wp_redirect( admin_url( 'admin.php?page=efc-chatbot&tab=knowledge&uploaded=' . count( $items ) ) );
        exit;
    }

    private static function render_stats() {
        global $wpdb;
        $top = EFC_Stats::top_questions( 20 );
        $unm = EFC_Stats::unmatched( 20 );
        $total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . EFC_Stats::table() );
        ?>
        <h2>Статистика</h2>
        <p>Всего обращений: <strong><?php echo $total; ?></strong></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-right:8px;">
            <input type="hidden" name="action" value="efc_export_stats">
            <input type="hidden" name="type" value="agg">
            <?php wp_nonce_field( 'efc_export_stats' ); ?>
            <button class="button">Выгрузить частые вопросы (CSV)</button>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-right:8px;">
            <input type="hidden" name="action" value="efc_export_stats">
            <input type="hidden" name="type" value="raw">
            <?php wp_nonce_field( 'efc_export_stats' ); ?>
            <button class="button">Выгрузить все обращения (CSV)</button>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Очистить статистику?');">
            <input type="hidden" name="action" value="efc_clear_stats">
            <?php wp_nonce_field( 'efc_clear_stats' ); ?>
            <button class="button">Очистить</button>
        </form>

        <h3>Топ вопросов</h3>
        <table class="widefat striped">
            <thead><tr><th>Вопрос</th><th>Язык</th><th>Кол-во</th><th>Последний раз</th></tr></thead>
            <tbody>
            <?php foreach ( $top as $r ) : ?>
                <tr><td><?php echo esc_html( $r['q'] ); ?></td><td><?php echo esc_html( $r['lang'] ); ?></td><td><?php echo (int) $r['cnt']; ?></td><td><?php echo esc_html( $r['last_at'] ); ?></td></tr>
            <?php endforeach; ?>
            <?php if ( empty( $top ) ) : ?><tr><td colspan="4">Нет данных.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <h3>Вопросы без ответа</h3>
        <table class="widefat striped">
            <thead><tr><th>Вопрос</th><th>Язык</th><th>Кол-во</th></tr></thead>
            <tbody>
            <?php foreach ( $unm as $r ) : ?>
                <tr><td><?php echo esc_html( $r['q'] ); ?></td><td><?php echo esc_html( $r['lang'] ); ?></td><td><?php echo (int) $r['cnt']; ?></td></tr>
            <?php endforeach; ?>
            <?php if ( empty( $unm ) ) : ?><tr><td colspan="3">Нет данных.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    public static function export_stats() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'efc_export_stats' );
        $type = isset( $_POST['type'] ) && $_POST['type'] === 'raw' ? 'raw' : 'agg';
        $csv = EFC_Stats::export_csv( $type );
        $filename = 'efc-stats-' . $type . '-' . date( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo $csv; // phpcs:ignore
        exit;
    }

    public static function clear_stats() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'efc_clear_stats' );
        EFC_Stats::clear();
        wp_redirect( admin_url( 'admin.php?page=efc-chatbot&tab=stats&cleared=1' ) );
        exit;
    }

    public static function download_qa() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'efc_download_qa' );
        $items = EFC_Data::load_qa();
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="qa.json"' );
        echo wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        exit;
    }
}