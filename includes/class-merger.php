<?php
/**
 * Класс для объединения профилей компаний
 *
 * @package StrapiCSVParser
 */

if (!defined('ABSPATH')) {
    exit;
}

class StrapiCSVParser_Merger {
    /**
     * API-клиент для работы со Strapi
     *
     * @var StrapiCSVParser_ApiClient
     */
    private $api_client;

    /**
     * Логгер для записи событий
     *
     * @var StrapiCSVParser_Logger
     */
    private $logger;

    /**
     * Конструктор класса
     */
    public function __construct() {
        $this->api_client = new StrapiCSVParser_ApiClient();
        $this->logger = new StrapiCSVParser_Logger();
    }

    /**
     * Объединение профилей компаний по номеру телефона
     *
     * @return array|WP_Error Результат объединения
     */
    public function merge_profiles() {
        $this->logger->log('info', 'Запуск объединения профилей по номеру телефона');
        
        // Отправляем запрос к API для объединения профилей
        $response = $this->api_client->merge_profiles_by_phone();
        
        if (is_wp_error($response)) {
            $this->logger->log('error', 'Ошибка при объединении профилей: ' . $response->get_error_message());
            return $response;
        }
        
        // Логируем результат
        $merged_count = isset($response['mergedCount']) ? intval($response['mergedCount']) : 0;
        $this->logger->log('info', "Объединение профилей завершено. Объединено профилей: {$merged_count}");
        
        return [
            'success' => true,
            'merged' => $merged_count,
            'details' => isset($response['details']) ? $response['details'] : []
        ];
    }

    /**
     * Объединение конкретных профилей по ID
     *
     * @param string $source_id ID профиля-источника
     * @param string $target_id ID целевого профиля
     * @return array|WP_Error Результат объединения
     */
    public function merge_specific_profiles($source_id, $target_id) {
        $this->logger->log('info', "Запуск объединения конкретных профилей: источник {$source_id}, цель {$target_id}");
        
        if (empty($source_id) || empty($target_id)) {
            return new WP_Error('invalid_ids', 'Необходимо указать ID обоих профилей');
        }
        
        // Отправляем запрос к API для объединения конкретных профилей
        $response = $this->api_client->send_request(
            "/api/company-profiles/merge/{$source_id}/{$target_id}", 
            [], 
            'POST'
        );
        
        if (is_wp_error($response)) {
            $this->logger->log('error', "Ошибка при объединении профилей {$source_id} и {$target_id}: " . $response->get_error_message());
            return $response;
        }
        
        $this->logger->log('info', "Успешно объединены профили: источник {$source_id}, цель {$target_id}");
        
        return [
            'success' => true,
            'source_id' => $source_id,
            'target_id' => $target_id,
            'result' => $response
        ];
    }

    /**
     * Поиск и получение дубликатов профилей по разным критериям
     *
     * @param string $type Тип поиска дубликатов (taxId, name, phone)
     * @return array|WP_Error Найденные дубликаты
     */
    public function find_duplicates($type = 'taxId') {
        $this->logger->log('info', "Поиск дубликатов по типу: {$type}");
        
        // Отправляем запрос к API для поиска дубликатов
        $response = $this->api_client->send_request("/api/company-profiles/duplicates?type={$type}");
        
        if (is_wp_error($response)) {
            $this->logger->log('error', "Ошибка при поиске дубликатов: " . $response->get_error_message());
            return $response;
        }
        
        // Получаем количество найденных дубликатов
        $count = isset($response['data']) ? count($response['data']) : 0;
        $this->logger->log('info', "Найдено {$count} дубликатов по типу {$type}");
        
        return [
            'success' => true,
            'count' => $count,
            'duplicates' => isset($response['data']) ? $response['data'] : []
        ];
    }

    /**
     * Получение статистики по профилям из разных источников
     *
     * @return array|WP_Error Статистика по источникам
     */
    public function get_source_statistics() {
        $this->logger->log('debug', 'Получение статистики по источникам данных');
        
        // Отправляем запрос к API для получения статистики по источникам
        $response = $this->api_client->send_request("/api/company-profiles/sources-stats");
        
        if (is_wp_error($response)) {
            $this->logger->log('error', "Ошибка при получении статистики по источникам: " . $response->get_error_message());
            return $response;
        }
        
        return [
            'success' => true,
            'stats' => $response
        ];
    }

    /**
     * Отображение страницы объединения профилей
     */
    public function merge_page() {
        // Получаем статистику по источникам для отображения
        $sources_stats = $this->get_source_statistics();
        
        // Проверяем на ошибки
        if (is_wp_error($sources_stats)) {
            $error_message = $sources_stats->get_error_message();
            $sources_stats = ['success' => false, 'error' => $error_message];
        }
        
        // Получаем дубликаты для отображения
        $duplicates_taxid = $this->find_duplicates('taxId');
        $duplicates_name = $this->find_duplicates('name');
        
        // Проверяем на ошибки
        if (is_wp_error($duplicates_taxid)) {
            $duplicates_taxid = ['success' => false, 'error' => $duplicates_taxid->get_error_message()];
        }
        
        if (is_wp_error($duplicates_name)) {
            $duplicates_name = ['success' => false, 'error' => $duplicates_name->get_error_message()];
        }
        
        // Отображаем страницу объединения
        include STRAPI_PARSER_PLUGIN_DIR . 'views/merge-page.php';
    }

    /**
     * Проверка и объединение конкретных профилей через AJAX
     */
    public function ajax_merge_specific_profiles() {
        check_ajax_referer('strapi-parser-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        // Получаем параметры
        $source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
        $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;
        
        if ($source_id <= 0 || $target_id <= 0) {
            wp_send_json_error('Неверные ID профилей');
        }
        
        // Выполняем объединение
        $result = $this->merge_specific_profiles($source_id, $target_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * AJAX обработчик для отображения дубликатов
     */
    public function ajax_get_duplicates() {
        check_ajax_referer('strapi-parser-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Недостаточно прав');
        }
        
        // Получаем тип поиска дубликатов
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'taxId';
        
        // Получаем дубликаты
        $duplicates = $this->find_duplicates($type);
        
        if (is_wp_error($duplicates)) {
            wp_send_json_error($duplicates->get_error_message());
        } else {
            wp_send_json_success($duplicates);
        }
    }
}
