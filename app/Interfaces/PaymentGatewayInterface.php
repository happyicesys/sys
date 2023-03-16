<?php

namespace App\Interfaces;

interface PaymentGatewayInterface
{
  public function executeRequest();
}