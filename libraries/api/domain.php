<?php
namespace Addon\Enom\Libraries\Api;

class Domain extends EnomApi
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Domain Status
     *
     * Checks the registration and ownership status of a domain.
     *
     * @param  string $domain The domain name we want to check
     * @return object Returns the status result of the domain
     */
    public function domainStatus($domain)
    {
        $domain_parts = $this->splitDomain($domain);

        $params = array(
            'command' => 'GetDomainStatus',
            'sld' => $domain_parts[0],
            'tld' => $domain_parts[1]
        );

        return $this->get($params);
    }

    /**
     * Domain Info
     *
     * Returns all the details about the domain - the raw object direct from
     * Logicboxes
     *
     * @param  string $domain The domain name we want to get the details for
     * @return object Returns the domain details object
     */
    public function domainInfo($domain)
    {
        $domain_parts = $this->splitDomain($domain);

        $params = array(
            'command' => 'GetDomainInfo',
            'sld' => $domain_parts[0],
            'tld' => $domain_parts[1]
        );

        return $this->get($params);
    }

    /**
     * Domain Lock Status
     *
     * Checks the domain's lock status.
     *
     * @param  string $domain The domain name we want to check
     * @return object Returns the status result of the domain lock
     */
    public function domainLockStatus($domain)
    {
        $domain_parts = $this->splitDomain($domain);

        $params = array(
            'command' => 'GetRegLock',
            'sld' => $domain_parts[0],
            'tld' => $domain_parts[1]
        );

        return $this->get($params);
    }

    /**
     * Domain Contacts
     *
     * Returns the contacts associated with a domain.
     *
     * @param  string $domain The domain name we want to check
     * @return object Returns the object of contacts used for the domain.
     */
    public function domainContacts($domain)
    {
        $domain_parts = $this->splitDomain($domain);

        $params = array(
            'command' => 'GetContacts',
            'sld' => $domain_parts[0],
            'tld' => $domain_parts[1]
        );

        return $this->get($params);
    }

    /**
     * Set Domain Contacts
     *
     * Updates the contact details associated with a domain.
     *
     * @param  array $params The params required to perform the update
     * @return object Returns the contact response object.
     */
    public function setDomainContacts($params)
    {
        $params['command'] = 'Contacts';

        return $this->get($params);
    }

    /**
     * Check Domain Availablity
     *
     * Runs an availablity check on a domain.
     *
     * @param  string $params The domain name params
     * @return object Returns the availablity object
     */
    public function domainAvailability($params)
    {
        $params['command'] = 'Check';

        return $this->get($params);
    }

    /**
     * Domain Extension Attributes
     *
     * Gets the extra attributes required for some domain extensions
     *
     * @param  string $domain The domain name we want to check
     * @return object Returns the status result of the domain
     */
    public function domainExtensionAttributes($tld)
    {
        $params = array(
            'command' => 'GetExtAttributes',
            'tld' => $tld
        );

        return $this->get($params);
    }

    /**
     * Register Domain
     *
     * Attemps to register a domain name using provided domain and contact params.
     *
     * @param  array $params The params required to perform the domain registration
     * @param  arrat $contact_params The contact details to use for the registration
     * @return object Returns the registration object.
     */
    public function registerDomain($params)
    {
        $params['command'] = 'Purchase';
        return $this->get($params);
    }

    /**
     * Renew Domain
     *
     * Attempts to renew a domain that already exists in WHSuite.
     *
     * @param  array $params The domain params needed for the renewal
     * @return object Returns the renewal object
     */
    public function renewDomain($params)
    {
        $params['command'] = 'Extend';

        return $this->get($params);
    }

     /**
     * Transfer Domain
     *
     * Attemps to transfer a domain name using provided params.
     *
     * @param  array $params The params required to perform the domain registration
     * @param  arrat $contact_params The contact details to use for the registration
     * @return object Returns the registration object.
     */
    public function transferDomain($params)
    {
        $params['command'] = 'TP_CreateOrder';
        $params['OrderType'] = 'Autoverification';
        $params['DomainCount'] = '1';

        return $this->get($params);
    }

    /**
     * Get Nameservers
     *
     * Retrieves the curent nameserver records
     *
     * @param  array $params The domain details and nameservers to use
     * @return object Returns the modify-ns object
     */
    public function getNameservers(array $params)
    {
        $params['command'] = 'GetDNS';

        return $this->get($params);
    }

    /**
     * Set Nameservers
     *
     * Sets the new domain nameservers
     *
     * @param  array $params The domain details and nameservers to use
     * @return object Returns the modify-ns object
     */
    public function setNameservers(array $params)
    {
        $params['command'] = 'ModifyNS';

        $i=1;
        foreach($params['NS'] as $nameserver) {
            $params['NS'.$i] = $nameserver;
            $i++;
        }

        unset($params['NS']);
        return $this->get($params);
    }

    /**
     * Lock Domain
     *
     * Applies the registry lock to a domain to prevent unauthorized transfer.
     *
     * @param  array $params The domain params needed for the action
     * @return object Returns the response object
     */
    public function setDomainLock($params)
    {
        $params['command'] = 'SetRegLock';

        return $this->get($params);
    }

    /**
     * Send Auth Code
     *
     * Tells enom to send the domain auth code via email to the domain registrant.
     *
     * @param  array $params The domain params needed for the action
     * @return object Returns the response object
     */
    public function sendAuthCode($params)
    {
        $params['command'] = 'SynchAuthInfo';
        $params['EmailEPP'] = 'True';
        $params['RunSynchAutoInfo'] = 'True';

        return $this->get($params);
    }

    /**
     * Enable Theft Protection
     *
     * Enables the domain lock (not to be confused with the domainLocks method)
     * that prevents the domain being transferred out. Logicboxes calls this
     * 'Domain Theft Protection'
     *
     * @param  array $params The domain params needed for the theft protection
     * @return object Returns the enable-theft-protection object
     */
    public function enableTheftProtection(array $params)
    {
        return $this->get('/domains/enable-theft-protection.json', $params);
    }

    /**
     * Disable Theft Protection
     *
     * Disables the domain lock (not to be confused with the domainLocks method)
     * that prevents the domain being transferred out. Logicboxes calls this
     * 'Domain Theft Protection'
     *
     * @param  array $params The domain params needed for the theft protection
     * @return object Returns the disable-theft-protection object
     */
    public function disableTheftProtection(array $params)
    {
        return $this->get('/domains/disable-theft-protection.json', $params);
    }

    /**
     * Delete Domain
     *
     * Tells the registry to delete the domain, making it available to be
     * registered by anyone.
     *
     * @param  array $params The domain params needed for the delete action
     * @return object Returns the delete object
     */
    public function deleteDomain(array $params)
    {
        return $this->get('/domains/delete.json', $params);
    }

    /**
     * Resend RAA Email
     *
     * Resends the ICANN RAA Email verification to the registrant of the domain.
     *
     * @param  array $params The domain params needed to resend the email
     * @return object Returns the resend-verification object
     */
    public function resendRaaEmail(array $params)
    {
        return $this->get('/domains/raa/resend-verification.json', $params);
    }

    /**
     * Sync Domain
     *
     * Resyncs the local data we have about the domain such as its expiry date,
     * nameservers, etc. This is run when any major action is performed on a domain
     * to ensure we've got the up to date data on record.
     *
     * @param  string $domain The domain name to sync
     * @param  object $domain_info Optional - you can pass the domain info from the domainInfo method, however it'll do this itself if its not provided
     * @return object Returns the enable-theft-protection object
     */
    public function syncDomain($domain, $domain_info = null)
    {
        $domain_record = \Domain::where('domain', '=', $domain)->first();
        $purchase = \ProductPurchase::find($domain_record->product_purchase_id);
        if ($domain_record->enable_sync == '1') {

            if ($domain_info == null) {
                $domain_info = $this->domainInfo($domain);
            }

            if (!isset($domain_info) || !isset($domain_info->noOfNameServers)) {
                return null;
            }

            $nameservers = '';
            for($i=1;$i<=$domain_info->noOfNameServers;$i++) {
                $nsfield = 'ns'.$i;
                $nameservers .= $domain_info->$nsfield.', ';
            }
            $nameservers = rtrim($nameservers, ', ');
            $registrar_lock = '0';
            if (isset($domain_info->orderstatus))
            {
                foreach ($domain_info->orderstatus as $id => $orderstatus) {
                    if ($orderstatus == 'transferlock' || $orderstatus == 'customerlock') {
                        $registrar_lock = '1';
                    }
                }
            }

            // Since some domains dont have certain contact types, we'll double
            // check to make sure they are set in the domain_info var and if not
            // add them to it.
            if (!isset($domain_info->admincontact)) {
                $domain_info->admincontact = new \stdClass();
                $domain_info->admincontact->contactid = '0';
            }

            if (!isset($domain_info->billingcontact)) {
                $domain_info->billingcontact = new \stdClass();
                $domain_info->billingcontact->contactid = '0';
            }

            if (!isset($domain_info->techcontact)) {
                $domain_info->techcontact = new \stdClass();
                $domain_info->techcontact->contactid = '0';
            }

            $Carbon = \Carbon\Carbon::createFromTimestamp(
                $domain_info->creationtime,
                \App::get('configs')->get('settings.localization.timezone')
            );
            $domain_record->date_registered = $Carbon->toDateString();

            $Carbon = \Carbon\Carbon::createFromTimestamp(
                $domain_info->endtime,
                \App::get('configs')->get('settings.localization.timezone')
            );
            $domain_record->date_expires = $Carbon->toDateString();
            $domain_record->nameservers = $nameservers;
            $domain_record->registrar_lock = $registrar_lock;
            $domain_record->registrar_data = 'orderid: '.$domain_info->orderid.'. admincontactid: '.$domain_info->admincontact->contactid.'. billingcontactid: '.$domain_info->billingcontact->contactid.'. techcontactid: '.$domain_info->techcontact->contactid.'. registrantcontact: '.$domain_info->registrantcontact->contactid;
            $domain_record->save();

            // Recalculate the next invoice date.
            $invoice_days = \App::get('configs')->get('settings.domain_invoice_days');

            $expiry_date = new \DateTime();
            $expiry_date->setTimestamp($domain_info->endtime);
            $expiry_date->sub(new \DateInterval('P'.$invoice_days.'D'));
            $next_invoice_date = $expiry_date->format('Y-m-d');

            $Carbon = \Carbon\Carbon::createFromTimestamp(
                $domain_info->endtime,
                \App::get('configs')->get('settings.localization.timezone')
            );
            $purchase->next_renewal = $Carbon->toDateString();
            $purchase->next_invoice = $next_invoice_date;
            $purchase->save();
        }
    }

    /**
     * Domain TLD
     *
     * Strips out the domain to return just the TLD.
     *
     * @param  string $domain The domain to get the TLD of.
     * @return string Returns the tld
     */
    public function domainTld($domain)
    {
        $domain = strtolower($domain);

        $tld_part_exploded = explode(".", $domain, 2);
        $tld_part = end($tld_part_exploded);
        return $tld_part;
    }

    public function splitDomain($domain)
    {
        $domain = strtolower($domain);

        $domain_parts = explode(".", $domain, 2);
        return $domain_parts;
    }
}
