<?php

namespace App\Http\Controllers\WorkflowAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use SimpleXMLElement;

class RequestController extends Controller
{
  public function checkJobOnMonday($jobNum){
    $token = env('MONDAY_TOKEN');
    $query = '{ items_by_column_values(board_id: 736609738, column_id: "name", column_value: "'. $jobNum. '", state: active) {id name}}';
    $url = "https://api.monday.com/v2/";

    $response = Http::withHeaders([
              'Authorization' => $token,
              'Content-Type' => 'application/json',
          ])->withBody(json_encode(['query' => $query]), 'application/json')->POST($url);
    $jobArray = json_decode($response)->data->items_by_column_values;
    return count($jobArray);
  }

  public function getItemId($jobId){
    $token = env('MONDAY_TOKEN');
    $query = '{ items_by_column_values(board_id: 736609738, column_id: "name", column_value: "' . $jobId . '", state: active) {id}}';
    $url = "https://api.monday.com/v2/";
    $response = Http::withHeaders([
              'Authorization' => $token,
              'Content-Type' => 'application/json',
          ])->withBody(json_encode(['query' => $query]), 'application/json')->POST($url);
    $mondayItemId = json_decode($response)->data->items_by_column_values[0]->id;
    return $mondayItemId;
  }

  public function updateToMonady($jobId,$jobDetails){
    $token = env('MONDAY_TOKEN');
    $mondayItemId = $this -> getItemId($jobId);

    $query = 'mutation { change_multiple_column_values (board_id: 736609738,item_id:'.$mondayItemId.', column_values: "'. $jobDetails .'") { id } }';
    $url = "https://api.monday.com/v2/";

    $response = Http::withHeaders([
              'Authorization' => $token,
              'Content-Type' => 'application/json',
          ])->withBody(  json_encode(['query' => $query]), 'application/json')->POST($url);
  }


  public function createJobToMonday($jobId,$jobDetails){

    $token = env('MONDAY_TOKEN');
    $query = 'mutation { create_item (board_id: 736609738,item_name: "'. $jobId .'", column_values:"'. $jobDetails .'") { id } }';

    $url = "https://api.monday.com/v2/";
    $response = Http::withHeaders([
              'Authorization' => $token,
              'Content-Type' => 'application/json',
          ])->withBody(  json_encode(['query' => $query]), 'application/json')->POST($url);
  }



  public function updateToWM($jobId,$jobDetails){

    if(file_exists("TokenSave.txt")){
      $token = file_get_contents("TokenSave.txt");
    }else{
      // if not token, rertun 401. Frontend redirect to Authentication page.
      return 401;
    }

    $tenantId = env('WORKFLOW_TENANT_ID');
    $state = $jobDetails-> state;
    $dhfStatus = $jobDetails-> DHFStatus;
    $dhfStatusUUID = $jobDetails-> DHFStatusUUID;

      // Update state

      $url = 'https://api.xero.com/workflowmax/3.0/job.api/state';
      $xml = '<Job>
       <ID>' . $jobId . '</ID>
       <State>'.  $state .'</State>
      </Job>
      ';

      $response1 = Http::withHeaders([
          'xero-tenant-id' => $tenantId,
          'Authorization' => 'Bearer ' . $token,
      ])->withBody( $xml, 'text/plain; charset=utf-8')->put($url);

      $stateCode1 = $response1-> getStatusCode();


      // Update DHF state
      $url = 'https://api.xero.com/workflowmax/3.0/job.api/update/' . $jobId . '/customfield';
      $xml = '<CustomFields>
              <CustomField>
                  <UUID>'.$dhfStatusUUID.'</UUID>
                  <Name>DHF Status</Name>
                  <Text>' . $dhfStatus . '</Text>
              </CustomField>
      </CustomFields>
      ';
      $response2 = Http::withHeaders([
          'xero-tenant-id' => 'b01c0f54-45c5-439b-b103-97ef6ab6f588',
          'Authorization' => 'Bearer ' . $token,
      ])->withBody( $xml, 'text/plain; charset=utf-8')->put($url);

      $stateCode2 = $response2-> getStatusCode();
      if ($stateCode1!= 200 or $stateCode2!= 200 ){
        if($stateCode1==200){return $stateCode2;}
        else if ($stateCode2==200){return $stateCode1;}
        else {return $stateCode1;}
      }else{
        return 200;
    }      // return $response1;
  }


