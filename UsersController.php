<?php
class UsersController extends AppController{
    
    var $name = 'Users';
    var $components = array('Handler');
    
    function index(){
        
    }
    
    public function getUsersHome(){
        
        //$UserID = 3;
        $params = array();
        $UserID = $this->request->query['id'];
        
        //array to be populated with IDs to query with later
        $params[] = $UserID;
        
        $options['conditions'] = array('AsBsUserRelationshipX.a_id' => $UserID);
        $relationships = $this->User->AsBsUserRelationshipX->get($options);
        
        foreach($relationships as $relationship){
            $params[] = $relationship['AsBsUserRelationshipX']['b_id'];
        }
        $options['conditions'] = array('AsBsUserContactX.a_id' => $UserID);
        $contacts = $this->User->AsBsUserContactX->get($options);
        $mainContacts = $contacts["main"];
        $emergencyContacts = $contacts["emergency"];
        $options['conditions'] = array('AsBsUserAddressX.a_id' => $UserID);
        $addresses = $this->User->AsBsUserAddressX->get($options);
        $options['conditions'] = array('UserAllergy.User_id' => $UserID);
        $allergies = $this->User->UserAllergy->get($options);
        
        $options['conditions'] = array('UserProblemList.User_id' => $UserID);
        $problems = $this->User->UserProblemList->get($options);
       
        $options['conditions'] = array('UserMedication.User_id' => $UserID);
        $medication = $this->User->UserMedication->get($options);
        
        
        //Both orders and results need to go through the AsOrderResult model in
        //order to have encounter and visit ID's, so we use custom methods
        $options['conditions'] = array('AsOrderResult.User_id' => $UserID);
        $results = $this->User->AsOrderResult->getResults($options);
        
        $options['conditions'] = array('AsOrderResult.User_id' => $UserID);
        $orders = $this->User->AsOrderResult->getOrders($options);
        
        $options['conditions'] = array('Encounter.User_id' => $UserID);
        $encounters = $this->User->Encounter->get($options);
        
        $options['conditions'] = array('Visit.User_id' => $UserID);
        $visits = $this->User->Visit->get($options);
         
        $list["encounters"] = $encounters;
        $list["problems"] = $problems;
        $list["orders"] = $orders;
        $list["results"] = $results;
        $list["emergencyContacts"] = $emergencyContacts;
        $list["contacts"] = $mainContacts;
        $list["addr"] = $addresses;      
        
        $results = array($list);
        
        $this->Handler->respond(true, $results, "data");
        
    }
    
    public function editUser(){
        
        $first_name = $this->request->data['first_name'];
        $last_name = $this->request->data['last_name'];
        $gender = $this->request->data['gender'];
        $dob = $this->request->data['dob'];
        $UserID = $this->request->data['User_id'];
        $UserNameID = $this->request->data['User_name_id'];
        
        $this->User->set('User_id', $UserID);
        $this->User->set('gender', $gender);
        $this->User->set('dob', $dob);
        
        $this->User->UserName->set('User_name_id', $UserNameID);
        $this->User->UserName->set('User_id', $UserID);
        $this->User->UserName->set('first_name', $first_name);
        $this->User->UserName->set('last_name', $last_name);
        
        $this->User->save();
        $this->User->UserName->save();
        
        $this->Handler->respond(true); 
        
    }
    
    function quickAppt(){
        
        $request = $this->request->data;
        debug($request);
        
        $uuid = $this->User->query("SELECT UUID() AS uuid");
        $uuid = $uuid[0][0]['uuid'];
        
        $dob = $request['dob-year'].'-'.$request['dob-month'].'-'.$request['dob-day'];
        $dob = date('Y-m-d', strtotime($dob));
        
        $this->User->create();
        $this->User->set('User_id', $uuid);
        $this->User->set('dob', $dob);
        $this->User->set('gender', $request['gender']);
        
        $this->User->UserName->create();
        $this->User->UserName->set('User_id', $uuid);
        $this->User->UserName->set('first_name', $request['first_name']);
        $this->User->UserName->set('last_name', $request['last_name']);
        
        $date = date('Y-m-d', strtotime($request['date']));
        $time = date('H:i:s', strtotime($request['time']));
        
        $datetime = $date.' '.$time;
        
        $contact = $request['phone1'].'-'.$request['phone2'].'-'.$request['phone3'];
        
        $this->User->UserAppointment->create();
        $this->User->UserAppointment->set('User_id', $uuid);
        $this->User->UserAppointment->set('appointment_datetime', $datetime);
        $this->User->UserAppointment->set('chief_complaint', $request['complaint']);
        //$this->User->UserAppointment->set('appointment__id', $request['']);
        $this->User->UserAppointment->set('contact', $contact);
        
        $this->User->save();
        $this->User->UserName->save();
        $this->User->UserAppointment->save();
        
        echo json_encode(array("success" => true));
    }    
    
    function search(){
        
        $requests = $this->request->query;
        
        //instead of an array presiding in the controller, get this from model
        //or maybe some sort of attachable behavior
        $searchBy = array(
            'lname' => 'User.last_name',
            'fname' => 'User.first_name',
            'dob' => 'User.dob'
        );
        
        $params = array();
        
        foreach($requests as $key => $value){
            if(isset($searchBy[$key])){
                if(!empty($value)){
                    $params[$searchBy[$key]] = $value;
                }
            }
        }
        
        foreach($params as $key => $value){
            $options['conditions'][$key] = $value;
        }
        
        $options['fields'] = array(
            'User.user_id',
            'User.dob'
        );
        $options['link'] = array(
            'UserName' => array(
                'fields' => array(
                    'last_name',
                    'first_name'
                )
            ),
            'Social2' => array(
                'fields' => array(
                    'Social2.last4'
                )
            )
        );
        
        $count = count($params);
        if($count > 0){
            $results = $this->User->find('all', $options);
        }
        
        foreach($results as &$result){
            $pat = $result['User'];
            $name = $result['UserName'];
            $social = $result['Social2'];
            $result = array_merge($pat, $name, $social);
        }
        
        echo json_encode(array("success" => true, "data" => $results));
        
    }    
       
}
?>