<?php

class Registrar_Adapter_Gandi extends Registrar_AdapterAbstract
{
    public $config = [
        'api-key' => null,
    ];

    public function __construct($options)
    {
        if (!empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
        } else {
            throw new Registrar_Exception(
                'The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing',
                [':domain_registrar' => 'Gandi', ':missing' => 'API Key'],
                3001
            );
        }
    }

    public static function getConfig()
    {
        return [
            'label' => 'Gandi Registrar API integration',
            'form' => [
                'api-key' => [
                    'password',
                    [
                        'label'       => 'API Key',
                        'description' => 'Paste your Live or Test Gandi API key here.',
                        'required'    => true,
                    ],
                ],
            ],
        ];
    }

    /* -------------------------------------------------------------------------
        INTERNAL HELPERS
    ------------------------------------------------------------------------- */

    private function _getBaseUrl()
    {
        return $this->isTestEnv()
            ? 'https://api.sandbox.gandi.net/v5'
            : 'https://api.gandi.net/v5';
    }

    private function _makeRequest($method, $path, $params = [])
    {
        $client = $this->getHttpClient()->withOptions([
            'timeout'     => 60,
            'verify_peer' => 0,
            'verify_host' => 0,
        ]);

        $url = $this->_getBaseUrl() . $path;

        $headers = [
            'Authorization' => 'Apikey ' . $this->config['api-key'],
            'Accept'        => 'application/json',
        ];

        $opts = ['headers' => $headers];

        if ($method === 'GET') {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        } else {
            $opts['json'] = $params;
        }

        try {
            $this->getLog()->debug("API REQUEST: $method $url " . json_encode($params));

            $response = $client->request($method, $url, $opts);
            $data     = $response->getContent();

            $this->getLog()->info("API RESULT: " . $data);
        } catch (Exception $e) {
            $err = new Registrar_Exception("HttpClientException: {$e->getMessage()}.");
            $this->getLog()->err($err->getMessage());
            throw $err;
        }

        return json_decode($data, true);
    }

    /* -------------------------------------------------------------------------
        DOMAIN AVAILABILITY
    ------------------------------------------------------------------------- */

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $name = $domain->getName();
        $result = $this->_makeRequest('GET', "/domain/check", [
            'name' => $name,
        ]);

