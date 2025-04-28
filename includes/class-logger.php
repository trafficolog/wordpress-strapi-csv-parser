<?php
/**
 * Класс для логирования операций плагина
 *
 * @package StrapiCSVParser
 */

if (!defined('ABSPATH')) {
    exit;
}

class StrapiCSVParser_Logger {
    /**
     * Имя таблицы для хранения логов
     *
     * @var string
     */
    private $table_name;

    /**
     * Настройки уровней логирования
     *
     * @var array
     */
    private $log_levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];

    /**
     * Текущий уровень логирования
     *
     * @var string
     */
    private $current_level;

    /**
     * Максимальный размер лога в байтах
     *
     * @var int
     */
    private $max_log_size;

    /**
     * Максимальное количество дней хранения логов
     *
     * @var int
     */
    private $log_retention_days;

    /**
     * Конструктор класса
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'strapi_parser_logs';
        
        // Получаем настройки
        $settings = get_option('strapi_parser_settings', []);
        $this->current_level = isset($settings['log_level']) ? $settings['log_level'] : 'info';
        $this->max_log_size = 10 * 1024 * 1024; // 10 MB по умолчанию
        $this->log_retention_days = 30; // 30 дней по умолчанию
    }

    /**
     * Создание таблицы логов в базе данных
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            level varchar(10) NOT NULL,
            source varchar(50) DEFAULT '' NOT NULL,
            message text NOT NULL,
            context longtext,
            file_id varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY time (time),
            KEY source (source)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Проверяем успешность создания таблицы
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            error_log('Не удалось создать таблицу логов Strapi Parser');
        }
    }

    /**
     * Запись лога в базу данных
     *
     * @param string $level Уровень логирования (debug, info, warning, error)
     * @param string $message Сообщение лога
     * @param array $context Контекстная информация
     * @param string $source Источник события
     * @param string $file_id ID файла (для связывания с обрабатываемым файлом)
     * @return bool Успешность записи
     */
    public function log($level, $message, $context = [], $source = 'parser', $file_id = null) {
        // Проверяем, нужно ли логировать данный уровень
        if ($this->should_log($level) === false) {
            return false;
        }
        
        global $wpdb;
        
        // Форматируем контекстные данные как JSON
        $context_json = !empty($context) ? wp_json_encode($context) : null;
        
        // Вставляем запись в базу данных
        $result = $wpdb->insert(
            $this->table_name,
            [
                'time' => current_time('mysql'),
                'level' => $level,
                'source' => substr($source, 0, 50),
                'message' => $message,
                'context' => $context_json,
                'file_id' => $file_id
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
        );
        
        // Если запись не удалась, логируем ошибку в PHP error_log
        if ($result === false) {
            error_log('Strapi Parser: Ошибка при записи лога: ' . $wpdb->last_error);
        }
        
        // Раз в 100 записей очищаем старые логи
        if (wp_rand(0, 100) === 0) {
            $this->cleanup_logs();
        }
        
        return $result !== false;
    }

    /**
     * Определение, нужно ли логировать данный уровень
     *
     * @param string $level Уровень логирования
     * @return bool Нужно ли логировать
     */
    private function should_log($level) {
        if (!isset($this->log_levels[$level])) {
            return false;
        }
        
        return $this->log_levels[$level] >= $this->log_levels[$this->current_level];
    }

    /**
     * Очистка старых логов
     */
    public function cleanup_logs() {
        global $wpdb;
        
        // Удаляем логи старше указанного количества дней
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE time < %s",
                date('Y-m-d H:i:s', strtotime("-{$this->log_retention_days} days"))
            )
        );
        
        // Проверяем общий размер логов
        $total_size = $wpdb->get_var("SELECT SUM(LENGTH(message) + LENGTH(context)) FROM {$this->table_name}");
        
        // Если размер превышает максимальный, удаляем самые старые записи
        if ($total_size > $this->max_log_size) {
            $count_to_delete = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} ORDER BY time ASC LIMIT %d",
                    ceil(($total_size - $this->max_log_size) / 1000) // Примерная оценка количества записей для удаления
                )
            );
            
            if ($count_to_delete > 0) {
                $ids_to_delete = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT id FROM {$this->table_name} ORDER BY time ASC LIMIT %d",
                        $count_to_delete
                    )
                );
                
                if (!empty($ids_to_delete)) {
                    $wpdb->query(
                        "DELETE FROM {$this->table_name} WHERE id IN (" . implode(',', $ids_to_delete) . ")"
                    );
                }
            }
        }
    }

    /**
     * Запись успешного события
     *
     * @param array $row_data Данные из строки CSV
     * @param int $strapi_id ID созданной записи в Strapi
     * @param string $file_id ID обрабатываемого файла
     */
    public function log_success($row_data, $strapi_id, $file_id = null) {
        $entity_name = isset($row_data['Название']) ? $row_data['Название'] : 
                      (isset($row_data['Названия']) ? $row_data['Названия'] : 
                      (isset($row_data['name']) ? $row_data['name'] : 'неизвестно'));
        
        $this->log(
            'info',
            "Успешно создан профиль ID: {$strapi_id} для компании: {$entity_name}",
            [
                'strapi_id' => $strapi_id,
                'row_data' => $this->truncate_row_data($row_data)
            ],
            'parser',
            $file_id
        );
    }

    /**
     * Запись ошибки
     *
     * @param array $row_data Данные из строки CSV
     * @param string $error_message Сообщение об ошибке
     * @param string $file_id ID обрабатываемого файла
     */
    public function log_error($row_data, $error_message, $file_id = null) {
        $entity_name = isset($row_data['Название']) ? $row_data['Название'] : 
                      (isset($row_data['Названия']) ? $row_data['Названия'] : 
                      (isset($row_data['name']) ? $row_data['name'] : 'неизвестно'));
        
        $this->log(
            'error',
            "Ошибка создания профиля для компании: {$entity_name}. Причина: {$error_message}",
            [
                'error' => $error_message,
                'row_data' => $this->truncate_row_data($row_data)
            ],
            'parser',
            $file_id
        );
    }

    /**
     * Запись результатов объединения профилей
     *
     * @param array $result Результаты объединения
     */
    public function log_merge_results($result) {
        $this->log(
            'info',
            "Выполнено объединение профилей компаний по телефону. Объединено: " . ($result['mergedCount'] ?? 0),
            [
                'merged_count' => $result['mergedCount'] ?? 0,
                'details' => isset($result['details']) ? $this->truncate_array($result['details'], 20) : []
            ],
            'merger'
        );
    }

    /**
     * Логирование завершения обработки файла
     *
     * @param array $task_info Информация о задании
     */
    public function log_completion($task_info) {
        $this->log(
            'info',
            "Завершена обработка файла. Обработано записей: {$task_info['processed_rows']}, успешно: {$task_info['successful_rows']}, ошибок: {$task_info['failed_rows']}. Время обработки: " . ($task_info['end_time'] - $task_info['start_time']) . " сек.",
            $task_info,
            'parser',
            basename($task_info['file_path'], '.csv')
        );
    }

    /**
     * Получение логов с фильтрацией
     *
     * @param array $filters Параметры фильтрации
     * @param int $page Номер страницы
     * @param int $per_page Количество записей на страницу
     * @return array Логи и информация о пагинации
     */
    public function get_logs($filters = [], $page = 1, $per_page = 50) {
        global $wpdb;
        
        $where = [];
        $prepare_values = [];
        
        // Фильтрация по уровню логирования
        if (!empty($filters['level'])) {
            $where[] = "level = %s";
            $prepare_values[] = $filters['level'];
        }
        
        // Фильтрация по источнику
        if (!empty($filters['source'])) {
            $where[] = "source = %s";
            $prepare_values[] = $filters['source'];
        }
        
        // Фильтрация по файлу
        if (!empty($filters['file_id'])) {
            $where[] = "file_id = %s";
            $prepare_values[] = $filters['file_id'];
        }
        
        // Фильтрация по дате (начало периода)
        if (!empty($filters['date_from'])) {
            $where[] = "time >= %s";
            $prepare_values[] = $filters['date_from'];
        }
        
        // Фильтрация по дате (конец периода)
        if (!empty($filters['date_to'])) {
            $where[] = "time <= %s";
            $prepare_values[] = $filters['date_to'];
        }
        
        // Фильтрация по тексту сообщения
        if (!empty($filters['search'])) {
            $where[] = "message LIKE %s";
            $prepare_values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }
        
        // Собираем условие WHERE
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Общее количество записей
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($prepare_values)) {
            $total_query = $wpdb->prepare($total_query, $prepare_values);
        }
        $total = $wpdb->get_var($total_query);
        
        // Пагинация
        $offset = ($page - 1) * $per_page;
        $total_pages = ceil($total / $per_page);
        
        // Получение данных
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY time DESC LIMIT %d OFFSET %d";
        $prepare_values[] = $per_page;
        $prepare_values[] = $offset;
        $logs = $wpdb->get_results($wpdb->prepare($query, $prepare_values), ARRAY_A);
        
        // Декодируем контекст
        foreach ($logs as &$log) {
            if (!empty($log['context'])) {
                $log['context'] = json_decode($log['context'], true);
            } else {
                $log['context'] = null;
            }
        }
        
        return [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total_pages
            ]
        ];
    }

    /**
     * Отображение страницы логов в админке
     */
    public function logs_page() {
        // Получаем параметры фильтрации из запроса
        $filters = [
            'level' => isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '',
            'source' => isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '',
            'file_id' => isset($_GET['file_id']) ? sanitize_text_field($_GET['file_id']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''
        ];
        
        // Получаем номер страницы
        $page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
        $per_page = 50;
        
        // Получаем логи
        $logs_data = $this->get_logs($filters, $page, $per_page);
        
        // Отображаем страницу логов
        include STRAPI_PARSER_PLUGIN_DIR . 'views/logs-page.php';
    }

    /**
     * Преобразование полной строки данных для логирования (обрезка больших полей)
     *
     * @param array $row_data Данные строки
     * @return array Обрезанные данные
     */
    private function truncate_row_data($row_data) {
        $truncated = [];
        foreach ($row_data as $key => $value) {
            if (is_string($value) && strlen($value) > 200) {
                $truncated[$key] = substr($value, 0, 200) . '...';
            } else {
                $truncated[$key] = $value;
            }
        }
        return $truncated;
    }

    /**
     * Обрезка массива до указанного количества элементов
     *
     * @param array $array Массив для обрезки
     * @param int $max_items Максимальное количество элементов
     * @return array Обрезанный массив
     */
    private function truncate_array($array, $max_items = 10) {
        if (count($array) <= $max_items) {
            return $array;
        }
        
        return array_slice($array, 0, $max_items, true);
    }
}
