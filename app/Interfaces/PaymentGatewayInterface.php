<?php

namespace App\Interfaces;

interface PaymentGatewayInterface
{
  public function setApiKey();
  public function getApiKey();
}