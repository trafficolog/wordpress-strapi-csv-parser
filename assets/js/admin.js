jQuery(document).ready(function($) {
  // Хранилище для дерева категорий
  var industriesTree = [];
  
  /**
   * Обработчик переключения вкладок источников
   */
  $('.source-tab').on('click', function(e) {
      e.preventDefault();
      $('.source-tab').removeClass('active');
      $(this).addClass('active');
      
      var targetId = $(this).attr('href');
      $('.source-content').removeClass('active');
      $(targetId).addClass('active');
  });
  
  /**
   * Загрузка дерева категорий
   */
  function loadIndustriesTree() {
      // Блокируем все селекторы категорий на время загрузки
      $('.category-select').prop('disabled', true)
          .after('<span class="spinner is-active" style="float:none; margin-left:10px;"></span>');
      
      $.ajax({
          url: strapiParser.ajaxUrl,
          type: 'POST',
          data: {
              action: 'strapi_parser_get_industries_tree',
              nonce: strapiParser.nonce
          },
          success: function(response) {
              // Удаляем спиннеры
              $('.category-select').next('.spinner').remove();
              
              // Разблокируем селекторы
              $('.category-select').prop('disabled', false);
              
              if (response.success && response.data) {
                  // Сохраняем дерево категорий
                  industriesTree = response.data;
                  
                  // Заполняем селекторы категорий
                  fillCategorySelectors();
                  
                  console.log('Загружено дерево категорий:', industriesTree);
              } else {
                  // Выводим ошибку
                  var errorMessage = response.data || 'Ошибка при загрузке категорий';
                  console.error('Ошибка получения категорий:', errorMessage);
                  
                  $('.category-select').after(
                      '<p class="error-message" style="color:red;">Ошибка при загрузке категорий: ' + 
                      errorMessage + '</p>'
                  );
              }
          },
          error: function(xhr, status, error) {
              // Удаляем спиннеры
              $('.category-select').next('.spinner').remove();
              
              // Разблокируем селекторы
              $('.category-select').prop('disabled', false);
              
              console.error('AJAX ошибка при получении категорий:', error);
              $('.category-select').after(
                  '<p class="error-message" style="color:red;">Ошибка соединения: ' + error + '</p>'
              );
          }
      });
  }
  
  /**
   * Заполнение селекторов категорий первого уровня
   */
  function fillCategorySelectors() {
      // Заполняем селекторы категорий
      $('.category-select').each(function() {
          var select = $(this);
          
          // Очищаем селектор, оставляя только первую опцию (placeholder)
          select.find('option:not(:first)').remove();
          
          // Добавляем категории
          $.each(industriesTree, function(index, category) {
              select.append(
                  $('<option></option>')
                      .attr('value', category.id)
                      .text(category.name)
                      .data('children', category.children)
              );
          });
      });
  }
  
  /**
   * Обработчик изменения категории
   */
  $('.category-select').on('change', function() {
      var selectedOption = $(this).find('option:selected');
      var sourceId = $(this).attr('id').replace('-category', '');
      
      // Очищаем и скрываем дочерние селекторы
      $('#' + sourceId + '-subcategory').find('option:not(:first)').remove();
      $('#' + sourceId + '-subcategory-group').addClass('hidden');
      
      $('#' + sourceId + '-subsubcategory').find('option:not(:first)').remove();
      $('#' + sourceId + '-subsubcategory-group').addClass('hidden');
      
      // Если выбрана категория
      if (selectedOption.val()) {
          var children = selectedOption.data('children');
          
          // Если у категории есть дочерние элементы
          if (children && children.length > 0) {
              // Заполняем селектор подкатегорий
              $.each(children, function(index, subcategory) {
                  $('#' + sourceId + '-subcategory').append(
                      $('<option></option>')
                          .attr('value', subcategory.id)
                          .text(subcategory.name)
                          .data('children', subcategory.children)
                  );
              });
              
              // Показываем селектор подкатегорий
              $('#' + sourceId + '-subcategory-group').removeClass('hidden');
          }
      }
  });
  
  /**
   * Обработчик изменения подкатегории
   */
  $('.subcategory-select').on('change', function() {
      var selectedOption = $(this).find('option:selected');
      var sourceId = $(this).attr('id').replace('-subcategory', '');
      
      // Очищаем и скрываем селектор подподкатегорий
      $('#' + sourceId + '-subsubcategory').find('option:not(:first)').remove();
      $('#' + sourceId + '-subsubcategory-group').addClass('hidden');
      
      // Если выбрана подкатегория
      if (selectedOption.val()) {
          var children = selectedOption.data('children');
          
          // Если у подкатегории есть дочерние элементы
          if (children && children.length > 0) {
              // Заполняем селектор подподкатегорий
              $.each(children, function(index, subsubcategory) {
                  $('#' + sourceId + '-subsubcategory').append(
                      $('<option></option>')
                          .attr('value', subsubcategory.id)
                          .text(subsubcategory.name)
                  );
              });
              
              // Показываем селектор подподкатегорий
              $('#' + sourceId + '-subsubcategory-group').removeClass('hidden');
          }
      }
  });
  
  /**
   * Обработка отправки формы
   */
  $('.upload-form form').on('submit', function(e) {
      e.preventDefault();
      
      var form = $(this);
      var formData = new FormData(this);
      
      // Валидация
      var file = form.find('input[type="file"]').val();
      var categoryId = form.find('.category-select').val();
      
      if (!file) {
          alert('Пожалуйста, выберите CSV файл');
          return;
      }
      
      if (!categoryId) {
          alert('Пожалуйста, выберите категорию');
          return;
      }
      
      // Добавляем action для AJAX
      formData.append('action', 'strapi_parser_upload_csv');
      formData.append('nonce', strapiParser.nonce);
      
      // Отключаем кнопку и показываем индикатор загрузки
      form.find('.upload-button').prop('disabled', true)
          .after('<span class="spinner is-active" style="float:none; margin-left:10px;"></span>');
      
      // Отправляем форму через AJAX
      $.ajax({
          url: strapiParser.ajaxUrl,
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(response) {
              // Удаляем спиннер
              form.find('.spinner').remove();
              
              if (response.success) {
                  // Загрузка прошла успешно
                  var sourceId = form.closest('.source-content').attr('id');
                  
                  // Скрываем форму
                  form.closest('.upload-form').hide();
                  
                  // Показываем блок обработки
                  var processContainer = $('#' + sourceId + ' .process-container');
                  processContainer.removeClass('hidden');
                  
                  // Заполняем информацию о файле
                  processContainer.find('.file-info').html(
                      '<div class="notice notice-success">' +
                      '<p>Файл <strong>' + response.data.original_name + '</strong> успешно загружен. ' +
                      'Всего строк: <strong>' + response.data.total_rows + '</strong>.</p>' +
                      '</div>'
                  );
                  
                  // Обновляем данные прогресса
                  processContainer.find('.total').text(response.data.total_rows);
                  
                  // Сохраняем информацию о файле для дальнейшей обработки
                  processContainer.data({
                      'file-id': response.data.file_id,
                      'total-rows': response.data.total_rows,
                      'category-id': form.find('.category-select').val(),
                      'subcategory-id': form.find('.subcategory-select').val() || '',
                      'subsubcategory-id': form.find('.subsubcategory-select').val() || ''
                  });
              } else {
                  // Ошибка загрузки
                  form.find('.upload-button').prop('disabled', false);
                  alert('Ошибка загрузки файла: ' + response.data);
              }
          },
          error: function() {
              // Удаляем спиннер
              form.find('.spinner').remove();
              
              // Разблокируем кнопку
              form.find('.upload-button').prop('disabled', false);
              
              alert('Ошибка соединения при загрузке файла');
          }
      });
  });
  
  /**
   * Обработчик кнопки "Начать обработку"
   */
  $('.start-process-button').on('click', function() {
      var button = $(this);
      var source = button.data('source');
      var processContainer = button.closest('.process-container');
      
      // Блокируем кнопки
      button.prop('disabled', true);
      
      // Запускаем обработку
      startProcessing(processContainer, source);
  });
  
  /**
   * Обработчик кнопки "Отмена"
   */
  $('.cancel-process-button').on('click', function() {
      if (confirm('Вы уверены, что хотите отменить обработку?')) {
          var processContainer = $(this).closest('.process-container');
          var sourceId = processContainer.closest('.source-content').attr('id');
          
          // Скрываем блок обработки
          processContainer.addClass('hidden');
          
          // Показываем форму
          $('#' + sourceId + ' .upload-form').show();
          
          // Сбрасываем данные формы
          $('#' + sourceId + '-form')[0].reset();
      }
  });

  /**
   * Функция для запуска процесса обработки данных
   * @param {jQuery} processContainer - Контейнер процесса обработки
   * @param {string} source - Тип источника (YandexDirectories или SearchBase)
   */
  function startProcessing(processContainer, source) {
    // Переменные для отслеживания процесса
    var fileId = processContainer.data('file-id');
    var totalRows = parseInt(processContainer.data('total-rows'));
    var categoryId = processContainer.data('category-id');
    var subcategoryId = processContainer.data('subcategory-id') || '';
    var subsubcategoryId = processContainer.data('subsubcategory-id') || '';
    var batchSize = parseInt(processContainer.data('batch-size') || 50);
    var currentOffset = 0;
    var processedCount = 0;
    var successCount = 0;
    var failedCount = 0;
    var isProcessing = true;
    var startTime = new Date();

    // Очищаем контейнер результатов
    processContainer.find('.process-results').empty();

    // Функция для обработки одного пакета данных
    function processBatch() {
        // Если процесс остановлен, выходим
        if (!isProcessing) {
            return;
        }
    
        // Важно! Не завершаем процесс заранее на основе счетчиков
        // if (currentOffset >= totalRows) {
        //     finishProcessing();
        //     return;
        // }
    
        // Отображаем текущий прогресс
        var progress = Math.floor((processedCount / totalRows) * 100);
        processContainer.find('.progress-bar').css('width', progress + '%').text(progress + '%');
        processContainer.find('.processed').text(processedCount);
    
        // Отправляем AJAX запрос для обработки пакета
        $.ajax({
            url: strapiParser.ajaxUrl,
            type: 'POST',
            data: {
                action: 'strapi_parser_process_batch',
                nonce: strapiParser.nonce,
                file_id: fileId,
                offset: currentOffset,
                source: source,
                category_id: categoryId,
                subcategory_id: subcategoryId,
                subsubcategory_id: subsubcategoryId
            },
            success: function(response) {
                if (response.success) {
                    console.log('Ответ от сервера:', response.data);
                    
                    // Обновляем счетчики
                    if (typeof response.data.next_offset !== 'undefined') {
                        currentOffset = response.data.next_offset;
                    }
                    if (typeof response.data.processed !== 'undefined') {
                        processedCount = response.data.processed;
                    }
                    if (typeof response.data.success !== 'undefined') {
                        successCount = response.data.success;
                    }
                    if (typeof response.data.failed !== 'undefined') {
                        failedCount = response.data.failed;
                    }
    
                    // Обновляем прогресс
                    var newProgress = Math.floor((processedCount / totalRows) * 100);
                    processContainer.find('.progress-bar').css('width', newProgress + '%').text(newProgress + '%');
                    processContainer.find('.processed').text(processedCount);
                    
                    // Обновляем результаты
                    if (response.data.results && response.data.results.length > 0) {
                        updateResultsDisplay(response.data.results);
                    }
    
                    // Проверяем завершение только по флагу с сервера
                    if (response.data.completed === true) {
                        console.log('Процесс завершен по флагу с сервера');
                        finishProcessing();
                        return;
                    }
    
                    // Продолжаем обработку
                    setTimeout(processBatch, 500);
                } else {
                    handleError('Ошибка обработки данных: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', xhr.responseText);
                handleError('Ошибка соединения: ' + error);
            }
        });
    }

    // Функция для обновления отображения результатов
    function updateResultsDisplay(results) {
      var resultsContainer = processContainer.find('.process-results');
      var html = '';
      
      if (results.length > 0) {
          html += '<div class="batch-results">';
          html += '<h4>Результаты последнего пакета:</h4>';
          html += '<ul>';
          
          $.each(results, function(index, result) {
              if (result.success) {
                  html += '<li class="success">✓ ' + result.name + ' (ID: ' + result.id + ')</li>';
              } else {
                  html += '<li class="error">✗ ' + result.name + ' - Ошибка: ' + result.error + '</li>';
              }
          });
          
          html += '</ul>';
          html += '</div>';
      }
      
      // Добавляем новые результаты вверху контейнера
      resultsContainer.prepend(html);
      
      // Ограничиваем количество показываемых результатов
      var maxResults = 5;
      if (resultsContainer.find('.batch-results').length > maxResults) {
          resultsContainer.find('.batch-results:gt(' + (maxResults - 1) + ')').remove();
      }
    }

    // Функция для завершения процесса
    function finishProcessing() {
        // Если процесс уже был завершен, не делаем ничего
        if (!isProcessing) {
            return;
        }

        isProcessing = false;
        var endTime = new Date();
        var processingTime = Math.round((endTime - startTime) / 1000); // в секундах

        // Форматируем время обработки
        var timeStr = '';
        if (processingTime >= 3600) {
            var hours = Math.floor(processingTime / 3600);
            timeStr += hours + ' ч ';
            processingTime %= 3600;
        }
        if (processingTime >= 60) {
            var minutes = Math.floor(processingTime / 60);
            timeStr += minutes + ' мин ';
            processingTime %= 60;
        }
        timeStr += processingTime + ' сек';

        // Разблокируем кнопку старта
        processContainer.find('.start-process-button').prop('disabled', false).text('Начать обработку');

        // Показываем результаты
        processContainer.find('.process-results').html(
            '<div class="notice notice-success">' +
            '<p><strong>Обработка завершена!</strong></p>' +
            '<p>Обработано записей: ' + processedCount + ' из ' + totalRows + '</p>' +
            '<p>Успешно: ' + successCount + '</p>' +
            '<p>Ошибок: ' + failedCount + '</p>' +
            '<p>Время обработки: ' + timeStr + '</p>' +
            '</div>'
        );

        // Устанавливаем прогресс 100%
        processContainer.find('.progress-bar').css('width', '100%').text('100%');
    }

    // Функция для обработки ошибок
    function handleError(message) {
        isProcessing = false;
        
        // Разблокируем кнопку старта
        processContainer.find('.start-process-button').prop('disabled', false).text('Начать обработку');

        // Показываем сообщение об ошибке
        processContainer.find('.process-results').html(
            '<div class="notice notice-error">' +
            '<p><strong>Ошибка обработки!</strong></p>' +
            '<p>' + message + '</p>' +
            '</div>'
        );
    }

    // Меняем текст кнопки
    processContainer.find('.start-process-button').text('Обработка...');

    // Запускаем обработку первого пакета
    processBatch();
  }
  
  // Загружаем дерево категорий при загрузке страницы
  loadIndustriesTree();
});
