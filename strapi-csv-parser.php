<?php
/**
 * Plugin Name: Strapi CSV Parser
 * Plugin URI: https://example.com/strapi-csv-parser
 * Description: Парсер CSV данных из YandexDirectories и SearchBase с отправкой в Strapi API
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: strapi-csv-parser
 * Domain Path: /languages
 */

// Если вызван напрямую - выходим
if (!defined('ABSPATH')) {
    exit;
}

// Определяем константы
define('STRAPI_PARSER_VERSION', '1.0.0');
define('STRAPI_PARSER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STRAPI_PARSER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STRAPI_PARSER_ADMIN_URL', get_admin_url(null, 'admin.php?page=strapi-csv-parser'));
define('STRAPI_PARSER_MAX_BATCH_SIZE', 100); // Количество записей для обработки за раз

// Подключение основных файлов
require_once STRAPI_PARSER_PLUGIN_DIR . 'includes/class-settings.php';
require_once STRAPI_PARSER_PLUGIN_DIR . 'includes/class-csv-parser.php';
require_once STRAPI_PARSER_PLUGIN_DIR . 'includes/class-api-client.php';
require_once STRAPI_PARSER_PLUGIN_DIR . 'includes/class-logger.php';
require_once STRAPI_PARSER_PLUGIN_DIR . 'includes/class-merger.php';

/**
 * Основной класс плагина
 */
class StrapiCSVParser {

    /**
     * Экземпляр класса (singleton)
     */
    private static $instance = null;

    /**
     * Объект настроек
     */
    public $settings;

    /**
     * Объект парсера CSV
     */
    public $parser;

    /**
     * Объект API-клиента для Strapi
     */
    public $api_client;

    /**
     * Объект логгера
     */
    public $logger;

    /**
     * Объект для объединения профилей
     */
    public $merger;

