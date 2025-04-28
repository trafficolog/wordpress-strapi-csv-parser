<?php
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

        // Читаем первую строку (заголовки)
        $headers = fgetcsv($file, 0, ';');
        
        // Если разделитель неверный, пробуем запятую
        if (count($headers) <= 1) {
            rewind($file);
            $headers = fgetcsv($file, 0, ';');
            $delimiter = ';';
        } else {
            $delimiter = ';';
        }

        // Преобразуем заголовки в нужной кодировке
        if ($encoding !== 'UTF-8') {
            $headers = array_map(function($header) use ($encoding) {
                return iconv($encoding, 'UTF-8//IGNORE', $header);
            }, $headers);
        }

        // Считаем количество строк
        $row_count = 0;
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            $row_count++;
        }

        fclose($file);

        return [
            'total_rows' => $row_count,
            'columns' => $headers,
            'encoding' => $encoding,
            'delimiter' => $delimiter
        ];
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

        $source_config = $this->sources_config[$source];

        // Получаем размер пакета из настроек
        $settings = get_option('strapi_parser_settings', []);
        $batch_size = isset($settings['batch_size']) ? intval($settings['batch_size']) : 50;

        // Информация о файле
        $file_info = $this->analyze_file($file_path);
        $encoding = $file_info['encoding'];
        $delimiter = $file_info['delimiter'];
        $total_rows = $file_info['total_rows'];

        // Создаем API клиент
        $api_client = new StrapiCSVParser_ApiClient();
        $logger = new StrapiCSVParser_Logger();

        // Открываем файл
        $file = fopen($file_path, 'r');
        if (!$file) {
            return new WP_Error('file_open_error', 'Не удалось открыть файл');
        }

        // Читаем заголовки
        $headers = fgetcsv($file, 0, $delimiter);
        
        // Преобразуем заголовки в UTF-8
        if ($encoding !== 'UTF-8') {
            $headers = array_map(function($header) use ($encoding) {
                return iconv($encoding, 'UTF-8//IGNORE', $header);
            }, $headers);
        }

        // Перематываем файл к нужному смещению
        if ($offset > 0) {
            $current_row = 0;
            while ($current_row < $offset && fgetcsv($file, 0, $delimiter) !== false) {
                $current_row++;
            }
        }

        // Читаем пакет данных
        $batch_data = [];
        $processed_count = 0;
        $success_count = 0;
        $failed_count = 0;

        while (count($batch_data) < $batch_size && ($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // Пропускаем пустые строки
            if (empty($row) || count(array_filter($row)) === 0) {
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
                continue;
            }

            // Нормализуем данные
            $row_data = $this->clean_data($row_data);

            // Преобразуем данные в формат Strapi
            $strapi_data = $this->map_to_strapi_format($row_data, $source, $category_id, $subcategory_id, $subsubcategory_id);

            // Добавляем в пакет для отправки
            $batch_data[] = $strapi_data;
            $processed_count++;
        }

        fclose($file);

        // Обрабатываем каждую запись
        $result_data = [];
        foreach ($batch_data as $data) {
            try {
                // Отправляем данные в Strapi
                $response = $api_client->send_data($source_config['apiEndpoint'], $data);
                
                // Проверяем ответ
                if ($response && isset($response['id'])) {
                    $success_count++;
                    $logger->log('info', "Успешно создан профиль ID: {$response['id']} для компании: {$data['name']}");
                    $result_data[] = [
                        'id' => $response['id'],
                        'name' => $data['name'],
                        'success' => true
                    ];
                } else {
                    $failed_count++;
                    $error_message = isset($response['error']) ? $response['error'] : 'Неизвестная ошибка';
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
            'completed' => $new_offset >= $total_rows,
            'next_offset' => $new_offset,
            'results' => $result_data
        ];

        // Сохраняем прогресс в опциях
        $progress_key = 'strapi_parser_progress_' . basename($file_path, '.csv');
        update_option($progress_key, $progress);

        return $progress;
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
        foreach ($required_columns as $column) {
            if (!isset($row_data[$column]) || trim($row_data[$column]) === '') {
                $missing_columns[] = $column;
            }
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

        // Маппинг данных из CSV в структуру Strapi
        $mapped_data = [];
        foreach ($row_data as $column => $value) {
            if (isset($mapping[$column]) && !empty($value)) {
                $this->set_nested_value($mapped_data, $mapping[$column], $value);
            }
        }

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

        // Устанавливаем компоненты

        // Местоположение (location)
        if (isset($mapped_data['location'])) {
            $strapi_data['location'] = $mapped_data['location'];
        }

        // Контактная информация (contacts)
        if (isset($mapped_data['contacts'])) {
            $strapi_data['contacts'] = $mapped_data['contacts'];
        }

        // Социальные сети (social)
        if (isset($mapped_data['social'])) {
            $strapi_data['social'] = $mapped_data['social'];
        }

        // Контактные лица (contactPerson) - повторяющийся компонент
        if (isset($mapped_data['contactPerson'])) {
            $contact_persons = [];
            
            // Каждая персона должна иметь __component для Strapi 5
            foreach ($mapped_data['contactPerson'] as $index => $person) {
                if (!empty($person)) {
                    $person['__component'] = 'common.contact-person';
                    $contact_persons[] = $person;
                }
            }
            
            if (!empty($contact_persons)) {
                $strapi_data['contactPerson'] = $contact_persons;
            }
        }

        // Данные специфичные для источника
        switch ($source) {
            case 'YandexDirectories':
                if (isset($mapped_data['yandexDirectories'])) {
                    $strapi_data['yandexDirectories'] = $mapped_data['yandexDirectories'];
                }
                break;
                
            case 'SearchBase':
                if (isset($mapped_data['searchBase'])) {
                    $strapi_data['searchBase'] = $mapped_data['searchBase'];
                }
                break;
        }

        // Определяем организационно-правовую форму
        $this->determine_legal_status($strapi_data, $mapped_data);

        // Определяем ценовую категорию
        $this->determine_price_tier($strapi_data, $mapped_data);

        // Связь с категорией (промышленность)
        if ($category_id) {
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

        return $strapi_data;
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
