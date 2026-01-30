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
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApiHttpClientService;

class PasswordChangeService
{
    public const SECURE_LINK = "https://";

    protected $globalVariables;
    protected $logger;
    protected $currentUser;
    protected $session;
    protected $vaultConfigService;
    protected $apiHttpClientService;

    public function __construct(
        GlobalVariablesService $globalVariables,
        LoggerChannelFactoryInterface $loggerFactory,
        AccountProxyInterface $currentUser,
        SessionInterface $session,
        VaultConfigService $vaultConfigService,
        ApiHttpClientService $apiHttpClientService
    ) {
        $this->globalVariables = $globalVariables;
        $this->logger = $loggerFactory->get('change_password');
        $this->currentUser = $currentUser;
        $this->session = $session;
        $this->vaultConfigService = $vaultConfigService;
        $this->apiHttpClientService = $apiHttpClientService;
    }

    public function changePassword(string $oldPass, string $newPass, string $confirmPass): array
    {
        // Default failure response
        $result = [
            'status' => FALSE,
            'message' => 'Password not updated!',
        ];

        try {
            if ($newPass !== $confirmPass) {
                $result['message'] = 'New password and confirm password do not match.';
            } else {
                $email = $this->currentUser->getEmail();

                // Step 1: Lookup in SCIM
                $idamconfig = $this->vaultConfigService
                    ->getGlobalVariables()['applicationConfig']['config']['idamconfig'];

                $url = self::SECURE_LINK . $idamconfig . '/scim2/Users?filter='
                    . urlencode("emails eq \"$email\"");

                $responseData = $this->apiHttpClientService->getApi($url);

                if (empty($responseData['Resources'][0]['id'])) {
                    $this->logger->error('User ID not found for email: @mail', ['@mail' => $email]);
                    $result['message'] = 'User not found in SCIM.';
                } else {
                    $idamUserId = $responseData['Resources'][0]['id'];

                    // Step 2: Verify old password
                    $payloadOld = [
                        'grant_type' => 'password',
                        'password' => $oldPass,
                        'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
                        'username' => $email,
                    ];

                    $resOld = $this->apiHttpClientService->postIdam(
                        self::SECURE_LINK . $idamconfig . '/oauth2/token/',
                        $payloadOld
                    );

                    if (empty($resOld['access_token'])) {
                        $result['message'] = 'Old password not matching!';
                    } else {
                        // Step 3: Update password
                        $payloadPass = [
                            'schemas' => [
                                'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User',
                            ],
                            'Operations' => [[
                                'op' => 'replace',
                                'path' => 'password',
                                'value' => $newPass,
                            ]],
                        ];

                        $resPass = $this->apiHttpClientService->postIdamAuth(
                            self::SECURE_LINK . $idamconfig . '/scim2/Users/' . $idamUserId,
                            $payloadPass,
                            'PATCH'
                        );

                        if (!empty($resPass['error'])) {
                            $result['message'] = $resPass['details']['detail']
                                ?? 'Password update failed';
                        } elseif (!empty($resPass['emails'][0]) && $resPass['emails'][0] === $email) {
                            $result['message'] = 'Password changed successfully. Please log in again.';
                        } elseif (
                            !empty($resPass['detail'])
                            && str_contains(strtolower($resPass['detail']), 'password history')
                        ) {
                            $result['message'] =
                                'The password you are trying to use was already used in your last 3 password changes. Please choose a completely new password.';
                        } elseif (!empty($resPass['detail'])) {
                            $result['message'] = $resPass['detail'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Exception during password change: @msg',
                ['@msg' => $e->getMessage()]
            );
            $result['message'] = 'Unexpected error occurred.';
        }

        return $result;
    }
}
