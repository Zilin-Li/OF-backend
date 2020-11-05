<?php

namespace App\Http\Controllers\workflowAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkflowAuth\Storage;
require '../vendor/autoload.php';

class CallbackController extends Controller
{

    public function callback()
    {
      // ini_set('display_errors', 'On');

      // require_once('storage.php');

      // Storage Classe uses sessions for storing token > extend to your DB of choice
      $storage = new Storage();

      $provider = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => env('WORKFLOW_CLIENT_ID'),
        'clientSecret'            => env('WORKFLOW_CLIENT_SECRET'),
        'redirectUri'             => env('WORKFLOW_CALLBACK'),
        'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
        'urlAccessToken'          => 'https://identity.xero.com/connect/token',
        'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
      ]);

      // If we don't have an authorization code then get one
      if (!isset($_GET['code'])) {
        // echo "Something went wrong, no authorization code found";

        exit("Something went wrong, no authorization code found.Please close the current browser and log in again.");

      // Check given state against previously stored one to mitigate CSRF attack
      } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
        echo "Invalid State";
        unset($_SESSION['oauth2state']);
        exit('Invalid state');
      } else {

        try {
          // Try to get an access token using the authorization code grant.
          $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
          ]);

          $config = \XeroAPI\XeroPHP\Configuration::getDefaultConfiguration()->setAccessToken( (string)$accessToken->getToken() );
          $identityApi = new \XeroAPI\XeroPHP\Api\IdentityApi(
            new \GuzzleHttp\Client(),
            $config
          );

          $result = $identityApi->getConnections();

          file_put_contents ("..\storage\TokenSave.txt" , $accessToken->getToken());

          header('Location: ' . 'http://localhost:8080/mainpage');
          exit();

        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
          echo "Callback failed";

          exit();
        }
      }


    }
}
