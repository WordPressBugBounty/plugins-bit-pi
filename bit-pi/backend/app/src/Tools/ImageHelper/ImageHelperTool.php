<?php

namespace BitApps\Pi\src\Tools\ImageHelper;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class ImageHelperTool
{
    public const MACHINE_SLUG = 'imageHelper';

    protected $nodeInfoProvider;

    private $flowHistoryId;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistory)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistory;
    }

    public function execute()
    {
        $flowId = $this->nodeInfoProvider->getFlowId();
        $nodeId = $this->nodeInfoProvider->getNodeId();
        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $flowId);

        $imageHelperData = $this->nodeInfoProvider->getData()['imageHelper'] ?? [];

        $conversionType = $imageHelperData['conversionType'] ?? 'imageToBase64';

        $params = $this->extractInputParameters($imageHelperData);

        $result = $this->processImageConversion($conversionType, $params);

        $inputData = $this->prepareInputData($conversionType, $params);

        if (isset($result['error'])) {
            return $this->createErrorResponse($nodeVariableInstance, $nodeId, $result, $inputData, $conversionType);
        }

        return $this->createSuccessResponse($nodeVariableInstance, $nodeId, $result, $inputData, $conversionType);
    }

    private function extractInputParameters($imageHelperData)
    {
        return [
            'value'       => MixInputHandler::replaceMixTagValue($imageHelperData['value'] ?? ''),
            'title'       => MixInputHandler::replaceMixTagValue($imageHelperData['title'] ?? ''),
            'altText'     => MixInputHandler::replaceMixTagValue($imageHelperData['altText'] ?? ''),
            'caption'     => MixInputHandler::replaceMixTagValue($imageHelperData['caption'] ?? ''),
            'description' => MixInputHandler::replaceMixTagValue($imageHelperData['description'] ?? ''),
            'imageUrl'    => MixInputHandler::replaceMixTagValue($imageHelperData['imageUrl'] ?? ''),
            'width'       => MixInputHandler::replaceMixTagValue($imageHelperData['width'] ?? ''),
            'height'      => MixInputHandler::replaceMixTagValue($imageHelperData['height'] ?? ''),
            'degree'      => $imageHelperData['degree'] ?? '',
        ];
    }

    private function prepareInputData($conversionType, $params)
    {
        if ($conversionType === 'base64ToImage') {
            return [
                'value'       => $params['value'],
                'title'       => $params['title'],
                'altText'     => $params['altText'],
                'caption'     => $params['caption'],
                'description' => $params['description'],
            ];
        }

        if ($conversionType === 'resizeImage') {
            return [
                'imageUrl' => $params['imageUrl'],
                'width'    => $params['width'],
                'height'   => $params['height']
            ];
        }

        if ($conversionType === 'rotateImage') {
            return [
                'imageUrl' => $params['imageUrl'],
                'degree'   => $params['degree']
            ];
        }

        return ['value' => $params['value']];
    }

    private function createErrorResponse($nodeVariableInstance, $nodeId, $result, $inputData, $conversionType)
    {
        $nodeVariableInstance->setNodeResponse($nodeId, ['error' => $result['error']]);
        $nodeVariableInstance->setVariables($nodeId, ['error' => $result['error']]);

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['ERROR'],
            $inputData,
            $result,
            $result['error'],
            $this->createDetails($conversionType),
        );
    }

    private function createSuccessResponse($nodeVariableInstance, $nodeId, $result, $inputData, $conversionType)
    {
        $nodeVariableInstance->setNodeResponse($nodeId, $result);
        $nodeVariableInstance->setVariables($nodeId, $result);

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            $inputData,
            $result,
            $this->getSuccessMessage($conversionType),
            $this->createDetails($conversionType),
        );
    }

    private function createDetails($conversionType)
    {
        return [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
            'operation'    => $conversionType,
        ];
    }

    private function getSuccessMessage($conversionType)
    {
        $messages = [
            'imageToBase64' => 'Image converted to Base64 successfully',
            'base64ToImage' => 'Base64 converted to Image and uploaded to WordPress Media Library successfully',
            'resizeImage'   => 'Image resized successfully',
            'rotateImage'   => 'Image rotated successfully',
        ];

        return $messages[$conversionType] ?? 'Operation completed successfully';
    }

    private function processImageConversion($conversionType, $params)
    {
        switch ($conversionType) {
            case 'imageToBase64':
                return $this->imageToBase64($params['value']);

            case 'base64ToImage':
                return $this->base64ToImage(
                    $params['value'],
                    $params['title'],
                    $params['altText'],
                    $params['caption'],
                    $params['description']
                );

            case 'resizeImage':
                return $this->resizeImage(
                    $params['imageUrl'],
                    $params['width'],
                    $params['height'],
                );

            case 'rotateImage':
                return $this->rotateImage(
                    $params['imageUrl'],
                    $params['degree'],
                );

            default:
                return ['error' => 'Invalid operation specified'];
        }
    }

    private function imageToBase64($imageInput)
    {
        if (empty($imageInput)) {
            return ['error' => 'Image input is required'];
        }

        if (filter_var($imageInput, FILTER_VALIDATE_URL)) {
            return $this->urlToBase64($imageInput);
        }

        if (file_exists($imageInput)) {
            return $this->fileToBase64($imageInput);
        }

        return ['error' => 'Invalid image input. Please provide a valid URL, file path, or base64 string'];
    }

    private function base64ToImage($base64Input, $title = '', $altText = '', $caption = '', $description = '')
    {
        if (empty($base64Input)) {
            return ['error' => 'Base64 input is required'];
        }

        $base64Data = $this->extractBase64Data($base64Input);

        if (!$this->isBase64($base64Data)) {
            return ['error' => 'Invalid base64 string provided'];
        }

        $imageData = base64_decode($base64Data);

        if ($imageData === false) {
            return ['error' => 'Failed to decode base64 string'];
        }

        $imageInfo = getimagesizefromstring($imageData);

        if ($imageInfo === false) {
            return ['error' => 'Invalid image data in base64 string'];
        }

        $uploadResult = $this->uploadToMediaLibrary($imageData, $imageInfo['mime'], $title, $altText, $caption, $description);

        if (isset($uploadResult['error'])) {
            return $uploadResult;
        }

        return [
            'url'          => $uploadResult['url'],
            'attachmentId' => $uploadResult['attachment_id'],
            'mimeType'     => $imageInfo['mime'],
            'width'        => $imageInfo[0],
            'height'       => $imageInfo[1],
            'size'         => \strlen($imageData),
            'filename'     => $uploadResult['filename'],
            'title'        => $title,
            'altText'      => $altText,
            'caption'      => $caption,
            'description'  => $description
        ];
    }

    private function resizeImage($imageUrl, $width, $height)
    {
        if (empty($imageUrl)) {
            return ['error' => 'Image URL is required'];
        }

        $targetWidth = !empty($width) ? (int) $width : null;
        $targetHeight = !empty($height) ? (int) $height : null;

        if (empty($targetWidth) && empty($targetHeight)) {
            return ['error' => 'At least one dimension (width or height) must be specified'];
        }

        $this->includeWordPressImageFunctions();

        $filePathResult = $this->resolveImagePath($imageUrl);
        if (isset($filePathResult['error'])) {
            return $filePathResult;
        }

        $filePath = $filePathResult['path'];
        $tmpFile = $filePathResult['tmpFile'] ?? null;

        $imageEditor = $this->initializeImageEditor($filePath);
        if (isset($imageEditor['error'])) {
            $this->cleanupTempFile($tmpFile);

            return $imageEditor;
        }

        $editor = $imageEditor['editor'];
        $currentSize = $editor->get_size();
        $originalWidth = $currentSize['width'];
        $originalHeight = $currentSize['height'];

        // Calculate dimensions maintaining aspect ratio
        if (empty($targetWidth)) {
            $targetWidth = (int) ($originalWidth * ($targetHeight / $originalHeight));
        }
        if (empty($targetHeight)) {
            $targetHeight = (int) ($originalHeight * ($targetWidth / $originalWidth));
        }

        // Validate that target dimensions don't exceed original dimensions
        if ($targetWidth > $originalWidth || $targetHeight > $originalHeight) {
            $this->cleanupTempFile($tmpFile);

            return [
                'error' => "Target dimensions ({$targetWidth}x{$targetHeight}) exceed original image dimensions ({$originalWidth}x{$originalHeight}). Please use smaller dimensions."
            ];
        }

        $resizeResult = $editor->resize($targetWidth, $targetHeight, false);

        if (is_wp_error($resizeResult)) {
            $this->cleanupTempFile($tmpFile);

            return ['error' => 'Failed to resize image: ' . $resizeResult->get_error_message()];
        }

        $pathInfo = pathinfo($filePath);
        $newFileName = $this->generateFileName(
            $pathInfo['filename'],
            $pathInfo['extension']
        );

        return $this->saveImageAndCleanup($editor, $newFileName, $tmpFile);
    }

    private function rotateImage($imageUrl, $degree)
    {
        if (empty($imageUrl)) {
            return ['error' => 'Image URL is required'];
        }

        if (empty($degree)) {
            return ['error' => 'Rotation degree is required'];
        }

        $this->includeWordPressImageFunctions();

        $filePathResult = $this->resolveImagePath($imageUrl);
        if (isset($filePathResult['error'])) {
            return $filePathResult;
        }

        $filePath = $filePathResult['path'];
        $tmpFile = $filePathResult['tmpFile'] ?? null;

        $imageEditor = $this->initializeImageEditor($filePath);
        if (isset($imageEditor['error'])) {
            $this->cleanupTempFile($tmpFile);

            return $imageEditor;
        }

        $editor = $imageEditor['editor'];

        // Convert clockwise to counter-clockwise rotation for WordPress
        $rotateDegree = $this->convertToCounterClockwise($degree);

        $rotateResult = $editor->rotate($rotateDegree);

        if (is_wp_error($rotateResult)) {
            $this->cleanupTempFile($tmpFile);

            return ['error' => 'Failed to rotate image: ' . $rotateResult->get_error_message()];
        }

        $pathInfo = pathinfo($filePath);
        $newFileName = $this->generateFileName(
            $pathInfo['filename'],
            $pathInfo['extension']
        );

        return $this->saveImageAndCleanup($editor, $newFileName, $tmpFile);
    }

    private function resolveImagePath($imageUrl)
    {
        $filePath = $imageUrl;
        $tmpFile = null;

        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $uploadDir = wp_upload_dir();
            $filePath = str_replace($uploadDir['baseurl'], $uploadDir['basedir'], $imageUrl);

            if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                $tmpFile = download_url($imageUrl);
                if (is_wp_error($tmpFile)) {
                    return ['error' => 'Failed to download image: ' . $tmpFile->get_error_message()];
                }
                $filePath = $tmpFile;
            }
        }

        if (!file_exists($filePath)) {
            $this->cleanupTempFile($tmpFile);

            return ['error' => 'Image file not found'];
        }

        return ['path' => $filePath, 'tmpFile' => $tmpFile];
    }

    private function initializeImageEditor($filePath)
    {
        $imageEditor = wp_get_image_editor($filePath);

        if (is_wp_error($imageEditor)) {
            return ['error' => 'Failed to load image editor: ' . $imageEditor->get_error_message()];
        }

        return ['editor' => $imageEditor];
    }

    private function generateFileName($defaultFileName, $extension)
    {
        return uniqid() . '-' . $defaultFileName . '.' . $extension;
    }

    private function saveImageAndCleanup($imageEditor, $newFileName, $tmpFile = null)
    {
        $uploadDir = wp_upload_dir();
        $newFilePath = $uploadDir['path'] . '/' . $newFileName;
        $saveResult = $imageEditor->save($newFilePath);

        if (is_wp_error($saveResult)) {
            $this->cleanupTempFile($tmpFile);

            return ['error' => 'Failed to save image: ' . $saveResult->get_error_message()];
        }

        $this->cleanupTempFile($tmpFile);

        $newFileUrl = $uploadDir['url'] . '/' . $newFileName;

        return [
            'url'      => $newFileUrl,
            'filePath' => $newFilePath,
            'fileName' => $newFileName,
            'mimeType' => $saveResult['mime-type']
        ];
    }

    private function cleanupTempFile($tmpFile)
    {
        if ($tmpFile && file_exists($tmpFile)) {
            wp_delete_file($tmpFile);
        }
    }

    private function convertToCounterClockwise($degree)
    {
        if ($degree === '90') {
            return 270; // 90° clockwise = 270° counter-clockwise
        }
        if ($degree === '270') {
            return 90; // 270° clockwise = 90° counter-clockwise
        }

        return (int) $degree;
    }

    private function includeWordPressImageFunctions()
    {
        if (!\function_exists('wp_get_image_editor')) {
            include_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }

    private function urlToBase64($url)
    {
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return ['error' => 'Failed to fetch image from URL: ' . $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);

        $mimeType = $this->getMimeTypeFromData($body);

        $base64 = base64_encode($body);

        return [
            'base64'   => $base64,
            'mimeType' => $mimeType,
            'size'     => \strlen($body),
            'url'      => $url
        ];
    }

    private function fileToBase64($filePath)
    {
        $filePath = Utility::getFilePath(trim($filePath));

        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem || !$wp_filesystem->exists($filePath)) {
            return ['error' => 'File not found or not accessible'];
        }

        $imageData = $wp_filesystem->get_contents($filePath);

        if ($imageData === false) {
            return ['error' => 'Failed to read image file'];
        }

        $mimeType = $this->getMimeTypeFromData($imageData);

        $base64 = base64_encode($imageData);

        return [
            'base64'   => $base64,
            'mimeType' => $mimeType,
            'size'     => \strlen($imageData),
            'filePath' => $filePath
        ];
    }

    private function extractBase64Data($base64Input)
    {
        if (strpos($base64Input, 'data:') === 0) {
            $commaPos = strpos($base64Input, ',');
            if ($commaPos !== false) {
                return substr($base64Input, $commaPos + 1);
            }
        }

        return $base64Input;
    }

    private function isBase64($string)
    {
        return (bool) (base64_encode(base64_decode($string, true)) === $string);
    }

    private function getMimeTypeFromData($data)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $data);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    private function uploadToMediaLibrary($imageData, $mimeType, $title = '', $altText = '', $caption = '', $description = '')
    {
        $extension = $this->getExtensionFromMimeType($mimeType);

        $baseName = !empty($title) ? $title : 'image';

        $sanitizedTitle = trim(
            preg_replace('/[^a-z0-9-_]/', '', str_replace(' ', '-', strtolower($baseName))),
            '-_'
        );

        $fileName = $sanitizedTitle . '-' . uniqid() . '.' . $extension;

        if (!\function_exists('wp_handle_upload')) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!\function_exists('wp_insert_attachment')) {
            include_once ABSPATH . 'wp-admin/includes/media.php';

            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $uploadDir = wp_upload_dir();

        if (!$uploadDir['error']) {
            $uploadPath = $uploadDir['path'] . '/' . $fileName;
            $uploadUrl = $uploadDir['url'] . '/' . $fileName;

            $upload = wp_upload_bits($fileName, null, $imageData);

            if (!empty($upload['error'])) {
                return ['error' => $upload['error']];
            }

            $uploadPath = $upload['file'];

            $uploadUrl = $upload['url'];

            $attachmentId = $this->insertAttachment($uploadPath, $fileName, $mimeType, $title, $altText, $caption, $description);

            if (!$attachmentId) {
                if (file_exists($uploadPath)) {
                    wp_delete_file($uploadPath);
                }

                return ['error' => 'Failed to create attachment in database'];
            }

            return [
                'url'           => $uploadUrl,
                'file'          => $uploadPath,
                'attachment_id' => $attachmentId,
                'filename'      => $fileName
            ];
        }

        return ['error' => 'Failed to create upload directory'];
    }

    private function getExtensionFromMimeType($mimeType)
    {
        $mimeToExt = wp_get_mime_types();

        return $mimeToExt[$mimeType] ?? 'jpg';
    }

    private function insertAttachment($filePath, $fileName, $mimeType, $title = '', $altText = '', $caption = '', $description = '')
    {
        if (!\function_exists('wp_insert_attachment')) {
            include_once ABSPATH . 'wp-admin/includes/media.php';

            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $postTitle = !empty($title) ? sanitize_text_field($title) : sanitize_file_name(pathinfo($fileName, PATHINFO_FILENAME));

        $attachmentData = [
            'post_mime_type' => $mimeType,
            'post_title'     => $postTitle,
            'post_content'   => !empty($description) ? sanitize_textarea_field($description) : '',
            'post_excerpt'   => !empty($caption) ? sanitize_textarea_field($caption) : '',
            'post_status'    => 'inherit'
        ];

        $attachmentId = wp_insert_attachment($attachmentData, $filePath);

        if (is_wp_error($attachmentId)) {
            return false;
        }

        if (!empty($altText)) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($altText));
        }

        $attachmentMetadata = wp_generate_attachment_metadata($attachmentId, $filePath);
        wp_update_attachment_metadata($attachmentId, $attachmentMetadata);

        return $attachmentId;
    }
}
