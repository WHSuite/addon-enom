<?php
namespace Addon\Enom\Libraries;

class Enom implements \App\Libraries\Interfaces\Registrar\RegistrarLibrary
{

    /**
     * Get Domain Info
     *
     * Fetches the basic domain data from the registry to provide an overview
     * of the domain, it's registration period, lock status, etc.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function getDomainInfo($request)
    {
        // Load the domain api
        $domain_api = new Api\Domain();

        $response = new \stdClass();

        // Check the domain status
        $domain_status = $domain_api->domainStatus($request->domain->domain);

        if (isset($domain_status->DomainStatus) || !isset($domain_status->DomainStatus->InAccount)) {

            if ($domain_status->DomainStatus->InAccount != '1') {
                // The domain is registered. But not controlled by this account.
                $response->status = '0';

                $response->response = new \stdClass();
                $response->response->type = 'error';
                $response->response->message = $this->formatResponses($domain_status->responses);
                return $response;
            }

        } else {

            // Unable to get the status. API down? Return false for a generic error.
            return false;
        }

        // Run the domain info request
        $api_response = $domain_api->domainInfo($request->domain->domain);
        //return $api_response;

        if (!isset($api_response->ErrCount)) {

            // If the ErrCount param is missing, something went wrong with the
            // API call. Because of that we need to return a failure status without
            // a specific error, as we've not been provided one.
            return false;

        } elseif ($api_response->ErrCount < 1 && isset($api_response->GetDomainInfo)) {

            // The domain was found! Let's format it's data for the response.
            $domain = $api_response->GetDomainInfo;
            $domain_lock = $domain_api->domainLockStatus($request->domain->domain);

            // Nameservers
            $nameservers = (array) $api_response->GetDomainInfo->services->entry[0]->configuration->dns;

            $response->status = '1'; // Set the response status
            $response->domain_name = end($domain->domainname);
            $response->date_expires = $this->formatDate($domain->status->expiration);
            $response->lock_status = end($domain_lock->{'reg-lock'});
            $response->nameservers = $nameservers;

            return $response;

        } elseif ($api_response->ErrCount > 0) {

            // The api returned an error. Time to format it, and return it correctly.
            $response->status = '0';
            $response->response->type = 'error';
            $response->response->message = $this->formatResponses($api_response->responses);

        } else {

            // Something else went wrong. We've got no way of knowing what so we'll
            // return false to throw a generic error response.
            return false;

        }

        return $response;
    }

    /**
     * Register Domain
     *
     * Tells the registry to register the domain name for a given period of time
     * and sets the contacts, nameservers, etc.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function registerDomain($request)
    {

        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1],
            'NumYears' => $request->years,
            'IgnoreNSFail' => 'Yes',
        );

        // Add the nameservers to the $domain_params array
        $i = 1;
        foreach ($request->nameservers as $nameserver) {

            // Enom allows up to 12 nameservers to be set, so ensure no more than
            // that are submitted.
            if ($i <= 12) {
                $domain_params['NS'.$i] = $nameserver;
                $i++;
            }
        }

        // Build contact data
        $registrant = \App::get('domainhelper')->getContact($request->contacts['registrant']);
        $administrative = \App::get('domainhelper')->getContact($request->contacts['administrative']);
        $technical = \App::get('domainhelper')->getContact($request->contacts['technical']);
        $billing = \App::get('domainhelper')->getContact($request->contacts['billing']);


        // Registrant contact. Not all extensions require this, however we'll set
        // it anyway as it can still be used and reduces the need to do additional
        // unnecessery checks.
        $registrant_contact = array(
            'RegistrantFirstName' => $registrant->first_name,
            'RegistrantLastName' => $registrant->last_name,
            'RegistrantAddress1' => $registrant->address1,
            'RegistrantAddress2' => $registrant->address2,
            'RegistrantCity' => $registrant->city,
            'RegistrantStateProvince' => $registrant->state,
            'RegistrantPostalCode' => $registrant->postcode,
            'RegistrantCountry' => \App::get('domainhelper')->getIsoCode($registrant->country),
            'RegistrantEmailAddress' => $registrant->email,
            'RegistrantPhone' => '+' . $registrant->phone_cc . '.' . $registrant->phone,
        );

        if ($registrant->company !='') {
            $registrant_contact['RegistrantOrganization'] = $registrant->company;

            if ($registrant->job_title == '') {
                $registrant->job_title = 'N/A';
            }

            $registrant_contact['RegistrantJobTitle'] = $registrant->job_title;

            if ($registrant->fax_cc == '' || $registrant->fax == '') {
                $registrant_contact['RegistrantFax'] = '+' . $registrant->phone_cc . '.' . $registrant->phone;
            } else {
                $registrant_contact['RegistrantFax'] = '+' . $registrant->fax_cc . '.' . $registrant->fax;
            }
        }

        // Administrative contact
        $administrative_contact = array(
            'AdminFirstName' => $administrative->first_name,
            'AdminLastName' => $administrative->last_name,
            'AdminAddress1' => $administrative->address1,
            'AdminAddress2' => $administrative->address2,
            'AdminCity' => $administrative->city,
            'AdminStateProvince' => $administrative->state,
            'AdminPostalCode' => $administrative->postcode,
            'AdminCountry' => \App::get('domainhelper')->getIsoCode($administrative->country),
            'AdminEmailAddress' => $administrative->email,
            'AdminPhone' => '+' . $administrative->phone_cc . '.' . $administrative->phone
        );

        if ($administrative->company !='') {
            $administrative_contact['AdminOrganization'] = $administrative->company;

            if ($administrative->job_title == '') {
                $administrative->job_title = 'N/A';
            }

            $administrative_contact['AdminJobTitle'] = $administrative->job_title;
        }

        // Technical contact
        $technical_contact = array(
            'TechFirstName' => $technical->first_name,
            'TechLastName' => $technical->last_name,
            'TechAddress1' => $technical->address1,
            'TechAddress2' => $technical->address2,
            'TechCity' => $technical->city,
            'TechStateProvince' => $technical->state,
            'TechPostalCode' => $technical->postcode,
            'TechCountry' => \App::get('domainhelper')->getIsoCode($technical->country),
            'TechEmailAddress' => $technical->email,
            'TechPhone' => '+' . $technical->phone_cc . '.' . $technical->phone
        );

        if ($technical->company !='') {
            $technical_contact['TechOrganization'] = $technical->company;

            if ($technical->job_title == '') {
                $technical->job_title = 'N/A';
            }

            $technical_contact['TechJobTitle'] = $technical->job_title;
        }

        // Billing contact
        $billing_contact = array(
            'AuxBillingFirstName' => $billing->first_name,
            'AuxBillingLastName' => $billing->last_name,
            'AuxBillingAddress1' => $billing->address1,
            'AuxBillingAddress2' => $billing->address2,
            'AuxBillingCity' => $billing->city,
            'AuxBillingStateProvince' => $billing->state,
            'AuxBillingPostalCode' => $billing->postcode,
            'AuxBillingCountry' => \App::get('domainhelper')->getIsoCode($billing->country),
            'AuxBillingEmailAddress' => $billing->email,
            'AuxBillingPhone' => '+' . $billing->phone_cc . '.' . $billing->phone
        );

        if ($billing->company !='') {
            $billing_contact['AuxBillingOrganization'] = $billing->company;

            if ($billing->job_title == '') {
                $billing->job_title = 'N/A';
            }

            $billing_contact['AuxBillingJobTitle'] = $billing->job_title;
        }

        // Merge contacts into the $domain_params array
        $domain_params = array_merge($domain_params, $registrant_contact, $administrative_contact, $technical_contact, $billing_contact);

        if (is_array($request->custom_data)) {
            $domain_params = array_merge($domain_params, $request->custom_data);
        }

        $custom_registration_handler = false;

        $extension_class = \App::get('domainhelper')->loadExtensionClass($request->domain);

        if ($extension_class && $extension_class->registration_handler) {
            // Some domains may use a custom registration handler if they use a different
            // api call. These will be handled directly in the extension handler class
            // if one exists.
            // This domain extension uses a custom registration handler.
            $result = $extension_class->registerDomain($domain_params);
        } else {
            $result = $domain_api->registerDomain($domain_params);
        }

        if (!isset($result->errors) || count($result->errors) < 1) {
            $return = new \stdClass();
            $return->status = '1';

            return $return;
        }

        $error_messages = '<ul>';
        foreach ((array)$result->errors as $errcode => $error) {
            $error_messages .= '<li>'.$error.'</li>';
        }
        $error_messages .= '</ul>';

        $return = new \stdClass();
        $return->status = '0';
        $return->response = new \stdClass();
        $return->response->type = 'error';
        $return->response->message = $error_messages;

        return $return;
    }

    /**
     * Renew Domain
     *
     * Renews the domain name for a given period of time.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function renewDomain($request)
    {
        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1],
            'NumYears' => $request->years,
            'IgnoreNSFail' => 'Yes',
        );

        $custom_renewal_handler = false;

        $extension_class = \App::get('domainhelper')->loadExtensionClass($request->domain);

        if ($extension_class && $extension_class->renewal_handler) {
            // Some domains may use a custom renewal handler if they use a different
            // api call. These will be handled directly in the extension handler class
            // if one exists.
            // This domain extension uses a custom renewal handler.
            $result = $extension_class->renewDomain($domain_params);
        } else {
            $result = $domain_api->renewDomain($domain_params);
        }

        if (!isset($result->errors) || count($result->errors) < 1) {
            $return = new \stdClass();
            $return->status = '1';

            return $return;
        }

        $return = new \stdClass();
        $return->status = '0';
        return $return;
    }

   /**
     * Transfer Domain
     *
     * Tells the registry to request a transfer of the domain name and sets the
     * contacts.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function transferDomain($request)
    {

        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD1' => $domain_parts[0],
            'TLD1' => $domain_parts[1],
            'AuthInfo1' => $request->auth_code,
            'UseContacts' => '0',
        );

        // Build contact data
        $registrant = \App::get('domainhelper')->getContact($request->contacts['registrant']);
        $administrative = \App::get('domainhelper')->getContact($request->contacts['administrative']);
        $technical = \App::get('domainhelper')->getContact($request->contacts['technical']);
        $billing = \App::get('domainhelper')->getContact($request->contacts['billing']);


        // Registrant contact. Not all extensions require this, however we'll set
        // it anyway as it can still be used and reduces the need to do additional
        // unnecessery checks.
        $registrant_contact = array(
            'RegistrantFirstName' => $registrant->first_name,
            'RegistrantLastName' => $registrant->last_name,
            'RegistrantAddress1' => $registrant->address1,
            'RegistrantAddress2' => $registrant->address2,
            'RegistrantCity' => $registrant->city,
            'RegistrantStateProvince' => $registrant->state,
            'RegistrantPostalCode' => $registrant->postcode,
            'RegistrantCountry' => \App::get('domainhelper')->getIsoCode($registrant->country),
            'RegistrantEmailAddress' => $registrant->email,
            'RegistrantPhone' => '+' . $registrant->phone_cc . '.' . $registrant->phone,
        );

        if ($registrant->company !='') {
            $registrant_contact['RegistrantOrganization'] = $registrant->company;
            $registrant_contact['RegistrantJobTitle'] = $registrant->job_title;
            $registrant_contact['RegistrantFax'] = '+' . $registrant->fax_cc . '.' . $registrant->fax;
        }

        // Administrative contact
        $administrative_contact = array(
            'AdminFirstName' => $administrative->first_name,
            'AdminLastName' => $administrative->last_name,
            'AdminAddress1' => $administrative->address1,
            'AdminAddress2' => $administrative->address2,
            'AdminCity' => $administrative->city,
            'AdminStateProvince' => $administrative->state,
            'AdminPostalCode' => $administrative->postcode,
            'AdminCountry' => \App::get('domainhelper')->getIsoCode($administrative->country),
            'AdminEmailAddress' => $administrative->email,
            'AdminPhone' => '+' . $administrative->phone_cc . '.' . $administrative->phone
        );

        if ($administrative->company !='') {
            $administrative_contact['AdminOrganization'] = $administrative->company;
            $administrative_contact['AdminJobTitle'] = $administrative->job_title;
        }

        // Technical contact
        $technical_contact = array(
            'TechFirstName' => $technical->first_name,
            'TechLastName' => $technical->last_name,
            'TechAddress1' => $technical->address1,
            'TechAddress2' => $technical->address2,
            'TechCity' => $technical->city,
            'TechStateProvince' => $technical->state,
            'TechPostalCode' => $technical->postcode,
            'TechCountry' => \App::get('domainhelper')->getIsoCode($technical->country),
            'TechEmailAddress' => $technical->email,
            'TechPhone' => '+' . $technical->phone_cc . '.' . $technical->phone
        );

        if ($technical->company !='') {
            $technical_contact['TechOrganization'] = $technical->company;
            $technical_contact['TechJobTitle'] = $technical->job_title;
        }

        // Billing contact
        $billing_contact = array(
            'AuxBillingFirstName' => $billing->first_name,
            'AuxBillingLastName' => $billing->last_name,
            'AuxBillingAddress1' => $billing->address1,
            'AuxBillingAddress2' => $billing->address2,
            'AuxBillingCity' => $billing->city,
            'AuxBillingStateProvince' => $billing->state,
            'AuxBillingPostalCode' => $billing->postcode,
            'AuxBillingCountry' => \App::get('domainhelper')->getIsoCode($billing->country),
            'AuxBillingEmailAddress' => $billing->email,
            'AuxBillingPhone' => '+' . $billing->phone_cc . '.' . $billing->phone
        );

        if ($billing->company !='') {
            $billing_contact['AuxBillingOrganization'] = $billing->company;
            $billing_contact['AuxBillingJobTitle'] = $billing->job_title;
        }

        // Merge contacts into the $domain_params array
        $domain_params = array_merge($domain_params, $registrant_contact, $administrative_contact, $technical_contact, $billing_contact);

        if (is_array($request->custom_data)) {
            $domain_params = array_merge($domain_params, $request->custom_data);
        }
        $custom_transfer_handler = false;

        $extension_class = \App::get('domainhelper')->loadExtensionClass($request->domain);

        if ($extension_class && $extension_class->transfer_handler) {
            // Some domains may use a custom transfer handler if they use a different
            // api call. These will be handled directly in the extension handler class
            // if one exists.
            // This domain extension uses a custom transfer handler.
            $result = $extension_class->transferDomain($domain_params);
        } else {
            $result = $domain_api->transferDomain($domain_params);
        }

        if (!isset($result->errors) || count($result->errors) < 1) {
            $return = new \stdClass();
            $return->status = '1';

            return $return;
        }

        $return = new \stdClass();
        $return->status = '0';
        return $return;
    }

    /**
     * Set Domain Lock
     *
     * Sets the domain to either locked or unlocked depending on the request.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function setDomainLock($request)
    {
        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1],
            'UnlockRegistrar' => $request->unlocked,
        );


        $result = $domain_api->setDomainLock($domain_params);

        if (!isset($result->errors) || count($result->errors) < 1) {
            $result->status = '1';
            return $result;
        }

        $result->status = '0';
        return $result;
    }

    /**
     * Get Domain Auth Code
     *
     * Tells Enom to email the auth code to the domain owner, as they do not
     * currently support getting the auth code directly. Because of this we'll
     * return a message based response to the domain helper.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function getDomainAuthCode($request)
    {
        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1]
        );

        $result = $domain_api->sendAuthCode($domain_params);

        if (!isset($result->errors) || count($result->errors) < 1) {
            $result->status = '0';
            $result->response->type = 'success';
            $result->response->message = 'enom_transfer_code_emailed';
            return $result;
        }

        $result->status = '0';
        return $result;
    }

    /**
     * Get Domain Nameservers
     *
     * Retrieves the current nameservers for the domain.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function getDomainNameservers($request)
    {
        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1]
        );

        // Because we need to move the 'dns' value over to the 'nameservers' value
        // it's easier to just convert the simplexmlelement to a standard object,
        // hence the json encode/decode below.
        $result = json_decode(json_encode($domain_api->getNameservers($domain_params)), false);

        if (!isset($result->errors) || count($result->errors) < 1) {

            if (isset($result->dns)) {
                $result->nameservers = $result->dns;
            } else {
                $result->nameservers = array();
            }
            $result->status = '1';
            return $result;
        }

        $result->status = '0';
        return $result;
    }

    /**
     * Set Domain Nameservers
     *
     * Sets the nameservers for the domain.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function setDomainNameservers($request)
    {
        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1],
            'NS' => $request->nameservers
        );

        // Because we need to move the 'dns' value over to the 'nameservers' value
        // it's easier to just convert the simplexmlelement to a standard object,
        // hence the json encode/decode below.
        $result = $domain_api->setNameservers($domain_params);
        if (!isset($result->errors) || count($result->errors) < 1) {
            $result->status = '1';
            return $result;
        }

        $result->status = '0';
        return $result;
    }


    /**
     * Set Domain Contacts
     *
     * Tells the registry to update the contact details for the domain whois.
     *
     * @param  Object $request An object containing the domain data.
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function setDomainContacts($request)
    {

        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1]
        );

        // Build contact data
        $registrant = \App::get('domainhelper')->getContact($request->contacts['registrant']);
        $administrative = \App::get('domainhelper')->getContact($request->contacts['administrative']);
        $technical = \App::get('domainhelper')->getContact($request->contacts['technical']);
        $billing = \App::get('domainhelper')->getContact($request->contacts['billing']);


        // Registrant contact. Not all extensions require this, however we'll set
        // it anyway as it can still be used and reduces the need to do additional
        // unnecessery checks.
        $registrant_contact = array(
            'RegistrantFirstName' => $registrant->first_name,
            'RegistrantLastName' => $registrant->last_name,
            'RegistrantAddress1' => $registrant->address1,
            'RegistrantAddress2' => $registrant->address2,
            'RegistrantCity' => $registrant->city,
            'RegistrantStateProvince' => $registrant->state,
            'RegistrantPostalCode' => $registrant->postcode,
            'RegistrantCountry' => \App::get('domainhelper')->getIsoCode($registrant->country),
            'RegistrantEmailAddress' => $registrant->email,
            'RegistrantPhone' => '+' . $registrant->phone_cc . '.' . $registrant->phone,
        );

        if ($registrant->company !='') {
            $registrant_contact['RegistrantOrganization'] = $registrant->company;
            $registrant_contact['RegistrantJobTitle'] = $registrant->job_title;
            $registrant_contact['RegistrantFax'] = '+' . $registrant->fax_cc . '.' . $registrant->fax;
        }

        // Administrative contact
        $administrative_contact = array(
            'AdminFirstName' => $administrative->first_name,
            'AdminLastName' => $administrative->last_name,
            'AdminAddress1' => $administrative->address1,
            'AdminAddress2' => $administrative->address2,
            'AdminCity' => $administrative->city,
            'AdminStateProvince' => $administrative->state,
            'AdminPostalCode' => $administrative->postcode,
            'AdminCountry' => \App::get('domainhelper')->getIsoCode($administrative->country),
            'AdminEmailAddress' => $administrative->email,
            'AdminPhone' => '+' . $administrative->phone_cc . '.' . $administrative->phone
        );

        if ($administrative->company !='') {
            $administrative_contact['AdminOrganization'] = $administrative->company;
            $administrative_contact['AdminJobTitle'] = $administrative->job_title;
        }

        // Technical contact
        $technical_contact = array(
            'TechFirstName' => $technical->first_name,
            'TechLastName' => $technical->last_name,
            'TechAddress1' => $technical->address1,
            'TechAddress2' => $technical->address2,
            'TechCity' => $technical->city,
            'TechStateProvince' => $technical->state,
            'TechPostalCode' => $technical->postcode,
            'TechCountry' => \App::get('domainhelper')->getIsoCode($technical->country),
            'TechEmailAddress' => $technical->email,
            'TechPhone' => '+' . $technical->phone_cc . '.' . $technical->phone
        );

        if ($technical->company !='') {
            $technical_contact['TechOrganization'] = $technical->company;
            $technical_contact['TechJobTitle'] = $technical->job_title;
        }

        // Billing contact
        $billing_contact = array(
            'AuxBillingFirstName' => $billing->first_name,
            'AuxBillingLastName' => $billing->last_name,
            'AuxBillingAddress1' => $billing->address1,
            'AuxBillingAddress2' => $billing->address2,
            'AuxBillingCity' => $billing->city,
            'AuxBillingStateProvince' => $billing->state,
            'AuxBillingPostalCode' => $billing->postcode,
            'AuxBillingCountry' => \App::get('domainhelper')->getIsoCode($billing->country),
            'AuxBillingEmailAddress' => $billing->email,
            'AuxBillingPhone' => '+' . $billing->phone_cc . '.' . $billing->phone
        );

        if ($billing->company !='') {
            $billing_contact['AuxBillingOrganization'] = $billing->company;
            $billing_contact['AuxBillingJobTitle'] = $billing->job_title;
        }

        // Merge contacts into the $domain_params array
        $domain_params = array_merge($domain_params, $registrant_contact, $administrative_contact, $technical_contact, $billing_contact);

        $result = $domain_api->setDomainContacts($domain_params);

        if (!isset($result->errors) || count($result->errors) < 1) {
            $return = new \stdClass();
            $return->status = '1';


            return $return;
        }

        $return = new \stdClass();
        $return->status = '0';
        return $return;
    }


    /**
     * Update Remote
     *
     * The update remote method is primarily used for hosting, and allows WHSuite
     * to request that a hosting account is updated. With hosting accounts the
     * param parsed is the hosting account id. However for domains and other services
     * we parse the purchase id.
     *
     * In most cases domains have no need to perform a remote update as you cant
     * exactly go and tell a domain registrar to modify the expiry date, or anything like
     * that. So for now, we dont actually do anything at all, and just return true.
     *
     * We've left this method here purely for any future developments or special
     * cases that do require you to update a registrar.
     *
     * @param  int $id The id of the client who owns the service
     * @param  int $service_id The id of the purchased service (aka purchase id)
     * @return Redirect Residrects back to the domain management page
     */
    public function updateRemote($purchase_id)
    {
        return true;
    }

