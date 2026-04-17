<?php

namespace BitApps\Pi\src\Integrations\ContactForm7;

use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use WPCF7_ContactForm;
use WPCF7_Submission;

if (!defined('ABSPATH')) {
    exit;
}


class ContactForm7Trigger
{
    public static function handleSubmit()
    {
        $flows = FlowService::exists('contactForm7', 'formSubmitted');

        if (!$flows || !class_exists('WPCF7_ContactForm')) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();

        $postID = (int) $submission->get_meta('container_post_id');

        if (!$submission || !$formData = $submission->get_posted_data()) {
            return;
        }

        if (isset($formData['_wpcf7'])) {
            $formId = $formData['_wpcf7'];
        } else {
            $current_form = WPCF7_ContactForm::get_current();
            $formId = $current_form->id();
        }

        $files = self::setFileRoot($submission->uploaded_files());
        $formData = array_merge($formData, $files);

        if ($postID !== 0) {
            $formData['post_id'] = $postID;
        }

        $data = ['form_id' => $formId, 'form_data' => $formData];

        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);

            if (!$triggerNode) {
                continue;
            }

            $nodeHelper = new NodeInfoProvider($triggerNode);
            $configFormId = $nodeHelper->getFieldMapConfigs('form-id.value');

            if ($configFormId !== 'any' && (int) $configFormId !== $formId) {
                continue;
            }

            FlowExecutor::execute($flow, $data);
        }
    }

    private static function setFileRoot($files)
    {
        $allFiles = [];

        foreach ($files as $key => $file) {
            $allFiles[$key] = \is_array($file) ? self::setFileRoot($file) : self::fileUrl($file);
        }

        return $allFiles;
    }

    private static function fileUrl($file)
    {
        $uploadDir = wp_upload_dir();
        $fileBaseURL = $uploadDir['baseurl'];
        $fileBasePath = $uploadDir['basedir'];

        if (\is_array($file)) {
            $url = [];

            foreach ($file as $fileIndex => $fileUrl) {
                $url[$fileIndex] = str_replace($fileBaseURL, $fileBasePath, $fileUrl);
            }
        } else {
            $url = str_replace($fileBasePath, $fileBaseURL, $file);
        }

        return $url;
    }
}
