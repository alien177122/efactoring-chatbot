<?php
class EFC_Data {

    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . 'efc_stats';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            lang varchar(4) NOT NULL DEFAULT 'ru',
            question text NOT NULL,
            matched tinyint(1) NOT NULL DEFAULT 0,
            matched_q text NULL,
            category varchar(191) NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $upload = wp_upload_dir();
        $dir = $upload['basedir'] . '/efactoring-chatbot';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        if ( ! get_option( 'efc_settings' ) ) {
            add_option( 'efc_settings', array(
                'enabled'        => true,
                'title'          => 'Чат-бот',
                'greeting_ru'    => 'Здравствуйте! Задайте вопрос о факторинге.',
                'greeting_kk'    => 'Сәлеметсіз бе! Факторинг туралы сұрақ қойыңыз.',
                'placeholder_ru' => 'Введите вопрос...',
                'placeholder_kk' => 'Сұрағыңызды жазыңыз...',
                'manager_btn_ru' => 'Связаться с менеджером',
                'manager_btn_kk' => 'Менеджерге хабарласу',
                'no_answer_ru'   => 'По этому вопросу точного ответа нет. Свяжитесь с менеджером.',
                'no_answer_kk'   => 'Бұл сұрақ бойынша нақты жауап жоқ. Менеджерге хабарласыңыз.',
                'quick_count'    => 6,
                'primary_color'  => '#1f6feb',
                'logo_url'       => '',
                'position'       => 'right',
                'manager_email'  => get_option( 'admin_email' ),
                'telegram_token' => '',
                'telegram_chat'  => '',
            ) );
        }
    }

    public static function deactivate() {
        // keep data
    }

    public static function get_qa_path() {
        $upload = wp_upload_dir();
        return $upload['basedir'] . '/efactoring-chatbot/qa.json';
    }

    public static function get_qa_url() {
        $upload = wp_upload_dir();
        return $upload['baseurl'] . '/efactoring-chatbot/qa.json';
    }

    public static function load_qa() {
        $path = self::get_qa_path();
        if ( ! file_exists( $path ) ) {
            return array();
        }
        $json = file_get_contents( $path );
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : array();
    }

    public static function save_qa( $items ) {
        $path = self::get_qa_path();
        $dir = dirname( $path );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        file_put_contents( $path, wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    }

    /**
     * Parse uploaded file (CSV or XLSX) into array of items.
     */
    public static function parse_file( $tmp_path, $filename ) {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( $ext === 'csv' ) {
            return self::parse_csv( $tmp_path );
        }
        if ( $ext === 'xlsx' ) {
            return self::parse_xlsx( $tmp_path );
        }
        return new WP_Error( 'bad_format', 'Поддерживаются только CSV и XLSX.' );
    }

    private static function normalize_header( $h ) {
        $h = trim( (string) $h );
        $h = mb_strtolower( $h, 'UTF-8' );
        return $h;
    }

    private static function map_headers( $headers ) {
        $map = array( 'q' => -1, 'a_ru' => -1, 'a_kk' => -1, 'category' => -1 );
        foreach ( $headers as $i => $h ) {
            $nh = self::normalize_header( $h );
            if ( $map['q'] < 0 && ( strpos( $nh, 'вопрос' ) !== false || $nh === 'question' || $nh === 'q' ) ) {
                $map['q'] = $i;
            } elseif ( $map['a_ru'] < 0 && ( strpos( $nh, 'рус' ) !== false || strpos( $nh, 'ru' ) !== false || $nh === 'answer_ru' || $nh === 'ответ на русском' ) ) {
                $map['a_ru'] = $i;
            } elseif ( $map['a_kk'] < 0 && ( strpos( $nh, 'каз' ) !== false || strpos( $nh, 'kk' ) !== false || strpos( $nh, 'kz' ) !== false || $nh === 'answer_kk' || $nh === 'ответ на казахском' ) ) {
                $map['a_kk'] = $i;
            } elseif ( $map['category'] < 0 && ( strpos( $nh, 'категор' ) !== false || $nh === 'category' ) ) {
                $map['category'] = $i;
            }
        }
        // fallback by position
        if ( $map['q'] < 0 ) $map['q'] = 0;
        if ( $map['a_ru'] < 0 ) $map['a_ru'] = 1;
        if ( $map['a_kk'] < 0 ) $map['a_kk'] = 2;
        if ( $map['category'] < 0 ) $map['category'] = 3;
        return $map;
    }

    private static function parse_csv( $path ) {
        $items = array();
        $fh = fopen( $path, 'r' );
        if ( ! $fh ) {
            return new WP_Error( 'read_error', 'Не удалось прочитать файл.' );
        }
        // detect BOM
        $bom = fread( $fh, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $fh );
        }
        $delimiter = ',';
        $first = fgets( $fh );
        rewind( $fh );
        if ( $bom === "\xEF\xBB\xBF" ) {
            // skip bom already consumed? reopen
        }
        // reopen cleanly
        fclose( $fh );
        $fh = fopen( $path, 'r' );
        $bom = fread( $fh, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $fh );
        }
        // detect delimiter
        $sample = fgets( $fh );
        rewind( $fh );
        if ( $bom === "\xEF\xBB\xBF" ) {
            // skip bom
            fread( $fh, 3 );
        }
        if ( substr_count( $sample, ';' ) > substr_count( $sample, ',' ) ) {
            $delimiter = ';';
        }
        // read header
        $header = fgetcsv( $fh, 0, $delimiter );
        if ( ! $header ) {
            fclose( $fh );
            return $items;
        }
        $map = self::map_headers( $header );
        while ( ( $row = fgetcsv( $fh, 0, $delimiter ) ) !== false ) {
            if ( count( $row ) === 1 && trim( implode( '', $row ) ) === '' ) {
                continue;
            }
            $q = isset( $row[ $map['q'] ] ) ? trim( $row[ $map['q'] ] ) : '';
            if ( $q === '' ) {
                continue;
            }
            $items[] = array(
                'q'        => $q,
                'a_ru'     => isset( $row[ $map['a_ru'] ] ) ? trim( $row[ $map['a_ru'] ] ) : '',
                'a_kk'     => isset( $row[ $map['a_kk'] ] ) ? trim( $row[ $map['a_kk'] ] ) : '',
                'category' => isset( $row[ $map['category'] ] ) ? trim( $row[ $map['category'] ] ) : '',
            );
        }
        fclose( $fh );
        return $items;
    }

    private static function parse_xlsx( $path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'no_zip', 'ZipArchive не доступен на сервере.' );
        }
        $zip = new ZipArchive();
        if ( $zip->open( $path ) !== true ) {
            return new WP_Error( 'open_error', 'Не удалось открыть XLSX.' );
        }
        // shared strings
        $shared = array();
        $ss = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $ss !== false ) {
            $xml = @simplexml_load_string( $ss );
            if ( $xml ) {
                foreach ( $xml->si as $si ) {
                    $text = '';
                    if ( isset( $si->t ) ) {
                        $text = (string) $si->t;
                    } else {
                        foreach ( $si->r as $r ) {
                            $text .= (string) $r->t;
                        }
                    }
                    $shared[] = $text;
                }
            }
        }
        // find first sheet
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $sheetXmlStr = $zip->getFromName( $sheetPath );
        if ( $sheetXmlStr === false ) {
            // try workbook rels
            $zip->close();
            return new WP_Error( 'no_sheet', 'Не найден лист в XLSX.' );
        }
        $sheetXml = @simplexml_load_string( $sheetXmlStr );
        $zip->close();
        if ( ! $sheetXml ) {
            return new WP_Error( 'parse_error', 'Не удалось разобрать XLSX.' );
        }

        // Parse rows into arrays of cell values
        $rows = array();
        foreach ( $sheetXml->sheetData->row as $row ) {
            $cells = array();
            $maxCol = 0;
            foreach ( $row->c as $c ) {
                $ref = (string) $c['r'];
                preg_match( '/^([A-Z]+)/', $ref, $m );
                $col = self::col_to_index( $m[1] );
                $val = '';
                $type = (string) $c['t'];
                if ( $type === 's' ) {
                    $idx = (int) $c->v;
                    $val = isset( $shared[ $idx ] ) ? $shared[ $idx ] : '';
                } elseif ( $type === 'inlineStr' ) {
                    $val = (string) $c->is->t;
                } else {
                    $val = (string) $c->v;
                }
                $cells[ $col ] = $val;
                if ( $col > $maxCol ) $maxCol = $col;
            }
            $rowArr = array();
            for ( $i = 0; $i <= $maxCol; $i++ ) {
                $rowArr[] = isset( $cells[ $i ] ) ? $cells[ $i ] : '';
            }
            $rows[] = $rowArr;
        }

        if ( empty( $rows ) ) {
            return array();
        }
        $header = array_shift( $rows );
        $map = self::map_headers( $header );
        $items = array();
        foreach ( $rows as $row ) {
            $q = isset( $row[ $map['q'] ] ) ? trim( $row[ $map['q'] ] ) : '';
            if ( $q === '' ) continue;
            $items[] = array(
                'q'        => $q,
                'a_ru'     => isset( $row[ $map['a_ru'] ] ) ? trim( $row[ $map['a_ru'] ] ) : '',
                'a_kk'     => isset( $row[ $map['a_kk'] ] ) ? trim( $row[ $map['a_kk'] ] ) : '',
                'category' => isset( $row[ $map['category'] ] ) ? trim( $row[ $map['category'] ] ) : '',
            );
        }
        return $items;
    }

    private static function col_to_index( $col ) {
        $col = strtoupper( $col );
        $index = 0;
        for ( $i = 0; $i < strlen( $col ); $i++ ) {
            $index = $index * 26 + ( ord( $col[ $i ] ) - ord( 'A' ) + 1 );
        }
        return $index - 1;
    }
}