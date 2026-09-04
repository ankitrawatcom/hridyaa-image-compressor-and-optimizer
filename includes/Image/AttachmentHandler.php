<?php
/**
 * WordPress Attachment Lifecycle & Media Handler.
 *
 * Coordinates automatic conversion on upload for WebP and AVIF,
 * intermediate size generation, failure isolation, and safe derivative cleanup.
 *
 * @package NextGen\Image
 */

namespace NextGen\Image;

use NextGen\Converter\ConverterManager;
use NextGen\Core\Config;
use NextGen\Core\Features;
use NextGen\Storage\MetadataManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AttachmentHandler {

    /**
     * Configuration manager.
     *
     * @var Config
     */
    private Config $config;

    /**
     * Converter manager.
     *
     * @var ConverterManager
     */
    private ConverterManager $converter;

    /**
     * Processing lock tracking in-memory to prevent recursion.
     *
     * @var array<int, bool>
     */
    private array $lockedAttachments = [];

    /**
     * Constructor.
     */
    public function __construct(Config $config, ConverterManager $converter) {
        $this->config = $config;
        $this->converter = $converter;
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    public function registerHooks(): void {
        // Hook into attachment metadata generation (fires right after upload & thumbnail generation)
        add_filter('wp_generate_attachment_metadata', [$this, 'handleUploadMetadata'], 10, 2);

        // Hook into attachment deletion to clean up generated WebP & AVIF files
        add_action('delete_attachment', [$this, 'handleAttachmentDeletion'], 10, 1);
    }

    /**
     * Handler for wp_generate_attachment_metadata filter on new upload.
     *
     * @param array|mixed $metadata Attachment metadata.
     * @param int|mixed $attachmentId Attachment ID.
     * @return array|mixed Unmodified metadata.
     */
    public function handleUploadMetadata($metadata, $attachmentId) {
        if (!is_array($metadata) || empty($attachmentId)) {
            return $metadata;
        }

        if (!$this->config->get('auto_convert_uploads', true)) {
            return $metadata;
        }

        $this->processAttachment((int) $attachmentId, $metadata);
        return $metadata;
    }

    /**
     * Process an attachment and all its intermediate sizes for WebP and AVIF.
     *
     * @param int $attachmentId Attachment post ID.
     * @param array|null $metadata Optional attachment metadata if already loaded.
     * @param array $options Optional conversion parameters (force, formats, etc.).
     * @return array Processing summary.
     */
    public function processAttachment(int $attachmentId, ?array $metadata = null, array $options = []): array {
        // Prevent recursive execution or parallel duplicate processing
        if (isset($this->lockedAttachments[$attachmentId])) {
            return ['status' => 'locked', 'attachment_id' => $attachmentId];
        }

        $this->lockedAttachments[$attachmentId] = true;

        try {
            $fullPath = get_attached_file($attachmentId);
            if (empty($fullPath) || !file_exists($fullPath)) {
                $this->recordFailure($attachmentId, 'file_not_found', 'Attached file path does not exist on disk.');
                return ['status' => 'failed', 'error' => 'file_not_found'];
            }

            if ($metadata === null) {
                $metadata = wp_get_attachment_metadata($attachmentId);
                if (!is_array($metadata)) {
                    $metadata = [];
                }
            }

            $baseDir = dirname($fullPath);
            $totalSavedBytes = 0;
            $totalOriginalBytes = 0;
            $formatsReport = [];

            // Determine formats to generate (AVIF strictly requires active entitlement)
            $optFormat = $this->config->get('optimization_format', 'avif_webp');
            $formats = ['webp'];

            if (Features::isAvifEnabled() && $this->config->get('auto_convert_avif', true) && $this->converter->isFormatSupported('avif')) {
                if ($optFormat === 'avif_webp') {
                    $formats = ['avif', 'webp'];
                } elseif ($optFormat === 'avif') {
                    $formats = ['avif'];
                } else {
                    $formats = ['webp'];
                }
            }

            if (!empty($options['formats'])) {
                $requested = (array) $options['formats'];
                if (!Features::isAvifEnabled()) {
                    $requested = array_values(array_diff($requested, ['avif']));
                }
                if (empty($requested)) {
                    $requested = ['webp'];
                }
                $formats = $requested;
            }

            $anyFormatSuccess = false;
            $allFormatsSuccess = true;

            foreach ($formats as $format) {
                $formatSizesReport = [];
                $formatOriginalBytes = 0;
                $formatSavedBytes = 0;

                // 1. Process Main / Full Image for this format
                $mainResult = $this->converter->convert($fullPath, null, $options, $format);
                $formatSizesReport['full'] = $mainResult->toArray();

                $formatOriginalBytes += $mainResult->getOriginalSize();
                if ($mainResult->isSuccess()) {
                    $formatSavedBytes += $mainResult->getSavedBytes();
                } else {
                    if ($mainResult->getErrorCode() === 'skipped_animated_gif') {
                        $this->recordSkipped($attachmentId, 'skipped_animated_gif', $mainResult->getErrorMessage());
                        return ['status' => 'skipped', 'reason' => 'animated_gif'];
                    }
                    if ($mainResult->getErrorCode() !== 'skipped_larger') {
                        $allFormatsSuccess = false;
                    }
                }

                // 2. Process Intermediate Sizes for this format (if enabled)
                if ($this->config->get('convert_thumbnails', true) && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                    foreach ($metadata['sizes'] as $sizeName => $sizeInfo) {
                        if (empty($sizeInfo['file'])) {
                            continue;
                        }

                        $thumbPath = $baseDir . DIRECTORY_SEPARATOR . $sizeInfo['file'];
                        if (!file_exists($thumbPath)) {
                            continue;
                        }

                        $thumbResult = $this->converter->convert($thumbPath, null, $options, $format);
                        $formatSizesReport[$sizeName] = $thumbResult->toArray();

                        $formatOriginalBytes += $thumbResult->getOriginalSize();
                        if ($thumbResult->isSuccess()) {
                            $formatSavedBytes += $thumbResult->getSavedBytes();
                        }
                    }
                }

                if ($formatSavedBytes > 0 || $mainResult->isSuccess()) {
                    $anyFormatSuccess = true;
                }

                $totalOriginalBytes = max($totalOriginalBytes, $formatOriginalBytes);
                $totalSavedBytes += $formatSavedBytes;

                $formatsReport[$format] = [
                    'sizes'       => $formatSizesReport,
                    'saved_bytes' => $formatSavedBytes,
                    'status'      => ($formatSavedBytes > 0 || $mainResult->isSuccess()) ? 'completed' : 'skipped_or_failed',
                ];
            }

            // Determine overall status
            $status = $anyFormatSuccess ? 'completed' : ($allFormatsSuccess ? 'skipped' : 'failed');

            // Save persistent conversion metadata with backward-compatible sizes map
            $sizesMap = $formatsReport['webp']['sizes'] ?? ($formatsReport['avif']['sizes'] ?? []);

            MetadataManager::saveAttachmentData($attachmentId, [
                'status'               => $status,
                'attachment_id'        => $attachmentId,
                'total_original_bytes' => $totalOriginalBytes,
                'total_saved_bytes'    => $totalSavedBytes,
                'sizes'                => $sizesMap,
                'formats'              => $formatsReport,
                'processed_at'         => time(),
            ]);

            return [
                'status'        => $status,
                'attachment_id' => $attachmentId,
                'saved_bytes'   => $totalSavedBytes,
                'formats'       => $formatsReport,
            ];

        } finally {
            unset($this->lockedAttachments[$attachmentId]);
        }
    }

    /**
     * Handler for delete_attachment action.
     * Safely deletes WebP and AVIF derivatives without touching original files.
     *
     * @param int|mixed $attachmentId Attachment ID being deleted.
     * @return void
     */
    public function handleAttachmentDeletion($attachmentId): void {
        $attachmentId = (int) $attachmentId;
        if ($attachmentId <= 0) {
            return;
        }

        $fullPath = get_attached_file($attachmentId);
        if (empty($fullPath)) {
            MetadataManager::deleteAttachmentData($attachmentId);
            return;
        }

        $baseDir = dirname($fullPath);
        $metadata = wp_get_attachment_metadata($attachmentId);

        // Delete full image WebP and AVIF derivatives
        $this->safeDeleteDerivative($fullPath, 'webp');
        $this->safeDeleteDerivative($fullPath, 'avif');

        // Delete intermediate size WebP and AVIF derivatives
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $sizeInfo) {
                if (!empty($sizeInfo['file'])) {
                    $thumbPath = $baseDir . DIRECTORY_SEPARATOR . $sizeInfo['file'];
                    $this->safeDeleteDerivative($thumbPath, 'webp');
                    $this->safeDeleteDerivative($thumbPath, 'avif');
                }
            }
        }

        MetadataManager::deleteAttachmentData($attachmentId);
    }

    /**
     * Safely delete a specific format derivative for a given source path.
     *
     * @param string $sourcePath Path to source image.
     * @param string $format Target derivative format ('webp' or 'avif').
     * @return bool True if deleted or did not exist.
     */
    public function safeDeleteDerivative(string $sourcePath, string $format = 'webp'): bool {
        $derivativePath = FilenameHelper::generateDerivativePath($sourcePath, $format);

        // Strict verification: only delete if path exists and is a verified NextGen derivative
        if (!empty($derivativePath) && file_exists($derivativePath) && FilenameHelper::isNextGenDerivative($derivativePath)) {
            // NEVER delete if it matches the source path (which is the original image)
            if (realpath($derivativePath) !== realpath($sourcePath)) {
                return @unlink($derivativePath);
            }
        }

        return true;
    }

    /**
     * Legacy helper for WebP deletion.
     */
    public function safeDeleteWebpDerivative(string $sourcePath): bool {
        return $this->safeDeleteDerivative($sourcePath, 'webp');
    }

    /**
     * Record skipped state.
     */
    private function recordSkipped(int $attachmentId, string $code, ?string $message): void {
        MetadataManager::saveAttachmentData($attachmentId, [
            'status'       => 'skipped',
            'error_code'   => $code,
            'message'      => $message,
            'processed_at' => time(),
        ]);
    }

    /**
     * Record failure state.
     */
    private function recordFailure(int $attachmentId, string $code, ?string $message): void {
        MetadataManager::saveAttachmentData($attachmentId, [
            'status'       => 'failed',
            'error_code'   => $code,
            'message'      => $message,
            'processed_at' => time(),
        ]);
    }
}
