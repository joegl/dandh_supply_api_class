<?php

// dandhsupply.com API CLASS

// DOCUMENTATION LINKS
// Main Site: https://www.dandh.com/
// XML API Documentation: https://www.dandh.com/docs/us/DH-IS-WEB-XML_US.pdf
// XML API Reference Tool: https://www.dandh.com/pdfs/DH-XMLProcess-ReferenceTool_US.pdf

// TERMINOLOGY
// Account: Company's 10-digit reference number within D&H systems.
// Subaccount: A 10-digit account number derived from the company's main account. Shares the same 6-digit
// (AcctNoAR) reference number as the main account.
// UserCode: Used to describe the username, userid, login name etc… used to authenticate access to DandH.com.
// PO: (Purchase Order) refers to the purchase order number assigned to an order by the customer. For XML requests,
// this number must be unique per account.
// Spawned Order: A separate order created from an original order due to shipments from different branch or
// backorder.

// SUBMISSION
// There are 3 methods that can be used to post the document to D&H
// 1. HTTP POST
// 2. HTML form POST
// a. Create an HTTP POST request containing a form field named "xmlDoc", and include your XML document
// as the value of the “xmlDoc” field.
// 3. SOAP
// a. Enclose your XML document in a SOAP envelope.

// WE ARE GOING TO BE USING SOAP

// SUBMISSION ENDPOINT URL: https://www.dandh.com/dhXML/xmlDispatch


class dandh_api {

  // api default settings
  protected $api_endpoint_url = 'https://www.dandh.com/dhXML/xmlDispatch';

  // api login settings
  protected $api_usercode;  // user account id
  protected $api_password;  // user account password

  // api shipping settings
  protected $dropship_enabled = FALSE;
  protected $dropship_password;

  // api request settings
  public $api_request_type;
  public $api_request_xml;

  // api response settings
  protected $api_response_xml;
  public $api_response_data_obj;
  public $api_response_error = FALSE;
  public $api_response_error_msg;

  // Construct the object with the usercode and password and if using 
  // dropshipping
  function __construct($api_usercode, $api_password, $dropship_password=FALSE) {
    $this->api_usercode = $api_usercode;
    $this->api_password = $api_password;
    if($dropship_password) {
      $this->dropship_password = $dropship_password;
      $this->dropship_enabled = TRUE;
    }
  }

  // Add the XML request header to the top of the XML request
  public function buildXMLRequestHeader() {
    $this->api_request_xml = '
      <REQUEST>'. $this->api_request_type .'</REQUEST>
      <LOGIN>
        <USERID>'. $this->api_usercode .'</USERID>
        <PASSWORD>'. $this->api_password .'</PASSWORD>
      </LOGIN>
    '. $this->api_request_xml;
  }

  // Wrap the XML request before sending
  public function wrapXMLRequest() {
    $this->api_request_xml = "\n<XMLFORMPOST>". $this->api_request_xml ."\n</XMLFORMPOST>";
  }

  // Execute the current XML request
  // Returns TRUE/FALSE boolean based on success/failure
  public function execXMLRequest() {
    // tack on the header
    $this->buildXMLRequestHeader();
    // wrap the request
    $this->wrapXMLRequest();
    // perform the request
    $this->sendXMLRequest();
    // parse the response
    $this->parseXMLResponse();
    // return opposite of error status for success/failure
    return $this->returnResponse();
  }

