<?php

namespace Artistas\PagSeguro;

class PagSeguro extends PagSeguroClient
{
    /**
 * Define o tipo de comprador.
 *
 * @var string
 */
private $senderType;

/**
 * Informações do comprador.
 *
 * @var array
 */
private $senderInfo;

/**
 * Endereço do comprador.
 *
 * @var array
 */
private $senderAddress;

/**
 * Itens da compra.
 *
 * @var array
 */
private $items;

/**
 * Valor adicional para a compra.
 *
 * @var float
 */
private $extraAmount;

/**
 * Identificador da compra.
 *
 * @var string
 */
private $reference;

/**
 * Frete.
 *
 * @var array
 */
private $shippingInfo;

/**
 * Define o tipo do comprador.
 *
 * @param string $senderType
 *
 * @return $this
 */
public function setSenderType($senderType)
{
    $this->senderType = $senderType;

    return $this;
}

/**
 * Define os dados do comprador.
 *
 * @param array $senderInfo
 *
 * @return $this
 */
public function setSenderInfo(array $senderInfo)
{
    if ($this->sandbox) {
        $formattedSenderEmail = 'teste@sandbox.pagseguro.com.br';
    } else {
        $formattedSenderEmail = $senderInfo['senderEmail'];
    }

    $formattedSenderPhone = preg_replace('/\D/', '', $senderInfo['senderPhone']);

    $formattedSenderInfo = [
      'senderName'     => trim(preg_replace('/\s+/', ' ', $senderInfo['senderName'])),
      'senderAreaCode' => substr($formattedSenderPhone, 0, 2),
      'senderPhone'    => substr($formattedSenderPhone, 2),
      'senderEmail'    => $formattedSenderEmail,
    ];

    if ($this->senderType === 'J') {
        $formattedSenderInfo['senderCNPJ'] = preg_replace('/\D/', '', $senderInfo['senderCNPJ']);
    } else {
        $formattedSenderInfo['senderCPF'] = preg_replace('/\D/', '', $senderInfo['senderCPF']);
    }

    if ($this->validateSenderInfo($formattedSenderInfo)) {
        $this->senderInfo = $formattedSenderInfo;
    }

    return $this;
}

/**
 * Valida os dados contidos na array de informações do comprador.
 *
 * @param array $formattedSenderInfo
 *
 * @throws \Artistas\PagSeguro\PagSeguroException
 *
 * @return bool
 */
private function validateSenderInfo($formattedSenderInfo)
{
    $rules = [
      'senderName'     => 'required|max:50',
      'senderAreaCode' => 'required|digits:2',
      'senderPhone'    => 'required|digits_between:8,9',
      'senderEmail'    => 'required|email|max:60',
    ];

    if ($this->senderType === 'J') {
        $rules['senderCNPJ'] = 'required|digits:14';
    } else {
        $rules['senderCPF'] = 'required|digits:11';
    }

    $validator = $this->validator->make($formattedSenderInfo, $rules);
    if ($validator->fails()) {
        throw new PagSeguroException($validator->messages()->first());
    }

    return true;
}

/**
 * Define o endereço do comprador.
 *
 * @param array $senderAddress
 *
 * @return $this
 */
public function setSenderAddress(array $senderAddress)
{
    $formattedSenderAddress = [
      'shippingAddressStreet'     => trim(preg_replace('/\s+/', ' ', $senderAddress['shippingAddressStreet'])),
      'shippingAddressNumber'     => trim(preg_replace('/\s+/', ' ', $senderAddress['shippingAddressNumber'])),
      'shippingAddressComplement' => trim(preg_replace('/\s+/', ' ', $senderAddress['shippingAddressComplement'])),
      'shippingAddressDistrict'   => trim(preg_replace('/\s+/', ' ', $senderAddress['shippingAddressDistrict'])),
      'shippingAddressPostalCode' => preg_replace('/\D/', '', $senderAddress['shippingAddressPostalCode']),
      'shippingAddressCity'       => trim(preg_replace('/\s+/', ' ', $senderAddress['shippingAddressCity'])),
      'shippingAddressState'      => strtoupper($senderAddress['shippingAddressState']),
      'shippingAddressCountry'    => 'BRA',
    ];

    if ($this->validateSenderAddress($formattedSenderAddress)) {
        $this->senderAddress = $formattedSenderAddress;
    }

    return $this;
}

/**
 * Valida os dados contidos na array de endereço do comprador.
 *
 * @param array $formattedSenderAddress
 *
 * @throws \Artistas\PagSeguro\PagSeguroException
 *
 * @return bool
 */
private function validateSenderAddress($formattedSenderAddress)
{
    $rules = [
      'shippingAddressStreet'     => 'required|max:80',
      'shippingAddressNumber'     => 'required|max:20',
      'shippingAddressComplement' => 'max:40',
      'shippingAddressDistrict'   => 'required|max:60',
      'shippingAddressPostalCode' => 'required|digits:8',
      'shippingAddressCity'       => 'required|min:2|max:60',
      'shippingAddressState'      => 'required|min:2|max:2',
    ];

    $validator = $this->validator->make($formattedSenderAddress, $rules);

    if ($validator->fails()) {
        throw new PagSeguroException($validator->messages()->first());
    }

    return true;
}

