<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Класс для работы с CSV файлами
 *
 * @package StrapiCSVParser
 */

if (!defined('ABSPATH')) {
    exit;
}

class StrapiCSVParser_Parser {
    /**
     * Директория для временных файлов
     *
     * @var string
     */
    private $temp_dir;

    /**
     * Размер буфера для чтения файла
     *
     * @var int
     */
    private $buffer_size = 4096;

    /**
     * Конфигурация источников данных
     *
     * @var array
     */
    private $sources_config = [];

    /**
     * Конструктор класса
     */
    public function __construct() {
        $this->temp_dir = STRAPI_PARSER_PLUGIN_DIR . 'temp/';
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }

        // Инициализация конфигурации источников
        $this->init_sources_config();

        
        add_action('wp_ajax_strapi_parser_process_batch', array($this, 'ajax_process_batch'));
    }

    /**
     * Инициализация конфигурации источников данных
     */
    private function init_sources_config() {
        // YandexDirectories
        $this->sources_config['YandexDirectories'] = [
            'id' => 'yandexdirectories',
            'name' => 'Яндекс.Справочник',
            'apiEndpoint' => '/api/company-profiles/phone-match/yandexDirectories',
            'columnMapping' => [
                'Названия' => 'name',
                'Описание' => 'description',
                'Типы' => 'yandexDirectories.types',
                'Категории' => 'yandexDirectories.categories',
                'Регионы, области' => 'location.region',
                'Населенные пункты' => 'location.city',
                'Адреса' => 'location.address',
                'Округа, районы' => 'location.district',
                'Филиалов' => 'branchesCount',
                'Индексы' => 'location.zip_code',
                'Мобильные телефоны' => 'contacts.mobilePhone',
                'Городские телефоны' => 'contacts.phone',
                'Доп. телефоны' => 'contacts.additionalPhones',
                'Email-адреса' => 'contacts.email',
                'Доп. email' => 'contacts.additionalEmails',
                'Сайты' => 'contacts.website',
                'Вконтакте' => 'social.vkontakte',
                'Одноклассники' => 'social.odnoklassniki',
                'WhatsApp' => 'social.whatsapp',
                'Viber' => 'social.viber',
                'YouTube' => 'social.youtube',
                'Telegram' => 'social.telegram',
                'Twitter' => 'social.twitter',
                'Facebook' => 'social.facebook',
                'Rutube' => 'social.rutube',
                'Яндекс Дзен' => 'social.yandexZen',
                'Дата парсинга' => 'yandexDirectories.parsingDate',
                'Тип организации' => 'legalStatusRaw',
                'Организационно-правовая форма' => 'legalStatusRaw',
                'Год основания' => 'foundedYear',
                'Кол-во сотрудников' => 'employeesCount',
                'Имя контактного лица' => 'contactPerson.0.firstName',
                'Фамилия контактного лица' => 'contactPerson.0.lastName',
                'Отчество контактного лица' => 'contactPerson.0.middleName',
                'Должность контактного лица' => 'contactPerson.0.position'
            ],
            'requiredColumns' => ['Названия']
        ];

        // SearchBase
        $this->sources_config['SearchBase'] = [
            'id' => 'searchbase',
            'name' => 'SearchBase',
            'apiEndpoint' => '/api/company-profiles/phone-match/searchBase',
            'columnMapping' => [
                'Название' => 'name',
                'Описание' => 'description',
                'Сайты' => 'contacts.website',
                'Мобильные телефоны' => 'contacts.mobilePhone',
                'Городские телефоны' => 'contacts.phone',
                'Доп. телефоны' => 'contacts.additionalPhones',
                'Email-адреса' => 'contacts.email',
                'Доп. email' => 'contacts.additionalEmails',
                'Регион, область' => 'location.region',
                'Населенный пункт' => 'location.city',
                'Адрес' => 'location.address',
                'Индекс' => 'location.zip_code',
                'Вконтакте' => 'social.vkontakte',
                'Instagram' => 'social.instagram',
                'Одноклассники' => 'social.odnoklassniki',
                'Facebook' => 'social.facebook',
                'WhatsApp' => 'social.whatsapp',
                'Viber' => 'social.viber',
                'YouTube' => 'social.youtube',
                'Telegram' => 'social.telegram',
                'Twitter' => 'social.twitter',
                'Rutube' => 'social.rutube',
                'Яндекс Дзен' => 'social.yandexZen',
                'CMS' => 'searchBase.cms',
                'Даты парсинга' => 'searchBase.parsingDate',
                'Тип организации' => 'legalStatusRaw',
                'Организационно-правовая форма' => 'legalStatusRaw',
                'Год основания' => 'foundedYear',
                'Кол-во сотрудников' => 'employeesCount',
                'Имя контактного лица' => 'contactPerson.0.firstName',
                'Фамилия контактного лица' => 'contactPerson.0.lastName',
                'Отчество контактного лица' => 'contactPerson.0.middleName',
                'Должность контактного лица' => 'contactPerson.0.position'
            ],
            'requiredColumns' => ['Сайты']
        ];
    }

    /**
     * Загрузка CSV файла
     *
     * @param array $file Файл из массива $_FILES
     * @return array|WP_Error Результат загрузки
     */
    public function upload_csv($file) {
        // Проверка наличия файла
        if (empty($file) || !is_array($file)) {
            return new WP_Error('invalid_file', 'Файл не был загружен');
        }

        // Проверка ошибок загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }

        // Проверка расширения файла
        $file_info = pathinfo($file['name']);
        if (strtolower($file_info['extension']) !== 'csv') {
            return new WP_Error('invalid_extension', 'Файл должен иметь расширение .csv');
        }

        // Создаём уникальное имя файла
        $file_id = uniqid('csv_');
        $filename = $file_id . '.csv';
        $file_path = $this->temp_dir . $filename;

        // Перемещаем загруженный файл
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return new WP_Error('move_error', 'Ошибка при перемещении файла');
        }

        // Анализируем файл и получаем информацию
        $file_info = $this->analyze_file($file_path);
        $file_info['file_id'] = $file_id;
        $file_info['filename'] = $filename;
        $file_info['original_name'] = $file['name'];
        $file_info['file_path'] = $file_path;

        return $file_info;
    }

    /**
     * Анализ CSV файла
     *
     * @param string $file_path Путь к файлу
     * @return array Информация о файле
     */
    public function analyze_file($file_path) {
        // Получаем кодировку файла
        $encoding = $this->detect_encoding($file_path);
    
        // Открываем файл
        $file = fopen($file_path, 'r');
        if (!$file) {
            return [
                'total_rows' => 0,
                'columns' => [],
                'encoding' => $encoding,
                'error' => 'Не удалось открыть файл'
            ];
        }
    
        // Определяем разделитель - пробуем сначала точку с запятой
        $first_line = fgets($file);
        rewind($file);
        
        $delimiter = ';';
        $semicolon_count = substr_count($first_line, ';');
        $comma_count = substr_count($first_line, ',');
        
        // Если запятых больше, чем точек с запятой, используем запятую как разделитель
        if ($comma_count > $semicolon_count) {
            $delimiter = ',';
        }
        
        // Читаем первую строку (заголовки)
        $headers = fgetcsv($file, 0, $delimiter);
    
        // Преобразуем заголовки в нужной кодировке
        if ($encoding !== 'UTF-8') {
            $headers = array_map(function($header) use ($encoding) {
                return iconv($encoding, 'UTF-8//IGNORE', $header);
            }, $headers);
        }

        $logger = new StrapiCSVParser_Logger();
        $logger->log('debug', "Заголовки файла: " . implode(', ', $headers));
    
        // Считаем количество строк (кроме заголовка)
        $row_count = 0;
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // Проверяем, что строка не пустая
            if (!empty($row) && count(array_filter($row)) > 0) {
                $row_count++;
            }
        }

        if (in_array('Названия', $headers)) {
          $logger->log('debug', "Найдена обязательная колонка 'Названия'");
        } else {
            $logger->log('warning', "В файле отсутствует обязательная колонка 'Названия'");
        }

        if (in_array('Сайты', $headers)) {
          $logger->log('debug', "Найдена обязательная колонка 'Сайты'");
        } else {
            $logger->log('warning', "В файле отсутствует обязательная колонка 'Сайты'");
        }
    
        fclose($file);
        
        // Создаем логгер и логируем результат анализа
        $logger = new StrapiCSVParser_Logger();
        $logger->log('debug', "Анализ файла {$file_path}: разделитель '{$delimiter}', кодировка {$encoding}, строк {$row_count}, заголовки: " . implode(', ', $headers));
    
        return [
            'total_rows' => $row_count,
            'columns' => $headers,
            'encoding' => $encoding,
            'delimiter' => $delimiter
        ];
    }

    /**
     * AJAX обработчик для обработки пакета данных
     */
    public function ajax_process_batch() {

        try {
            check_ajax_referer('strapi-parser-nonce', 'nonce');
    
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Недостаточно прав');
            }
            
            $logger = new StrapiCSVParser_Logger();
    
            // Получаем параметры
            $file_id = isset($_POST['file_id']) ? sanitize_text_field($_POST['file_id']) : '';
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';
            $category_id = isset($_POST['category_id']) ? sanitize_text_field($_POST['category_id']) : '';
            $subcategory_id = isset($_POST['subcategory_id']) ? sanitize_text_field($_POST['subcategory_id']) : '';
            $subsubcategory_id = isset($_POST['subsubcategory_id']) ? sanitize_text_field($_POST['subsubcategory_id']) : '';
    
            // Проверяем обязательные поля
            if (empty($file_id) || empty($source) || empty($category_id)) {
                wp_send_json_error('Не указаны обязательные параметры');
            }
    
            // Проверяем существование файла
            $file_path = $this->get_file_path($file_id);
            if (!file_exists($file_path)) {
                wp_send_json_error('Файл не найден');
            }
    
            // Проверка на бесконечный цикл
            $progress_key = 'strapi_parser_progress_' . $file_id;
            $current_progress = get_option($progress_key, ['processed' => 0, 'total' => 0]);
            
            // Если общее количество строк меньше текущего смещения, значит процесс уже завершен
            if (isset($current_progress['total']) && $current_progress['total'] > 0 && $offset >= $current_progress['total']) {
                $logger->log('info', "Отправка завершающего ответа: offset={$offset}, total={$current_progress['total']}");
                
                wp_send_json_success([
                    'processed' => $current_progress['total'],
                    'total' => $current_progress['total'],
                    'success' => $current_progress['success'] ?? 0,
                    'failed' => $current_progress['failed'] ?? 0,
                    'percentage' => 100,
                    'completed' => true,
                    'next_offset' => $current_progress['total'],
                    'results' => []
                ]);
                return;
            }
    
            // Обработка пакета данных с обработкой исключений
            try {
                $result = $this->process_batch($file_path, $offset, $source, $category_id, $subcategory_id, $subsubcategory_id);
                
                if (is_wp_error($result)) {
                    $logger->log('error', 'Ошибка обработки пакета: ' . $result->get_error_message());
                    wp_send_json_error($result->get_error_message());
                } else {
                    $logger->log('info', "Обработан пакет записей: {$result['processed']} из {$result['total']}");
                    
                    // Явно устанавливаем флаг completed
                    $result['completed'] = ($result['next_offset'] >= $result['total']);
                    
                    wp_send_json_success($result);
                }
            } catch (Exception $e) {
                $logger->log('error', 'Исключение при обработке пакета: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                wp_send_json_error('Ошибка обработки: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            // Перехватываем все исключения на верхнем уровне
            $logger->log('error', 'Глобальное исключение: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            wp_send_json_error('Внутренняя ошибка сервера: ' . $e->getMessage());
        }
    }

    /**
     * Обработка пакета данных из CSV
     *
     * @param string $file_path Путь к файлу
     * @param int $offset Смещение (строка, с которой начать чтение)
     * @param string $source Тип источника данных
     * @param string $category_id ID категории
     * @param string $subcategory_id ID подкатегории
     * @param string $subsubcategory_id ID подподкатегории
     * @return array|WP_Error Результат обработки
     */
    public function process_batch($file_path, $offset, $source, $category_id, $subcategory_id, $subsubcategory_id = '') {
        // Проверяем наличие конфигурации источника
        if (!isset($this->sources_config[$source])) {
            return new WP_Error('invalid_source', "Неизвестный источник данных: {$source}");
        }

        // Создаем API клиент и логгер
        $api_client = new StrapiCSVParser_ApiClient();
        $logger = new StrapiCSVParser_Logger();

        $logger->log('debug', "=== НАЧАЛО ОБРАБОТКИ ПАКЕТА ===");
        $logger->log('debug', "Файл: {$file_path}, смещение: {$offset}, источник: {$source}, категория: {$category_id}");

        $source_config = $this->sources_config[$source];

        $logger->log('debug', "Конфигурация источника: " . json_encode($source_config, JSON_UNESCAPED_UNICODE));
    
        // Получаем размер пакета из настроек
        $settings = get_option('strapi_parser_settings', []);
        $batch_size = isset($settings['batch_size']) ? intval($settings['batch_size']) : 50;
    
        // Информация о файле
        $file_info = $this->analyze_file($file_path);
        $encoding = $file_info['encoding'];
        $delimiter = $file_info['delimiter'];
        $total_rows = $file_info['total_rows'];

        $logger->log('debug', "Обязательные колонки для источника {$source}: " . 
            json_encode($source_config['requiredColumns'], JSON_UNESCAPED_UNICODE));
        $logger->log('debug', "Маппинг колонок для источника {$source}: " . 
            json_encode(array_slice($source_config['columnMapping'], 0, 5), JSON_UNESCAPED_UNICODE) . "...");
        
        // Логируем для отладки
        $logger->log('debug', "Запуск обработки файла {$file_path}, смещение {$offset}, всего строк {$total_rows}, разделитель '{$delimiter}', кодировка {$encoding}");
    
        // Открываем файл
        $file = fopen($file_path, 'r');
        if (!$file) {
            return new WP_Error('file_open_error', 'Не удалось открыть файл');
        }

        $logger->log('debug', "Файл успешно открыт");
    
        // Читаем заголовки
        $headers = fgetcsv($file, 0, $delimiter);
        
        // Преобразуем заголовки в UTF-8
        if ($encoding !== 'UTF-8') {
            $headers = array_map(function($header) use ($encoding) {
                return iconv($encoding, 'UTF-8//IGNORE', $header);
            }, $headers);
        }
        
        // Логируем заголовки
        $logger->log('debug', "Прочитаны заголовки: " . implode(', ', $headers));
    
        // Перематываем файл к нужному смещению
        rewind($file); // Сначала перематываем в начало
        
        // Пропускаем заголовок
        fgetcsv($file, 0, $delimiter);
        
        // Если нужно пропустить строки для нужного смещения
        if ($offset > 0) {
            $logger->log('debug', "Пропускаем {$offset} строк для достижения нужного смещения");
            $current_row = 0;
            while ($current_row < $offset && fgetcsv($file, 0, $delimiter) !== false) {
                $current_row++;
            }
            $logger->log('debug', "Пропущено {$current_row} строк");
        }
    
        // Читаем пакет данных
        $batch_data = [];
        $processed_count = 0;
        $success_count = 0;
        $failed_count = 0;
    
        $logger->log('debug', "Начинаем чтение пакета данных, размер пакета {$batch_size}");
        
        while (count($batch_data) < $batch_size && ($row = fgetcsv($file, 0, $delimiter)) !== false) {
            $logger->log('debug', "Чтение строки: " . implode(', ', array_slice($row, 0, 3)) . "...");
            
            // Пропускаем пустые строки
            if (empty($row) || count(array_filter($row)) === 0) {
                $logger->log('debug', "Пропущена пустая строка");
                continue;
            }
    
            // Преобразуем данные в UTF-8
            if ($encoding !== 'UTF-8') {
                $row = array_map(function($value) use ($encoding) {
                    return iconv($encoding, 'UTF-8//IGNORE', $value);
                }, $row);
            }
    
            // Создаем ассоциативный массив из строки
            $row_data = [];
            foreach ($headers as $i => $header) {
                if (isset($row[$i])) {
                    $row_data[$header] = $row[$i];
                }
            }
    
            // Проверяем наличие обязательных полей
            $missing_columns = $this->validate_row($row_data, $source_config['requiredColumns']);
            if (!empty($missing_columns)) {
                $logger->log('warning', "Пропущена строка из-за отсутствия обязательных полей: " . implode(', ', $missing_columns));
                $failed_count++;
                $processed_count++; // Учитываем в общем прогрессе
                continue;
            }

            $logger->log('debug', "Данные строки после маппинга: " . json_encode(
                array_intersect_key($row_data, array_flip(array_slice($headers, 0, min(5, count($headers))))), 
                JSON_UNESCAPED_UNICODE
            ) . "...");
    
            // Нормализуем данные
            $row_data = $this->clean_data($row_data);
    
            // Преобразуем данные в формат Strapi
            $strapi_data = $this->map_to_strapi_format($row_data, $source, $category_id, $subcategory_id, $subsubcategory_id);
    
            // Добавляем в пакет для отправки
            $batch_data[] = $strapi_data;
            $processed_count++;
            
            $logger->log('debug', "Добавлена запись в пакет: " . $strapi_data['name']);
        }
    
        fclose($file);
        
        $logger->log('debug', "Завершено чтение пакета. Прочитано записей: {$processed_count}, размер пакета: " . count($batch_data));
    
        // Обрабатываем каждую запись
        $result_data = [];
        foreach ($batch_data as $data) {
            try {
                $logger->log('debug', "Отправка данных в Strapi для компании: " . $data['name']);
                
                // Отправляем данные в Strapi
                $response = $api_client->send_data($source_config['apiEndpoint'], $data);
                
                // Проверяем на WP_Error
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    $logger->log('error', "Ошибка создания профиля для компании: {$data['name']}. Причина: {$error_message}");
                    
                    // Проверяем, не связана ли ошибка с дубликатом названия
                    if (strpos($error_message, 'already exists') !== false || 
                        strpos($error_message, 'должно быть уникально') !== false || 
                        strpos($error_message, 'already taken') !== false ||
                        strpos($error_message, 'уже занято') !== false) {
                        
                        // Модифицируем название и пробуем снова
                        $original_name = $data['name'];
                        $data['name'] = $this->make_unique_name($original_name);
                        $logger->log('info', "Найден дубликат названия '{$original_name}', пробуем с новым названием: {$data['name']}");
                        
                        // Повторная отправка с новым названием
                        $retry_response = $api_client->send_data($source_config['apiEndpoint'], $data);
                        
                        if (is_wp_error($retry_response)) {
                            $failed_count++;
                            $retry_error = $retry_response->get_error_message();
                            $logger->log('error', "Ошибка повторной отправки для компании: {$data['name']}. Причина: {$retry_error}");
                            $result_data[] = [
                                'name' => $data['name'],
                                'error' => $retry_error,
                                'success' => false
                            ];
                        } else if (isset($retry_response['data']) && isset($retry_response['data']['id'])) {
                            $success_count++;
                            $profile_id = $retry_response['data']['id'];
                            $logger->log('info', "Успешно создан профиль с модифицированным названием, ID: {$profile_id}, название: {$data['name']}");
                            $result_data[] = [
                                'id' => $profile_id,
                                'name' => $data['name'],
                                'original_name' => $original_name,
                                'success' => true
                            ];
                        } else if (isset($retry_response['id'])) {
                            $success_count++;
                            $profile_id = $retry_response['id'];
                            $logger->log('info', "Успешно создан профиль с модифицированным названием, ID: {$profile_id}, название: {$data['name']}");
                            $result_data[] = [
                                'id' => $profile_id,
                                'name' => $data['name'],
                                'original_name' => $original_name,
                                'success' => true
                            ];
                        } else {
                            $failed_count++;
                            $logger->log('error', "Неизвестная ошибка при повторной отправке для компании: {$data['name']}. Ответ: " . json_encode($retry_response, JSON_UNESCAPED_UNICODE));
                            $result_data[] = [
                                'name' => $data['name'],
                                'error' => 'Неизвестная ошибка при повторной отправке',
                                'success' => false
                            ];
                        }
                    } else {
                        $failed_count++;
                        $result_data[] = [
                            'name' => $data['name'],
                            'error' => $error_message,
                            'success' => false
                        ];
                    }
                }
                // Проверяем успешный ответ (должен содержать поле data с id)
                else if (isset($response['data']) && isset($response['data']['id'])) {
                    $success_count++;
                    $profile_id = $response['data']['id'];
                    $logger->log('info', "Успешно создан профиль ID: {$profile_id} для компании: {$data['name']}");
                    $result_data[] = [
                        'id' => $profile_id,
                        'name' => $data['name'],
                        'success' => true
                    ];
                }
                // Проверяем старый формат ответа (просто с id)
                else if (isset($response['id'])) {
                    $success_count++;
                    $profile_id = $response['id'];
                    $logger->log('info', "Успешно создан профиль ID: {$profile_id} для компании: {$data['name']}");
                    $result_data[] = [
                        'id' => $profile_id,
                        'name' => $data['name'],
                        'success' => true
                    ];
                }
                // Обрабатываем ошибки API
                else {
                    $failed_count++;
                    // Пытаемся извлечь сообщение об ошибке из ответа API
                    $error_message = 'Неизвестная ошибка';
                    
                    if (isset($response['error'])) {
                        if (is_string($response['error'])) {
                            $error_message = $response['error'];
                        } elseif (isset($response['error']['message'])) {
                            $error_message = $response['error']['message'];
                        }
                    }
                    
                    // Логируем полный ответ для отладки
                    $logger->log('error', "Ошибка API: " . json_encode($response, JSON_UNESCAPED_UNICODE));
                    $logger->log('error', "Ошибка создания профиля для компании: {$data['name']}. Причина: {$error_message}");
                    
                    $result_data[] = [
                        'name' => $data['name'],
                        'error' => $error_message,
                        'success' => false
                    ];
                }
            } catch (Exception $e) {
                $failed_count++;
                $logger->log('error', "Исключение при создании профиля для компании: {$data['name']}. Ошибка: " . $e->getMessage());
                $result_data[] = [
                    'name' => $data['name'],
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
    
        // Обновляем прогресс
        $new_offset = $offset + $processed_count;
        $progress = [
            'processed' => $new_offset,
            'total' => $total_rows,
            'success' => $success_count,
            'failed' => $failed_count,
            'percentage' => $total_rows > 0 ? round(($new_offset / $total_rows) * 100) : 0,
            'completed' => ($new_offset >= $total_rows),
            'next_offset' => $new_offset,
            'results' => $result_data
        ];
    
        // Сохраняем прогресс в опциях
        $progress_key = 'strapi_parser_progress_' . basename($file_path, '.csv');
        update_option($progress_key, $progress);
        
        $logger->log('debug', "Завершена обработка пакета. Смещение: {$offset}, новое смещение: {$new_offset}, всего строк: {$total_rows}, обработано: {$processed_count}, успешно: {$success_count}, ошибок: {$failed_count}");
    
        return $progress;
    }
    
    /**
     * Создание уникального названия для компании
     *
     * @param string $name Исходное название
     * @return string Уникализированное название
     */
    private function make_unique_name($name) {
        // Добавляем случайный суффикс к названию
        $suffix = ' [' . substr(md5(uniqid()), 0, 5) . ']';
        return $name . $suffix;
    }

    /**
     * Валидация строки данных
     *
     * @param array $row_data Данные строки
     * @param array $required_columns Обязательные колонки
     * @return array Отсутствующие колонки
     */
    private function validate_row($row_data, $required_columns) {
        $missing_columns = [];
        $logger = new StrapiCSVParser_Logger();
        
        // Логируем входные данные
        $logger->log('debug', "Проверка строки с данными: " . json_encode($row_data, JSON_UNESCAPED_UNICODE));
        $logger->log('debug', "Требуемые колонки: " . implode(', ', $required_columns));
        
        foreach ($required_columns as $column) {
            // Проверяем наличие колонки
            if (!isset($row_data[$column])) {
                $logger->log('debug', "Колонка '{$column}' отсутствует в данных строки");
                $missing_columns[] = $column;
                continue;
            }
            
            // Получаем значение и логируем его
            $value = $row_data[$column];
            $logger->log('debug', "Значение колонки '{$column}': '{$value}'");
            
            // Проверяем непустое значение
            if (trim($value) === '') {
                $logger->log('debug', "Колонка '{$column}' имеет пустое значение");
                $missing_columns[] = $column;
            }
            // Особый случай для "#не_формат" - считаем это отсутствующим значением
            else if (trim($value) === '#не_формат') {
                $logger->log('debug', "Колонка '{$column}' имеет значение '#не_формат'");
                $missing_columns[] = $column;
            }
        }
        
        // Логируем результат валидации
        if (!empty($missing_columns)) {
            $logger->log('warning', "Валидация строки: отсутствуют обязательные поля: " . implode(', ', $missing_columns));
        } else {
            $logger->log('debug', "Валидация строки: все обязательные поля присутствуют");
        }
        
        return $missing_columns;
    }

    /**
     * Очистка и нормализация данных
     *
     * @param array $data Данные для очистки
     * @return array Очищенные данные
     */
    private function clean_data($data) {
        $cleaned_data = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Очистка строковых значений
            if (is_string($value)) {
                $value = trim($value);
            }

            $cleaned_data[$key] = $value;
        }
        return $cleaned_data;
    }

    /**
     * Преобразование данных в формат Strapi
     *
     * @param array $row_data Данные строки
     * @param string $source Тип источника
     * @param string $category_id ID категории
     * @param string $subcategory_id ID подкатегории
     * @param string $subsubcategory_id ID подподкатегории
     * @return array Данные в формате Strapi
     */
    public function map_to_strapi_format($row_data, $source, $category_id, $subcategory_id, $subsubcategory_id = '') {
      $source_config = $this->sources_config[$source];
      $mapping = $source_config['columnMapping'];

      // Создаем структуру данных для Strapi
      $strapi_data = [
          'name' => null,
          'description' => null,
          'legalStatus' => 'unknown',
          'priceTier' => 'unknown',
          'branchesCount' => 1,
          'lastUpdated' => date('Y-m-d\TH:i:s\Z')
      ];

      // Маппинг данных из CSV в структуру
      $mapped_data = [];
      foreach ($row_data as $column => $value) {
          if (isset($mapping[$column]) && !empty($value)) {
              $this->set_nested_value($mapped_data, $mapping[$column], $value);
          }
      }

      var_dump($mapped_data);

      // Устанавливаем базовые поля
      if (isset($mapped_data['name'])) {
          $strapi_data['name'] = $mapped_data['name'];
      }
      
      if (isset($mapped_data['description'])) {
          $strapi_data['description'] = $mapped_data['description'];
      }

      // Обрабатываем телефон
      if (isset($mapped_data['contacts']['phone'])) {
          $strapi_data['phone'] = $mapped_data['contacts']['phone'];
      } elseif (isset($mapped_data['contacts']['mobilePhone'])) {
          $strapi_data['phone'] = $mapped_data['contacts']['mobilePhone'];
      }

      // Обрабатываем email и website
      if (isset($mapped_data['contacts']['email'])) {
          $strapi_data['email'] = $mapped_data['contacts']['email'];
      }
      
      if (isset($mapped_data['contacts']['website'])) {
          $strapi_data['website'] = $mapped_data['contacts']['website'];
      }

      // Адрес
      if (isset($mapped_data['location']['address'])) {
          $strapi_data['address'] = $mapped_data['location']['address'];
      }

      // ИНН
      if (isset($mapped_data['taxId'])) {
          $strapi_data['taxId'] = $mapped_data['taxId'];
      }

      // Год основания
      if (isset($mapped_data['foundedYear'])) {
          $strapi_data['foundedYear'] = intval($mapped_data['foundedYear']);
      }

      // Количество сотрудников
      if (isset($mapped_data['employeesCount'])) {
          $strapi_data['employeesCount'] = intval($mapped_data['employeesCount']);
      }

      // Количество филиалов
      if (isset($mapped_data['branchesCount'])) {
          $strapi_data['branchesCount'] = intval($mapped_data['branchesCount']);
      }

      // Подготовка компонента контактной информации
      $contact_info = null;
      if (isset($mapped_data['contacts'])) {
          $contact_info = [
              '__component' => 'common.contact-info',
              'phone' => $mapped_data['contacts']['phone'] ?? null,
              'mobilePhone' => $mapped_data['contacts']['mobilePhone'] ?? null,
              'email' => $mapped_data['contacts']['email'] ?? null,
              'website' => $mapped_data['contacts']['website'] ?? null,
              'additionalPhones' => $mapped_data['contacts']['additionalPhones'] ?? null,
              'additionalEmails' => $mapped_data['contacts']['additionalEmails'] ?? null
          ];
          
          // Добавляем контактную информацию на верхний уровень
          $strapi_data['contactInfo'] = $contact_info;
      }

      // Местоположение
      if (isset($mapped_data['location'])) {
          $strapi_data['location'] = [
              '__component' => 'common.geo-location-extended',
              'country' => 'Россия', // По умолчанию
              'region' => $mapped_data['location']['region'] ?? null,
              'city' => $mapped_data['location']['city'] ?? null,
              'district' => $mapped_data['location']['district'] ?? null,
              'address' => $mapped_data['location']['address'] ?? null,
              'zip_code' => $mapped_data['location']['zip_code'] ?? null
          ];
      }

      // Социальные сети - нормализуем и очищаем от доменов
      if (isset($mapped_data['social'])) {
          $social_media = [
              '__component' => 'common.social-media'
          ];
          
          // Нормализация данных социальных сетей
          if (isset($mapped_data['social']['vkontakte'])) {
              $vk = $mapped_data['social']['vkontakte'];
              // Удаляем доменное имя и лишние символы
              $vk = preg_replace('~^https?://(?:www\.)?vk\.com/~i', '', $vk);
              $vk = preg_replace('~^https?://(?:www\.)?vkontakte\.ru/~i', '', $vk);
              $social_media['vkontakte'] = trim($vk, '/ ');
          }
          
          if (isset($mapped_data['social']['telegram'])) {
              $telegram = $mapped_data['social']['telegram'];
              // Удаляем доменное имя и лишние символы
              $telegram = preg_replace('~^https?://(?:www\.)?t\.me/~i', '', $telegram);
              $telegram = preg_replace('~^https?://(?:www\.)?telegram\.me/~i', '', $telegram);
              $social_media['telegram'] = trim($telegram, '/ ');
          }
          
          if (isset($mapped_data['social']['whatsapp'])) {
              $whatsapp = $mapped_data['social']['whatsapp'];
              // Удаляем доменное имя и лишние символы
              $whatsapp = preg_replace('~^https?://(?:www\.)?wa\.me/~i', '', $whatsapp);
              $whatsapp = preg_replace('~^https?://(?:www\.)?whatsapp\.com/~i', '', $whatsapp);
              $social_media['whatsapp'] = trim($whatsapp, '/ ');
          }
          
          if (isset($mapped_data['social']['viber'])) {
              $viber = $mapped_data['social']['viber'];
              // Удаляем доменное имя и лишние символы
              $viber = preg_replace('~^https?://(?:www\.)?viber\.com/~i', '', $viber);
              $social_media['viber'] = trim($viber, '/ ');
          }
          
          if (isset($mapped_data['social']['youtube'])) {
              $youtube = $mapped_data['social']['youtube'];
              // Удаляем доменное имя и лишние символы
              $youtube = preg_replace('~^https?://(?:www\.)?youtube\.com/~i', '', $youtube);
              $social_media['youtube'] = trim($youtube, '/ ');
          }
          
          if (isset($mapped_data['social']['instagram'])) {
              $instagram = $mapped_data['social']['instagram'];
              // Удаляем доменное имя и лишние символы
              $instagram = preg_replace('~^https?://(?:www\.)?instagram\.com/~i', '', $instagram);
              $social_media['instagram'] = trim($instagram, '/ ');
          }
          
          if (isset($mapped_data['social']['facebook'])) {
              $facebook = $mapped_data['social']['facebook'];
              // Удаляем доменное имя и лишние символы
              $facebook = preg_replace('~^https?://(?:www\.)?facebook\.com/~i', '', $facebook);
              $social_media['facebook'] = trim($facebook, '/ ');
          }
          
          if (isset($mapped_data['social']['odnoklassniki'])) {
              $ok = $mapped_data['social']['odnoklassniki'];
              // Удаляем доменное имя и лишние символы
              $ok = preg_replace('~^https?://(?:www\.)?ok\.ru/~i', '', $ok);
              $ok = preg_replace('~^https?://(?:www\.)?odnoklassniki\.ru/~i', '', $ok);
              $social_media['odnoklassniki'] = trim($ok, '/ ');
          }
          
          if (isset($mapped_data['social']['twitter'])) {
              $twitter = $mapped_data['social']['twitter'];
              // Удаляем доменное имя и лишние символы
              $twitter = preg_replace('~^https?://(?:www\.)?twitter\.com/~i', '', $twitter);
              $twitter = preg_replace('~^https?://(?:www\.)?x\.com/~i', '', $twitter);
              $social_media['twitter'] = trim($twitter, '/ ');
          }
          
          if (isset($mapped_data['social']['rutube'])) {
              $rutube = $mapped_data['social']['rutube'];
              // Удаляем доменное имя и лишние символы
              $rutube = preg_replace('~^https?://(?:www\.)?rutube\.ru/~i', '', $rutube);
              $social_media['rutube'] = trim($rutube, '/ ');
          }
          
          if (isset($mapped_data['social']['yandexZen'])) {
              $zen = $mapped_data['social']['yandexZen'];
              // Удаляем доменное имя и лишние символы
              $zen = preg_replace('~^https?://(?:www\.)?zen\.yandex\.ru/~i', '', $zen);
              $zen = preg_replace('~^https?://(?:www\.)?dzen\.ru/~i', '', $zen);
              $social_media['yandexZen'] = trim($zen, '/ ');
          }
          
          // Добавляем социальные сети только если есть хотя бы одна заполненная соцсеть
          $has_social = false;
          foreach ($social_media as $key => $value) {
              if ($key !== '__component' && !empty($value)) {
                  $has_social = true;
                  break;
              }
          }
          
          if ($has_social) {
              $strapi_data['socialMedia'] = $social_media;
          }
      }

      // Контактные лица - повторяющийся компонент
      if (isset($mapped_data['contactPerson'])) {
          $contact_persons = [];
          
          foreach ($mapped_data['contactPerson'] as $index => $person) {
              if (!empty($person)) {
                  $person['__component'] = 'common.contact-person';
                  $contact_persons[] = $person;
              }
          }
          
          if (!empty($contact_persons)) {
              $strapi_data['contactPersons'] = $contact_persons;
          }
      }

      // Данные специфичные для источника
      switch ($source) {
          case 'YandexDirectories':
              if (isset($mapped_data['yandexDirectories'])) {
                  // Подготавливаем компонент YandexDirectories
                  $yandex_data = [
                      '__component' => 'business-listings.yandex-directories-profile',
                      'types' => $mapped_data['yandexDirectories']['types'] ?? null,
                      'categories' => $mapped_data['yandexDirectories']['categories'] ?? null,
                      'branches' => isset($mapped_data['yandexDirectories']['branches']) ? intval($mapped_data['yandexDirectories']['branches']) : null
                  ];
                  
                  // Обрабатываем дату парсинга
                  if (isset($mapped_data['yandexDirectories']['parsingDate'])) {
                      $date_str = $mapped_data['yandexDirectories']['parsingDate'];
                      // Преобразуем дату из формата dd.mm.yyyy в yyyy-mm-dd
                      if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $date_str, $matches)) {
                          $yandex_data['parsingDate'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                      } else {
                          $yandex_data['parsingDate'] = $date_str;
                      }
                  }
                  
                  // Дублируем контактную информацию в компонент YandexDirectories если она есть
                  if ($contact_info) {
                      $yandex_data['contactInfo'] = $contact_info;
                  }
                  
                  $strapi_data['yandexDirectories'] = $yandex_data;
              }
              break;
              
          case 'SearchBase':
              if (isset($mapped_data['searchBase'])) {
                  // Подготавливаем компонент SearchBase
                  $search_base_data = [
                      '__component' => 'business-listings.search-base-profile',
                      'description' => $mapped_data['searchBase']['description'] ?? null,
                      'cms' => $mapped_data['searchBase']['cms'] ?? null
                  ];
                  
                  // Обрабатываем дату парсинга
                  if (isset($mapped_data['searchBase']['parsingDate'])) {
                      $date_str = $mapped_data['searchBase']['parsingDate'];
                      // Преобразуем дату из формата dd.mm.yyyy в yyyy-mm-dd
                      if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $date_str, $matches)) {
                          $search_base_data['parsingDate'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
                      } else {
                          $search_base_data['parsingDate'] = $date_str;
                      }
                  }
                  
                  // Дублируем контактную информацию в компонент SearchBase если она есть
                  if ($contact_info) {
                      $search_base_data['contactInfo'] = $contact_info;
                  }
                  
                  $strapi_data['searchBase'] = $search_base_data;
              }
              break;
      }

      // Определяем организационно-правовую форму
      $this->determine_legal_status($strapi_data, $mapped_data);

      // Определяем ценовую категорию
      $this->determine_price_tier($strapi_data, $mapped_data);

      // Связь с категорией (промышленность) - приоритет отдаём категории 3-го уровня
      if (!empty($subsubcategory_id)) {
          $strapi_data['industry'] = $subsubcategory_id;
      } else if (!empty($subcategory_id)) {
          $strapi_data['industry'] = $subcategory_id;
      } else if (!empty($category_id)) {
          $strapi_data['industry'] = $category_id;
      }

      // Информация об импорте
      $strapi_data['dataSources'] = [
          'lastImportSource' => $source_config['id'],
          'lastImportDate' => date('Y-m-d\TH:i:s\Z'),
          'importHistory' => [
              [
                  'source' => $source_config['id'],
                  'date' => date('Y-m-d\TH:i:s\Z'),
                  'categoryId' => $category_id,
                  'subcategoryId' => $subcategory_id,
                  'subsubcategoryId' => $subsubcategory_id
              ]
          ]
      ];
      
      // Удаляем пустые значения для предотвращения проблем с API
      $strapi_data = $this->remove_empty_values($strapi_data);

      return $strapi_data;
    }

    /**
    * Удаление пустых значений из массива (рекурсивно)
    *
    * @param array $array Исходный массив
    * @return array Очищенный массив
    */
    private function remove_empty_values($array) {
      foreach ($array as $key => $value) {
          // Если это массив, рекурсивно обрабатываем его
          if (is_array($value)) {
              $array[$key] = $this->remove_empty_values($value);
              
              // Если после обработки массив пустой, удаляем его
              if (empty($array[$key]) && $key !== '__component') {
                  unset($array[$key]);
              }
          } 
          // Удаляем null, пустые строки и пустые массивы
          else if ($value === null || $value === '' || (is_array($value) && empty($value))) {
              // Не удаляем поле __component, оно должно оставаться всегда
              if ($key !== '__component') {
                  unset($array[$key]);
              }
          }
      }
      
      return $array;
    }

    /**
     * Установка вложенного значения в массив
     *
     * @param array &$array Массив для модификации
     * @param string $path Путь в нотации точек ("location.address")
     * @param mixed $value Значение для установки
     */
    private function set_nested_value(&$array, $path, $value) {
        $segments = explode('.', $path);
        $current = &$array;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Определение организационно-правовой формы
     *
     * @param array &$strapi_data Данные для Strapi
     * @param array $mapped_data Маппированные данные
     */
    private function determine_legal_status(&$strapi_data, $mapped_data) {
        // Проверяем явно указанный статус
        if (isset($mapped_data['legalStatusRaw'])) {
            $raw_status = mb_strtolower($mapped_data['legalStatusRaw']);
            
            if (preg_match('/ооо|зао|оао|ао|нко|гуп|муп|общество|компания|организация|предприятие|учреждение|фирма|юр\.? ?лицо/ui', $raw_status)) {
                $strapi_data['legalStatus'] = 'company';
                return;
            } elseif (preg_match('/ип|инд\.? ?пред|индивидуальный предприниматель/ui', $raw_status)) {
                $strapi_data['legalStatus'] = 'individual_entrepreneur';
                return;
            } elseif (preg_match('/самозанятый|плательщик нпд|налог на проф|физ\.? ?лицо нпд/ui', $raw_status)) {
                $strapi_data['legalStatus'] = 'self_employed';
                return;
            } elseif (preg_match('/физ\.? ?лицо|частное лицо|гражданин/ui', $raw_status)) {
                $strapi_data['legalStatus'] = 'individual';
                return;
            }
        }
        
        // Проверяем название компании
        $name = $strapi_data['name'] ?? '';
        
        // Индикаторы компании в имени
        if (preg_match('/^(?:ООО|ЗАО|ОАО|АО|НКО|ГУП|МУП|ПАО|НАО)\s+|[\s"«(](?:ООО|ЗАО|ОАО|АО|НКО|ГУП|МУП|ПАО|НАО)[\s"»)]|(?:общество с ограниченной ответственностью|открытое акционерное общество|закрытое акционерное общество|акционерное общество)/ui', $name)) {
            $strapi_data['legalStatus'] = 'company';
            return;
        }
        
        // Индикаторы ИП в имени
        if (preg_match('/^ИП\s+|[\s"«(]ИП[\s"»)]|^индивидуальный предприниматель\s+|[\s"«(]индивидуальный предприниматель[\s"»)]|ип[\s"»)]/ui', $name)) {
            $strapi_data['legalStatus'] = 'individual_entrepreneur';
            return;
        }
        
        // Индикаторы самозанятого в имени
        if (preg_match('/самозанятый|плательщик налога на профессиональный доход|плательщик НПД/ui', $name)) {
            $strapi_data['legalStatus'] = 'self_employed';
            return;
        }
        
        // Проверка ИНН для определения типа
        if (!empty($strapi_data['taxId'])) {
            $clean_tax_id = preg_replace('/\D/', '', $strapi_data['taxId']);
            if (strlen($clean_tax_id) === 12) {
                // 12-значный ИНН для физлиц и ИП
                $strapi_data['legalStatus'] = 'individual_entrepreneur';
            } elseif (strlen($clean_tax_id) === 10) {
                // 10-значный ИНН для компаний
                $strapi_data['legalStatus'] = 'company';
            }
        }
        
        // Проверка по количеству филиалов
        if (isset($strapi_data['branchesCount']) && $strapi_data['branchesCount'] > 1) {
            $strapi_data['legalStatus'] = 'company';
        }
        
        // Проверка по описанию
        $description = $strapi_data['description'] ?? '';
        if (preg_match('/ооо|зао|оао|ао|компания|фирма/ui', $description)) {
            $strapi_data['legalStatus'] = 'company';
        } elseif (preg_match('/ип|индивидуальный предприниматель/ui', $description)) {
            $strapi_data['legalStatus'] = 'individual_entrepreneur';
        } elseif (preg_match('/самозанятый|нпд/ui', $description)) {
            $strapi_data['legalStatus'] = 'self_employed';
        }
    }

    /**
     * Определение ценовой категории
     *
     * @param array &$strapi_data Данные для Strapi
     * @param array $mapped_data Маппированные данные
     */
    private function determine_price_tier(&$strapi_data, $mapped_data) {
        // Собираем сигналы для определения ценовой категории
        $signals = [
            // Тип бизнеса
            'businessType' => $mapped_data['yandexDirectories']['categories'] ?? 
                             ($mapped_data['searchBase']['description'] ?? ''),
            
            // Местоположение
            'address' => $strapi_data['address'] ?? '',
            'district' => $mapped_data['location']['district'] ?? '',
            'city' => $mapped_data['location']['city'] ?? '',
            
            // Размер бизнеса
            'branchesCount' => $strapi_data['branchesCount'] ?? 1,
            
            // Веб-присутствие
            'hasSocialMedia' => !empty($mapped_data['social']),
            'hasWebsite' => !empty($strapi_data['website']),
            
            // Название и описание
            'name' => $strapi_data['name'] ?? '',
            'description' => $strapi_data['description'] ?? ''
        ];
        
        // Индикаторы премиум-класса
        $premium_indicators = [
            'премиум', 'luxury', 'vip', 'эксклюзив', 'бутик', 'deluxe', 'элитный',
            'премиальный', 'люкс', 'престиж', 'высшего класса'
        ];
        
        // Индикаторы бюджетного класса
        $budget_indicators = [
            'дешево', 'эконом', 'низкие цены', 'доступно', 'бюджетно', 'акция', 'распродажа',
            'скидки', 'недорого', 'выгодно'
        ];
        
        // Проверка на премиум-индикаторы
        $has_premium = false;
        foreach ($premium_indicators as $indicator) {
            if (
                mb_stripos($signals['name'], $indicator) !== false || 
                mb_stripos($signals['description'], $indicator) !== false || 
                mb_stripos($signals['businessType'], $indicator) !== false
            ) {
                $has_premium = true;
                break;
            }
        }
        
        // Проверка на бюджетные индикаторы
        $has_budget = false;
        foreach ($budget_indicators as $indicator) {
            if (
                mb_stripos($signals['name'], $indicator) !== false || 
                mb_stripos($signals['description'], $indicator) !== false || 
                mb_stripos($signals['businessType'], $indicator) !== false
            ) {
                $has_budget = true;
                break;
            }
        }
        
        // Определение категории
        if ($has_premium || 
            ($signals['branchesCount'] > 10 && 
             (mb_stripos($signals['city'], 'москва') !== false || 
              mb_stripos($signals['city'], 'санкт-петербург') !== false))
        ) {
            $strapi_data['priceTier'] = 'premium';
        } 
        else if ($has_budget) {
            $strapi_data['priceTier'] = 'budget';
        } 
        else if ($signals['branchesCount'] > 3) {
            $strapi_data['priceTier'] = 'mid-range';
        }
    }

    /**
     * Определение кодировки файла
     *
     * @param string $file_path Путь к файлу
     * @return string Кодировка файла
     */
    private function detect_encoding($file_path) {
        $file_content = file_get_contents($file_path, false, null, 0, $this->buffer_size);
        
        // Проверяем BOM для UTF-8
        if (substr($file_content, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8';
        }
        
        // Пробуем определить кодировку
        $encodings = ['UTF-8', 'Windows-1251', 'KOI8-R', 'ISO-8859-5'];
        foreach ($encodings as $encoding) {
            $sample = @iconv($encoding, 'UTF-8', $file_content);
            if ($sample !== false) {
                return $encoding;
            }
        }
        
        // По умолчанию - Windows-1251 (распространенная кодировка в русскоязычных CSV)
        return 'Windows-1251';
    }

    /**
     * Получение пути к файлу по его ID
     *
     * @param string $file_id Идентификатор файла
     * @return string Полный путь к файлу
     */
    public function get_file_path($file_id) {
        return $this->temp_dir . $file_id . '.csv';
    }

    /**
     * Очистка временных файлов
     *
     * @param string $file_id Идентификатор файла (если не указан, очищаются все файлы)
     */
    public function clear_temp_files($file_id = null) {
        if ($file_id) {
            $file_path = $this->get_file_path($file_id);
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        } else {
            $files = glob($this->temp_dir . '*.csv');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Получение сообщения об ошибке загрузки
     *
     * @param int $error_code Код ошибки
     * @return string Сообщение об ошибке
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Размер файла превышает лимит, установленный в php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Размер файла превышает лимит, указанный в форме';
            case UPLOAD_ERR_PARTIAL:
                return 'Файл был загружен не полностью';
            case UPLOAD_ERR_NO_FILE:
                return 'Файл не был загружен';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Отсутствует временная директория для загрузки';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Не удалось записать файл на диск';
            case UPLOAD_ERR_EXTENSION:
                return 'Загрузка файла была остановлена расширением PHP';
            default:
                return 'Неизвестная ошибка при загрузке файла';
        }
    }
}
