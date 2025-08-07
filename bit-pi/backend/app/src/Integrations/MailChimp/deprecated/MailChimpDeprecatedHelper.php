<?php

namespace BitApps\Pi\src\Integrations\MailChimp\deprecated;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Helpers\Hash;

final class MailChimpDeprecatedHelper
{
    public function getAudienceFields(Request $request)
    {
        $accessToken = Hash::decrypt($request->accessToken);
        $module = $request->module;
        if (property_exists($request, 'module') && $request->module !== null && ($module == 'd' || $module == 'remove_tag_from_a_member')) {
            $fields[] = ['value' => 'Email', 'label' => 'Email', 'required' => true];
            $response['audienceField'] = $fields;

            return $response;
        }

        $apiEndpoints = self::baseURL($request->dataCenter) . '/lists/' . $request->listId . '/merge-fields';
        $httpClient = new HttpClient(['headers' => ['Authorization' => 'Bearer ' . $accessToken]]);
        $apiResponse = $httpClient->request($apiEndpoints, 'GET', []);
        $fields = [];

        if (isset($apiResponse->merge_fields)) {
            $fields[] = (object) ['value' => 'Email', 'label' => 'Email', 'required' => true];
            $allFields = $apiResponse->merge_fields;
            foreach ($allFields as $field) {
                if ($field->name === 'Address') {
                    continue;
                }
                $fields[] = [
                    'value'    => $field->tag,
                    'label'    => $field->name,
                    'required' => $field->required ?? false
                ];
            }
            $response['audienceField'] = $fields;

            return $response;
        }
    }

    /**
     * MailChimp API Endpoint.
     *
     * @param mixed $dataCenter
     */
    public static function baseURL($dataCenter)
    {
        return "https://{$dataCenter}.api.mailchimp.com/3.0";
    }
}
