<?php
class LeadsController extends AppController {
    public $helpers = array('Html', 'Form');

    public function index() {
        $this->set('leads', $this->Lead->find('all'));
    }

    public function beforeFilter() {
		parent::beforeFilter();
		// All posts must authenticate.
		if($this->request->is('post')){
			//echo "Here I am";
			//print_r($this->request->data);
		}
	}

	public function processCrmLead(){
		
      //Check to make sure of Request Type
        
        if(!$this->request->is('post')){
            $this->errors[] = 'Request type is not a POST';
            $this->_responseError($msg="POST");
        }
        $lead_data = $this->request->data;

        if( isset($lead_data['TrackId'])){
             $track_id = $lead_data['TrackId'];
        }

        if( isset($lead_data['lastInsertId'])){
            $lastInsertId = $lead_data['lastInsertId'];
            $params = array('conditions'=> array('Lead.id'=>$lastInsertId));
            $track_count = $this->Lead->find('count', $params);
            $lead = $this->Lead->find('first', $params);
        }else {
            $params = array('conditions'=> array('Lead.track_id'=>$track_id));
            $track_count = 0;
        } 

        //if($lead_data['Step'] == 2 && !isset($lead_data['LeadID'])){
        if($lead_data['Step'] == 2 && !(isset($lead_data['lead_id']))) {
            
           if($lead_data['FirstName'] != "") {

                $result = $this->step2_processLead($lead_data);
                $response = json_decode($result);
                if($response->result == 'failed'){
                    $return['status'] ='failed';
                    $return['redirect'] = '';
                }
                $lead_id = $response->lead_id;
                $lead_data["lead_id"] =$lead_id;
                $return['lead_id'] = $lead_id;
            } else {
                $return['status'] ='failed';
                $return['redirect'] = '';
            }
        }

        if(isset($lead_data['Ssn'])){

            $ssn = $this->_hash_ssn_process_crm_lead($this->request->data['Ssn']);
            $lead_data['SsnHash']=$ssn;
           // unset($lead_data['Ssn']);

        }

        if($lead_data['Step'] == 6 ){
            if($lead_data['FirstName'] != "") {

                $distribute_calls = substr_count($lead['Lead']['response'], 'distribute_request');
                if($distribute_calls < 2){

                    $result = $this->update_processLead($lead_data);
                    $response = json_decode($result);
                    $info = $response->info;
                    $return['status'] = $response->message;
                    $return['redirect'] = $info->redirectURL;
                    $return['total_sold'] = $info->total_delivery;

                    if(isset($lead_data['RequestId'])){

                        if($info->total_delivery > 0){
                     
                            $urlpixel = "https://nkoeg.com/pixel.do?t=pb&request_id=".$lead_data['RequestId'];
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $urlpixel);
                            $rsp = curl_exec($ch);
                            curl_close($ch);
                            $lead_data['pixel_response'] = $rsp;
                        }
                    }
                }else{
                    $return['status'] = "Success";
                    $return['redirect'] = "";
                    $return['total_sold'] = 0;
                }
            }else{
                $return['status'] = "Success";
                $return['redirect'] = "";
                $return['total_sold'] = 0;
            }
                  
        }
        $lead_info = json_encode($lead_data);

