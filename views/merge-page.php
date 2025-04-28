<?php
/**
 * Страница объединения профилей
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
        <a href="?page=strapi-csv-parser-merge" class="nav-tab nav-tab-active"><?php _e('Объединение профилей', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-logs" class="nav-tab"><?php _e('Журнал', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-settings" class="nav-tab"><?php _e('Настройки', 'strapi-csv-parser'); ?></a>
    </div>
    
    <div class="strapi-parser-container">
        <div class="strapi-parser-section">
            <h2><?php _e('Объединение профилей компаний', 'strapi-csv-parser'); ?></h2>
            <p><?php _e('Объедините дубликаты профилей компаний, которые были импортированы из разных источников.', 'strapi-csv-parser'); ?></p>
            
            <?php
            // Показываем статистику по источникам
            if (isset($sources_stats) && $sources_stats['success']) :
            ?>
            <div class="stats-container">
                <h3><?php _e('Статистика по источникам', 'strapi-csv-parser'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Источник', 'strapi-csv-parser'); ?></th>
                            <th><?php _e('Количество профилей', 'strapi-csv-parser'); ?></th>
                            <th><?php _e('Последнее обновление', 'strapi-csv-parser'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources_stats['stats'] as $source) : ?>
                        <tr>
                            <td><?php echo esc_html($source['source']); ?></td>
                            <td><?php echo intval($source['count']); ?></td>
                            <td><?php echo esc_html($source['lastUpdated'] ? date('d.m.Y H:i', strtotime($source['lastUpdated'])) : '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php 
            elseif (isset($sources_stats) && !$sources_stats['success']) : 
            ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($sources_stats['error']); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="merge-actions">
                <div class="merge-button-container">
                    <button id="merge-by-phone" class="button button-primary button-large"><?php _e('Объединить профили по номеру телефона', 'strapi-csv-parser'); ?></button>
                    <p class="description"><?php _e('Запустить автоматическое объединение профилей компаний, у которых совпадают номера телефонов.', 'strapi-csv-parser'); ?></p>
                </div>
                
                <div class="merge-status hidden">
                    <div class="spinner is-active"></div>
                    <span class="status-text"><?php _e('Выполняется объединение...', 'strapi-csv-parser'); ?></span>
                </div>
                
                <div class="merge-results hidden"></div>
            </div>
            
            <div class="duplicates-container">
                <h3><?php _e('Дубликаты профилей', 'strapi-csv-parser'); ?></h3>
                
                <div class="duplicates-tabs">
                    <a href="#duplicates-taxid" class="duplicate-tab active" data-type="taxId"><?php _e('По ИНН', 'strapi-csv-parser'); ?></a>
                    <a href="#duplicates-name" class="duplicate-tab" data-type="name"><?php _e('По названию', 'strapi-csv-parser'); ?></a>
                </div>
                
                <div class="duplicates-content">
                    <!-- Дубликаты по ИНН -->
                    <div id="duplicates-taxid" class="duplicate-content active">
                        <?php if (isset($duplicates_taxid) && $duplicates_taxid['success']) : ?>
                        
                        <?php if ($duplicates_taxid['count'] > 0) : ?>
                        <p><?php printf(__('Найдено %d профилей с одинаковыми ИНН.', 'strapi-csv-parser'), $duplicates_taxid['count']); ?></p>
                        <div class="duplicates-list">
                            <?php 
                            // Группируем дубликаты по ИНН
                            $taxid_groups = [];
                            foreach ($duplicates_taxid['duplicates'] as $profile) {
                                $taxId = $profile['attributes']['taxId'] ?? '';
                                if (!empty($taxId)) {
                                    $taxid_groups[$taxId][] = $profile;
                                }
                            }
                            
                            foreach ($taxid_groups as $taxId => $profiles) :
                                if (count($profiles) < 2) continue;
                            ?>
                            <div class="duplicate-group">
                                <h4><?php printf(__('Профили с ИНН: %s', 'strapi-csv-parser'), $taxId); ?></h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('ID', 'strapi-csv-parser'); ?></th>
                                            <th><?php _e('Название', 'strapi-csv-parser'); ?></th>
                                            <th><?php _e('Источник', 'strapi-csv-parser'); ?></th>
                                            <th><?php _e('Действие', 'strapi-csv-parser'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($profiles as $profile) : ?>
                                        <tr>
                                            <td><?php echo intval($profile['id']); ?></td>
                                            <td><?php echo esc_html($profile['attributes']['name'] ?? ''); ?></td>
                                            <td><?php 
                                                $sources = [];
                                                if (!empty($profile['attributes']['dataSources'])) {
                                                    $dataSources = $profile['attributes']['dataSources'];
                                                    if (isset($dataSources['yandexDirectories'])) $sources[] = 'Яндекс.Справочник';
                                                    if (isset($dataSources['searchBase'])) $sources[] = 'SearchBase';
                                                    if (isset($dataSources['yandexMaps'])) $sources[] = 'Яндекс.Карты';
                                                    if (isset($dataSources['twoGis'])) $sources[] = '2GIS';
                                                    if (isset($dataSources['rusBase'])) $sources[] = 'RusBase';
                                                }
                                                echo esc_html(implode(', ', $sources));
                                            ?></td>
                                            <td><button class="button merge-profile-button" data-target="<?php echo intval($profile['id']); ?>"><?php _e('Сделать основным', 'strapi-csv-parser'); ?></button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else : ?>
                        <p><?php _e('Дубликатов с одинаковыми ИНН не найдено.', 'strapi-csv-parser'); ?></p>
                        <?php endif; ?>
                        
                        <?php else : ?>
                        <div class="notice notice-error">
                            <p><?php echo isset($duplicates_taxid['error']) ? esc_html($duplicates_taxid['error']) : __('Ошибка при получении дубликатов', 'strapi-csv-parser'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Дубликаты по названию -->
                    <div id="duplicates-name" class="duplicate-content">
                        <?php if (isset($duplicates_name) && $duplicates_name['success']) : ?>
                        
                        <?php if ($duplicates_name['count'] > 0) : ?>
                        <p><?php printf(__('Найдено %d профилей с похожими названиями.', 'strapi-csv-parser'), $duplicates_name['count']); ?></p>
                        <div class="duplicates-list">
                            <?php 
                            // Группируем дубликаты по названию
                            $name_groups = [];
                            foreach ($duplicates_name['duplicates'] as $profile) {
                                $name = $profile['attributes']['name'] ?? '';
                                if (!empty($name)) {
                                    $name_groups[$name][] = $profile;
                                }
                            }
                            
                            foreach ($name_groups as $name => $profiles) :
                                if (count($profiles) < 2) continue;
                            ?>
                            <div class="duplicate-group">
                                <h4><?php printf(__('Профили с названием: %s', 'strapi-csv-parser'), $name); ?></h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('ID', 'strapi-csv-parser'); ?></th>
                                            <th><?php _e('ИНН', 'strapi-csv-parser'); ?></th>
                                            <th><?php _e('Адрес', 'strapi-csv-parser'); ?></th>
                                            <th><?php _e('Источник', 'strapi-csv-parser'); ?></th>
                                            <th><?php _e('Действие', 'strapi-csv-parser'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($profiles as $profile) : ?>
                                        <tr>
                                            <td><?php echo intval($profile['id']); ?></td>
                                            <td><?php echo esc_html($profile['attributes']['taxId'] ?? ''); ?></td>
                                            <td><?php echo esc_html($profile['attributes']['address'] ?? ''); ?></td>
                                            <td><?php 
                                                $sources = [];
                                                if (!empty($profile['attributes']['dataSources'])) {
                                                    $dataSources = $profile['attributes']['dataSources'];
                                                    if (isset($dataSources['yandexDirectories'])) $sources[] = 'Яндекс.Справочник';
                                                    if (isset($dataSources['searchBase'])) $sources[] = 'SearchBase';
                                                    if (isset($dataSources['yandexMaps'])) $sources[] = 'Яндекс.Карты';
                                                    if (isset($dataSources['twoGis'])) $sources[] = '2GIS';
                                                    if (isset($dataSources['rusBase'])) $sources[] = 'RusBase';
                                                }
                                                echo esc_html(implode(', ', $sources));
                                            ?></td>
                                            <td><button class="button merge-profile-button" data-target="<?php echo intval($profile['id']); ?>"><?php _e('Сделать основным', 'strapi-csv-parser'); ?></button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else : ?>
                        <p><?php _e('Дубликатов с похожими названиями не найдено.', 'strapi-csv-parser'); ?></p>
                        <?php endif; ?>
                        
                        <?php else : ?>
                        <div class="notice notice-error">
                            <p><?php echo isset($duplicates_name['error']) ? esc_html($duplicates_name['error']) : __('Ошибка при получении дубликатов', 'strapi-csv-parser'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Диалог подтверждения объединения -->
<div id="merge-confirm-dialog" style="display:none;" title="<?php _e('Подтверждение объединения', 'strapi-csv-parser'); ?>">
    <p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span>
    <?php _e('Вы собираетесь объединить несколько профилей в один, выбрав основной профиль. Данное действие нельзя отменить. Продолжить?', 'strapi-csv-parser'); ?></p>
    
    <div class="merge-details">
        <p><?php _e('Основной профиль (останется):', 'strapi-csv-parser'); ?> <strong id="target-profile-info"></strong></p>
        <p><?php _e('Профили для объединения (будут удалены):', 'strapi-csv-parser'); ?></p>
        <ul id="source-profiles-list"></ul>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Переключение вкладок дубликатов
    $('.duplicate-tab').on('click', function(e) {
        e.preventDefault();
        
        // Активная вкладка
        $('.duplicate-tab').removeClass('active');
        $(this).addClass('active');
        
        // Активное содержимое
        var target = $(this).attr('href');
        $('.duplicate-content').removeClass('active');
        $(target).addClass('active');
        
        // Загрузка дубликатов, если нужно
        var type = $(this).data('type');
        loadDuplicates(type);
    });
    
    // Загрузка дубликатов по типу
    function loadDuplicates(type) {
        var contentContainer = $('#duplicates-' + type.toLowerCase());
        
        // Если содержимое уже загружено, не делаем запрос
        if (contentContainer.data('loaded')) {
            return;
        }
        
        // Показываем индикатор загрузки
        contentContainer.html('<div class="spinner is-active"></div>');
        
        // Запрашиваем дубликаты
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'strapi_parser_get_duplicates',
                type: type,
                nonce: strapiParser.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Обновляем содержимое
                    contentContainer.html(response.data.html);
                } else {
                    contentContainer.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
                
                // Помечаем как загруженное
                contentContainer.data('loaded', true);
            },
            error: function() {
                contentContainer.html('<div class="notice notice-error"><p><?php _e('Ошибка при загрузке данных', 'strapi-csv-parser'); ?></p></div>');
            }
        });
    }
    
    // Обработчик кнопки "Сделать основным"
    $(document).on('click', '.merge-profile-button', function() {
        var targetId = $(this).data('target');
        var targetName = $(this).closest('tr').find('td:nth-child(2)').text();
        var groupContainer = $(this).closest('.duplicate-group');
        
        // Собираем ID всех профилей в группе, кроме выбранного
        var sourceIds = [];
        var sourceNames = [];
        
        groupContainer.find('tr').each(function() {
            var id = $(this).find('.merge-profile-button').data('target');
            var name = $(this).find('td:nth-child(2)').text();
            
            if (id && id != targetId) {
                sourceIds.push(id);
                sourceNames.push(name);
            }
        });
        
        // Заполняем информацию для диалога
        $('#target-profile-info').text(targetId + ': ' + targetName);
        
        var sourcesList = '';
        for (var i = 0; i < sourceIds.length; i++) {
            sourcesList += '<li>' + sourceIds[i] + ': ' + sourceNames[i] + '</li>';
        }
        $('#source-profiles-list').html(sourcesList);
        
        // Показываем диалог подтверждения
        $('#merge-confirm-dialog').dialog({
            resizable: false,
            height: "auto",
            width: 500,
            modal: true,
            buttons: {
                "<?php _e('Объединить', 'strapi-csv-parser'); ?>": function() {
                    mergeProfiles(targetId, sourceIds);
                    $(this).dialog('close');
                },
                "<?php _e('Отмена', 'strapi-csv-parser'); ?>": function() {
                    $(this).dialog('close');
                }
            }
        });
    });
    
    // Функция объединения профилей
    function mergeProfiles(targetId, sourceIds) {
        // Отключаем кнопки в текущей группе
        var buttons = $('.merge-profile-button');
        buttons.prop('disabled', true);
        
        // Показываем индикатор загрузки рядом с целевым профилем
        $('.merge-profile-button[data-target="' + targetId + '"]').after('<span class="spinner is-active" style="float:none;"></span>');
        
        // Последовательно объединяем профили
        var mergedCount = 0;
        var failedCount = 0;
        
        function mergeNext() {
            if (sourceIds.length === 0) {
                // Все профили обработаны
                buttons.prop('disabled', false);
                $('.spinner').remove();
                
                // Показываем результат
                alert('<?php _e('Объединение завершено. Успешно: ', 'strapi-csv-parser'); ?>' + mergedCount + ', <?php _e('ошибок: ', 'strapi-csv-parser'); ?>' + failedCount);
                
                // Обновляем страницу для отображения актуальных данных
                location.reload();
                return;
            }
            
            var sourceId = sourceIds.shift();
            
            // Отправляем запрос на объединение
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'strapi_parser_merge_specific_profiles',
                    source_id: sourceId,
                    target_id: targetId,
                    nonce: strapiParser.nonce
                },
                success: function(response) {
                    if (response.success) {
                        mergedCount++;
                    } else {
                        failedCount++;
                        console.error('Ошибка объединения профилей:', response.data);
                    }
                    
                    // Продолжаем с следующим профилем
                    mergeNext();
                },
                error: function() {
                    failedCount++;
                    console.error('Сетевая ошибка при объединении профилей');
                    
                    // Продолжаем с следующим профилем
                    mergeNext();
                }
            });
        }
        
        // Запускаем процесс объединения
        mergeNext();
    }
    
    // Обработчик кнопки "Объединить профили по номеру телефона"
    $('#merge-by-phone').on('click', function() {
        var button = $(this);
        var statusContainer = $('.merge-status');
        var resultsContainer = $('.merge-results');
        
        // Отключаем кнопку и показываем индикатор загрузки
        button.prop('disabled', true);
        statusContainer.removeClass('hidden');
        resultsContainer.addClass('hidden');
        
        // Отправляем запрос на объединение
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'strapi_parser_merge_profiles',
                nonce: strapiParser.nonce
            },
            success: function(response) {
                statusContainer.addClass('hidden');
                resultsContainer.removeClass('hidden');
                
                if (response.success) {
                    var message = '<?php _e('Объединение профилей успешно завершено. ', 'strapi-csv-parser'); ?>';
                    message += '<?php _e('Объединено профилей: ', 'strapi-csv-parser'); ?>' + response.data.merged;
                    
                    resultsContainer.html('<div class="notice notice-success"><p>' + message + '</p></div>');
                } else {
                    resultsContainer.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
                
                // Активируем кнопку
                button.prop('disabled', false);
            },
            error: function() {
                statusContainer.addClass('hidden');
                resultsContainer.removeClass('hidden');
                resultsContainer.html('<div class="notice notice-error"><p><?php _e('Произошла ошибка при выполнении запроса', 'strapi-csv-parser'); ?></p></div>');
                
                // Активируем кнопку
                button.prop('disabled', false);
            }
        });
    });
});
</script>

<style>
.strapi-parser-wrap .nav-tab-wrapper {
    margin-bottom: 15px;
}

.strapi-parser-container {
    margin: 20px 0;
}

.strapi-parser-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.stats-container {
    margin: 20px 0;
}

.merge-actions {
    margin: 30px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
}

.merge-button-container {
    margin-bottom: 15px;
}

.merge-status {
    display: flex;
    align-items: center;
    margin: 15px 0;
}

.merge-status .spinner {
    margin-right: 10px;
}

.duplicates-tabs {
    margin: 20px 0 0;
    border-bottom: 1px solid #ccd0d4;
}

.duplicate-tab {
    display: inline-block;
    padding: 10px 15px;
    margin: 0 5px -1px 0;
    font-weight: 600;
    border: 1px solid transparent;
    text-decoration: none;
    background: #f1f1f1;
}

.duplicate-tab.active {
    border-color: #ccd0d4;
    border-bottom-color: #fff;
    background: #fff;
}

.duplicates-content {
    margin-top: 20px;
}

.duplicate-content {
    display: none;
}

.duplicate-content.active {
    display: block;
}

.duplicate-group {
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f1f1f1;
}

.duplicate-group h4 {
    margin: 15px 0;
    padding: 10px;
    background: #f6f7f7;
    border-left: 4px solid #2271b1;
}

.hidden {
    display: none;
}
</style>