<?php

namespace App\Interfaces;

interface PaymentGatewayInterface
{
  public function executeRequest();
  // public function getApiKey();
  // public function getUrl();
}