<?php

namespace App\Http\Controllers\WorkflowAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use SimpleXMLElement;

class RequestController extends Controller
{
  // This function uses to check whether a job is already exist on Monday.com.
  // Return a array of job list with specific job Id.
  public function checkJobOnMonday($jobId){
    $token = env('MONDAY_TOKEN');
    $query = '{ items_by_column_values(board_id: 736609738, column_id: "name", column_value: "'. $jobId. '", state: active) {id name}}';
    $url = "https://api.monday.com/v2/";

    $response = Http::withHeaders([
              'Authorization' => $token,
              'Content-Type' => 'application/json',
          ])->withBody(json_encode(['query' => $query]), 'application/json')->POST($url);
    $jobArray = json_decode($response)->data->items_by_column_values;
    return count($jobArray);
  }

  // This fuction uses to get item id on Monday.com with specific job id.
  // Return item id which can be use to add multiple item value.
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

  // This function uses to update Monday.com job details with specific job id.
  // Parameter（$jobDetails） is an array which including all job details that need to be update.
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

  // This function uses to create a new job with job details on Monday.com
  // Parameter（$jobDetails） is an array which including all job details.
  public function createJobToMonday($jobId,$jobDetails){

    $token = env('MONDAY_TOKEN');
    $query = 'mutation { create_item (board_id: 736609738,item_name: "'. $jobId .'", column_values:"'. $jobDetails .'") { id } }';

    $url = "https://api.monday.com/v2/";
    $response = Http::withHeaders([
              'Authorization' => $token,
              'Content-Type' => 'application/json',
          ])->withBody(  json_encode(['query' => $query]), 'application/json')->POST($url);
  }

  // This function is uses to update Workflow Max data.
  // Currently this feature only updates "state" and "DHF status"
  public function updateToWM($jobId,$jobDetails){

    //Check whether the token file is exist.
    //If not return error no. 401.
    if(file_exists("../storage/TokenSave.txt")){
      $token = file_get_contents("../storage/TokenSave.txt");
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
      // After send a request, get the response code.
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
      // After send a request, get the response code.
      $stateCode2 = $response2-> getStatusCode();

      // Check the responses code. if all equal to 200 means nothing wrong.
      // If one of responses code not equal to 200, means the update failed.
      // Return the responses code to frontend.
      if ($stateCode1!= 200 or $stateCode2!= 200 ){
        if($stateCode1==200){return $stateCode2;}
        else if ($stateCode2==200){return $stateCode1;}
        else {return $stateCode1;}
      }else{
        return 200;
    }
  }

  // This funciton uses to receive request send from fron-tend.
  // Parse the data sent from the front-end
  // Call the functions to synchronize the data
  // Sends the synchronization status information to the front-end
  public function syncData (Request $request){

    //get job id from url
    $jobId = $request->get('jobId');

    // get and parse request body content
    //ref:https://stackoverflow.com/questions/28459172/how-do-i-get-http-request-body-content-in-laravel
    $jobDetails = json_decode($request->getContent());
    $result = new \stdClass;

    //Update to Workflow Max and get the update status code
    $updateStatusCode = $this -> updateToWM($jobId,$jobDetails);

    //If the update status code equal to 200 means the update is successed,Continue to execute the function.
    //Else return the error code and stop the function.
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
    // $dataSendToMonday->hospital ="Queensland Children's Hospital";
    $res = json_encode($dataSendToMonday);
    
    // adds slashes to double quotes
    $res = addcslashes($res, '"');

    //Check the job list with specific job Id
    $jobArrayLength=$this-> checkJobOnMonday($jobId);

    //If the $jobArrayLength equal to 0, means this job id is not exist on Monday.com.
    //Create a new job on Monday.com.
    if($jobArrayLength == 0){
      $this -> createJobToMonday($jobId,$res);
      // return the status and status descripiton to fron-end.

      $result-> status = "OK";
      $result-> description = "Job: " . $jobId ." has been created to Monday.com and update to Workflow Max.";
    }
    //If the $jobArrayLength equal to 1, means this job id is already exist and only one on Monday.com.
    //Update the job details on Monday.com
    else if($jobArrayLength == 1){
      $this ->updateToMonady($jobId,$res);
      // return the status and status descripiton to fron-end.
      $result-> status = "OK";
      $result-> description = "Job: " . $jobId ." has been update to Workflow Max and Monday.com.";
    }
    //If the $jobArrayLength greater than 1, means this job id has multiple records on Monday
    // Return error message to front-end.
    else{
      $result-> status = "ERROR";
      $result-> description = "Something wrong, Please check on Monday.com ";
    }
    return json_encode($result);
  }



  // This funciton uses to receive search request send from fron-tend.
  // Parse the data sent from the front-end
  // Call the functions to search the job information from Workflow Max
  // If the job is exist in Workflow Max, Package the data and send it to the front-end
  // If the job is not exist in Workflow Max, send the error / fail information to front-end
  public function searchJob (Request $request)
  {
    // Get search job id from the request
    $searchNum = $request->get('jobId');

    //Check whether the token file is exist.
    //If not return error no. 401.
    if(file_exists("../storage/TokenSave.txt")){
      $token = file_get_contents("../storage/TokenSave.txt");
    }else{
      $result = new \stdClass;
      $result-> status = "Unauthorized";
      return json_encode($result);
    }

    $tenantId = env('WORKFLOW_TENANT_ID');

    // Send request to get the default job information from Workflow Max .
    $responseDefault = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'xero-tenant-id' => $tenantId
    ])->get('https://api.xero.com/workflowmax/3.0/job.api/get/' . $searchNum);
    //Check whether the request send successed.
    $stateCode1 = $responseDefault-> getStatusCode();
    // If state code not equal to 200, means something wrong with the token
    if ($stateCode1!=200){
      $result = new \stdClass;
      $result-> status = "Unauthorized";
      return json_encode($result);
    }
    $xmlDefault=simplexml_load_string($responseDefault) or die("Error: Cannot create object");

    // Send request to get the user-defined information from Workflow Max .
    $responseCustom = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'xero-tenant-id' => $tenantId
    ])->get('https://api.xero.com/workflowmax/3.0/job.api/get/'. $searchNum . '/customfield');
    //Check whether the request send successed.
    $stateCode2 = $responseCustom-> getStatusCode();
    // If state code not equal to 200, means something wrong with the token.
    if ($stateCode2!=200){
      $result = new \stdClass;
      $result-> status = "Unauthorized";
      return json_encode($result);
    }
    $xmlCustom=simplexml_load_string($responseCustom) or die("Error: Cannot create object");

    // If Job id exist and successed get all the job information. Pakage the job information.
    // If job id is not exist in Workflow Max. Send the error message to front-end.
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