  // Send the XML request
  public function sendXMLRequest() {
    // Send the XML request and store the response
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->api_endpoint_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->api_request_xml);
    $this->api_response_xml = curl_exec($ch);
    curl_close($ch);
  }

  // Parse the XML response
  public function parseXMLResponse() {
    // Clear pre-existing error
    $this->api_response_error = FALSE;
    // ALL XML Responses return the same failure format:
    // <status>failure</status>
    // <message>{ FAILURE MESSAGE }</message>
    $xml_response_object = simplexml_load_string($this->api_response_xml);
    // set xml_response_object as the data
    $this->api_response_data_obj = $xml_response_object;
    // check response status for errors
    if($xml_response_object->STATUS == 'failure') {
      // error; set the error to true and the message
      $this->api_response_error = TRUE;
      $this->api_response_error_msg = (string) $xml_response_object->MESSAGE;
    } else if($xml_response_object->STATUS == 'success') {
      // no errors;
    }
  }

  // Return an Response Array with the appropriate params
  public function returnResponse() {
    $response = array();
    if($this->api_response_error) {
      // all error responses are the same
      $response['error'] = $this->api_response_error;
      $response['message'] = $this->api_response_error_msg;
    } else {
      // successful
      $response['data'] = $this->api_response_data_obj;
    }
    return $response;
  }



  /*

    GET DATA REQUEST METHODS

  */

  /*
    Price and Availability Request
    Price and/or Availability requests are made to receive real-time item prices and/or inventory availability. Requests may be submitted for price only, availability only, or combined price and availability. Multiple item numbers may be submitted per request
  */
  public function getPriceAvailibility($part_number) {
    // set the request type
    $this->api_request_type = 'price-availability';
    // build the XML request based on the data passed
    $xml_request = "\n<PARTNUM>".$part_number."</PARTNUM>";
    // set the XML request
    $this->api_request_xml = $xml_request;
    // execute the request and return the response array
    return $this->execXMLRequest();
  }

  /*
    Order Status Request
    Order Status requests are made to return information on previously submitted orders. Multiple status requests may be made by including multiple <STATUSREQUEST> elements.

    Three ways to lookup: ORDERNUM, PONUM, INVOICE
  */
  public function getOrderStatus($order_ref_id, $order_ref_type='ORDERNUM') {
    // set the request type
    $this->api_request_type = 'orderStatus';

    // build the status request with the order reference type and reference id
    $status_request = '<'.$order_ref_type.'>'.$order_ref_id.'</'.$order_ref_type.'>';

    // build the XML request based on the data passed
    $xml_request = '<STATUSREQUEST>'.$status_request.'</STATUSREQUEST>';

    // set the XML request
    $this->api_request_xml = $xml_request;
    // execute the request and return the response array
    return $this->execXMLRequest();
  }

  /*
    Create an Order Status Request by ORDERNUM
  */
  public function getOrderByNum($order_num) {
    $this->getOrderStatus($order_num, 'ORDERNUM');
  }

  /*
    Create an Order Status Request by PONUM
  */
  public function getOrderByPo($po_num) {
    $this->getOrderStatus($po_num, 'PONUM');
  }

  /*
    Create an Order Status Request by INVOICE
  */
  public function getOrderByInvoice($invoice_num) {
    $this->getOrderStatus($invoice_num, 'INVOICE');
  }




  /*

    SEND (POST) DATA REQUEST METHODS

  */

  /*
    Order Entry Request
    Order Entry requests are made to submit orders. Multiple items may be submitted per request by including an <ITEM>    element for each item within the <ORDERITEMS> element. For drop ship authorized accounts, if <SHIPTO> elements are    used, the order will be drop shipped to the address specified; otherwise orders will default to the shipping address on file.
    * Certain <SHIPTO> elements are required for drop shipping.
  */
  public function sendOrder($order_info) {
    // set the request type
    $this->api_request_type = 'orderEntry';

    // build the XML request based on the data passed
    // construct the order header first
    $xml_order_header = $this->formatOrderHeader($order_info);

    // construct the order items second
    $xml_order_items = $this->formatOrderItems($order_info);

    // add the order header to the order items to create the full xml request
    $xml_request = $xml_order_header.$xml_order_items;

    // dump the built request
    //echo "<textarea>".var_export($xml_request, TRUE)."</textarea>";

    // set the XML request
    $this->api_request_xml = $xml_request;
    // execute the request and return the response array
    return $this->execXMLRequest();
  }

  // Helper function to format the XML Order Header
  // For sending new orders
  public function formatOrderHeader($order_info) {
    // setup a blank var first
    $xml_order_header = '';

    // PONUM -- REQUIRED
    // Size: 30
    // Contents: The unique Purchase Order number for the order. May not be 
    // duplicated with any other order from the account.
    $this->formatXMLKey('PONUM', $order_info, $xml_order_header, 30);

    // SHIPCARRIER -- REQUIRED
    // Valid Values: 'Pickup', 'UPS', 'FedEx'
    // Contents: The Ship Via carrier preference.
    $this->formatXMLKey('SHIPCARRIER', $order_info, $xml_order_header);

    // SHIPSERVICE -- REQUIRED
    // Valid Values: 'Pickup', 'Ground', '2nd Day Air', 'Next Day Air', 'Red Sat Del'
    // Contents: The Ship Via service level.
    $this->formatXMLKey('SHIPSERVICE', $order_info, $xml_order_header);

    // ONLYBRANCH -- OPTIONAL
    // Valid Values: 'Harrisburg', 'California', 'Chicago', 'Atlanta'
    // Contents: The Only D&H branch to ship the entire order from. This element 
    // will be overridden by the <BRANCH> element at the item detail level.
    $this->formatXMLKey('ONLYBRANCH', $order_info, $xml_order_header);

    // PARTSHIPALLOW -- OPTIONAL
    // Valid Values: 'Y', 'N'
    // Contents: Determines whether to allow partial shipments. The default 
    // allows for partial shipments when all items and quantities contained in 
    // an order are not available at a specific distribution center. 'N' will 
    // prevent partial shipments. Combined with <BACKORDERALLOW> could cause 
    // orders to be cancelled in the event they cannot be completed as desired.
    $this->formatXMLKey('PARTSHIPALLOW', $order_info, $xml_order_header);

    // BACKORDERALLOW -- OPTIONAL
    // Valid Values: 'Y', 'N'
    // Contents: Determines whether items that are not available are to be 
    // backordered. The default allows for items to be backordered when 
    // unavailable. 'N' will prevent items from being backordered. Combined with
    // <PARTSHIPALLOW> could cause orders to be cancelled in the event they 
    // cannot be completed as desired.
    $this->formatXMLKey('BACKORDERALLOW', $order_info, $xml_order_header);

    // DROPSHIPPW -- DROP SHIP ONLY -- REQUIRED
    // Contents: If a drop ship password is assigned to the account, this
    // element becomes required for drop ship orders. Drop ship authorized 
    // accounts only
    if($this->dropship_enabled) $xml_order_header .= "\n<DROPSHIPPW>" . $this->dropship_password . "</DROPSHIPPW>";

    // SHIPTOADDRESS -- DROP SHIP ONLY -- REQUIRED
    // Size: 30
    // Contents: The street address shipped to Drop ship authorized accounts 
    // only
    if($this->dropship_enabled) $this->formatXMLKey('SHIPTOADDRESS', $order_info, $xml_order_header, 30);

    // SHIPTOADDRESS2 -- DROP SHIP ONLY -- OPTIONAL
    // Contents: Additional field provided for the street address being shipped 
    // to. Drop ship authorized accounts only.
    // Size: 30
    if($this->dropship_enabled) $this->formatXMLKey('SHIPTOADDRESS2', $order_info, $xml_order_header, 30);

    // SHIPTONAME -- DROP SHIP ONLY -- REQUIRED
    // Contents: The Ship-To name. Drop ship authorized accounts only.
    // Size: 25
    if($this->dropship_enabled) $this->formatXMLKey('SHIPTONAME', $order_info, $xml_order_header, 25);

    // SHIPTOATTN -- DROP SHIP ONLY -- OPTIONAL
    // Contents: The Ship-To attention line. Drop ship authorized accounts only.
    // Size: 30
    if($this->dropship_enabled) $this->formatXMLKey('SHIPTOATTN', $order_info, $xml_order_header, 30);

    // SHIPTOCITY -- DROP SHIP ONLY -- REQUIRED
    // Contents: The Ship-To city. Drop ship authorized accounts only
    // Size: 18
    if($this->dropship_enabled) $this->formatXMLKey('SHIPTOCITY', $order_info, $xml_order_header, 18);

    // SHIPTOSTATE -- DROP SHIP ONLY -- REQUIRED
    // Valid Values: Must be a valid USPS two character state code.
    // Contents: The Ship-To state. Drop ship authorized accounts only.
    // Size: 2
    if($this->dropship_enabled) $this->formatXMLKey('SHIPTOSTATE', $order_info, $xml_order_header, 2);

    // SHIPTOZIP -- DROP SHIP ONLY -- REQUIRED
    // Valid Values: The five (5) [or nine (9) digit separated with a hyphen 
    // (-)] zip code.
    // Contents: The Ship-To zip code. Drop ship authorized accounts only
    // Size: 10
    if($this->dropship_enabled) $this->formatXMLKey('SHIPTOZIP', $order_info, $xml_order_header, 10);

    // REMARKS -- OPTIONAL
    // Valid Values: Any special instructions regarding the order. Any entry in 
    // this element will cause the order to be placed in "On Hold" status 
    // awaiting sales representative processing.
    // Size: 58
    $this->formatXMLKey('REMARKS', $order_info, $xml_order_header, 58);


    // ENDUSERDATA -- OPTIONAL
    if(array_key_exists('ENDUSERDATA', $order_info)) $this->formatOrderEndUser($xml_order_header, $order_info);

    // wrap it
    $this->formatXMLWrapper('ORDERHEADER', $xml_order_header);

    // return the order header
    return $xml_order_header;
  }

  // Helper function to format the XML Order Items
  // For sending new orders
  public function formatOrderItems($order_info) {
    $xml_order_items = '';
    if(!empty($order_info['items'])) {
      // loop through the items and add
      foreach($order_info['items'] as $order_item) {
        $xml_order_item = '';

        // PARTNUM -- REQUIRED
        // Contents: The D&H item number being ordered.
        $this->formatXMLKey('PARTNUM', $order_item, $xml_order_item);

        // QTY -- REQUIRED
        // Contents: The requested quantity of the item being ordered.
        $this->formatXMLKey('QTY', $order_item, $xml_order_item);

        // BRANCH -- OPTIONAL
        // Valid Values: 'Harrisburg', 'California', 'Chicago', 'Atlanta'
        // Contents: The only D&H Branch to ship the line item from. This element 
        // will override the value of the header level <ONLYBRANCH> element.
        $this->formatXMLKey('BRANCH', $order_item, $xml_order_item);

        // PRICE -- OPTIONAL
        // Contents: Only available to select customers. The customer requested 
        // item price.
        $this->formatXMLKey('PRICE', $order_item, $xml_order_item);

        // wrap it
        $this->formatXMLWrapper('ITEM', $xml_order_item);

        // add it
        $xml_order_items .= $xml_order_item;
      }

      // wrap it
      $this->formatXMLWrapper('ORDERITEMS', $xml_order_items);
    }

    // return the xml formatted order items
    return $xml_order_items;
  }

  // Helper function to format the XML Order Header end user data
  // For sending new orders
  // I AM NOT REALLY SURE WHAT THIS IS FOR -- I AM BUILDING MINIMAL SUPPORT FOR
  // IT RIGHT NOW
  public function formatOrderEndUser(&$xml_order_header, $order_info) {
    if(!empty($order_info['ENDUSERDATA'])) {
      // setup some vars
      $xml_order_end_user = '';
      $end_user_data = $order_info['ENDUSERDATA'];

      // do basic setting if set, no comments right now
      // ALL ARE OPTIONAL
      $this->formatXMLKey('ORGANIZATION', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('ATTENTION', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('ADDRESS', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('ADDRESS2', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('CITY', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('STATE', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('ZIP', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('PONUM', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('DEPARTMENT', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('PHONE', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('FAX', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('EMAIL', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('AUTHQUOTENUM', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('MCN', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('CCOIDNUM', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('SERIALNUM', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('ESDEMAIL', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('RESELLEREMAIL', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('CUSTACCTNO', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('DATEOFSALE', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('MODELNO', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('SKU', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('DOMAIN', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('ADMINEMAIL', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('UPDATETYPE', $end_user_data, $xml_order_end_user);
      $this->formatXMLKey('SUPPORTSTARTDATE', $end_user_data, $xml_order_end_user);

      // wrap it
      $this->formatXMLWrapper('ENDUSERDATA', $xml_order_end_user);

      // add it
      $xml_order_header = $xml_order_header.$xml_order_end_user;
    }

  }

  // Helper function which builds adds a new XML line using the key, value pair
  public function formatXMLLine($key, $value, &$xml, $trim=FALSE) {
    // trim the value to the specified chars if set
    if($trim) $value = substr($value, 0, $trim);
    $xml .= "\n<".$key.">".$value."</".$key.">";
  }


  // Helper function which checks if the given array key exists, and adds it as
  // an XML line in the passed variable
  public function formatXMLKey($key, $array, &$xml, $trim=FALSE) {
    if(array_key_exists($key, $array)) {
      $this->formatXMLLine($key, $array[$key], $xml, $trim);
    }
  }

  // Helper function wraps the passed xml string in the wrapper KEY passed
  public function formatXMLWrapper($wrapper_key, &$xml) {
    // add a tab after every new line in the $xml for proper indentation
    $xml = str_replace("\n", "\n\t", $xml);
    // wrap it
    $xml = "\n<".$wrapper_key.">".$xml."\n</".$wrapper_key.">";
  }

}




?>