    /**
     * Product Fields
     *
     * Returns form fields specific to domains registered through this registrar
     * on the product management page.
     *
     * @param  int $extension_id The id of the domain extension
     * @param  int $service_id The id of the purchased service (aka purchase id)
     * @return string Returns the HTML form that gets injected into the product management page.
     */
    public function productFields($extension_id)
    {
        return null;
    }

    /**
     * Terminate Service
     *
     * With domains we do not want to terminate them. The only option a termination
     * would provide is completely deleting the domain, and making it available
     * to register by anyone. Generally this isn't the standard practice when
     * terminating someones domain service. For now until we review all options,
     * the terminate function does nothing.
     *
     * This will likely however eventually provide a number of options such as
     * changing the domain owner and nameservers to a parking page, or simply
     * suspending the domain.
     *
     * For now we return true to allow WHSuite to continue and remove the service
     * as being active for the client.
     *
     * @param  int $domain_id The id of the domain to terminate
     * @return bool Returns the status of the termination
     */
    public function terminateService($domain_id)
    {
        return true;
    }

    /**
     * Suspend Service
     *
     * Suspends the domain with a generic suspention notice. Note: domain
     * suspensions are currently not supported and will not perform any actions
     * on domains.
     *
     * @param  int $domain_id The id of the domain name
     * @return string Returns true if the action was successful.
     */
    public function suspendService($domain_id)
    {
        return true;
    }

