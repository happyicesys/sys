<?php

namespace App\Services;
use App\Models\Customer;
use Carbon\Carbon;

class CustomerService
{
    protected $createEmptyInvoiceEndpoint;

    public function __construct()
    {
        $this->createEmptyInvoiceEndpoint = env('CMS_URL') . '/api/sys/transactions/create';
    }

    public function createCMSEmptyInvoice(Customer $customer, $date, $driver)
    {

    }
}