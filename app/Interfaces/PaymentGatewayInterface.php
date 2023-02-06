<?php

namespace App\Interfaces;

interface PaymentGatewayInterface
{
  public function getApiKey();
  public function executeRequest();
}