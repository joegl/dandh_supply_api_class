# dandh_supply_api_class

A PHP class for making interactions with the D&H Supply API: https://www.dandh.com/

This API class can send an order and its products to D&H for fulfillment and check on the status of fulfillment for an order.

Construct the Object

    $dandh_usercode = "xxxxxxxxx";
    $dandh_password = "xxxxxxxxx";
    $dropship_password = "xxxxxxxxx"; // dropship password is optional but required if doing drop shipping
    $dandh_api = new dandh_api($dandh_usercode, $dandh_password, $dropship_password);

