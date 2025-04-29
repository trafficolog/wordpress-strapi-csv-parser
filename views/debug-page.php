<?php
/**
 * Отладочная страница
 */
if (!defined('ABSPATH')) {
    exit;
}

// Получаем последние 100 записей лога
global $wpdb;
$table_name = $wpdb->prefix . 'strapi_parser_logs';
$logs = $wpdb->get_results(
    "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 100",
    ARRAY_A
);
?>

<div class="wrap">
    <h1>Отладка парсера CSV</h1>
    
    <div class="debug-controls">
        <button id="refresh-logs" class="button button-primary">Обновить логи</button>
        <select id="log-filter">
            <option value="">Все уровни</option>
            <option value="debug">Debug</option>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
        </select>
        <button id="clear-logs" class="button">Очистить логи</button>
    </div>
    
    <div id="logs-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="150">Время</th>
                    <th width="80">Уровень</th>
                    <th>Сообщение</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr class="log-entry log-level-<?php echo esc_attr($log['level']); ?>">
                    <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['time']))); ?></td>
                    <td><span class="log-level log-level-<?php echo esc_attr($log['level']); ?>"><?php echo esc_html($log['level']); ?></span></td>
                    <td><?php echo esc_html($log['message']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.debug-controls {
    margin: 20px 0;
    display: flex;
    gap: 10px;
}
.log-level {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    color: white;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 10px;
}
.log-level-debug {
    background-color: #6c757d;
}
.log-level-info {
    background-color: #17a2b8;
}
.log-level-warning {
    background-color: #ffc107;
    color: #000;
}
.log-level-error {
    background-color: #dc3545;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Фильтрация логов по уровню
    $('#log-filter').on('change', function() {
        var level = $(this).val();
        
        if (level) {
            $('.log-entry').hide();
            $('.log-level-' + level).closest('tr').show();
        } else {
            $('.log-entry').show();
        }
    });
    
    // Обновление логов
    $('#refresh-logs').on('click', function() {
        location.reload();
    });
    
    // Очистка логов
    $('#clear-logs').on('click', function() {
        if (confirm('Вы уверены, что хотите очистить все логи?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'strapi_parser_clear_logs',
                    nonce: '<?php echo wp_create_nonce('strapi-parser-nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Ошибка при очистке логов: ' + response.data);
                    }
                },
                error: function() {
                    alert('Произошла ошибка при выполнении запроса');
                }
            });
        }
    });
});
</script>