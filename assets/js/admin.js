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

                if (!response.success || !response.data.queue) {
                    alert(response.data.message || nextgenOptimizer.i18n.error);
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

                if (response.success) {
                    alert(nextgenOptimizer.i18n.resetComplete);
                    if (response.data.stats) {
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
                    alert(response.data.message || nextgenOptimizer.i18n.error);
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
                if (response.success) {
                    alert(response.data.message || 'Queue cleared for retry.');
                    window.location.reload();
                }
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
                if (response.success) {
                    window.location.reload();
                }
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
                if (response.success) {
                    $('#cron-status-indicator').removeClass('nextgen-badge-neutral').addClass('nextgen-badge-success').text('Worker Running (WP-Cron)');
                } else {
                    alert(response.data.message || 'Failed to start background worker.');
                }
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
                if (response.success) {
                    $('#cron-status-indicator').removeClass('nextgen-badge-success').addClass('nextgen-badge-neutral').text('Worker Idle');
                }
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

                if (response.success && response.data.result) {
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
                    logMessage('✖ #' + attachmentId + ' ' + nextgenOptimizer.i18n.error + ': ' + (response.data.message || 'Server error'), 'log-error');
                }

                if (throttle > 0) {
                    setTimeout(processNext, throttle);
                } else {
                    processNext();
                }

            }).fail(function () {
                processedCount++;
                updateProgress(processedCount, totalItems);
                logMessage('✖ #' + attachmentId + ' request failed (Network timeout/error).', 'log-error');

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
            $('#stat-total').text(stats.total_images);
            $('#stat-optimized').text(stats.optimized_images);
            $('#stat-pending').text(stats.pending_images);
            $('#stat-saved').text(formatBytes(stats.saved_bytes));
        }

        function logMessage(msg, className) {
            var $li = $('<li>').addClass(className || '').text('[' + new Date().toLocaleTimeString() + '] ' + msg);
            $logList.prepend($li);
        }

        function formatBytes(bytes) {
            if (bytes <= 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    });
})(jQuery);
