(function($){
    'use strict';

    function hasData(){
        return typeof AAWEBAIR !== 'undefined' && AAWEBAIR.ajaxurl;
    }

    function presetOptions(){
        if (!hasData()) return '';
        var html = '<option value="">' + AAWEBAIR.i18n.selectSize + '</option>';
        $.each(AAWEBAIR.presets, function(key, obj){
            html += '<option value="' + key + '">' + obj.label + '</option>';
        });
        return html;
    }

    function modeOptions(){
        var replaceSelected = AAWEBAIR.defaultMode === 'replace' ? ' selected' : '';
        var createSelected = AAWEBAIR.defaultMode === 'create' ? ' selected' : '';
        return '<option value="replace"' + replaceSelected + '>' + AAWEBAIR.i18n.replace + '</option>' +
               '<option value="create"' + createSelected + '>' + AAWEBAIR.i18n.create + '</option>';
    }

    function formatOptions(){
        if (!hasData() || !AAWEBAIR.formats) return '';
        var html = '';
        $.each(AAWEBAIR.formats, function(key, label){
            var selected = AAWEBAIR.outputFormat === key ? ' selected' : '';
            html += '<option value="' + key + '"' + selected + '>' + label + '</option>';
        });
        return html;
    }

    function runResize(id, dimension, mode, outputFormat, $button){
        if (!id || !dimension || !mode) {
            alert(AAWEBAIR.i18n.missing);
            return;
        }

        if (!outputFormat) {
            alert(AAWEBAIR.i18n.formatMissing);
            return;
        }

        if (mode === 'replace' && AAWEBAIR.confirmReplace && !window.confirm(AAWEBAIR.i18n.confirm)) {
            return;
        }

        var oldText = $button.text();
        $button.prop('disabled', true).text(AAWEBAIR.i18n.working);

        $.post(AAWEBAIR.ajaxurl, {
            action: 'aaweb_air_resize_image',
            nonce: AAWEBAIR.nonce,
            attachment_id: id,
            dimension: dimension,
            mode: mode,
            output_format: outputFormat
        }).done(function(response){
            if (response && response.success) {
                alert((response.data && response.data.message) ? response.data.message : AAWEBAIR.i18n.done);
                window.location.reload();
                return;
            }

            alert(AAWEBAIR.i18n.error + ': ' + (response && response.data && response.data.message ? response.data.message : 'Unknown'));
        }).fail(function(){
            alert(AAWEBAIR.i18n.error + ': AJAX failed');
        }).always(function(){
            $button.prop('disabled', false).text(oldText);
        });
    }

    $('body').on('click', '.aaweb-air-resize', function(e){
        e.preventDefault();
        var $button = $(this);
        var $wrap = $button.closest('.aaweb-air-row-action, .aaweb-air-modal-toolbar');
        var id = parseInt($button.attr('data-id') || $wrap.attr('data-attachment-id'), 10);
        var dimension = $wrap.find('.aaweb-air-dimension, .aaweb-air-modal-dimension').first().val();
        var mode = $wrap.find('.aaweb-air-mode, .aaweb-air-modal-mode').first().val();
        var outputFormat = $wrap.find('.aaweb-air-output-format, .aaweb-air-modal-output-format').first().val();
        runResize(id, dimension, mode, outputFormat, $button);
    });

    function getAttachmentIdFromPanel($panel) {
        var $root = $panel.closest('[id^="image-editor-"]');
        var match;
        if ($root.length) {
            match = ($root.attr('id') || '').match(/^image-editor-(\d+)$/);
            if (match && match[1]) return parseInt(match[1], 10);
        }

        var $form = $panel.closest('.imgedit-wrap').find('input[name="postid"]');
        if ($form.length && $form.val()) return parseInt($form.val(), 10);

        match = ($panel.attr('id') || '').match(/imgedit-panel-(\d+)/);
        if (match && match[1]) return parseInt(match[1], 10);
        return 0;
    }

    function modalHtml(id){
        return '<div class="aaweb-air-modal-toolbar" data-attachment-id="' + id + '">' +
            '<strong>AAWEB Resize</strong>' +
            '<select class="aaweb-air-modal-dimension">' + presetOptions() + '</select>' +
            '<select class="aaweb-air-modal-mode">' + modeOptions() + '</select>' +
            '<select class="aaweb-air-modal-output-format">' + formatOptions() + '</select>' +
            '<button type="button" class="button button-primary aaweb-air-resize" data-id="' + id + '">' + AAWEBAIR.i18n.modalButton + '</button>' +
        '</div>' +
        '<div class="aaweb-air-backup-box" data-attachment-id="' + id + '">' +
            '<div class="aaweb-air-backup-head"><strong>' + AAWEBAIR.i18n.backups + '</strong> <button type="button" class="button button-small aaweb-air-load-backups" data-id="' + id + '">' + AAWEBAIR.i18n.loadBackups + '</button></div>' +
            '<div class="aaweb-air-backup-list" aria-live="polite"></div>' +
        '</div>';
    }

    function backupRow(item){
        var disabled = item.exists ? '' : ' disabled';
        var status = item.exists ? '' : ' <em>(missing)</em>';
        return '<div class="aaweb-air-backup-item" data-index="' + item.index + '">' +
            '<div class="aaweb-air-backup-meta"><strong>' + item.name + '</strong><span>' + (item.time || '') + (item.size ? ' · ' + item.size : '') + status + '</span></div>' +
            '<div class="aaweb-air-backup-actions">' +
                '<button type="button" class="button button-small aaweb-air-restore-backup" data-index="' + item.index + '"' + disabled + '>' + AAWEBAIR.i18n.restore + '</button>' +
                '<button type="button" class="button button-small aaweb-air-delete-backup" data-index="' + item.index + '">' + AAWEBAIR.i18n.delete + '</button>' +
            '</div>' +
        '</div>';
    }

    function loadBackups(id, $box){
        var $list = $box.find('.aaweb-air-backup-list');
        $list.html('<p class="aaweb-air-muted">' + AAWEBAIR.i18n.working + '</p>');
        $.post(AAWEBAIR.ajaxurl, {
            action: 'aaweb_air_get_backups',
            nonce: AAWEBAIR.nonce,
            attachment_id: id
        }).done(function(response){
            if (!response || !response.success) {
                $list.html('<p class="aaweb-air-error">' + AAWEBAIR.i18n.error + '</p>');
                return;
            }
            var backups = response.data && response.data.backups ? response.data.backups : [];
            if (!backups.length) {
                $list.html('<p class="aaweb-air-muted">' + AAWEBAIR.i18n.noBackups + '</p>');
                return;
            }
            var html = '';
            $.each(backups.reverse(), function(i, item){ html += backupRow(item); });
            $list.html(html);
        }).fail(function(){
            $list.html('<p class="aaweb-air-error">' + AAWEBAIR.i18n.error + ': AJAX failed</p>');
        });
    }

    $('body').on('click', '.aaweb-air-load-backups', function(e){
        e.preventDefault();
        var $button = $(this);
        var id = parseInt($button.attr('data-id'), 10);
        var $box = $button.closest('.aaweb-air-backup-box');
        if (id && $box.length) loadBackups(id, $box);
    });

    $('body').on('click', '.aaweb-air-restore-backup', function(e){
        e.preventDefault();
        if (!window.confirm(AAWEBAIR.i18n.restoreConfirm)) return;
        var $button = $(this);
        var $box = $button.closest('.aaweb-air-backup-box');
        var id = parseInt($box.attr('data-attachment-id'), 10);
        var index = parseInt($button.attr('data-index'), 10);
        $button.prop('disabled', true).text(AAWEBAIR.i18n.working);
        $.post(AAWEBAIR.ajaxurl, {
            action: 'aaweb_air_restore_backup',
            nonce: AAWEBAIR.nonce,
            attachment_id: id,
            backup_index: index
        }).done(function(response){
            if (response && response.success) {
                alert((response.data && response.data.message) ? response.data.message : AAWEBAIR.i18n.backupRestored);
                window.location.reload();
                return;
            }
            alert(AAWEBAIR.i18n.error + ': ' + (response && response.data && response.data.message ? response.data.message : 'Unknown'));
        }).fail(function(){
            alert(AAWEBAIR.i18n.error + ': AJAX failed');
        }).always(function(){
            $button.prop('disabled', false).text(AAWEBAIR.i18n.restore);
        });
    });

    $('body').on('click', '.aaweb-air-delete-backup', function(e){
        e.preventDefault();
        if (!window.confirm(AAWEBAIR.i18n.deleteConfirm)) return;
        var $button = $(this);
        var $box = $button.closest('.aaweb-air-backup-box');
        var id = parseInt($box.attr('data-attachment-id'), 10);
        var index = parseInt($button.attr('data-index'), 10);
        $button.prop('disabled', true).text(AAWEBAIR.i18n.working);
        $.post(AAWEBAIR.ajaxurl, {
            action: 'aaweb_air_delete_backup',
            nonce: AAWEBAIR.nonce,
            attachment_id: id,
            backup_index: index
        }).done(function(response){
            if (response && response.success) {
                loadBackups(id, $box);
                return;
            }
            alert(AAWEBAIR.i18n.error + ': ' + (response && response.data && response.data.message ? response.data.message : 'Unknown'));
        }).fail(function(){
            alert(AAWEBAIR.i18n.error + ': AJAX failed');
        }).always(function(){
            $button.prop('disabled', false).text(AAWEBAIR.i18n.delete);
        });
    });

    function injectModalTool($panel){
        if (!hasData() || !AAWEBAIR.enableModal) return;
        var id = getAttachmentIdFromPanel($panel);
        if (!id) return;
        if ($panel.find('.aaweb-air-modal-toolbar[data-attachment-id="' + id + '"]').length) return;

        var $tools = $panel.find('.imgedit-panel-tools').first();
        if (!$tools.length) $tools = $panel.find('.imgedit-panel-content').first();
        if (!$tools.length) return;

        $tools.append(modalHtml(id));
    }

    if (hasData() && AAWEBAIR.enableModal && window.MutationObserver && document.body) {
        var observer = new MutationObserver(function(mutations){
            mutations.forEach(function(mutation){
                $(mutation.addedNodes).each(function(){
                    var $node = $(this);
                    var $panels = $node.find ? $node.find('#image-editor-*, .imgedit-wrap, [id^="imgedit-panel-"]') : $();
                    if ($node.is('#image-editor-*') || $node.is('.imgedit-wrap') || $node.is('[id^="imgedit-panel-"]')) {
                        $panels = $panels.add($node);
                    }
                    $panels.each(function(){ injectModalTool($(this)); });
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    function ensureBulkControls(){
        if (!hasData() || !AAWEBAIR.enableBulk) return;
        var $form = $('#posts-filter');
        if (!$form.length) return;
        if ($('#aaweb_air_bulk_dimension').length && $('#aaweb_air_bulk_mode').length) return;

        var $bulk = $form.find('.tablenav.top .bulkactions').first();
        if (!$bulk.length) return;

        var $action = $bulk.find('select[name="action"]').first();
        if (!$action.length) return;

        var $dimension = $('<select/>', { id: 'aaweb_air_bulk_dimension', name: 'aaweb_air_bulk_dimension', class: 'aaweb-air-bulk-control' }).html(presetOptions());
        var $mode = $('<select/>', { id: 'aaweb_air_bulk_mode', name: 'aaweb_air_bulk_mode', class: 'aaweb-air-bulk-control' }).html('<option value="">' + AAWEBAIR.i18n.mode + '</option>' + modeOptions());
        var $format = $('<select/>', { id: 'aaweb_air_bulk_output_format', name: 'aaweb_air_bulk_output_format', class: 'aaweb-air-bulk-control' }).html(formatOptions());

        $dimension.insertAfter($action);
        $mode.insertAfter($dimension);
        $format.insertAfter($mode);

        if (!$form.find('input[name="aaweb_air_bulk_nonce"]').length) {
            $form.append($('<input/>', { type: 'hidden', name: 'aaweb_air_bulk_nonce', value: AAWEBAIR.bulkNonce }));
        }
    }

    $(document).ready(ensureBulkControls);
    $(document).on('ajaxComplete', ensureBulkControls);
})(jQuery);