        return isset($result['available']) && $result['available'] === true;
    }

    /* -------------------------------------------------------------------------
        TRANSFER SUPPORT CHECK
    ------------------------------------------------------------------------- */

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        return !$this->isDomainAvailable($domain);
    }

    /* -------------------------------------------------------------------------
        REGISTER
    ------------------------------------------------------------------------- */

    public function registerDomain(Registrar_Domain $domain)
    {
        $params = [
            'fqdn' => $domain->getName(),
            'duration' => $domain->getRegistrationPeriod() . 'y',
            'owner' => $this->_contactToArray($domain->getContactRegistrar()),
        ];

        // Nameservers
        $ns = [];
        if ($domain->getNs1()) $ns[] = $domain->getNs1();
        if ($domain->getNs2()) $ns[] = $domain->getNs2();
        if ($domain->getNs3()) $ns[] = $domain->getNs3();
        if ($domain->getNs4()) $ns[] = $domain->getNs4();

        if (!empty($ns)) {
            $params['nameservers'] = $ns;
        }

        $result = $this->_makeRequest('POST', "/domain/domains", $params);

        return isset($result['message']) && str_contains(strtolower($result['message']), 'created');
    }

    /* -------------------------------------------------------------------------
        TRANSFER
    ------------------------------------------------------------------------- */

    public function transferDomain(Registrar_Domain $domain)
    {
        $params = [
            'fqdn' => $domain->getName(),
            'authinfo' => $domain->getEpp(),
            'owner' => $this->_contactToArray($domain->getContactRegistrar()),
        ];

        $result = $this->_makeRequest('POST', "/domain/transfers", $params);

        return isset($result['status']) && $result['status'] === 'pending';
    }

    /* -------------------------------------------------------------------------
        GET DOMAIN DETAILS
    ------------------------------------------------------------------------- */

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $result = $this->_makeRequest('GET', "/domain/domains/" . $domain->getName());

        if (!isset($result['fqdn'])) {
            $placeholders = [':action:' => 'retrieve domain details', ':type:' => 'Gandi'];
            throw new Registrar_Exception(
                'Failed to :action: with the :type: registrar, check the error logs for further details',
                $placeholders
            );
        }

        $domain->setRegistrationTime(strtotime($result['dates']['created_at'] ?? 'now'));
        $domain->setExpirationTime(strtotime($result['dates']['registry_ends_at'] ?? '+1 year'));

        // Nameservers
        if (!empty($result['nameservers'])) {
            foreach ($result['nameservers'] as $i => $ns) {
                $index = $i + 1;
                if ($index <= 4) {
                    $domain->{"setNs$index"}($ns);
                }
            }
        }

        return $domain;
    }

    /* -------------------------------------------------------------------------
        RENEW
    ------------------------------------------------------------------------- */

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = [
            'duration' => $domain->getRegistrationPeriod() . 'y',
        ];

        $result = $this->_makeRequest(
            'POST',
            "/domain/domains/" . $domain->getName() . "/renew",
            $params
        );

        return isset($result['message']) && str_contains(strtolower($result['message']), 'renew');
    }

    /* -------------------------------------------------------------------------
        DELETE (NOT SUPPORTED)
    ------------------------------------------------------------------------- */

    public function deleteDomain(Registrar_Domain $domain): never
    {
        throw new Registrar_Exception(
            ':type: does not support :action:',
            [':type:' => 'Gandi', ':action:' => 'deleting domains']
        );
    }

    /* -------------------------------------------------------------------------
        NAMESERVERS
    ------------------------------------------------------------------------- */

    public function modifyNs(Registrar_Domain $domain)
    {
        $ns = [];
        if ($domain->getNs1()) $ns[] = $domain->getNs1();
        if ($domain->getNs2()) $ns[] = $domain->getNs2();
        if ($domain->getNs3()) $ns[] = $domain->getNs3();
        if ($domain->getNs4()) $ns[] = $domain->getNs4();

        $result = $this->_makeRequest(
            'PUT',
            "/domain/domains/" . $domain->getName() . "/nameservers",
            $ns
        );

        return isset($result['message']);
    }

    /* -------------------------------------------------------------------------
        CONTACT INFORMATION
    ------------------------------------------------------------------------- */

    private function _contactToArray(Registrar_Domain_Contact $c)
    {
        return [
            'given' => $c->getFirstName(),
            'family' => $c->getLastName(),
            'email' => $c->getEmail(),
            'phone' => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'streetaddr' => $c->getAddress1() . ' ' . $c->getAddress2(),
            'city' => $c->getCity(),
            'zip' => $c->getZip(),
            'country' => $c->getCountry(),
            'state' => $c->getState(),
        ];
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $payload = [
            'owner' => $this->_contactToArray($domain->getContactRegistrar()),
        ];

        $result = $this->_makeRequest(
            'PUT',
            "/domain/domains/" . $domain->getName() . "/contacts",
            $payload
        );

        return isset($result['message']);
    }

    /* -------------------------------------------------------------------------
        PRIVACY / WHOIS PROTECTION
    ------------------------------------------------------------------------- */

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $result = $this->_makeRequest(
            'POST',
            "/domain/domains/" . $domain->getName() . "/privacy",
            ['action' => 'enable']
        );

        return isset($result['message']);
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $result = $this->_makeRequest(
            'POST',
            "/domain/domains/" . $domain->getName() . "/privacy",
            ['action' => 'disable']
        );

        return isset($result['message']);
    }

    /* -------------------------------------------------------------------------
        EPP CODE
    ------------------------------------------------------------------------- */

    public function getEpp(Registrar_Domain $domain)
    {
        $result = $this->_makeRequest(
            'GET',
            "/domain/domains/" . $domain->getName() . "/authinfo"
        );

        return $result['authinfo'] ?? '';
    }

    /* -------------------------------------------------------------------------
        LOCK / UNLOCK
    ------------------------------------------------------------------------- */

    public function lock(Registrar_Domain $domain)
    {
        $result = $this->_makeRequest(
            'POST',
            "/domain/domains/" . $domain->getName() . "/lock",
            ['action' => 'lock']
        );

        return isset($result['message']);
    }

    public function unlock(Registrar_Domain $domain)
    {
        $result = $this->_makeRequest(
            'POST',
            "/domain/domains/" . $domain->getName() . "/lock",
            ['action' => 'unlock']
        );

        return isset($result['message']);
    }
}
