<?php
/**
 * Класс для работы с настройками плагина
 */
class StrapiParserSettings {

    /**
     * Имя опции в базе данных
     */
    private $option_name = 'strapi_parser_settings';

    /**
     * Настройки по умолчанию
     */
    private $default_settings = array(
        'strapi_url' => 'https://example.com/api',
        'api_token' => '',
        'batch_size' => 50,
        'timeout' => 120,
        'debug' => false
    );

    /**
     * Установка настроек по умолчанию
     */
    public function set_default_settings() {
        if (!get_option($this->option_name)) {
            add_option($this->option_name, $this->default_settings);
        }
    }

    /**
     * Получение всех настроек
     */
    public function get_all_settings() {
        return get_option($this->option_name, $this->default_settings);
    }

    /**
     * Получение конкретной настройки
     */
    public function get_setting($key) {
        $settings = $this->get_all_settings();
        return isset($settings[$key]) ? $settings[$key] : null;
    }

    /**
     * Обновление настроек
     */
    public function update_settings($new_settings) {
        $settings = $this->get_all_settings();
        $updated_settings = array_merge($settings, $new_settings);
        update_option($this->option_name, $updated_settings);
        return $updated_settings;
    }

    /**
     * Страница настроек
     */
    public function settings_page() {
        // Обработка формы
        if (isset($_POST['strapi_parser_settings_nonce']) && wp_verify_nonce($_POST['strapi_parser_settings_nonce'], 'strapi_parser_settings')) {
            $new_settings = array(
                'strapi_url' => sanitize_text_field($_POST['strapi_url']),
                'api_token' => sanitize_text_field($_POST['api_token']),
                'batch_size' => intval($_POST['batch_size']),
                'timeout' => intval($_POST['timeout']),
                'debug' => isset($_POST['debug'])
            );
            $this->update_settings($new_settings);
            
            echo '<div class="notice notice-success is-dismissible"><p>Настройки успешно сохранены.</p></div>';
        }

        $settings = $this->get_all_settings();
        include STRAPI_PARSER_PLUGIN_DIR . 'views/settings-page.php';
    }
}
