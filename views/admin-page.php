<?php
/**
 * Главная страница плагина
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
        <a href="?page=strapi-csv-parser" class="nav-tab nav-tab-active"><?php _e('Парсер CSV', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-merge" class="nav-tab"><?php _e('Объединение профилей', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-logs" class="nav-tab"><?php _e('Журнал', 'strapi-csv-parser'); ?></a>
        <a href="?page=strapi-csv-parser-settings" class="nav-tab"><?php _e('Настройки', 'strapi-csv-parser'); ?></a>
    </div>
    
    <div class="strapi-parser-container">
        <div class="strapi-parser-section">
            <h2><?php _e('Импорт данных из CSV в Strapi', 'strapi-csv-parser'); ?></h2>
            <p><?php _e('Выберите источник данных и загрузите CSV файл для парсинга и отправки данных в Strapi API.', 'strapi-csv-parser'); ?></p>
            
            <div class="strapi-parser-sources">
                <div class="strapi-parser-source-tabs">
                    <a href="#yandex-directories" class="source-tab active" data-source="YandexDirectories"><?php _e('Яндекс.Справочник', 'strapi-csv-parser'); ?></a>
                    <a href="#search-base" class="source-tab" data-source="SearchBase"><?php _e('SearchBase', 'strapi-csv-parser'); ?></a>
                </div>
                
                <div class="strapi-parser-source-content">
                    <!-- Яндекс.Справочник -->
                    <div id="yandex-directories" class="source-content active">
                        <h3><?php _e('Импорт из Яндекс.Справочника', 'strapi-csv-parser'); ?></h3>
                        
                        <div class="upload-form">
                            <form method="post" enctype="multipart/form-data" id="yandex-directories-form">
                                <div class="form-group">
                                    <label for="yandex-directories-file"><?php _e('Выберите CSV файл:', 'strapi-csv-parser'); ?></label>
                                    <input type="file" name="csv_file" id="yandex-directories-file" accept=".csv" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="yandex-directories-category"><?php _e('Выберите категорию:', 'strapi-csv-parser'); ?></label>
                                    <select name="category_id" id="yandex-directories-category" class="category-select" required>
                                        <option value=""><?php _e('-- Выберите категорию --', 'strapi-csv-parser'); ?></option>
                                        <!-- Категории будут загружены через AJAX -->
                                    </select>
                                </div>
                                
                                <div class="form-group hidden" id="yandex-directories-subcategory-group">
                                    <label for="yandex-directories-subcategory"><?php _e('Выберите подкатегорию:', 'strapi-csv-parser'); ?></label>
                                    <select name="subcategory_id" id="yandex-directories-subcategory" class="subcategory-select">
                                        <option value=""><?php _e('-- Выберите подкатегорию --', 'strapi-csv-parser'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="form-group hidden" id="yandex-directories-subsubcategory-group">
                                    <label for="yandex-directories-subsubcategory"><?php _e('Выберите подподкатегорию:', 'strapi-csv-parser'); ?></label>
                                    <select name="subsubcategory_id" id="yandex-directories-subsubcategory" class="subsubcategory-select">
                                        <option value=""><?php _e('-- Выберите подподкатегорию --', 'strapi-csv-parser'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="form-actions">
                                    <input type="hidden" name="source_type" value="YandexDirectories">
                                    <button type="submit" class="button button-primary upload-button"><?php _e('Загрузить файл', 'strapi-csv-parser'); ?></button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="process-container hidden">
                            <div class="file-info"></div>
                            <div class="progress-info">
                                <div class="progress-bar-wrapper">
                                    <div class="progress-bar" style="width: 0%;">0%</div>
                                </div>
                                <div class="progress-stats">
                                    <span class="processed">0</span>/<span class="total">0</span> записей
                                </div>
                            </div>
                            <div class="process-actions">
                                <button class="button button-primary start-process-button" data-source="YandexDirectories"><?php _e('Начать обработку', 'strapi-csv-parser'); ?></button>
                                <button class="button cancel-process-button"><?php _e('Отмена', 'strapi-csv-parser'); ?></button>
                            </div>
                            <div class="process-results"></div>
                        </div>
                    </div>
                    
                    <!-- SearchBase -->
                    <div id="search-base" class="source-content">
                        <h3><?php _e('Импорт из SearchBase', 'strapi-csv-parser'); ?></h3>
                        
                        <div class="upload-form">
                            <form method="post" enctype="multipart/form-data" id="search-base-form">
                                <div class="form-group">
                                    <label for="search-base-file"><?php _e('Выберите CSV файл:', 'strapi-csv-parser'); ?></label>
                                    <input type="file" name="csv_file" id="search-base-file" accept=".csv" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="search-base-category"><?php _e('Выберите категорию:', 'strapi-csv-parser'); ?></label>
                                    <select name="category_id" id="search-base-category" class="category-select" required>
                                        <option value=""><?php _e('-- Выберите категорию --', 'strapi-csv-parser'); ?></option>
                                        <!-- Категории будут загружены через AJAX -->
                                    </select>
                                </div>
                                
                                <div class="form-group hidden" id="search-base-subcategory-group">
                                    <label for="search-base-subcategory"><?php _e('Выберите подкатегорию:', 'strapi-csv-parser'); ?></label>
                                    <select name="subcategory_id" id="search-base-subcategory" class="subcategory-select">
                                        <option value=""><?php _e('-- Выберите подкатегорию --', 'strapi-csv-parser'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="form-group hidden" id="search-base-subsubcategory-group">
                                    <label for="search-base-subsubcategory"><?php _e('Выберите подподкатегорию:', 'strapi-csv-parser'); ?></label>
                                    <select name="subsubcategory_id" id="search-base-subsubcategory" class="subsubcategory-select">
                                        <option value=""><?php _e('-- Выберите подподкатегорию --', 'strapi-csv-parser'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="form-actions">
                                    <input type="hidden" name="source_type" value="SearchBase">
                                    <button type="submit" class="button button-primary upload-button"><?php _e('Загрузить файл', 'strapi-csv-parser'); ?></button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="process-container hidden">
                            <div class="file-info"></div>
                            <div class="progress-info">
                                <div class="progress-bar-wrapper">
                                    <div class="progress-bar" style="width: 0%;">0%</div>
                                </div>
                                <div class="progress-stats">
                                    <span class="processed">0</span>/<span class="total">0</span> записей
                                </div>
                            </div>
                            <div class="process-actions">
                                <button class="button button-primary start-process-button" data-source="SearchBase"><?php _e('Начать обработку', 'strapi-csv-parser'); ?></button>
                                <button class="button cancel-process-button"><?php _e('Отмена', 'strapi-csv-parser'); ?></button>
                            </div>
                            <div class="process-results"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="strapi-parser-section">
            <h2><?php _e('Инструкция по использованию', 'strapi-csv-parser'); ?></h2>
            <div class="instructions">
                <ol>
                    <li><?php _e('Выберите тип источника данных (Яндекс.Справочник или SearchBase).', 'strapi-csv-parser'); ?></li>
                    <li><?php _e('Загрузите CSV файл с данными компаний.', 'strapi-csv-parser'); ?></li>
                    <li><?php _e('Выберите категорию и подкатегорию для импортируемых профилей.', 'strapi-csv-parser'); ?></li>
                    <li><?php _e('Запустите процесс импорта, нажав кнопку "Начать обработку".', 'strapi-csv-parser'); ?></li>
                    <li><?php _e('После завершения импорта из обоих источников перейдите на вкладку "Объединение профилей" для объединения дубликатов.', 'strapi-csv-parser'); ?></li>
                </ol>
                <p><strong><?php _e('Внимание:', 'strapi-csv-parser'); ?></strong> <?php _e('Убедитесь, что настройки API Strapi корректно заполнены на странице настроек.', 'strapi-csv-parser'); ?></p>
            </div>
        </div>
    </div>
</div>
