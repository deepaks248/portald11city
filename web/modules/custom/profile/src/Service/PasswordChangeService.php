<?php

namespace Drupal\profile\Service;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountInterface;

class PasswordChangeService
{
    public const SECURE_LINK = "https://";

    protected $globalVariables;
    protected $logger;
    protected $currentUser;
    protected $session;

    public function __construct(
        GlobalVariablesService $globalVariables,
        LoggerChannelFactoryInterface $loggerFactory,
        AccountProxyInterface $currentUser,
        SessionInterface $session
    ) {
        $this->globalVariables = $globalVariables;
        $this->logger = $loggerFactory->get('change_password');
        $this->currentUser = $currentUser;
        $this->session = $session;
    }

    public function changePassword(string $oldPass, string $newPass, string $confirmPass): array
    {
        try {
            if ($newPass !== $confirmPass) {
                return ['status' => FALSE, 'message' => 'New password and confirm password do not match.'];
            }

            $email = $this->currentUser->getEmail();

            // Step 1: Lookup in SCIM
            $idamconfig = $this->globalVariables->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
            $url = self::SECURE_LINK . $idamconfig . '/scim2/Users?filter=' . urlencode("emails eq \"$email\"");
            $responseData = $this->globalVariables->curl_get_api($url);

            if (empty($responseData['Resources'][0]['id'])) {
                $this->logger->error('User ID not found for email: @mail', ['@mail' => $email]);
                return ['status' => FALSE, 'message' => 'User not found in SCIM.'];
            }

            $idamUserId = $responseData['Resources'][0]['id'];

            // Step 2: Verify old password
            $payloadOld = [
                "grant_type" => "password",
                "password" => $oldPass,
                "client_id" => "hVBu5NSpBJHJ84KF70nfQ8ZMdnQa",
                "username" => $email,
            ];
            $resOld = $this->globalVariables->curl_post_idam(
                self::SECURE_LINK . $idamconfig . '/oauth2/token/',
                $payloadOld
            );
            if (empty($resOld['access_token'])) {
                return ['status' => FALSE, 'message' => 'Old password not matching!'];
            }

            // Step 3: Update password
            $payloadPass = [
                "schemas" => ["urn:ietf:params:scim:schemas:extension:enterprise:2.0:User"],
                "Operations" => [[
                    "op" => "replace",
                    "path" => "password",
                    "value" => $newPass,
                ]],
            ];
            $resPass = $this->globalVariables->curl_post_idam_auth(
                self::SECURE_LINK . $idamconfig . '/scim2/Users/' . $idamUserId,
                $payloadPass,
                'PATCH'
            );

            if (!empty($resPass['error'])) {
                $details = $resPass['details']['detail'] ?? 'Password update failed';
                return ['status' => FALSE, 'message' => $details];
            }

            if (!empty($resPass['emails'][0]) && $resPass['emails'][0] === $email) {
                return ['status' => FALSE, 'message' => 'Password changed successfully. Please log in again.'];
            }

            if (!empty($resPass['detail']) && str_contains(strtolower($resPass['detail']), 'password history')) {
                return ['status' => FALSE, 'message' => 'The password you are trying to use was already used in your last 3 password changes. Please choose a completely new password.'];
            }

            if (!empty($resPass['detail'])) {
                return ['status' => FALSE, 'message' => $resPass['detail']];
            }

            return ['status' => FALSE, 'message' => 'Password not updated!'];
        } catch (\Exception $e) {
            $this->logger->error('Exception during password change: @msg', ['@msg' => $e->getMessage()]);
            return ['status' => FALSE, 'message' => 'Unexpected error occurred.'];
        }
    }
}