    /**
     * Unsuspend Service
     *
     * Unsuspends the domain with a generic suspention notice. Note: domain
     * unsuspensions are currently not supported and will not perform any
     * actions on domains.
     *
     * @param  int $domain_id The id of the domain name
     * @return string Returns true if the action was successful.
     */
    public function unsuspendService($domain_id)
    {
        return true;
    }

    /**
     * Check Availability
     *
     * Checks the availability of a domain name.
     *
     * @param  Object $request The request object
     * @return Object Returns a result object containing either error responses or the requested data.
     */
    public function domainAvailability($request)
    {
        // Load the domain api
        $domain_api = new Api\Domain();

        // Split the domain at the extension
        $domain_parts = \App::get('domainhelper')->splitDomain($request->domain);

        $domain_params = array(
            'SLD' => $domain_parts[0],
            'TLD' => $domain_parts[1],
        );

        $result = $domain_api->domainAvailability($domain_params);

        if (!isset($result->errors) || count($result->errors) < 1) {
            $result->status = '1';

            if ($result->RRPText == 'Domain available') {
                $result->availability = 'available';
            } elseif ($result->RRPText == 'Domain not available') {
                $result->availability = 'registered';
            } else {
                $result->availability = 'unknown';
            }

            return $result;
        }

        $result->status = '0';
        return $result;
    }

    /**
     * Format Responses
     *
     * This is used to re-format Enom's response messages, which usually contain
     * either a single, or a group of error messages.
     * @param  Object $responses Enom's responses object
     * @return Array Reformatted responses in a simple array list of response messages.
     */
    private function formatResponses($enom_responses)
    {
        return end($enom_responses->response->ResponseString);
    }

    private function formatDate($date)
    {
        if (is_int($date)) {
            $Carbon = \Carbon\Carbon::createFromTimestamp(
                $date,
                \App::get('configs')->get('settings.localization.timezone')
            );
        } else {
            $Carbon = \Carbon\Carbon::parse(
                $date,
                \App::get('configs')->get('settings.localization.timezone')
            );
        }

        if (! empty($Carbon)) {
            return $Carbon->toDateString();
        }

        return false;
    }
}
