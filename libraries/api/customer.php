<?php
namespace Addon\Enom\Libraries\Api;

class Customer extends EnomApi
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create Customer
     *
     * Creates a new customer on the enom system based on a client's
     * contact details.
     *
     * @param  array $params Client paramiters to use for the new customer record.
     * @return int Returns the contact ID for the client.
     */
    public function createCustomer(array $params)
    {
        $client = \Client::find($params['client_id']);
        $country = \Country::where('name', '=', $client->country)->first();

        // Extract the country code from the telephone number.
        $phone = $this->formatPhone($client->phone, $country->iso_code);

        $password = substr(md5(\App::get('str')->random()), 0, 15);

        $customer_params = array(
            'command' => 'CreateSubAccount',
            'newuid' => $this->generateUid($client->first_name, $client->last_name),
            'newpw' => $password,
            'confirmpw' => $password,
            'RegistrantOrganizationName' => ($client->company !='' ? $client->company : 'None'),
            'RegistrantFirstName' => $client->first_name,
            'RegistrantLastName' => $client->last_name,
            'RegistrantAddress1' => $client->address1,
            'RegistrantCity' => $client->city,
            'RegistrantPostalCode' => $client->postcode,
            'RegistrantCountry' => $client->country,
            'RegistrantPhone' => '+' . $phone['cc'] . '.' . $phone['number']
        );

        $customer = $this->post($customer_params);

        if(isset($customer->status) && $customer_id->status == 'ERROR') {
            return $customer_id->message;
        }
        $params['customer-id'] = $customer_id;
        // Customer is now registered, now create the default contact details for them.
        $contact = $this->createContact($params);

        return $customer_id;
    }

    /**
     * Create Contact
     *
     * Creates a new contact record for a customer.
     *
     * @param  array $params Client paramiters to use for the new contact record.
     * @return object|string Returns the contact object, or the error message returned.
     */
    public function createContact(array $params)
    {
        $client = \Client::find($params['client_id']);
        $country = \Country::where('name', '=', $client->country)->first();

        if(!isset($params['customer_id'])) {
            // We dont know the customer id, so look it up based on the client
            // email address.
            $customer = $this->getCustomerByEmail(array('username' => $client->email));
            $customer_id = $customer->customerid;
        } else {
            $customer_id = $params['customer_id'];
        }
        // Extract the country code from the telephone number.
        $phone = $this->formatPhone($client->phone, $country->iso_code);

        $contact_params = array(
            'name' => $client->first_name.' '.$client->last_name,
            'company' => ($client->company !='' ? $client->company : 'Not Applicable'),
            'email' => $client->email,
            'address-line-1' => $client->address1,
            'city' => $client->city,
            'country' => $country->iso_code,
            'zipcode' => $client->postcode,
            'phone-cc' => $phone['cc'],
            'phone' => $phone['number'],
            'customer-id' => $customer_id,
            'type' => $this->contactType($params['domain'])
        );

        if ($client->address2 !='') {
            $contact_params['address-line-2'] = $client->address2;
        }
        if ($client->address3 !='') {
            $contact_params['address-line-3'] = $client->address3;
        }

        // Check for any domain-specific params that need dealing with
        if(isset($params['domain_contact_params'])) {
            $contact_params = $contact_params + $params['domain_contact_params'];
        }

        // All data processed, now create the contact and get the id

        $contact_id = $this->post('/contacts/add.json', $contact_params);
        if(isset($contact_id->status) && $contact_id->status == 'ERROR') {
            return $contact_id->message;
        }

        // All good - return the id.
        return $contact_id;
    }

    /**
     * Get Customer By Email
     *
     * Retrieves a customer from your Logicboxes account based on the email
     * address used.
     *
     * @param  array $params Client paramiters to use for the lookup
     * @return object Returns the customer object
     */
    public function getCustomerByEmail($email)
    {

        return $this->get($params);
    }

    /**
     * Get Default Contact
     *
     * Retrieves the default contact details for a customer based on the domain
     * type. Certain domains have custom contact types, so we add a check for that.
     *
     * @param  int $customer_id ID of the customer on Logicboxes
     * @param  string $domain The domain name we want to get the contact details for.
     * @return object Returns the contact object.
     */
    public function getDefaultContact($customer_id, $domain)
    {
        $params = array(
            'type' => $this->contactType($domain),
            'customer-id' => $customer_id
        );
        return $this->get('/contacts/default.json', $params);
    }

    /**
     * Contact Type
     *
     * A selected number of domains have specialist contact profiles. This method
     * checks to see if a provided domain name has one of these specialist types.
     * If it does we return that type's string. So for example a .UK domain has
     * a specialst contact. This method will return 'ContactUk'.
     *
     * @param  string $domain The domain name we want to check against
     * @return string Returns the contact type string (if applicable, otherwise returns nothing).
     */
    public function contactType($domain)
    {
        $type = 'Contact';
        $tld = strtolower($domain);
        $tld_part_exploded = explode(".", $tld);
        $tld_part = end($tld_part_exploded);

        $contactTypes = array('at', 'ca', 'cn', 'co', 'coop', 'de', 'es', 'eu', 'ni', 'ru', 'uk');

        if(in_array($tld_part, $contactTypes)) {
            $type = ucfirst($tld_part).$type;
        }
        return $type;
    }

    /**
     * Modify contact
     *
     * Updates a contact based on provided params.
     *
     * @param  array $params Array of contact details to use
     * @return object Returns the contact object.
     */
    public function modifyContact(array $params)
    {
        return $this->post('/contacts/modify.json', $params);
    }

    /**
     * Format Phone
     *
     * Formats the telephone number to be compatible with Logicboxes. For this we
     * use a 3rd party library that ships with WHSuite.
     *
     * @param  string $phine Telephone number to convert
     * @param  string $cc The Country code for the phone number
     * @return array Returns an array containing two params. CC and Number.
     */
    private function formatPhone($phone, $cc)
    {
        $phone_util = \libphonenumber\PhoneNumberUtil::getInstance();

        $number_data = $phone_util->parse($phone, $cc);

        return array(
            'cc' => $number_data->getCountryCode(),
            'number' => $number_data->getNationalNumber()
        );
    }

    /**
     * Generate UID
     *
     * Generates a random user id based on the clients name. The result will be
     * a maximum of 20 character long, and is created by combining the first
     * character of the customers first name, followed by their last name. If this
     * is longer than 16 character's it gets truncated before we add a random
     * 4 digit number to the end to ensure the name is random and wont collide.
     *
     * @param  string $first_name First name
     * @param  string $last_name Last name
     * @return string Returns a generated UID
     */
    private function generateUid($first_name, $last_name)
    {
        $uid = substr($first_name, 0, 1);
        $uid .= $last_name;

        $uid = preg_replace("/[^A-Za-z]/", '', $uid);

        if(strlen($uid) > 16) {
            $uid = substr($uid, 0, 16);
        }

        $uid .= mt_rand(1000,9999);
    }
}