        if($track_count == 0){ //track not found so add         
            $this->Lead->create(array('track_id'=>$track_id, 'lead_info'=>$lead_info    ,'created_at'=> date("Y-m-d G:i:s", time()),'updated_at'=>date("    Y-m-d G:i:s", time())));
            $this->Lead->save();
            $lastInsertId = $this->Lead->getLastInsertID();
            $return['lastInsertId'] = $lastInsertId;
            $lead_updated_data = false; 

        }else{

            //$lead = $this->Lead->find('first', $params);
            
            $response_old = $lead['Lead']['response'];
            if(!empty($response)){
                $response_new = json_encode($response);
                $response_db = $response_old.$response_new;
            }else{
                $response_db = $response_old;
            }

            $this->Lead->id = $lead['Lead']['id'];
            $this->Lead->set('track_id', $track_id);
            $this->Lead->set('lead_info', $lead_info);
            if(!empty($response)){
                $this->Lead->set('response', $response_db);
            }
            $this->Lead->set('updated_at', date("Y-m-d G:i:s", time()));
            $this->Lead->save();
            $lead_updated_data = true;            
        }
       /* if(isset($lead_data['Check'])){
            if($lead_updated_data == true && $lead_data['Step'] == 6 && $lead_data['Check'] == true)
            {
                $lead = $this->Lead->find('first', $params);

                $datetime1 = strtotime($lead['Lead']['created_at']);
              
                $datetime2 = strtotime($lead['Lead']['updated_at']);
            
                $interval  = abs($datetime2 - $datetime1);
        
                $minutes   = round($interval / 60);
            
                //$totalDuration = $finishTime->diffInMinutes($startTime);
                if($minutes > 3){
                   
                    $return['status'] = 'error';
                    $return['redirect'] = '';
                 
                }

            }
        }*/
        echo $this->jsonresponse($return);
        exit;
	}

	public function step2_processLead($lead_data){
        $query  = 'lp_test=0'.
                '&ip_address='.$lead_data['IPAddress'].
                '&lp_campaign_id='.$lead_data['Campaign_id'].
                '&lp_campaign_key='.$lead_data['Campaign_key'].
                '&lp_no_distribute=1'.

                //First Page
                '&loan_purpose='.$lead_data['LoanPurpose'].
                '&credit_rating='.$lead_data['CreditRating'].
                '&zip_code='.$lead_data['Zip'].
                '&military='.$lead_data['Military'].
                '&agree=1'.
                
                //Personal Information
                '&loan_amount='.$lead_data['LoanAmount'].
                '&first_name='.$lead_data['FirstName'].
                '&last_name='.$lead_data['LastName'].
                '&email_address='.$lead_data['Email'].
                '&address='.$lead_data['Address1'].      
                '&state='.$lead_data['State'].
                '&city='.$lead_data['City'].
                '&residence_type='.$lead_data['ResidenceType'].
                '&rent_mortgage='.$lead_data['RentMortgage'].
                '&residence_month='.$lead_data['ResidentSinceDate'];
                
                if(isset($lead_data['RequestId']))
                    $query.='&lp_request_id='.$lead_data['RequestId'];

                if(isset($lead_data['SubId1']))
                    $query.='&lp_s1='.$lead_data['SubId1'];
                
                if(isset($lead_data['SubId2']))
                    $query.='&lp_s2='.$lead_data['SubId2'];
                
                if(isset($lead_data['SubId3']))
                    $query.='&lp_s3='.$lead_data['SubId3'];
                
                if(isset($lead_data['SubId4']))
                    $query.='&lp_s4='.$lead_data['SubId4'];

                if(isset($lead_data['SubId5']))
                    $query.='&lp_s5='.$lead_data['SubId5'];

                if(isset($lead_data['Address2']))
                    $query.='&address2='.$lead_data['Address2'];

                if(isset($lead_data['LoanAmount1']))
                    $query.='&loan_amount_first='.$lead_data['LoanAmount1'];
                else
                    $query.='&loan_amount_first='.$lead_data['LoanAmount'];
                
                if(isset($lead_data['Url']))
                    $query.='&URL='.$lead_data['Url'];

                $curl = curl_init();

        $url = "https://cmportal.leadspediatrack.com/post.do";
        $query = str_replace(' ', '+', $query);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url."?".$query,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_POSTFIELDS => "{}",
        ));

        if (curl_error($curl)) {
            $status_json = "cURL Error #:" . curl_error($curl);
        } else {              
            $status_json = curl_exec($curl);
        }
        curl_close($curl);
        $xml = simplexml_load_string($status_json);
        $xml->post_request = "https://cmportal.leadspediatrack.com/post.do?".$query;
        $status_json= json_encode($xml);
        return $status_json;
    }

    public function update_processLead($lead_data){
        //echo "lead_id :: ";echo $lead_id;exit;die();
        /* if(isset($lead_data['LeadID'])){

        $lead_id =$lead_data['LeadID'];

        }else if(isset($lead_data['lead_id'])) {

        $lead_id =$lead_data['lead_id'];
        }*/
        if (strpos($lead_data['Url'], 'peerkeyloan') !== false) {

            if($lead_data['LeadID'] != "")
                $lead_id =$lead_data['LeadID'] ;
            else
                $lead_id =$lead_data['lead_id'] ;

        } else {
            if(isset($lead_data['LeadID'])){

                $lead_id =$lead_data['LeadID'];

            }else if(isset($lead_data['lead_id'])) {

                $lead_id =$lead_data['lead_id'];
            }
        }

        //Formatting
        $dob = date("Y-m-d", strtotime($lead_data['DateOfBirth']));
        $Paydate1 = date("Y-m-d", strtotime($lead_data['Paydate1']));
        $Paydate2 = date("Y-m-d", strtotime($lead_data['Paydate2']));

        $query  =   'lp_test=0'.
                '&lp_exclude_contract_seen=1'.
                '&lp_no_distribute=1'.

                //Verify Identity
                '&dob='.$dob.
                '&SSN='.$lead_data['Ssn'].
                '&drivers_license_number='.$lead_data['DriversLicenseNumber'].
                '&drivers_license_state='.$lead_data['DriversLicenseState'].

                //Employment Info
                '&employee_type='.$lead_data['EmployeeType'].
                '&employer_name='.$lead_data['EmployerName'].
                '&employment_months='.$lead_data['EmploymentTime'].
                '&phone_work='.$lead_data['WorkPhone'].
                '&employer_address='.$lead_data['EmployerAddress'].
                '&employer_zip='.$lead_data['EmployerZip'].
                '&employer_city='.$lead_data['EmployerCity'].
                '&employer_state='.$lead_data['EmployerState'].
                '&monthly_income='.$lead_data['MonthlyNetIncome'].
                '&pay_frequency='.$lead_data['PayFrequency'].
                '&pay_date_1='.$Paydate1.
                '&pay_date_2='.$Paydate2.

                //Bank Details
                '&bank_account_type='.$lead_data['BankAccountType'].
                '&bank_routing_number='.$lead_data['BankRoutingNumber'].
                '&bank_account_number='.$lead_data['BankAccountNumber'].
                '&bank_name='.$lead_data['BankName'].
                '&bank_months='.$lead_data['BankTime'];

        if(isset($lead_data['PrimaryPhone']))                 
            $query.='&phone_home='.$lead_data['PrimaryPhone'];

        if(isset($lead_data['SecondaryPhone']))                 
            $query.='&phone_cell='.$lead_data['SecondaryPhone'];

        if(isset($lead_data['DirectDeposit']))  {
            
            if($lead_data['DirectDeposit'] == 'true'){
                $query.='&direct_deposit=1';
            }else{
                $query.='&direct_deposit=0';
            }               
        }

        if(isset($lead_data['AgreeConsent']))  {
            
            if($lead_data['AgreeConsent'] == 'true'){
                $query.='&agree_consent=1';
            }else{
                $query.='&agree_consent=0';
            }               
        }

        if($lead_data['Phone_TCPA'] == "")  {
            $query.='&agree_phone=0';
        }else{
            $phonetcpa = intval(preg_replace('/[^0-9]+/', '', $lead_data['Phone_TCPA']), 10);
            $query.='&phone_tcpa='.$phonetcpa;
            $query.='&agree_phone=1';
        }

        if(isset($lead_data['LoanAmount']))                    
            $query.='&loan_amount='.$lead_data['LoanAmount'];

        if(isset($lead_data['LoanAmount1']))                
            $query.='&loan_amount_first='.$lead_data['LoanAmount1'];
        
        if(isset($lead_data['LoanAmount2']))                 
            $query.='&loan_amount_second='.$lead_data['LoanAmount2'];

        $query  .= '&lp_api_key=9186a80444607e3a119ef1c02f7d4c84'.
                  '&api_secret=e914bdc70b6f813aba7227edda802afe'.
                  '&lp_lead_id='.$lead_id;

        $curl = curl_init();
        $url  = "https://cmportal.leadspediatrack.com/distribute.do";
        $query = str_replace(' ', '+', $query);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url."?".$query,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 200,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_POSTFIELDS => "{}",
        ));

        if (curl_error($curl)) {
            $status_json = "cURL Error #:" . curl_error($curl);
        } else {              
            $status_json = curl_exec($curl);
        }
        curl_close($curl);
        //$status_json = json_encode($status_json);
        $status_json_new = json_decode($status_json);
        $status_json_new->actual_response = $status_json;
        $status_json_new->distribute_request = "https://cmportal.leadspediatrack.com/distribute.do?".$query;

        $status_json_return = json_encode($status_json_new);

        return $status_json_return;
    }

	private function _hash_ssn_process_crm_lead($ssn){
		$last4 = substr($ssn, -4);
        $last4_md5 = md5($last4);
        return $last4_md5;  
	}

	public function jsonresponse($response){
		$response['response_date'] = date('Y-m-d H:i:s');
		return str_replace('\\',"", json_encode($response));
	}

    public function processCrmLeadSpa(){

        if(!$this->request->is('post')){
            $this->errors[] = 'Request type is not a POST';
            $this->_responseError($msg="POST");         
        }
        $lead_data = $this->request->data;

        //Post Call -- First API Call
        if( !isset($lead_data['LoanAmountSecond'])){
            $result = $this->step2_processLeadSpa($lead_data);
            $response = json_decode($result);

            if($response->result == 'failed'){
                $return['status'] ='failed';
                $return['redirect'] = '';
            }

            //Lead ID from first API response
            $lead_id = $response->lead_id;
            $lead_data["lead_id"] = $lead_id;
            $return['lead_id'] = $lead_id;

        } else {
            //Lead ID resent on payday amount selection page
            $return['lead_id'] = $lead_data["lead_id"];
        }

        //Call to distrbute
        $result = $this->update_processLeadSpa($lead_data);
        $response = json_decode($result);


        //Response formatting for redirect
        $info = $response->info;
        $return['status'] = $response->message;
        $return['redirect'] = $info->redirectURL;
        $return['total_sold'] = $info->total_delivery;

        if(isset($lead_data['RequestId'])){

            if($info->total_delivery > 0){
         
                $urlpixel = "https://nkoeg.com/pixel.do?t=pb&request_id=".$lead_data['RequestId'];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $urlpixel);
                $rsp = curl_exec($ch);
                curl_close($ch);
                $lead_data['pixel_response'] = $rsp;
            }
        }

        $lead_info = json_encode($lead_data);
        if(!isset($lead_data['LoanAmountSecond'])){

            //Insert in Clickmedia DB           
            $this->Lead->create(array( 'lead_info'=>$lead_info, 'lead_id'=>$lead_data['lead_id'], 'response'=>json_encode($response), 'created_at'=> date("Y-m-d G:i:s", time()),'updated_at'=>date("Y-m-d G:i:s", time())));
            $this->Lead->save();
        }else{

            //Update Clickmedia DB
            $lead = $this->Lead->find('first', array(
                'conditions' => array(
                'lead_info LIKE ' => '"%'.$lead_data["lead_id"].'%"'
                )
            ));         
            $lead = $this->Lead->find('first', $params);            
            $this->Lead->id = $lead['Lead']['id'];
            $this->Lead->set('lead_info', $lead_info);
            if(!empty($response)){
                $this->Lead->set('response', json_encode($response));
            }
            $this->Lead->set('updated_at', date("Y-m-d G:i:s", time()));
            $this->Lead->save();
        }
        
        $minutes = "";
        if( isset($lead_data['Check'])){
            if($lead_data['Check'] == true)
            {
                $lead = $this->Lead->find('first', $params);
                $datetime1 = strtotime($lead_data['Created']);            
                $datetime2 = strtotime(date("Y-m-d G:i:s", time()));            
                $interval  = abs($datetime2 - $datetime1);
                $minutes   = round($interval / 60);                 
            }
        }

        if($minutes == "" || $minutes <= 3)   
        {
            echo $this->jsonresponse($return);
            exit;

        } else if($minutes > 3){
            $return['status'] = 'error';
            $return['redirect'] = '';
            echo $return;
            exit;
        }
    }

    public function step2_processLeadSpa($lead_data){
        $query  = 'lp_test=0'.
                '&ip_address='.$lead_data['IPAddress'].
                '&lp_campaign_id='.$lead_data['Campaign_id'].
                '&lp_campaign_key='.$lead_data['Campaign_key'].
                '&lp_no_distribute=1'.

                //First Page
                '&loan_purpose='.$lead_data['LoanPurpose'].
                '&credit_rating='.$lead_data['CreditRating'].
                '&zip_code='.$lead_data['Zip'].
                '&military='.$lead_data['Military'].
                '&agree=1'.
                
                //Personal Information
                '&loan_amount='.$lead_data['LoanAmount'].
                '&first_name='.$lead_data['FirstName'].
                '&last_name='.$lead_data['LastName'].
                '&email_address='.$lead_data['Email'].
                '&address='.$lead_data['Address1'].      
                //'&state='.$lead_data['State'].
                '&city='.$lead_data['City'].
                '&residence_type='.$lead_data['ResidenceType'].
                '&rent_mortgage='.$lead_data['RentMortgage'].
                '&residence_month='.$lead_data['ResidentSinceDate'];
                
                if(isset($lead_data['RequestId']))
                    $query.='&lp_request_id='.$lead_data['RequestId'];

                if(isset($lead_data['State']))
                    $query.='&state='.$lead_data['State'];

                if(isset($lead_data['SubId1']))
                    $query.='&lp_s1='.$lead_data['SubId1'];
                
                if(isset($lead_data['SubId2']))
                    $query.='&lp_s2='.$lead_data['SubId2'];
                
                if(isset($lead_data['SubId3']))
                    $query.='&lp_s3='.$lead_data['SubId3'];
                
                if(isset($lead_data['SubId4']))
                    $query.='&lp_s4='.$lead_data['SubId4'];

                if(isset($lead_data['SubId5']))
                    $query.='&lp_s5='.$lead_data['SubId5'];

                if(isset($lead_data['Address2']))
                    $query.='&address2='.$lead_data['Address2'];

                if(isset($lead_data['LoanAmount1']))
                    $query.='&loan_amount_first='.$lead_data['LoanAmount1'];
                else
                    $query.='&loan_amount_first='.$lead_data['LoanAmount'];
                
                if(isset($lead_data['Url']))
                    $query.='&URL='.$lead_data['Url'];

                $curl = curl_init();

        $url = "https://cmportal.leadspediatrack.com/post.do";
        $query = str_replace(' ', '+', $query);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url."?".$query,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_POSTFIELDS => "{}",
        ));

        if (curl_error($curl)) {
            $status_json = "cURL Error #:" . curl_error($curl);
        } else {              
            $status_json = curl_exec($curl);
        }
        curl_close($curl);
        $xml = simplexml_load_string($status_json);
        $status_json= json_encode($xml);
        return $status_json;
    }

    public function update_processLeadSpa($lead_data){

        if(isset($lead_data['LeadID']))
            $lead_id =$lead_data['LeadID'] ;
        else
            $lead_id =$lead_data['lead_id'] ;

        //Formatting
        $dob = date("Y-m-d", strtotime($lead_data['DateOfBirth']));
        $Paydate1 = date("Y-m-d", strtotime($lead_data['Paydate1']));
        if(isset($lead_data['Paydate2']))
            $Paydate2 = date("Y-m-d", strtotime($lead_data['Paydate2']));
        else
            $Paydate2 = "";

        $query  =   'lp_test=0'.
                '&lp_exclude_contract_seen=1'.
                '&lp_no_distribute=1'.

                //Verify Identity
                '&dob='.$dob.
                '&SSN='.$lead_data['Ssn'].
                '&drivers_license_number='.$lead_data['DriversLicenseNumber'].
                '&drivers_license_state='.$lead_data['DriversLicenseState'].

                //Employment Info
                '&employee_type='.$lead_data['EmployeeType'].
                '&employer_name='.$lead_data['EmployerName'].
                '&employment_months='.$lead_data['EmploymentTime'].
                '&phone_work='.$lead_data['WorkPhone'].
                '&employer_address='.$lead_data['EmployerAddress'].
                '&employer_zip='.$lead_data['EmployerZip'].
                '&employer_city='.$lead_data['EmployerCity'].
                //'&employer_state='.$lead_data['EmployerState'].
                '&monthly_income='.$lead_data['MonthlyNetIncome'].
                '&pay_frequency='.$lead_data['PayFrequency'].
                '&pay_date_1='.$Paydate1.
                '&pay_date_2='.$Paydate2.

                //Bank Details
                '&bank_account_type='.$lead_data['BankAccountType'].
                '&bank_routing_number='.$lead_data['BankRoutingNumber'].
                '&bank_account_number='.$lead_data['BankAccountNumber'].
                '&bank_name='.$lead_data['BankName'].
                '&bank_months='.$lead_data['BankTime'];

        if(isset($lead_data['EmployerState']))                 
            $query.='&employer_state='.$lead_data['EmployerState'];

        if(isset($lead_data['PrimaryPhone']))                 
            $query.='&phone_home='.$lead_data['PrimaryPhone'];

        if(isset($lead_data['SecondaryPhone']))                 
            $query.='&phone_cell='.$lead_data['SecondaryPhone'];

        if(isset($lead_data['DirectDeposit']))  {
            
            if($lead_data['DirectDeposit'] == 'true'){
                $query.='&direct_deposit=1';
            }else{
                $query.='&direct_deposit=0';
            }               
        }

        if(isset($lead_data['AgreeConsent']))  {
            
            if($lead_data['AgreeConsent'] == 'true'){
                $query.='&agree_consent=1';
            }else{
                $query.='&agree_consent=0';
            }               
        }

        if($lead_data['Phone_TCPA'] == "")  {
            $query.='&agree_phone=0';
        }else{
            $phonetcpa = intval(preg_replace('/[^0-9]+/', '', $lead_data['Phone_TCPA']), 10);
            $query.='&phone_tcpa='.$phonetcpa;
            $query.='&agree_phone=1';
        }

        if(isset($lead_data['LoanAmount']))                    
            $query.='&loan_amount='.$lead_data['LoanAmount'];

        if(isset($lead_data['LoanAmount1']))                
            $query.='&loan_amount_first='.$lead_data['LoanAmount1'];
        
        if(isset($lead_data['LoanAmount2']))                 
            $query.='&loan_amount_second='.$lead_data['LoanAmount2'];

        $query  .= '&lp_api_key=9186a80444607e3a119ef1c02f7d4c84'.
                  '&api_secret=e914bdc70b6f813aba7227edda802afe'.
                  '&lp_lead_id='.$lead_id;

        $curl = curl_init();
        $url  = "https://cmportal.leadspediatrack.com/distribute.do";
        $query = str_replace(' ', '+', $query);
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url."?".$query,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 100,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_POSTFIELDS => "{}",
        ));

        if (curl_error($curl)) {
            $status_json = "cURL Error #:" . curl_error($curl);
        } else {              
            $status_json = curl_exec($curl);
        }
        curl_close($curl);
        //$status_json = json_encode($status_json);
        return $status_json;
    }

}
?>
