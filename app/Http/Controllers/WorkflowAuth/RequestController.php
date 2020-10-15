<?php

namespace App\Http\Controllers\WorkflowAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;

use SimpleXMLElement;
class RequestController extends Controller
{
    public function update(Request $request){

      $token = file_get_contents("TokenSave.txt");
      $tenantId = 'b01c0f54-45c5-439b-b103-97ef6ab6f588';

      $searchNum = $request->get('jobId');
      $statuSelected = $request->get('Status');
      $DHFstatuSelected = $request->get('DHFStatus');
      $dhfStatusUUID = $request->get('dhfStatusUUID');


      // Update state

      $url = 'https://api.xero.com/workflowmax/3.0/job.api/state';
      $xml = '<Job>
       <ID>' . $searchNum . '</ID>
       <State>'.  $statuSelected .'</State>
      </Job>
      ';

      $response = Http::withHeaders([
          'xero-tenant-id' => $tenantId,
          'Authorization' => 'Bearer ' . $token,
      ])->withBody( $xml, 'text/plain; charset=utf-8')->put($url);


      // Update DHF state

      $url = 'https://api.xero.com/workflowmax/3.0/job.api/update/' . $searchNum . '/customfield';
      $xml = '<CustomFields>
              <CustomField>
                  <UUID>'.$dhfStatusUUID.'</UUID>
                  <Name>DHF Status</Name>
                  <Text>' . $DHFstatuSelected . '</Text>
              </CustomField>
      </CustomFields>
      ';

      $response1 = Http::withHeaders([
          'xero-tenant-id' => 'b01c0f54-45c5-439b-b103-97ef6ab6f588',
          'Authorization' => 'Bearer ' . $token,
      ])->withBody( $xml, 'text/plain; charset=utf-8')->put($url);

      return $response1;

    }

    public function index(Request $request)
    {
      // $searchNum =60550;
      $searchNum = $request->get('jobId');
      $token = file_get_contents("TokenSave.txt");
      $tenantId = 'b01c0f54-45c5-439b-b103-97ef6ab6f588';
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
      // dd ($xmlCustom);

      $array = (array)$xmlCustom->CustomFields;

      $arrayfields = (array)$array['CustomField'];
        // dd ($arrayfields);
      //customfield
      // $customfields = new \stdClass();
      for( $x = 0; $x < count($arrayfields); $x++){
        $arraydata = (array)$arrayfields[$x];
        // $valueType = 0; // if 0, means key, if 1 means value
        $key = '';
        // $UUIDKey='';
        $value = '';
        // $UUIDValue='';

        foreach($arraydata as $d_key => $d_value) {
          if($d_key == 'UUID'){
            continue;
             // $UUIDValue = $d_value;
          }
          else if($d_key == 'Name'){
            $key = $d_value;
            // $UUIDKey =$d_value;
          }else {
            $value = $d_value;
          }
        }
        //create Associative Arrays
        // https://www.w3schools.com/php/php_arrays_associative.asp
        // $customfields Use to store jobdetail name and value
        $customfields[$key] = $value;

        //$UUDIfields use to store jobdetail name and UUID
        // $UUDIfields[$UUIDKey] =$UUIDValue;
      }
      // dd($UUDIfields);
      $result = new \stdClass;
      // Default data
      $result -> jobId = strval($xmlDefault->Job->ID);
      $result -> state = strval($xmlDefault->Job->State)??'';
      $result -> client = strval($xmlDefault->Job->Client ->Name)??'';
      // Customfield data
      $result -> patienName = $customfields['Patient Name']??'';
      $result -> hospital = $customfields['Hospital']??'';
      $result -> deviceType = $customfields['Device Type']??'';
      $result -> anatomy = $customfields['Anatomy']??'';
      $result -> pathology = $customfields['Pathology']??'';
      $result -> surgicalApproach = $customfields['Surgical Approach']??'';
      $result -> DHFStatus = $customfields['DHF Status']??'';
      $result -> DHFStatusUUID = '242b780b-c94d-48ff-82a6-f34ef804f84f';

      // If date is exist in array, format and assignment.
      // If date is not exist in array, Set the value to null.
      if (array_key_exists('Surgery Date',$customfields)){
        $sDate = substr($customfields['Surgery Date'],0,10);
        $result -> surgeryDate = $sDate;
      }else{
        $result -> surgeryDate = '';
      }
      // If date is exist in array, format and assignment.
      // If date is not exist in array, Set the value to null.
      if (array_key_exists('Date Of Birth',$customfields)){
        $pDate = substr($customfields['Date Of Birth'],0,10);
        $result -> dateOfBirth =$pDate;
      }else{
        $result -> dateOfBirth = '';
      }

      return json_encode($result);





  }
}
