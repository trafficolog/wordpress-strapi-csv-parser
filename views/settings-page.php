<?php
/**
 * Страница настроек плагина
 *
 * @package StrapiCSVParser
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap strapi-parser-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="nav-tab-wrapper">
        <a href="?page=strapi-csv-parser" class="nav-tab"><?php _e('Парсер CSV', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-merge" class="nav-tab"><?php _e('Объединение профилей', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-logs" class="nav-tab"><?php _e('Журнал', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-settings" class="nav-tab nav-tab-active"><?php _e('Настройки', 'strapi-csv-parser'); ?></a>
    </div>
    
    <div class="strapi-parser-container">
        <div class="strapi-settings-wrapper">
            <form method="post" action="">
                <?php wp_nonce_field('strapi_parser_settings', 'strapi_parser_settings_nonce'); ?>
                
                <div class="settings-section">
                    <h2><?php _e('Настройки API Strapi', 'strapi-csv-parser'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="strapi_url"><?php _e('URL API Strapi', 'strapi-csv-parser'); ?></label>
                            </th>
                            <td>
                                <input type="url" name="strapi_url" id="strapi_url" class="regular-text" value="<?php echo esc_attr($settings['strapi_url']); ?>" required>
                                <p class="description"><?php _e('Полный URL к вашему Strapi API, например: https://api.example.com', 'strapi-csv-parser'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="api_token"><?php _e('API Token', 'strapi-csv-parser'); ?></label>
                            </th>
                            <td>
                                <input type="password" name="api_token" id="api_token" class="regular-text" value="<?php echo esc_attr($settings['api_token']); ?>" autocomplete="new-password">
                                <button type="button" id="toggle-token" class="button button-secondary"><?php _e('Показать', 'strapi-csv-parser'); ?></button>
                                <p class="description"><?php _e('Токен доступа к API Strapi с правами на создание и редактирование профилей компаний', 'strapi-csv-parser'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="settings-section">
                    <h2><?php _e('Настройки парсера', 'strapi-csv-parser'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="batch_size"><?php _e('Размер пакета', 'strapi-csv-parser'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="batch_size" id="batch_size" class="small-text" value="<?php echo esc_attr($settings['batch_size']); ?>" min="1" max="500" required>
                                <p class="description"><?php _e('Количество записей, обрабатываемых за один запрос (рекомендуется 50-100)', 'strapi-csv-parser'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="timeout"><?php _e('Таймаут', 'strapi-csv-parser'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="timeout" id="timeout" class="small-text" value="<?php echo esc_attr($settings['timeout']); ?>" min="10" max="600" required>
                                <p class="description"><?php _e('Максимальное время ожидания ответа от API (в секундах)', 'strapi-csv-parser'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e('Режим отладки', 'strapi-csv-parser'); ?>
                            </th>
                            <td>
                                <label for="debug">
                                    <input type="checkbox" name="debug" id="debug" <?php checked($settings['debug']); ?>>
                                    <?php _e('Включить подробное логирование (больше информации в журнале)', 'strapi-csv-parser'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="settings-section">
                    <h2><?php _e('Дополнительные настройки', 'strapi-csv-parser'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php _e('Очистка данных', 'strapi-csv-parser'); ?>
                            </th>
                            <td>
                                <button type="button" id="clear-logs" class="button button-secondary"><?php _e('Очистить логи', 'strapi-csv-parser'); ?></button>
                                <button type="button" id="clear-temp" class="button button-secondary"><?php _e('Очистить временные файлы', 'strapi-csv-parser'); ?></button>
                                <p class="description"><?php _e('Удаление временных данных плагина', 'strapi-csv-parser'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Сохранить настройки', 'strapi-csv-parser'); ?>">
                </p>
            </form>
            
            <div class="test-connection-section">
                <h2><?php _e('Проверка подключения', 'strapi-csv-parser'); ?></h2>
                <p><?php _e('Проверьте подключение к Strapi API с использованием текущих настроек.', 'strapi-csv-parser'); ?></p>
                <button id="test-connection" class="button button-secondary"><?php _e('Проверить подключение', 'strapi-csv-parser'); ?></button>
                <div id="connection-result" class="connection-result"></div>
            </div>
        </div>
    </div>
</div>

<style>
.strapi-parser-container {
    margin-top: 20px;
}

.strapi-settings-wrapper {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.settings-section {
    margin-bottom: 30px;
}

.settings-section h2 {
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.test-connection-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.connection-result {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
    display: none;
}

.connection-success {
    background-color: #dff0d8;
    color: #3c763d;
    border: 1px solid #d6e9c6;
}

.connection-error {
    background-color: #f2dede;
    color: #a94442;
    border: 1px solid #ebccd1;
}

.spinner-container {
    display: flex;
    align-items: center;
}

.spinner-container .spinner {
    float: none;
    margin-right: 10px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Переключение видимости токена
    $('#toggle-token').on('click', function() {
        var tokenField = $('#api_token');
        
        if (tokenField.attr('type') === 'password') {
            tokenField.attr('type', 'text');
            $(this).text('<?php _e('Скрыть', 'strapi-csv-parser'); ?>');
        } else {
            tokenField.attr('type', 'password');
            $(this).text('<?php _e('Показать', 'strapi-csv-parser'); ?>');
        }
    });
    
    // Проверка подключения к API
    $('#test-connection').on('click', function() {
        var button = $(this);
        var resultContainer = $('#connection-result');
        
        button.prop('disabled', true);
        resultContainer.removeClass('connection-success connection-error').hide();
        
        // Показываем спиннер
        resultContainer.html('<div class="spinner-container"><span class="spinner is-active"></span> <?php _e('Проверка подключения...', 'strapi-csv-parser'); ?></div>');
        resultContainer.show();
        
        // Отправляем запрос на проверку
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'strapi_parser_test_connection',
                nonce: strapiParser.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultContainer.html('<p><strong><?php _e('Подключение успешно!', 'strapi-csv-parser'); ?></strong></p>' +
                        '<p><?php _e('Найдено профилей в Strapi:', 'strapi-csv-parser'); ?> ' + response.data.count + '</p>');
                    resultContainer.addClass('connection-success');
                } else {
                    resultContainer.html('<p><strong><?php _e('Ошибка подключения:', 'strapi-csv-parser'); ?></strong> ' + response.data + '</p>');
                    resultContainer.addClass('connection-error');
                }
            },
            error: function() {
                resultContainer.html('<p><strong><?php _e('Ошибка подключения:', 'strapi-csv-parser'); ?></strong> <?php _e('Не удалось выполнить запрос к серверу', 'strapi-csv-parser'); ?></p>');
                resultContainer.addClass('connection-error');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Очистка логов
    $('#clear-logs').on('click', function() {
        if (confirm('<?php _e('Вы уверены, что хотите очистить все логи? Это действие нельзя отменить.', 'strapi-csv-parser'); ?>')) {
            var button = $(this);
            button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'strapi_parser_clear_logs',
                    nonce: strapiParser.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Логи успешно очищены!', 'strapi-csv-parser'); ?>');
                    } else {
                        alert('<?php _e('Ошибка при очистке логов:', 'strapi-csv-parser'); ?> ' + response.data);
                    }
                },
                error: function() {
                    alert('<?php _e('Произошла ошибка при выполнении запроса', 'strapi-csv-parser'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        }
    });
    
    // Очистка временных файлов
    $('#clear-temp').on('click', function() {
        if (confirm('<?php _e('Вы уверены, что хотите удалить все временные файлы? Это действие нельзя отменить.', 'strapi-csv-parser'); ?>')) {
            var button = $(this);
            button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'strapi_parser_clear_temp',
                    nonce: strapiParser.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Временные файлы успешно удалены!', 'strapi-csv-parser'); ?>');
                    } else {
                        alert('<?php _e('Ошибка при удалении временных файлов:', 'strapi-csv-parser'); ?> ' + response.data);
                    }
                },
                error: function() {
                    alert('<?php _e('Произошла ошибка при выполнении запроса', 'strapi-csv-parser'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        }
    });
});
</script>