  /**
   * Define os itens da compra.
   *
   * @param array $items
   *
   * @return $this
   */
  public function setItems(array $items)
  {
      $i = 1;
      foreach ($items as $item) {
          $formattedItems['items'][$i++] = [
            'itemId'          => trim(preg_replace('/\s+/', ' ', $item['itemId'])),
            'itemDescription' => trim(preg_replace('/\s+/', ' ', $item['itemDescription'])),
            'itemAmount'      => number_format($item['itemAmount'], 2, '.', ''),
            'itemQuantity'    => preg_replace('/\D/', '', $item['itemQuantity']),
          ];
      }

      if ($this->validateItems($formattedItems)) {
          $this->items = collect($formattedItems['items'])->flatMap(function ($values, $parentKey) {
              $laravel = app();
              $version = $laravel::VERSION;

              if (substr($version, 0, 3) >= '5.3') {
                  return collect($values)->mapWithKeys(function ($value, $key) use ($parentKey) {
                      return [$key.$parentKey => $value];
                  });
              }

              return collect($values)->flatMap(function ($value, $key) use ($parentKey) {
                  return [$key.$parentKey => $value];
              });
          })->toArray();
      }

      return $this;
  }

  /**
   * Valida os dados contidos na array de itens.
   *
   * @param array $formattedItems
   *
   * @throws \Artistas\PagSeguro\PagSeguroException
   *
   * @return bool
   */
  private function validateItems($formattedItems)
  {
      $laravel = app();
      $version = $laravel::VERSION;

      if (substr($version, 0, 3) >= '5.2') {
          $rules = [
          'items.*.itemId'              => 'required|max:100',
          'items.*.itemDescription'     => 'required|max:100',
          'items.*.itemAmount'          => 'required|numeric|between:0.00,9999999.00',
          'items.*.itemQuantity'        => 'required|integer|between:1,999',
        ];
      } else {
          $rules = [];
          foreach ($formattedItems['items'] as $key => $item) {
              $rules = array_merge($rules, [
                'items.'.$key.'.itemId'              => 'required|max:100',
                'items.'.$key.'.itemDescription'     => 'required|max:100',
                'items.'.$key.'.itemAmount'          => 'required|numeric|between:0.00,9999999.00',
                'items.'.$key.'.itemQuantity'        => 'required|integer|between:1,999',
              ]);
          }
      }

      $validator = $this->validator->make($formattedItems, $rules);

      if ($validator->fails()) {
          throw new PagSeguroException($validator->messages()->first());
      }

      return true;
  }

  /**
   * Define um valor adicional para a compra.
   *
   * @param float $extraAmount
   *
   * @return $this
   */
  public function setExtraAmount($extraAmount)
  {
      $this->extraAmount = number_format($extraAmount, 2, '.', '');

      return $this;
  }

  /**
   * Define um id de referência da compra no pagseguro.
   *
   * @param string $reference
   *
   * @return $this
   */
  public function setReference($reference)
  {
      $this->reference = trim(preg_replace('/\s+/', ' ', $reference));

      return $this;
  }

  /**
   * Define o valor e o tipo do frete cobrado.
   *
   * @param array $shippingInfo
   *
   * @return $this
   */
  public function setShippingInfo(array $shippingInfo)
  {
      $formattedShippingInfo = [
        'shippingType'     => preg_replace('/\D/', '', $shippingInfo['shippingType']),
        'shippingCost'     => number_format($shippingInfo['shippingCost'], 2, '.', ''),
      ];

      if ($this->validateShippingInfo($formattedShippingInfo)) {
          $this->shippingInfo = $formattedShippingInfo;
      }

      return $this;
  }

  /**
   * Valida os dados contidos no array de frete.
   *
   * @param  array $formattedShippingInfo
   *
   * @throws \Artistas\PagSeguro\PagSeguroException
   *
   * @return bool
   */
  private function validateShippingInfo($formattedShippingInfo)
  {
      $rules = [
        'shippingType'          => 'required|numeric|between:0.00,9999999.00',
        'shippingCost'          => 'required|integer|between:1,3',
      ];

      $validator = $this->validator->make($formattedShippingInfo, $rules);

      if ($validator->fails()) {
          throw new PagSeguroException($validator->messages()->first());
      }

      return true;
  }
}
