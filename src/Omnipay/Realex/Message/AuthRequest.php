<?php

namespace Omnipay\Realex\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;

/**
 * Realex Auth Request
 */
class AuthRequest extends RemoteAbstractRequest
{
    protected $endpoint = 'https://epage.payandshop.com/epage-remote.cgi';

    public function getCavv()
    {
        return $this->getParameter('cavv');
    }

    public function setCavv($value)
    {
        return $this->setParameter('cavv', $value);
    }

    public function getEci()
    {
        return $this->getParameter('eci');
    }

    public function setEci($value)
    {
        return $this->setParameter('eci', $value);
    }

    public function getXid()
    {
        return $this->getParameter('xid');
    }

    public function setXid($value)
    {
        return $this->setParameter('xid', $value);
    }

    /**
     * Get the XML registration string to be sent to the gateway
     *
     * @return string
     */
    public function getData()
    {
        $this->validate('amount', 'currency', 'transactionId');

        // Set the endpoint to be used during the connection
        $this->setEndpoint($this->getEndpoint());

        // Create the hash
        $timestamp = strftime("%Y%m%d%H%M%S");
        $merchantId = $this->getMerchantId();
        $orderId = $this->getTransactionId();
        $amount = $this->getAmountInteger();
        $currency = $this->getCurrency();
        $cardNumber = $this->getCard()->getNumber();
        $secret = $this->getSecret();
        $tmp = "$timestamp.$merchantId.$orderId.$amount.$currency.$cardNumber";
        $sha1hash = sha1($tmp);
        $tmp2 = "$sha1hash.$secret";
        $sha1hash = sha1($tmp2);

        $domTree = new \DOMDocument('1.0', 'UTF-8');

        // root element
        $root = $domTree->createElement('request');
        $root->setAttribute('type', 'auth');
        $root->setAttribute('timestamp', $timestamp);
        $root = $domTree->appendChild($root);

        // merchant ID
        $merchantEl = $domTree->createElement('merchantid', $merchantId);
        $root->appendChild($merchantEl);

        // account
        $merchantEl = $domTree->createElement('account', $this->getAccount());
        $root->appendChild($merchantEl);

        // order ID
        $merchantEl = $domTree->createElement('orderid', $orderId);
        $root->appendChild($merchantEl);

        // amount
        $amountEl = $domTree->createElement('amount', $amount);
        $amountEl->setAttribute('currency', $this->getCurrency());
        $root->appendChild($amountEl);

        /**
         * @var \Omnipay\Common\CreditCard $card
         */
        $card = $this->getCard();

        // Card details
        $cardEl = $domTree->createElement('card');

        $cardNumberEl = $domTree->createElement('number', $card->getNumber());
        $cardEl->appendChild($cardNumberEl);

        $expiryEl = $domTree->createElement('expdate', $card->getExpiryDate("my")); // mmyy
        $cardEl->appendChild($expiryEl);

        $cardTypeEl = $domTree->createElement('type', $this->getCardBrand());
        $cardEl->appendChild($cardTypeEl);

        $cardNameEl = $domTree->createElement('chname', $card->getBillingName());
        $cardEl->appendChild($cardNameEl);

        $cvnEl = $domTree->createElement('cvn');

        $cvnNumberEl = $domTree->createElement('number', $card->getCvv());
        $cvnEl->appendChild($cvnNumberEl);

        $presIndEl = $domTree->createElement('presind', 1);
        $cvnEl->appendChild($presIndEl);

        $cardEl->appendChild($cvnEl);

        $issueEl = $domTree->createElement('issueno', $card->getIssueNumber());
        $cardEl->appendChild($issueEl);

        $root->appendChild($cardEl);

        $settleEl = $domTree->createElement('autosettle');
        $settleEl->setAttribute('flag', 1);
        $root->appendChild($settleEl);

        // 3D Secure section
        $mpiEl = $domTree->createElement('mpi');
        $cavvEl = $domTree->createElement('cavv', $this->getCavv());
        $xidEl = $domTree->createElement('xid', $this->getXid());
        $eciEl = $domTree->createElement('eci', $this->getEci());
        $mpiEl->appendChild($cavvEl);
        $mpiEl->appendChild($xidEl);
        $mpiEl->appendChild($eciEl);
        $root->appendChild($mpiEl);

        $sha1El = $domTree->createElement('sha1hash', $sha1hash);
        $root->appendChild($sha1El);

        $tssEl = $domTree->createElement('tssinfo');
        $addressEl = $domTree->createElement('address');
        $addressEl->setAttribute('type', 'billing');
        $countryEl = $domTree->createElement('country', $card->getBillingCountry());
        $addressEl->appendChild($countryEl);
        $tssEl->appendChild($addressEl);
        $root->appendChild($tssEl);

        $xmlString = $domTree->saveXML($root);

        return $xmlString;
    }

    protected function createResponse($data)
    {
        return $this->response = new AuthResponse($this, $data);
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function setEndpoint($value)
    {
        $this->endpoint = $value;
    }
}