    /**
     * Инициализация плагина
     */
    private function __construct() {
        // Инициализация объектов
        $this->settings = new StrapiParserSettings();
        $this->parser = new StrapiCSVParser_Parser();
        $this->api_client = new StrapiCSVParser_ApiClient();
        $this->logger = new StrapiCSVParser_Logger();
        $this->merger = new StrapiCSVParser_Merger();

        // Хуки активации и деактивации
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));

        // Инициализация админки
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Регистрируем AJAX обработчики
        add_action('wp_ajax_strapi_parser_upload_csv', array($this, 'ajax_upload_csv'));
        add_action('wp_ajax_strapi_parser_merge_profiles', array($this, 'ajax_merge_profiles'));
        add_action('wp_ajax_strapi_parser_get_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_strapi_parser_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_strapi_parser_test_connection', array($this, 'strapi_parser_clear_logs'));
        add_action('wp_ajax_strapi_parser_test_connection', array($this, 'strapi_parser_clear_temp'));
        add_action('wp_ajax_strapi_parser_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_strapi_parser_get_industries_tree', array($this, 'ajax_get_industries_tree'));
    }

    /**
     * Получение экземпляра класса (singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Активация плагина
     */
    public function activate_plugin() {
        // Создаем таблицы для логирования и кеширования
        $this->logger->create_tables();

        // Создаем директории для временных файлов
        wp_mkdir_p(STRAPI_PARSER_PLUGIN_DIR . 'temp');
        wp_mkdir_p(STRAPI_PARSER_PLUGIN_DIR . 'logs');
        
        // Устанавливаем настройки по умолчанию
        $this->settings->set_default_settings();
    }

    /**
     * Деактивация плагина
     */
    public function deactivate_plugin() {
        // Очистка временных файлов
        $this->parser->clear_temp_files();
    }

    /**
     * Добавление пунктов меню в админку
     */
    public function add_admin_menu() {
        add_menu_page(
            'Strapi CSV Parser',
            'Strapi Parser',
            'manage_options',
            'strapi-csv-parser',
            array($this, 'admin_page'),
            'dashicons-database-import',
            30
        );

        add_submenu_page(
            'strapi-csv-parser',
            'Настройки парсера',
            'Настройки',
            'manage_options',
            'strapi-csv-parser-settings',
            array($this->settings, 'settings_page')
        );

        add_submenu_page(
            'strapi-csv-parser',
            'Журнал событий',
            'Журнал',
            'manage_options',
            'strapi-csv-parser-logs',
            array($this->logger, 'logs_page')
        );

        add_submenu_page(
            'strapi-csv-parser',
            'Объединение профилей компаний',
            'Объединение профилей',
            'manage_options',
            'strapi-csv-parser-merge',
            array($this->merger, 'merge_page')
        );

        // Добавляем страницу отладки
        add_submenu_page(
          'strapi-csv-parser',
          'Отладка парсера',
          'Отладка',
          'manage_options',
          'strapi-csv-parser-debug',
          array($this, 'debug_page')
        );
    }

    /**
     * Подключение скриптов для админки
     */
    public function enqueue_admin_scripts($hook) {
        $pages = array(
            'toplevel_page_strapi-csv-parser',
            'strapi-parser_page_strapi-csv-parser-settings',
            'strapi-parser_page_strapi-csv-parser-logs'
        );

        if (!in_array($hook, $pages)) {
            return;
        }

        // Подключаем CSS
        wp_enqueue_style(
            'strapi-parser-admin',
            STRAPI_PARSER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            STRAPI_PARSER_VERSION
        );

        // Подключаем JS
        wp_enqueue_script(
            'strapi-parser-admin',
            STRAPI_PARSER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            STRAPI_PARSER_VERSION,
            true
        );

        // Передаем данные в JS
        wp_localize_script('strapi-parser-admin', 'strapiParser', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('strapi-parser-nonce'),
            'maxBatchSize' => STRAPI_PARSER_MAX_BATCH_SIZE,
            'texts' => array(
                'processing' => 'Обработка...',
                'success' => 'Успешно',
                'error' => 'Ошибка',
                'confirm' => 'Вы уверены?'
            )
        ));
    }

    /**
     * Главная страница плагина
     */
    public function admin_page() {
        include STRAPI_PARSER_PLUGIN_DIR . 'views/admin-page.php';
    }

    // Добавьте метод для отображения отладочной страницы
    public function debug_page() {
      include STRAPI_PARSER_PLUGIN_DIR . 'views/debug-page.php';
    }

    /**
     * AJAX обработчик для загрузки CSV
     */
    public function ajax_upload_csv() {
        check_ajax_referer('strapi-parser-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }

        // Обработка загрузки файла
        $result = $this->parser->upload_csv($_FILES['csv_file']);

        if (is_wp_error($result)) {
            $this->logger->log('error', 'Ошибка загрузки файла: ' . $result->get_error_message());
            wp_send_json_error($result->get_error_message());
        } else {
            $this->logger->log('info', 'Файл успешно загружен: ' . $result['filename']);
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX обработчик для получения категорий из Strapi
     */
    public function ajax_get_categories() {
      check_ajax_referer('strapi-parser-nonce', 'nonce');
      
      if (!current_user_can('manage_options')) {
          wp_send_json_error('Недостаточно прав');
      }
      
      $categories = $this->api_client->get_categories();
      
      if (is_wp_error($categories)) {
          wp_send_json_error($categories->get_error_message());
      } else {
          wp_send_json_success($categories);
      }
    }

    /**
     * AJAX-обработчик для получения дерева категорий
     */
    public function ajax_get_industries_tree() {
      // Проверка безопасности
      check_ajax_referer('strapi-parser-nonce', 'nonce');
      
      if (!current_user_can('manage_options')) {
          wp_send_json_error('Недостаточно прав доступа');
      }
      
      // Получаем дерево категорий
      $industries = $this->api_client->get_industries_tree();
      
      if (is_wp_error($industries)) {
          wp_send_json_error($industries->get_error_message());
      } else {
          wp_send_json_success($industries);
      }
    }

    /**
     * AJAX обработчик для объединения профилей
     */
    public function ajax_merge_profiles() {
        check_ajax_referer('strapi-parser-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }

        $result = $this->merger->merge_profiles();

        if (is_wp_error($result)) {
            $this->logger->log('error', 'Ошибка объединения профилей: ' . $result->get_error_message());
            wp_send_json_error($result->get_error_message());
        } else {
            $this->logger->log('info', "Объединено профилей: {$result['merged']}");
            wp_send_json_success($result);
        }
    }

    /**
     * AJAX обработчик для проверки подключения к Strapi API
     */
    public function ajax_test_connection() {
      check_ajax_referer('strapi-parser-nonce', 'nonce');
      
      if (!current_user_can('manage_options')) {
          wp_send_json_error('Недостаточно прав');
      }
      
      // Проверяем подключение к API
      $result = $this->api_client->test_connection();
      
      if (is_wp_error($result)) {
          wp_send_json_error($result->get_error_message());
      } else {
          wp_send_json_success($result);
      }
    }

    /**
     * AJAX обработчик для получения прогресса
     */
    public function ajax_get_progress() {
        check_ajax_referer('strapi-parser-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }

        $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
        
        if (empty($file_id)) {
            wp_send_json_error('Не указан ID файла');
        }

        $progress = get_option('strapi_parser_progress_' . $file_id, array(
            'processed' => 0,
            'total' => 0,
            'status' => 'pending',
            'message' => 'Подготовка к обработке'
        ));

        wp_send_json_success($progress);
    }

    /**
     * AJAX обработчик для очистки логов
     */
    public function ajax_clear_logs() {
      check_ajax_referer('strapi-parser-nonce', 'nonce');
      
      if (!current_user_can('manage_options')) {
          wp_send_json_error('Недостаточно прав');
      }
      
      global $wpdb;
      $table_name = $wpdb->prefix . 'strapi_parser_logs';
      
      // Очищаем таблицу логов
      $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
      
      if ($result !== false) {
          $this->logger->log('info', 'Логи очищены администратором', [], 'system');
          wp_send_json_success(['message' => 'Логи успешно очищены']);
      } else {
          wp_send_json_error('Ошибка при очистке логов');
      }
    }

    /**
    * AJAX обработчик для очистки временных файлов
    */
    public function ajax_clear_temp() {
      check_ajax_referer('strapi-parser-nonce', 'nonce');
      
      if (!current_user_can('manage_options')) {
          wp_send_json_error('Недостаточно прав');
      }
      
      // Очищаем временные файлы
      $result = $this->parser->clear_temp_files();
      
      if ($result) {
          $this->logger->log('info', 'Временные файлы очищены администратором', [], 'system');
          wp_send_json_success(['message' => 'Временные файлы успешно удалены']);
      } else {
          wp_send_json_error('Ошибка при удалении временных файлов');
      }
    }

}

// Запуск плагина
function strapi_csv_parser() {
    return StrapiCSVParser::get_instance();
}

strapi_csv_parser();
