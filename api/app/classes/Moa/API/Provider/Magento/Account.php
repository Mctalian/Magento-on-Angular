<?php
namespace Moa\API\Provider\Magento;

/**
 * Magento API provider traits for Laravel
 *
 * @author Raja Kapur <raja.kapur@gmail.com>
 * @author Adam Timberlake <adam.timberlake@gmail.com>
 */
trait Account {

    /**
     * @method getCustomerModel
     * @return Mage_Customer_Model_Customer
     * @private
     */
    private function getCustomerModel()
    {
        // Gather the website and store preferences.
        $websiteId = \Mage::app()->getWebsite()->getId();
        $store     = \Mage::app()->getStore();

        // Update the customer model to reflect the current user.
        $customer = \Mage::getModel('customer/customer');
        $customer->website_id = $websiteId;
        $customer->setStore($store);

        return $customer;

    }

    /**
     * @method login
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login($email, $password)
    {
        $response = array('success' => true, 'error' => null, 'model' => array());

        $customer = $this->getCustomerModel();
        $customer->loadByEmail($email);

        try {

            // Attempt the login procedure.
            $session = \Mage::getSingleton('customer/session');
            $session->login($email, $password);

            $account = $this->getAccount();
            $response['model'] = $account['model'];
            
        } catch (\Exception $e) {

            $response['success'] = false;

            switch ($e->getMessage()) {

                case 'Invalid login or password.':
                    $response['error'] = 'credentials';
                    break;

                default:
                    $response['error'] = 'unknown';
                    break;

            }

        }

        return $response;
    }

    /**
     * @method logout
     * @return array
     */
    public function logout()
    {
        \Mage::getSingleton('customer/session')->logout();
        $account = $this->getAccount();
        return array('success' => true, 'error' => null, 'model' => $account['model']);
    }

    /**
     * @method getAccount
     * @return array
     */
    public function getAccount()
    {
        $isLoggedIn = \Mage::getSingleton('customer/session')->isLoggedIn();

        if (!$isLoggedIn) {

            // User isn't logged in.
            return array('loggedIn' => false, 'model' => array());

        }

        // Gather the user data, and MD5 the email address for use with Gravatar.
        $datum = \Mage::helper('customer')->getCustomer()->getData();
        $datum['gravatar'] = md5($datum['email']);

        // Otherwise the user is logged in. Voila!
        return array('success' => true, 'model' => $datum);
    }

    /**
     * @method register
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $password
     * @return array
     */
    public function register($values)
    {
        $session = \Mage::getSingleton('customer/session');
        $customer = $this->getCustomerModel();

        try {

            if (!is_array($values)) return array('success' => false, 'message' => "customer object missing"); 

            $password = $values["password"];
            $email = $values["email"];

            // If new, save customer information
            $customer->firstname     = $values["firstname"];
            $customer->lastname      = $values["lastname"];
            $customer->email         = $values["email"];
            $customer->password_hash = md5($password);
            $customer->save();

            $address = \Mage::getModel("customer/address");

            $address->setCustomerId($customer->getId())
                    ->setFirstname($customer->getFirstname())
                    ->setMiddleName($customer->getMiddlename())
                    ->setLastname($customer->getLastname())
                    ->setCountryId($values["country_id"])
                    ->setPostcode($values["postcode"])
                    ->setCity($values["city"])
                    ->setTelephone($values["telephone"])
                    ->setCompany($values["company"])
                    ->setStreet($values["street"])
                    ->setIsDefaultBilling('1')
                    ->setIsDefaultShipping('1')
                    ->setSaveInAddressBook('1');

            $address->save();        

            // Log in the newly created user.
            $this->login($email, $password);
            // $session->login($email, $password);

            $account = $this->getAccount();

            $response = array('success' => true, 'error' => null, 'model' => array());
            $response['model'] = $account['model'];

        } catch (\Exception $e) {

            $response['success'] = false;

            switch ($e->getMessage()) {

                case 'Customer email is required':
                    $response['error'] = 'email';
                    break;

                case 'This customer email already exists':
                    $response['error'] = 'exists';
                    break;

                default:
                    $response['error'] = 'unknown';
                    break;

            }

        }

        return $response;
    }

}