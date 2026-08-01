<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Contact;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client portal access for a contact.
 *
 * Bit CRM does not model portal users separately: portal access is a set of
 * capabilities plus a marker meta on the WordPress user that shares the
 * contact's email. Every action here therefore starts from a contact and
 * resolves that user itself.
 */
final class ClientPortalService extends BaseService
{
    public function grantPortalAccess()
    {
        [$contact, , $email, $payload, $error] = $this->resolvePortalUser();
        if ($error !== null) {
            return $error;
        }

        $portalService = new \BitApps\CrmPro\Services\ClientPortalService();

        if ($portalService->hasPortalAccessByEmail($email)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('This contact already has client portal access.', 'bit-pi')];
        }

        $capabilities = $this->portalCapabilities();
        $payload['capabilities'] = $capabilities;

        // Creates the WordPress user when the email is new, and queues Bit CRM's
        // access email with the generated password.
        $result = $portalService->upsertPortalUser($contact, $capabilities);

        if (is_wp_error($result)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => $result->get_error_message()];
        }

        $userId = (int) ($result['userId'] ?? 0);

        return [
            'status_code' => 200,
            'payload'     => $payload,
            'response'    => [
                'user_id'      => $userId,
                'email'        => $email,
                'capabilities' => $portalService->getUserCapabilities($userId),
            ],
        ];
    }

    public function updatePortalAccess()
    {
        [, $user, $email, $payload, $error] = $this->resolvePortalUser();
        if ($error !== null) {
            return $error;
        }

        $portalService = new \BitApps\CrmPro\Services\ClientPortalService();

        if (!$user || !$portalService->hasPortalAccessByUserId((int) $user->ID)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('This contact does not have client portal access yet.', 'bit-pi')];
        }

        $capabilities = $this->portalCapabilities();
        $payload['capabilities'] = $capabilities;

        if (!$portalService->syncUserCapabilities($user, $capabilities)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to update client portal capabilities.', 'bit-pi')];
        }

        return [
            'status_code' => 200,
            'payload'     => $payload,
            'response'    => [
                'user_id'      => (int) $user->ID,
                'email'        => $email,
                'capabilities' => $portalService->getUserCapabilities((int) $user->ID),
            ],
        ];
    }

    public function revokePortalAccess()
    {
        [, $user, $email, $payload, $error] = $this->resolvePortalUser();
        if ($error !== null) {
            return $error;
        }

        $portalService = new \BitApps\CrmPro\Services\ClientPortalService();

        if (!$user || !$portalService->hasPortalAccessByUserId((int) $user->ID)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('This contact does not have client portal access.', 'bit-pi')];
        }

        // Strips the portal capabilities only; the WordPress account survives.
        if (!$portalService->revokePortalAccess((int) $user->ID)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to revoke client portal access.', 'bit-pi')];
        }

        return [
            'status_code' => 200,
            'payload'     => $payload,
            'response'    => ['user_id' => (int) $user->ID, 'email' => $email],
        ];
    }

    public function updatePortalPassword()
    {
        [, $user, $email, $payload, $error] = $this->resolvePortalUser();
        if ($error !== null) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['password' => ['required', 'string']]);
        if (!empty($error)) {
            return $error;
        }

        $portalService = new \BitApps\CrmPro\Services\ClientPortalService();

        if (!$user || !$portalService->hasPortalAccessByUserId((int) $user->ID)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('This contact does not have client portal access.', 'bit-pi')];
        }

        $portalService->updatePassword((int) $user->ID, $fields['password']);
        $portalService->markPasswordChanged((int) $user->ID);

        return [
            'status_code' => 200,
            'payload'     => $payload,
            'response'    => ['user_id' => (int) $user->ID, 'email' => $email],
        ];
    }

    public function getPortalAccess()
    {
        [, $user, $email, $payload, $error] = $this->resolvePortalUser();
        if ($error !== null) {
            return $error;
        }

        $portalService = new \BitApps\CrmPro\Services\ClientPortalService();
        $hasAccess = $user && $portalService->hasPortalAccessByUserId((int) $user->ID);

        return [
            'status_code' => 200,
            'payload'     => $payload,
            'response'    => [
                'user_id'           => $user ? (int) $user->ID : 0,
                'email'             => $email,
                'has_portal_access' => $hasAccess,
                'capabilities'      => $hasAccess ? $portalService->getUserCapabilities((int) $user->ID) : [],
            ],
        ];
    }

    /**
     * Turn the selected capability list into the `[shortName => true]` map Bit
     * CRM expects. An empty selection makes Bit CRM apply its own defaults.
     *
     * @return array<string, bool>
     */
    private function portalCapabilities(): array
    {
        $selected = (array) ($this->fields()['capabilities'] ?? []);

        $capabilities = [];
        foreach ($selected as $capability) {
            $capability = trim((string) $capability);

            if ($capability !== '') {
                $capabilities[$capability] = true;
            }
        }

        return $capabilities;
    }

    /**
     * Load the configured contact and the WordPress user behind its email.
     *
     * The user is null when no account exists for that email yet, which is a
     * valid state for granting access and an error for everything else.
     *
     * @return array{0: mixed, 1: mixed, 2: string, 3: array, 4: null|array{status_code: int, payload: array, response: mixed}}
     */
    private function resolvePortalUser()
    {
        if (!BitCrmHelper::isProActive()) {
            return [
                null, null, '', [],
                ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Client Portal) is not installed or activated', 'bit-pi')],
            ];
        }

        // The portal lives in Bit CRM Pro, the contact it hangs off does not.
        foreach (['BitApps\CrmPro\Services\ClientPortalService', 'BitApps\Crm\Model\Contact'] as $class) {
            if ($error = BitCrmHelper::validateClassExists($class)) {
                return [null, null, '', [], $error];
            }
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['contact_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return [null, null, '', [], $error];
        }

        $contactId = (int) $fields['contact_id'];
        $payload = ['contact_id' => $contactId];

        $contact = Contact::findOne(['id' => $contactId, 'is_trash' => 0]);

        if (empty($contact)) {
            return [
                null, null, '', $payload,
                ['status_code' => 400, 'payload' => $payload, 'response' => __('Contact not found.', 'bit-pi')],
            ];
        }

        $email = (string) ($contact->email ?? '');

        if ($email === '' || !is_email($email)) {
            return [
                null, null, '', $payload,
                ['status_code' => 400, 'payload' => $payload, 'response' => __('This contact has no valid email, so it cannot use the client portal.', 'bit-pi')],
            ];
        }

        $payload['email'] = $email;
        $user = get_user_by('email', $email);

        return [$contact, $user ?: null, $email, $payload, null];
    }
}
