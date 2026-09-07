/**
 * NextGen Image Optimizer Admin Client Script
 * Version: 1.2.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        var queue = [];
        var totalItems = 0;
        var processedCount = 0;
        var isPaused = false;
        var isRunning = false;

        var $btnStart = $('#btn-start-bulk');
        var $btnPause = $('#btn-pause-bulk');
        var $btnReset = $('#btn-reset-bulk');
        var $progressContainer = $('#bulk-progress-container');
        var $progressFill = $('#bulk-progress-fill');
        var $progressText = $('#bulk-progress-text');
        var $progressCounts = $('#bulk-progress-counts');
        var $logContainer = $('#bulk-log');
        var $logList = $('#bulk-log-list');

        /**
         * Defensive helper to safely extract error message from any AJAX response shape.
         */
        function extractErrorMessage(response, defaultMsg) {
            var fallback = defaultMsg || (nextgenOptimizer && nextgenOptimizer.i18n && nextgenOptimizer.i18n.error) || 'Server error';
            if (!response) {
                return fallback;
            }
            if (typeof response === 'string') {
                return response;
            }
            if (response.data) {
                if (typeof response.data === 'string') {
                    return response.data;
                }
                if (typeof response.data === 'object') {
                    if (response.data.message && typeof response.data.message === 'string') {
                        return response.data.message;
                    }
                    if (response.data.error && typeof response.data.error === 'string') {
                        return response.data.error;
                    }
                }
            }
            if (response.message && typeof response.message === 'string') {
                return response.message;
            }
            return fallback;
        }

        // Preset Radio Card Selection UI Feedback
        $('.nextgen-radio-card input[type="radio"]').on('change', function () {
            $('.nextgen-radio-card').removeClass('active');
            $(this).closest('.nextgen-radio-card').addClass('active');
        });

        // Diagnostics Copy Report Handler
        $('#nextgen-copy-report-btn').on('click', function () {
            var textarea = document.getElementById('nextgen-system-report');
            var $feedback = $('#nextgen-copy-report-feedback');

            if (textarea && textarea.value) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(textarea.value).then(function () {
                        $feedback.text(nextgenOptimizer.i18n.reportCopied).fadeIn(200).delay(2500).fadeOut(300);
                    }).catch(function () {
                        fallbackCopy(textarea, $feedback);
                    });
                } else {
                    fallbackCopy(textarea, $feedback);
                }
            }
        });

        function fallbackCopy(textarea, $feedback) {
            try {
                textarea.select();
                textarea.setSelectionRange(0, 99999);
                document.execCommand('copy');
                $feedback.text(nextgenOptimizer.i18n.reportCopied).fadeIn(200).delay(2500).fadeOut(300);
            } catch (err) {
                $feedback.css('color', '#d63638').text(nextgenOptimizer.i18n.copyFailed).fadeIn(200).delay(3000).fadeOut(300);
            }
        }

        // Bulk Converter Execution
        $btnStart.on('click', function () {
            if (isPaused && queue.length > 0) {
                // Resume
                isPaused = false;
                isRunning = true;
                $btnStart.hide();
                $btnPause.show();
                logMessage(nextgenOptimizer.i18n.resumingBulk, 'log-info');
                processNext();
                return;
            }

            var includeFailed = $('#bulk-include-failed').is(':checked') ? 1 : 0;
            $btnStart.prop('disabled', true).text(nextgenOptimizer.i18n.fetchingQueue);

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_get_bulk_queue',
                nonce: nextgenOptimizer.nonce,
                include_failed: includeFailed
            }, function (response) {
                $btnStart.prop('disabled', false);

                if (!response || !response.success || !response.data || !response.data.queue) {
                    alert(extractErrorMessage(response, nextgenOptimizer.i18n.error));
                    $btnStart.html('<span class="dashicons dashicons-controls-play"></span> ' + nextgenOptimizer.i18n.startBulk);
                    return;
                }

                queue = response.data.queue;
                totalItems = queue.length;
                processedCount = 0;

                if (totalItems === 0) {
                    alert(nextgenOptimizer.i18n.allOptimized);
                    $btnStart.html('<span class="dashicons dashicons-controls-play"></span> ' + nextgenOptimizer.i18n.startBulk);
                    return;
                }

                isRunning = true;
                isPaused = false;
                $btnStart.hide();
                $btnPause.show();
                $progressContainer.show();
                $logContainer.show();
                $logList.empty();

                var startMsg = nextgenOptimizer.i18n.startingBulk.replace('%d', totalItems);
                logMessage(startMsg, 'log-info');
                updateProgress(0, totalItems);

                processNext();
            }).fail(function () {
                $btnStart.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> ' + nextgenOptimizer.i18n.startBulk);
                alert(nextgenOptimizer.i18n.error);
            });
        });

        $btnPause.on('click', function () {
            isPaused = true;
            isRunning = false;
            $btnPause.hide();
            $btnStart.html('<span class="dashicons dashicons-controls-play"></span> ' + nextgenOptimizer.i18n.resumeBulk).show();
            logMessage(nextgenOptimizer.i18n.pausedByUser, 'log-info');
        });

        $btnReset.on('click', function () {
            if (!confirm(nextgenOptimizer.i18n.resetConfirm)) {
                return;
            }

            $btnReset.prop('disabled', true);

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_reset_bulk_queue',
                nonce: nextgenOptimizer.nonce
            }, function (response) {
                $btnReset.prop('disabled', false);

                if (response && response.success) {
                    alert(nextgenOptimizer.i18n.resetComplete);
                    if (response.data && response.data.stats) {
                        updateLiveStats(response.data.stats);
                    }
                    $progressFill.css('width', '0%');
                    $progressText.text('0%');
                    $progressCounts.text('0 / 0 images');
                    $progressContainer.hide();
                    $btnStart.html('<span class="dashicons dashicons-controls-play"></span> ' + nextgenOptimizer.i18n.startBulk).prop('disabled', false).show();
                    $btnPause.hide();
                    $logList.empty();
                    $logContainer.hide();
                } else {
                    alert(extractErrorMessage(response, nextgenOptimizer.i18n.error));
                }
            }).fail(function () {
                $btnReset.prop('disabled', false);
                alert(nextgenOptimizer.i18n.error);
            });
        });

        // Reports Screen Retry / Clear Failed Handlers
        $('#btn-retry-failed-reports').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_retry_failed',
                nonce: nextgenOptimizer.nonce
            }, function (response) {
                $btn.prop('disabled', false);
                if (response && response.success) {
                    alert(extractErrorMessage(response, 'Queue cleared for retry.'));
                    window.location.reload();
                } else {
                    alert(extractErrorMessage(response, nextgenOptimizer.i18n.error));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                alert(nextgenOptimizer.i18n.error);
            });
        });

        $('#btn-clear-failed-reports').on('click', function () {
            if (!confirm('Are you sure you want to clear the failed conversion queue?')) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_clear_failed_queue',
                nonce: nextgenOptimizer.nonce
            }, function (response) {
                $btn.prop('disabled', false);
                if (response && response.success) {
                    window.location.reload();
                } else {
                    alert(extractErrorMessage(response, nextgenOptimizer.i18n.error));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                alert(nextgenOptimizer.i18n.error);
            });
        });

        // Background WP-Cron Worker Handlers
        $('#btn-cron-start').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_bg_start',
                nonce: nextgenOptimizer.nonce
            }, function (response) {
                $btn.prop('disabled', false);
                if (response && response.success) {
                    $('#cron-status-indicator').removeClass('nextgen-badge-neutral').addClass('nextgen-badge-success').text('Worker Running (WP-Cron)');
                } else {
                    alert(extractErrorMessage(response, 'Failed to start background worker.'));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                alert(nextgenOptimizer.i18n.error);
            });
        });

        $('#btn-cron-cancel').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_bg_cancel',
                nonce: nextgenOptimizer.nonce
            }, function (response) {
                $btn.prop('disabled', false);
                if (response && response.success) {
                    $('#cron-status-indicator').removeClass('nextgen-badge-success').addClass('nextgen-badge-neutral').text('Worker Idle');
                } else {
                    alert(extractErrorMessage(response, 'Failed to stop background worker.'));
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                alert(nextgenOptimizer.i18n.error);
            });
        });

        // Quality & Format Visualizer Interactive Split Slider
        function setSliderPosition(percentage) {
            percentage = Math.max(0, Math.min(100, percentage));
            $('#nextgen-split-handle').css('left', percentage + '%');
            $('#nextgen-split-overlay').css('clip-path', 'polygon(' + percentage + '% 0, 100% 0, 100% 100%, ' + percentage + '% 100%)');
        }

        var isDraggingSlider = false;

        function updateSliderFromEvent(e) {
            var $splitContainer = $('#nextgen-split-container');
            if (!$splitContainer.length) return;
            var offset = $splitContainer.offset();
            var width = $splitContainer.width();
            var pageX = e.pageX;
            if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches.length > 0) {
                pageX = e.originalEvent.touches[0].pageX;
            }
            if (pageX === undefined || width <= 0) return;
            var relX = pageX - offset.left;
            var pct = (relX / width) * 100;
            setSliderPosition(pct);
        }

        $(document).on('mousedown touchstart', '#nextgen-split-container', function (e) {
            isDraggingSlider = true;
            updateSliderFromEvent(e);
        });

        $(document).on('mousemove touchmove', function (e) {
            if (isDraggingSlider) {
                updateSliderFromEvent(e);
            }
        });

        $(document).on('mouseup touchend', function () {
            isDraggingSlider = false;
        });

        $(document).on('click', '#nextgen-generate-preview-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $sampleSelect = $('#nextgen-comparison-sample');
            var attachmentId = parseInt($sampleSelect.val(), 10);
            if (!attachmentId || attachmentId <= 0) {
                alert('Please select a sample image from the dropdown.');
                return;
            }

            var format = $('input[name="nextgen_cmp_format"]:checked').val() || 'webp';
            var preset = $('input[name="nextgen_cmp_preset"]:checked').val() || 'balanced';
            var origUrl = $sampleSelect.find('option:selected').data('original-url') || '';
            var origBtnHtml = $btn.html();

            $btn.prop('disabled', true).text('Generating Live Preview...');

            var $comparisonStage = $('#nextgen-comparison-stage');
            var $imgOriginal = $('#nextgen-img-original');
            var $imgPreview = $('#nextgen-img-preview');
            var $labelAfter = $('#nextgen-label-after');
            var $valOrig = $('#nextgen-val-orig');
            var $valPrev = $('#nextgen-val-prev');
            var $valSaved = $('#nextgen-val-saved');
            var $valPercent = $('#nextgen-val-percent');

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_generate_preview',
                nonce: nextgenOptimizer.nonce,
                attachment_id: attachmentId,
                format: format,
                preset: preset
            }, function (response) {
                $btn.prop('disabled', false).html(origBtnHtml);

                if (response && response.success && response.data) {
                    var data = response.data;
                    var originalImageSrc = data.original_url || origUrl;

                    $imgOriginal.attr('src', originalImageSrc);
                    $imgPreview.attr('src', data.preview_url);

                    $valOrig.text(formatBytes(data.original_size));
                    $valPrev.text(formatBytes(data.preview_size));
                    $valSaved.text(formatBytes(data.bytes_saved));
                    $valPercent.text(data.percentage_saved + '%');

                    $labelAfter.text('Optimized (' + format.toUpperCase() + ')');

                    setSliderPosition(50);
                    $comparisonStage.slideDown(200);
                } else {
                    var msg = extractErrorMessage(response, 'Failed to generate preview.');
                    alert(msg);
                }
            }).fail(function (xhr) {
                $btn.prop('disabled', false).html(origBtnHtml);
                var msg = 'Preview generation failed.';
                if (xhr && xhr.responseJSON) {
                    msg = extractErrorMessage(xhr.responseJSON, msg);
                }
                alert(msg);
            });
        });

        function processNext() {
            if (isPaused || queue.length === 0) {
                if (queue.length === 0 && processedCount > 0) {
                    onComplete();
                }
                return;
            }

            var attachmentId = queue.shift();
            var throttle = parseInt($('#bulk-throttle-delay').val(), 10) || 0;

            $.post(nextgenOptimizer.ajaxUrl, {
                action: 'nextgen_process_bulk_item',
                nonce: nextgenOptimizer.nonce,
                attachment_id: attachmentId
            }, function (response) {
                processedCount++;
                updateProgress(processedCount, totalItems);

                try {
                    if (response && response.success && response.data && response.data.result) {
                        var res = response.data.result;
                        var saved = formatBytes(res.saved_bytes || 0);

                        if (res.status === 'completed') {
                            logMessage('✔ #' + attachmentId + ' ' + nextgenOptimizer.i18n.converted + ' (' + nextgenOptimizer.i18n.saved + ': ' + saved + ')', 'log-success');
                        } else if (res.status === 'skipped') {
                            logMessage('⚠ #' + attachmentId + ' ' + nextgenOptimizer.i18n.skipped + ' (' + (res.reason || 'Larger') + ')', 'log-skipped');
                        } else {
                            logMessage('✖ #' + attachmentId + ' ' + nextgenOptimizer.i18n.failed + ' (' + (res.error || 'unknown') + ')', 'log-error');
                        }

                        if (response.data.stats) {
                            updateLiveStats(response.data.stats);
                        }
                    } else {
                        var errMsg = extractErrorMessage(response, 'Server error');
                        logMessage('✖ #' + attachmentId + ' ' + nextgenOptimizer.i18n.error + ': ' + errMsg, 'log-error');
                    }
                } catch (err) {
                    var clientErrMsg = (err && err.message) ? err.message : 'Unknown client error';
                    logMessage('✖ #' + attachmentId + ' client processing error: ' + clientErrMsg, 'log-error');
                } finally {
                    if (throttle > 0) {
                        setTimeout(processNext, throttle);
                    } else {
                        processNext();
                    }
                }

            }).fail(function (xhr) {
                processedCount++;
                updateProgress(processedCount, totalItems);
                var statusText = (xhr && xhr.statusText) ? xhr.statusText : 'Network timeout/error';
                logMessage('✖ #' + attachmentId + ' request failed (' + statusText + ').', 'log-error');

                if (throttle > 0) {
                    setTimeout(processNext, throttle);
                } else {
                    processNext();
                }
            });
        }

        function onComplete() {
            isRunning = false;
            $btnPause.hide();
            $btnStart.html('<span class="dashicons dashicons-yes"></span> ' + nextgenOptimizer.i18n.complete).prop('disabled', true).show();
            logMessage('🎉 ' + nextgenOptimizer.i18n.complete, 'log-success');
        }

        function updateProgress(current, total) {
            var pct = total > 0 ? Math.round((current / total) * 100) : 0;
            $progressFill.css('width', pct + '%');
            $progressText.text(pct + '%');
            $progressCounts.text(current + ' / ' + total + ' images');
        }

        function updateLiveStats(stats) {
            if (!stats) return;
            $('#stat-total').text(stats.total_images || 0);
            $('#stat-optimized').text(stats.optimized_images || 0);
            $('#stat-pending').text(stats.pending_images || 0);
            $('#stat-saved').text(formatBytes(stats.saved_bytes || 0));
        }

        function logMessage(msg, className) {
            var $li = $('<li>').addClass(className || '').text('[' + new Date().toLocaleTimeString() + '] ' + msg);
            $logList.prepend($li);
        }

        function formatBytes(bytes) {
            if (!bytes || bytes <= 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    });
})(jQuery);
