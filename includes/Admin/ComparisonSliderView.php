<?php
/**
 * Interactive Visual Split-View Image Comparison Slider HTML Renderer.
 *
 * Renders the pure CSS / Vanilla JS before-and-after split comparison preview
 * with live quality preset toggles and format selection.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComparisonSliderView {

    /**
     * Render the Visual Comparison Slider component.
     *
     * @param int|null $defaultAttachmentId Sample attachment ID to load.
     * @return string Safe HTML markup.
     */
    public static function render(?int $defaultAttachmentId = null): string {
        $activePreset = QualityPresetManager::getActivePreset();
        $isPro = \NextGen\Core\Features::isAvifEnabled();

        ob_start();
        ?>
        <div class="nextgen-comparison-card">
            <div class="nextgen-comparison-header">
                <h3><?php esc_html_e('Interactive Visual Quality & Format Visualizer', 'nextgen-image-optimizer'); ?></h3>
                <p class="nextgen-comparison-subtitle">
                    <?php esc_html_e('Compare visual fidelity and byte reduction on your actual media files in real time without modifying original files.', 'nextgen-image-optimizer'); ?>
                </p>
            </div>

            <div class="nextgen-comparison-controls">
                <div class="nextgen-control-group">
                    <label for="nextgen-comparison-sample"><strong><?php esc_html_e('Select Sample Image:', 'nextgen-image-optimizer'); ?></strong></label>
                    <select id="nextgen-comparison-sample" class="nextgen-select">
                        <option value=""><?php esc_html_e('-- Choose Media Attachment --', 'nextgen-image-optimizer'); ?></option>
                    </select>
                </div>

                <div class="nextgen-control-group">
                    <label><strong><?php esc_html_e('Target Format:', 'nextgen-image-optimizer'); ?></strong></label>
                    <div class="nextgen-radio-pills">
                        <label class="nextgen-pill">
                            <input type="radio" name="nextgen_cmp_format" value="webp" checked>
                            <span>WebP</span>
                        </label>
                        <label class="nextgen-pill <?php echo !$isPro ? 'nextgen-pill-disabled' : ''; ?>">
                            <input type="radio" name="nextgen_cmp_format" value="avif" <?php echo !$isPro ? 'disabled' : ''; ?>>
                            <span>AVIF <?php echo !$isPro ? '<span class="nextgen-badge-pro">PRO</span>' : ''; ?></span>
                        </label>
                    </div>
                </div>

                <div class="nextgen-control-group">
                    <label><strong><?php esc_html_e('Quality Preset:', 'nextgen-image-optimizer'); ?></strong></label>
                    <div class="nextgen-radio-pills">
                        <label class="nextgen-pill">
                            <input type="radio" name="nextgen_cmp_preset" value="high" <?php checked($activePreset, 'high'); ?>>
                            <span><?php esc_html_e('High Quality', 'nextgen-image-optimizer'); ?></span>
                        </label>
                        <label class="nextgen-pill">
                            <input type="radio" name="nextgen_cmp_preset" value="balanced" <?php checked($activePreset, 'balanced'); ?>>
                            <span><?php esc_html_e('Balanced', 'nextgen-image-optimizer'); ?></span>
                        </label>
                        <label class="nextgen-pill">
                            <input type="radio" name="nextgen_cmp_preset" value="aggressive" <?php checked($activePreset, 'aggressive'); ?>>
                            <span><?php esc_html_e('Aggressive', 'nextgen-image-optimizer'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="nextgen-control-group nextgen-action-group">
                    <button type="button" id="nextgen-generate-preview-btn" class="button button-primary">
                        <?php esc_html_e('Generate Live Preview', 'nextgen-image-optimizer'); ?>
                    </button>
                </div>
            </div>

            <!-- Comparison Stage -->
            <div id="nextgen-comparison-stage" class="nextgen-comparison-stage" style="display: none;">
                <div class="nextgen-split-container" id="nextgen-split-container">
                    <img id="nextgen-img-original" class="nextgen-img-original" src="" alt="<?php esc_attr_e('Original', 'nextgen-image-optimizer'); ?>">
                    <div class="nextgen-split-overlay" id="nextgen-split-overlay">
                        <img id="nextgen-img-preview" class="nextgen-img-preview" src="" alt="<?php esc_attr_e('Preview', 'nextgen-image-optimizer'); ?>">
                    </div>
                    <div class="nextgen-split-handle" id="nextgen-split-handle">
                        <div class="nextgen-handle-line"></div>
                        <div class="nextgen-handle-circle">↔</div>
                    </div>
                    <span class="nextgen-label nextgen-label-before"><?php esc_html_e('Original', 'nextgen-image-optimizer'); ?></span>
                    <span class="nextgen-label nextgen-label-after" id="nextgen-label-after"><?php esc_html_e('Optimized', 'nextgen-image-optimizer'); ?></span>
                </div>

                <div class="nextgen-comparison-metrics" id="nextgen-comparison-metrics">
                    <div class="nextgen-metric-box">
                        <span class="nextgen-metric-label"><?php esc_html_e('Original Size', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-val" id="nextgen-val-orig">--</span>
                    </div>
                    <div class="nextgen-metric-box">
                        <span class="nextgen-metric-label"><?php esc_html_e('Preview Size', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-val" id="nextgen-val-prev">--</span>
                    </div>
                    <div class="nextgen-metric-box nextgen-metric-highlight">
                        <span class="nextgen-metric-label"><?php esc_html_e('Bytes Saved', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-val" id="nextgen-val-saved">--</span>
                    </div>
                    <div class="nextgen-metric-box nextgen-metric-highlight">
                        <span class="nextgen-metric-label"><?php esc_html_e('Reduction', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-val" id="nextgen-val-percent">--</span>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .nextgen-comparison-card {
                background: #ffffff;
                border: 1px solid #ccd0d4;
                border-radius: 6px;
                padding: 20px;
                margin-top: 15px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            .nextgen-comparison-controls {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                align-items: flex-end;
                margin-bottom: 20px;
                padding: 15px;
                background: #f6f7f7;
                border-radius: 4px;
            }
            .nextgen-control-group label {
                display: block;
                margin-bottom: 5px;
                font-size: 13px;
            }
            .nextgen-radio-pills {
                display: flex;
                gap: 5px;
            }
            .nextgen-pill {
                display: inline-flex;
                align-items: center;
                cursor: pointer;
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 12px;
            }
            .nextgen-pill input {
                margin-right: 5px;
            }
            .nextgen-pill-disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            .nextgen-badge-pro {
                background: #2271b1;
                color: #fff;
                font-size: 9px;
                padding: 2px 4px;
                border-radius: 3px;
                margin-left: 3px;
            }
            .nextgen-split-container {
                position: relative;
                width: 100%;
                max-width: 800px;
                height: 450px;
                overflow: hidden;
                border-radius: 6px;
                border: 1px solid #ccd0d4;
                background: #1e1e1e;
                margin: 0 auto 20px auto;
                user-select: none;
            }
            .nextgen-img-original, .nextgen-img-preview {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            .nextgen-split-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                clip-path: polygon(50% 0, 100% 0, 100% 100%, 50% 100%);
            }
            .nextgen-split-handle {
                position: absolute;
                top: 0;
                bottom: 0;
                left: 50%;
                width: 4px;
                background: #2271b1;
                cursor: ew-resize;
                transform: translateX(-50%);
            }
            .nextgen-handle-circle {
                position: absolute;
                top: 50%;
                left: 50%;
                width: 28px;
                height: 28px;
                background: #2271b1;
                color: #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transform: translate(-50%, -50%);
                font-size: 14px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            }
            .nextgen-label {
                position: absolute;
                top: 10px;
                padding: 4px 8px;
                background: rgba(0,0,0,0.6);
                color: #fff;
                font-size: 11px;
                border-radius: 3px;
                font-weight: 600;
            }
            .nextgen-label-before { left: 10px; }
            .nextgen-label-after { right: 10px; }
            .nextgen-comparison-metrics {
                display: flex;
                gap: 15px;
                max-width: 800px;
                margin: 0 auto;
            }
            .nextgen-metric-box {
                flex: 1;
                background: #f6f7f7;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 10px;
                text-align: center;
            }
            .nextgen-metric-box.nextgen-metric-highlight {
                background: #f0f6fc;
                border-color: #72aee6;
            }
            .nextgen-metric-label {
                display: block;
                font-size: 11px;
                color: #646970;
                text-transform: uppercase;
                margin-bottom: 4px;
            }
            .nextgen-metric-val {
                font-size: 16px;
                font-weight: 700;
                color: #1d2327;
            }
            .nextgen-metric-highlight .nextgen-metric-val {
                color: #2271b1;
            }
        </style>
        <?php
        return ob_get_clean();
    }
}
