<?php

namespace App\Http\Controllers\WorkflowAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RequestController extends Controller
{
    public function index(Request $request)
    {
      // $searchNum =60550;
      $searchNum = $request->get('jobId');
      $token = file_get_contents("TokenSave.txt");
      $tenantId = '88';
//---------------------------------------------------------------------------------------
//Test part:
      // $token = $_SESSION['oauth2']['token'];
      // $tenantId = $_SESSION['oauth2']['tenant_id'];
      // return $tenantId;
      // return $token;

      //Get current job info
      // $response = Http::withHeaders([
      //     'Authorization' => 'Bearer ' . $token,
      //     'xero-tenant-id' => $tenantId
      // ])->get('https://api.xero.com/workflowmax/3.0/job.api/current');

      // Update statu
      // $response = Http::withHeaders([
      //     'Authorization' => 'Bearer ' . $token,
      //     'xero-tenant-id' => $tenantId
      // ])->put('https://api.xero.com/workflowmax/3.0/job.api/state',[
      //
      // ]);
//----------------------------------------------------------------------------------
  // Get Job Details by job No. --finished
      $responseDefault = Http::withHeaders([
          'Authorization' => 'Bearer ' . $token,
          'xero-tenant-id' => $tenantId
      ])->get('https://api.xero.com/workflowmax/3.0/job.api/get/' . $searchNum);

      $xmlDefault=simplexml_load_string($responseDefault) or die("Error: Cannot create object");

      //Get Customer field
      $responseCustom = Http::withHeaders([
          'Authorization' => 'Bearer ' . $token,
          'xero-tenant-id' => $tenantId
      ])->get('https://api.xero.com/workflowmax/3.0/job.api/get/'. $searchNum . '/customfield');
      $xmlCustom=simplexml_load_string($responseCustom) or die("Error: Cannot create object");
      $array = (array)$xmlCustom->CustomFields;
      $arrayfields = (array)$array['CustomField'];

      //customfield
      // $customfields = new \stdClass();

      for( $x = 0; $x < count($arrayfields); $x++){
        $arraydata = (array)$array['CustomField'][$x];
        $valueType = 0; // if 0, means key, if 1 means value
        $key = '';
        $value = '';

        foreach($arraydata as $d => $d_value) {
          if($d == 'UUID')
          continue;
          if($valueType == 0){
            $key = $d_value;

          }else {
            $value = $d_value;
          }
          $valueType = 1;
        }
        //create Associative Arrays
        // https://www.w3schools.com/php/php_arrays_associative.asp
        $customfields[$key] = $value;
      }
      $result = new \stdClass;
      $result -> jobId = strval($xmlDefault->Job->ID);
      $result -> state = strval($xmlDefault->Job->State)??'';
      $result -> client = strval($xmlDefault->Job->Client ->Name)??'';
      $result -> patienName = $customfields['Patient Name']??'';
      $result -> hospital = $customfields['Hospital']??'';
      $result -> deviceType = $customfields['Device Type']??'';
      $result -> anatomy = $customfields['Anatomy']??'';
      $result -> pathology = $customfields['Pathology']??'';
      $result -> surgicalApproach = $customfields['Surgical Approach']??'';
      $sDate = substr($customfields['Surgery Date'],0,10);
      $result -> surgeryDate = $sDate??'';
      $pDate = substr($customfields['Date Of Birth'],0,10);
      $result -> dateOfBirth =$pDate;
      $result -> DHFStatus = $customfields['DHF Status']??'';


      return json_encode($result);
    }
}