  public function syncData (Request $request){
    //get job id from url
    $jobId = $request->get('jobId');

    // get and parse request body content
    //ref:https://stackoverflow.com/questions/28459172/how-do-i-get-http-request-body-content-in-laravel
    $jobDetails = json_decode($request->getContent());
    $result = new \stdClass;
    //Update to Workflow Max
    $updateStatusCode = $this -> updateToWM($jobId,$jobDetails);

    if($updateStatusCode!=200){
      if($updateStatusCode == 401){
        $result-> status = "Unauthorized";
        $result-> description = "Unauthorized";
      }else{
        $result-> status = "ERROR";
        $result-> description = "Workflow Max update failed";
      }
      return json_encode($result);
    }

    //Get the data ready that needs to be sent to monday.com
    $dataSendToMonday = new \stdClass;

    $dataSendToMonday->status = new \stdClass;
    $dataSendToMonday->status->label= $jobDetails-> state;
    $dataSendToMonday->dhf_status = new \stdClass;
    $dataSendToMonday->dhf_status->label = $jobDetails-> DHFStatus;
    $dataSendToMonday->surgery_date = new \stdClass;
    $dataSendToMonday->surgery_date->date = $jobDetails-> surgeryDate;
    $dataSendToMonday->dob = new \stdClass;
    $dataSendToMonday->dob->date =$jobDetails-> dateOfBirth;
    $dataSendToMonday->patient_name = $jobDetails-> patientName;
    $dataSendToMonday->surgeon = $jobDetails-> client;
    $dataSendToMonday->device2 = $jobDetails-> deviceType;
    $dataSendToMonday->anatomy0 = $jobDetails-> anatomy;
    $dataSendToMonday->pathology9 =$jobDetails-> pathology;
    $dataSendToMonday->surgical_approach0 = $jobDetails-> surgicalApproach;
    $dataSendToMonday->hospital = $jobDetails-> hospital;

    $res = json_encode($dataSendToMonday);
    $res = addslashes($res);

    //Use this method to get the job array' length on Monday with specific jobId.
    $jobArrayLength=$this-> checkJobOnMonday($jobId);

    if($jobArrayLength == 0){//This job is not exist on Monday.com
      //Create a new job to Monday.com
      $this -> createJobToMonday($jobId,$res);
      // return json_encode("This new job has been created to Monday.com and update to Workflow Max.");

      $result-> status = "OK";
      $result-> description = "This new job has been created to Monday.com and update to Workflow Max.";
    }else if($jobArrayLength == 1){//There is and only one job with this jobId on Monday.com
      //Update data to Monday.com
      $this ->updateToMonady($jobId,$res);
      // return json_encode("This job has been update to Workflow Max and Monday.com.");
      $result-> status = "OK";
      $result-> description = "This job has been update to Workflow Max and Monday.com.";

    }else{// There are Multiple job exit with this jobId.
      // Throw an error message, Prompt the user to go to Monday.com and delete the redundant jobs
        // return json_encode("Something wrong, Please check on Monday.com ");
        $result-> status = "ERROR";
        $result-> description = "Something wrong, Please check on Monday.com ";
    }
    return json_encode($result);
  }

  public function searchJob (Request $request)
  {
    // $searchNum =60550;
    $searchNum = $request->get('jobId');
    if(file_exists("TokenSave.txt")){
      $token = file_get_contents("TokenSave.txt");
    }else{
      $result = new \stdClass;
      $result-> status = "Unauthorized";
      return json_encode($result);
    }


    $tenantId = env('WORKFLOW_TENANT_ID');

    // Get Job Details by job No. --finished
    $responseDefault = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'xero-tenant-id' => $tenantId
    ])->get('https://api.xero.com/workflowmax/3.0/job.api/get/' . $searchNum);

    $stateCode1 = $responseDefault-> getStatusCode();

    if ($stateCode1==401){
      $result = new \stdClass;
      $result-> status = "Unauthorized";
      return json_encode($result);
    }

    $xmlDefault=simplexml_load_string($responseDefault) or die("Error: Cannot create object");

    //Get Customer field
    $responseCustom = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'xero-tenant-id' => $tenantId
    ])->get('https://api.xero.com/workflowmax/3.0/job.api/get/'. $searchNum . '/customfield');

    $stateCode2 = $responseCustom-> getStatusCode();

    if ($stateCode2==401){
      $result = new \stdClass;
      $result-> status = "Unauthorized";
      return json_encode($result);
    }

    $xmlCustom=simplexml_load_string($responseCustom) or die("Error: Cannot create object");

    if($xmlDefault->Status == 'OK' && $xmlCustom->Status == 'OK'){
      $array = (array)$xmlCustom->CustomFields;
      $arrayfields = (array)$array['CustomField'];

      //customfield
      for( $x = 0; $x < count($arrayfields); $x++){
        $arraydata = (array)$arrayfields[$x];
        $key = '';
        $value = '';

        foreach($arraydata as $d_key => $d_value) {
          if($d_key == 'UUID'){
            continue;
          }
          else if($d_key == 'Name'){
            $key = $d_value;
          }else {
            $value = $d_value;
          }
        }
        //create Associative Arrays
        // https://www.w3schools.com/php/php_arrays_associative.asp
        // $customfields Use to store jobdetail name and value
        $customfields[$key] = $value;
      }
      $result = new \stdClass;
      $result-> status = "OK";
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
    }else{
      $result = new \stdClass;
      $result-> status = "ERROR";
      $result-> description = "Invalid job identifier";
      return json_encode($result);
    }
  }
}
