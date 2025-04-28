<?php
/**
 * Страница журнала логирования
 *
 * @package StrapiCSVParser
 */

if (!defined('ABSPATH')) {
    exit;
}

// Цветовая схема для уровней логирования
$level_colors = [
    'debug' => '#007bff',
    'info' => '#28a745',
    'warning' => '#ffc107',
    'error' => '#dc3545'
];
?>

<div class="wrap strapi-parser-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="nav-tab-wrapper">
        <a href="?page=strapi-csv-parser" class="nav-tab"><?php _e('Парсер CSV', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-merge" class="nav-tab"><?php _e('Объединение профилей', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-logs" class="nav-tab nav-tab-active"><?php _e('Журнал', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-settings" class="nav-tab"><?php _e('Настройки', 'strapi-csv-parser'); ?></a>
    </div>
    
    <div class="strapi-parser-container">
        <!-- Фильтры -->
        <div class="log-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="strapi-csv-parser-logs">
                
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="level"><?php _e('Уровень:', 'strapi-csv-parser'); ?></label>
                        <select name="level" id="level">
                            <option value=""><?php _e('Все', 'strapi-csv-parser'); ?></option>
                            <option value="debug" <?php selected($filters['level'], 'debug'); ?>><?php _e('Отладка', 'strapi-csv-parser'); ?></option>
                            <option value="info" <?php selected($filters['level'], 'info'); ?>><?php _e('Информация', 'strapi-csv-parser'); ?></option>
                            <option value="warning" <?php selected($filters['level'], 'warning'); ?>><?php _e('Предупреждение', 'strapi-csv-parser'); ?></option>
                            <option value="error" <?php selected($filters['level'], 'error'); ?>><?php _e('Ошибка', 'strapi-csv-parser'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="source"><?php _e('Источник:', 'strapi-csv-parser'); ?></label>
                        <select name="source" id="source">
                            <option value=""><?php _e('Все', 'strapi-csv-parser'); ?></option>
                            <option value="parser" <?php selected($filters['source'], 'parser'); ?>><?php _e('Парсер', 'strapi-csv-parser'); ?></option>
                            <option value="api" <?php selected($filters['source'], 'api'); ?>><?php _e('API', 'strapi-csv-parser'); ?></option>
                            <option value="merger" <?php selected($filters['source'], 'merger'); ?>><?php _e('Объединение', 'strapi-csv-parser'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="file_id"><?php _e('ID файла:', 'strapi-csv-parser'); ?></label>
                        <input type="text" name="file_id" id="file_id" value="<?php echo esc_attr($filters['file_id']); ?>" placeholder="ID файла">
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="date_from"><?php _e('С:', 'strapi-csv-parser'); ?></label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
                    </div>
                    
                    <div class="filter-item">
                        <label for="date_to"><?php _e('По:', 'strapi-csv-parser'); ?></label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
                    </div>
                    
                    <div class="filter-item search-item">
                        <label for="search"><?php _e('Поиск:', 'strapi-csv-parser'); ?></label>
                        <input type="text" name="search" id="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Поиск по сообщению">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="button"><?php _e('Применить фильтры', 'strapi-csv-parser'); ?></button>
                        <a href="?page=strapi-csv-parser-logs" class="button"><?php _e('Сбросить', 'strapi-csv-parser'); ?></a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Таблица логов -->
        <div class="logs-table-container">
            <?php if (empty($logs_data['logs'])): ?>
                <div class="no-logs-message">
                    <p><?php _e('Логи не найдены. Попробуйте изменить параметры фильтрации.', 'strapi-csv-parser'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped logs-table">
                    <thead>
                        <tr>
                            <th class="column-time"><?php _e('Время', 'strapi-csv-parser'); ?></th>
                            <th class="column-level"><?php _e('Уровень', 'strapi-csv-parser'); ?></th>
                            <th class="column-source"><?php _e('Источник', 'strapi-csv-parser'); ?></th>
                            <th class="column-message"><?php _e('Сообщение', 'strapi-csv-parser'); ?></th>
                            <th class="column-file"><?php _e('ID файла', 'strapi-csv-parser'); ?></th>
                            <th class="column-actions"><?php _e('Действия', 'strapi-csv-parser'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs_data['logs'] as $log): ?>
                            <tr>
                                <td><?php echo esc_html(date('d.m.Y H:i:s', strtotime($log['time']))); ?></td>
                                <td>
                                    <?php 
                                    $level = $log['level'];
                                    $color = isset($level_colors[$level]) ? $level_colors[$level] : '#777';
                                    ?>
                                    <span class="log-level" style="background-color: <?php echo $color; ?>"><?php echo esc_html($level); ?></span>
                                </td>
                                <td><?php echo esc_html($log['source']); ?></td>
                                <td class="log-message"><?php echo esc_html($log['message']); ?></td>
                                <td><?php echo $log['file_id'] ? esc_html($log['file_id']) : '—'; ?></td>
                                <td>
                                    <?php if (!empty($log['context'])): ?>
                                        <button class="button button-small toggle-details" data-id="<?php echo esc_attr($log['id']); ?>"><?php _e('Детали', 'strapi-csv-parser'); ?></button>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($log['context'])): ?>
                                <tr class="log-details-row" id="log-details-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                    <td colspan="6">
                                        <div class="log-details">
                                            <h4><?php _e('Контекст:', 'strapi-csv-parser'); ?></h4>
                                            <pre><?php echo esc_html(json_encode($log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Пагинация -->
                <?php if ($logs_data['pagination']['total_pages'] > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(
                                    _n(
                                        '%s запись', 
                                        '%s записей', 
                                        $logs_data['pagination']['total'], 
                                        'strapi-csv-parser'
                                    ),
                                    number_format_i18n($logs_data['pagination']['total'])
                                ); ?>
                            </span>
                            
                            <span class="pagination-links">
                                <?php
                                $current_page = $logs_data['pagination']['page'];
                                $total_pages = $logs_data['pagination']['total_pages'];
                                
                                // Формирование URL с учетом текущих фильтров
                                $base_url = '?page=strapi-csv-parser-logs';
                                foreach ($filters as $key => $value) {
                                    if (!empty($value)) {
                                        $base_url .= '&' . urlencode($key) . '=' . urlencode($value);
                                    }
                                }
                                
                                // Первая страница
                                if ($current_page > 1) {
                                    echo '<a class="first-page button" href="' . esc_url($base_url . '&log_page=1') . '"><span class="screen-reader-text">' . __('Первая страница', 'strapi-csv-parser') . '</span><span aria-hidden="true">&laquo;</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
                                }
                                
                                // Предыдущая страница
                                if ($current_page > 1) {
                                    $prev_page = max(1, $current_page - 1);
                                    echo '<a class="prev-page button" href="' . esc_url($base_url . '&log_page=' . $prev_page) . '"><span class="screen-reader-text">' . __('Предыдущая страница', 'strapi-csv-parser') . '</span><span aria-hidden="true">&lsaquo;</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
                                }
                                
                                // Текущая страница
                                echo '<span class="paging-input">' . $current_page . ' из <span class="total-pages">' . $total_pages . '</span></span>';
                                
                                // Следующая страница
                                if ($current_page < $total_pages) {
                                    $next_page = min($total_pages, $current_page + 1);
                                    echo '<a class="next-page button" href="' . esc_url($base_url . '&log_page=' . $next_page) . '"><span class="screen-reader-text">' . __('Следующая страница', 'strapi-csv-parser') . '</span><span aria-hidden="true">&rsaquo;</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
                                }
                                
                                // Последняя страница
                                if ($current_page < $total_pages) {
                                    echo '<a class="last-page button" href="' . esc_url($base_url . '&log_page=' . $total_pages) . '"><span class="screen-reader-text">' . __('Последняя страница', 'strapi-csv-parser') . '</span><span aria-hidden="true">&raquo;</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.strapi-parser-container {
    margin-top: 20px;
}

.log-filters {
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.filter-item {
    margin-right: 15px;
    margin-bottom: 10px;
}

.filter-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.search-item {
    flex-grow: 1;
    max-width: 300px;
}

.filter-buttons {
    align-self: flex-end;
    margin-bottom: 10px;
}

.logs-table-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.logs-table {
    margin: 0;
}

.logs-table .column-time {
    width: 15%;
}

.logs-table .column-level {
    width: 10%;
}

.logs-table .column-source {
    width: 10%;
}

.logs-table .column-file {
    width: 10%;
}

.logs-table .column-actions {
    width: 10%;
}

.log-level {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    color: white;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.log-message {
    white-space: normal;
    word-break: break-word;
}

.log-details {
    background: #f8f8f8;
    padding: 10px 15px;
    border-radius: 3px;
    margin: 10px 0;
}

.log-details pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    background: #f1f1f1;
    padding: 10px;
    border-radius: 3px;
    margin: 10px 0;
    max-height: 300px;
    overflow-y: auto;
}

.no-logs-message {
    padding: 20px;
    text-align: center;
    font-style: italic;
    color: #777;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Переключение видимости деталей лога
    $('.toggle-details').on('click', function() {
        var id = $(this).data('id');
        $('#log-details-' + id).toggle();
        
        if ($(this).text() === '<?php _e('Детали', 'strapi-csv-parser'); ?>') {
            $(this).text('<?php _e('Скрыть', 'strapi-csv-parser'); ?>');
        } else {
            $(this).text('<?php _e('Детали', 'strapi-csv-parser'); ?>');
        }
    });
});
</script>
