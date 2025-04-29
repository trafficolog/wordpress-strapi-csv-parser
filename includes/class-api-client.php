<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Класс для взаимодействия с Strapi API
 *
 * @package StrapiCSVParser
 */

if (!defined('ABSPATH')) {
    exit;
}

class StrapiCSVParser_ApiClient {
    /**
     * URL Strapi API
     *
     * @var string
     */
    private $api_url;

    /**
     * API токен для авторизации
     *
     * @var string
     */
    private $api_token;

    /**
     * Таймаут запроса в секундах
     *
     * @var int
     */
    private $timeout;

    /**
     * Количество попыток запроса при ошибке
     *
     * @var int
     */
    private $max_retries;

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
        // Получение настроек из базы данных
        $settings = get_option('strapi_parser_settings', []);
        
        $this->api_url = isset($settings['strapi_url']) ? $settings['strapi_url'] : '';
        $this->api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
        $this->timeout = isset($settings['timeout']) ? intval($settings['timeout']) : 120;
        $this->max_retries = 3; // Количество попыток повтора запроса при ошибке
        $this->logger = new StrapiCSVParser_Logger();
    }

    /**
     * Отправка данных в Strapi API
     *
     * @param string $endpoint Конечная точка API
     * @param array $data Данные для отправки
     * @return array|WP_Error Результат запроса
     */
    public function send_data($endpoint, $data) {
      // Ensure data has proper structure
      $payload = ['data' => $data];
    
      // Логирование для отладки
      $this->logger->log('debug', "Sending data to endpoint: {$endpoint}");
      $this->logger->log('debug', "Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
      
      return $this->send_request($endpoint, $payload, 'POST');
    }

    /**
     * Отправка запроса к Strapi API
     *
     * @param string $endpoint Конечная точка API
     * @param array $data Данные для отправки (для POST и PUT запросов)
     * @param string $method HTTP метод (GET, POST, PUT, DELETE)
     * @param int $retry_count Текущее количество попыток (для рекурсивных вызовов)
     * @return array|WP_Error Результат запроса
     */
    public function send_request($endpoint, $data = [], $method = 'GET', $retry_count = 0) {
        try {
            // Проверка наличия URL и токена
            if (empty($this->api_url)) {
                return new WP_Error('invalid_api_url', 'URL API не указан в настройках');
            }
    
            if (empty($this->api_token)) {
                return new WP_Error('invalid_api_token', 'API токен не указан в настройках');
            }
    
            // Формирование полного URL
            $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');
    
            // Подготовка аргументов запроса
            $args = [
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_token
                ]
            ];
    
            // Добавление данных для POST и PUT запросов
            if ($method === 'POST' || $method === 'PUT') {
                $args['method'] = $method;
                $args['body'] = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
    
            $this->logger->log('debug', "Запрос {$method} к {$url}");
            
            // Для отладки логируем полные данные запроса, но только в режиме debug
            $settings = get_option('strapi_parser_settings', []);
            if (isset($settings['debug']) && $settings['debug']) {
                $this->logger->log('debug', "Полные данные запроса: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
    
            // Отправка запроса
            $response = wp_remote_request($url, $args);
    
            // Обработка ошибок запроса
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->logger->log('error', "Ошибка запроса: {$error_message}");
                
                // Пробуем повторить запрос при временных ошибках
                if ($retry_count < $this->max_retries) {
                    $wait_time = pow(2, $retry_count) * 1000000; // Экспоненциальная задержка в микросекундах
                    usleep($wait_time); // Ждем перед повторным запросом
                    return $this->send_request($endpoint, $data, $method, $retry_count + 1);
                }
                
                return $response;
            }
    
            // Получение кода ответа
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            // Проверяем, что ответ JSON и декодируем его
            $this->logger->log('debug', "Ответ {$response_code} от {$url}");
            
            if (!empty($response_body)) {
                // Проверка, что это действительно JSON
                if (substr(trim($response_body), 0, 1) === '{' || substr(trim($response_body), 0, 1) === '[') {
                    $response_data = json_decode($response_body, true);
                    
                    // Проверка на ошибку декодирования JSON
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->logger->log('error', "Ошибка декодирования JSON: " . json_last_error_msg() . ". Ответ начинается с: " . substr($response_body, 0, 100));
                        return new WP_Error('json_decode_error', 'Ошибка декодирования JSON: ' . json_last_error_msg());
                    }
                } else {
                    // Вероятно это HTML или другой формат
                    $this->logger->log('error', "Получен неожиданный формат ответа (не JSON). Ответ начинается с: " . substr($response_body, 0, 100));
                    return new WP_Error('invalid_response', 'Получен неожиданный формат ответа (не JSON): ' . substr($response_body, 0, 100));
                }
            } else {
                $response_data = [];
            }
    
            // Обработка ошибок от API
            if ($response_code < 200 || $response_code >= 300) {
                $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : "HTTP Error: {$response_code}";
                
                // Подробное логирование ошибки
                if (isset($response_data['error']['details'])) {
                    $this->logger->log('error', "Детали ошибки API: " . json_encode($response_data['error']['details'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
                
                $this->logger->log('error', "Ошибка API: {$error_message}");
                
                // Пробуем повторить запрос при временных ошибках сервера
                if ($response_code >= 500 && $retry_count < $this->max_retries) {
                    $wait_time = pow(2, $retry_count) * 1000000; // Экспоненциальная задержка
                    usleep($wait_time);
                    return $this->send_request($endpoint, $data, $method, $retry_count + 1);
                }
                
                return new WP_Error('api_error', $error_message, [
                    'code' => $response_code,
                    'body' => $response_data
                ]);
            }
    
            // Успешный ответ
            return $response_data;
        } catch (Exception $e) {
            $this->logger->log('error', "Исключение в send_request: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return new WP_Error('request_exception', 'Исключение при отправке запроса: ' . $e->getMessage());
        }
    }

    /**
     * Получение категорий из Strapi
     *
     * @return array|WP_Error Список категорий
     */
    // public function get_categories() {
    //   // Запрашиваем категории с pagination[pageSize]=100, чтобы получить до 100 категорий
    //   $response = $this->send_request('/api/industries?pagination[pageSize]=100');
      
    //   if (is_wp_error($response)) {
    //       return $response;
    //   }
      
    //   // Преобразование ответа в удобный формат
    //   $categories = [];
      
    //   if (isset($response['data']) && is_array($response['data'])) {
    //       foreach ($response['data'] as $category) {
    //           $categories[] = [
    //               'id' => $category['id'],
    //               'name' => $category['attributes']['name'] ?? '',
    //               'slug' => $category['attributes']['slug'] ?? '',
    //               'subcategories' => $this->extract_subcategories($category)
    //           ];
    //       }
    //   }
      
    //   return $categories;
    // }

    /**
    * Извлечение подкатегорий из данных категории
    *
    * @param array $category Данные категории
    * @return array Список подкатегорий
    */
    // private function extract_subcategories($category) {
    //   $subcategories = [];
      
    //   // Проверка наличия подкатегорий
    //   if (isset($category['attributes']['subcategories']['data']) && 
    //       is_array($category['attributes']['subcategories']['data'])) {
              
    //       foreach ($category['attributes']['subcategories']['data'] as $subcategory) {
    //           $subsubcategories = [];
              
    //           // Получение подподкатегорий, если они есть
    //           if (isset($subcategory['attributes']['subcategories']['data']) && 
    //               is_array($subcategory['attributes']['subcategories']['data'])) {
                      
    //               foreach ($subcategory['attributes']['subcategories']['data'] as $subsubcategory) {
    //                   $subsubcategories[] = [
    //                       'id' => $subsubcategory['id'],
    //                       'name' => $subsubcategory['attributes']['name'] ?? '',
    //                       'slug' => $subsubcategory['attributes']['slug'] ?? ''
    //                   ];
    //               }
    //           }
              
    //           $subcategories[] = [
    //               'id' => $subcategory['id'],
    //               'name' => $subcategory['attributes']['name'] ?? '',
    //               'slug' => $subcategory['attributes']['slug'] ?? '',
    //               'subcategories' => $subsubcategories
    //           ];
    //       }
    //   }
      
    //   return $subcategories;
    // }

    /**
     * Объединение профилей компаний по телефонам
     *
     * @return array|WP_Error Результат операции
     */
    public function merge_profiles_by_phone() {
        // Отправляем запрос к API для объединения профилей
        $response = $this->send_request('/api/company-profiles/merge-by-phone', [], 'POST');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Логирование результата
        if (isset($response['mergedCount'])) {
            $this->logger->log('info', "Объединено профилей: {$response['mergedCount']}");
        } else {
            $this->logger->log('warning', "Объединение выполнено, но количество не указано в ответе");
        }
        
        return $response;
    }

    /**
     * Получение дерева категорий из Strapi
     *
     * @return array|WP_Error Дерево категорий
     */
    public function get_industries_tree() {
      // Запрос к API для получения дерева категорий
      $response = $this->send_request('/api/industries/tree');
      
      if (is_wp_error($response)) {
          $this->logger->log('error', 'Ошибка получения дерева категорий: ' . $response->get_error_message());
          return $response;
      }
      
      // Проверяем полученные данные
      if (!isset($response['data'])) {
          return new WP_Error('invalid_response', 'Некорректный формат ответа API');
      }
      
      $this->logger->log('debug', 'Успешно получено дерево категорий');
      return $response['data'];
    }


    /**
     * Получение категорий из Strapi
     *
     * @return array|WP_Error Список категорий
     */
    public function get_categories() {
        $response = $this->send_request('/api/industries/tree?pagination[pageSize]=100');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Преобразование ответа в удобный формат
        $categories = [];
        
        if (isset($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as $category) {
                $categories[] = [
                    'id' => $category['id'],
                    'name' => $category['attributes']['name'] ?? '',
                    'slug' => $category['attributes']['slug'] ?? '',
                    'subcategories' => $this->extract_subcategories($category)
                ];
            }
        }
        
        return $categories;
    }

    /**
     * Извлечение подкатегорий из данных категории
     *
     * @param array $category Данные категории
     * @return array Список подкатегорий
     */
    private function extract_subcategories($category) {
        $subcategories = [];
        
        // Проверка наличия подкатегорий
        if (isset($category['attributes']['subcategories']['data']) && 
            is_array($category['attributes']['subcategories']['data'])) {
                
            foreach ($category['attributes']['subcategories']['data'] as $subcategory) {
                $subsubcategories = [];
                
                // Получение подподкатегорий, если они есть
                if (isset($subcategory['attributes']['subcategories']['data']) && 
                    is_array($subcategory['attributes']['subcategories']['data'])) {
                        
                    foreach ($subcategory['attributes']['subcategories']['data'] as $subsubcategory) {
                        $subsubcategories[] = [
                            'id' => $subsubcategory['id'],
                            'name' => $subsubcategory['attributes']['name'] ?? '',
                            'slug' => $subsubcategory['attributes']['slug'] ?? ''
                        ];
                    }
                }
                
                $subcategories[] = [
                    'id' => $subcategory['id'],
                    'name' => $subcategory['attributes']['name'] ?? '',
                    'slug' => $subcategory['attributes']['slug'] ?? '',
                    'subcategories' => $subsubcategories
                ];
            }
        }
        
        return $subcategories;
    }

    /**
     * Проверка доступности API и валидности токена
     *
     * @return array|WP_Error Результат проверки
     */
    public function test_connection() {

      // Проверяем соединение запросом к эндпоинту проверки
      $response = $this->send_request('/api/company-profiles/count');
      
      if (is_wp_error($response)) {
          return $response;
      }
      
      return [
          'success' => true,
          'status' => 'Соединение с Strapi API успешно установлено',
          'count' => isset($response['count']) ? $response['count'] : 'неизвестно'
      ];
    }
}
