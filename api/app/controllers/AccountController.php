<?php

class AccountController extends BaseAPIController {

    public function login() {

        $email    = Input::get('email');
        $password = Input::get('password');

        return Response::json($this->api->login($email, $password));

    }

    public function logout() {
        return Response::json($this->api->logout());
    }

    public function register() {

        $customer = Input::get("customer");

        return Response::json($this->api->register($customer));

    }

    public function getAccount() {
        return Response::json($this->api->getAccount());
    }

}