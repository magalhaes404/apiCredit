<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiController extends Controller
{

    public function index(){
      return response()->json(['message'=>'api init']);
    }

    public function help(){
        return response()->json(['urls'=>[
          'ofertas_de_credito' => 'api/{cpf}']]);
    }

    public function clear_cpf($cpf){
      $cpfstr = str_replace('-','',$cpf);
      $cpfstr = str_replace('.','',$cpfstr);
      return $cpfstr;
    }


    public function consult_credit_offer($cpf,Request $request){
      if(strlen($cpf) == 14){
          $valido1 = substr_count($cpf,".",0,14);
          $valido2 = substr_count($cpf,"-",0,14);
          if($valido1 == 2 && $valido2 ==1){
            $cpf = $this->clear_cpf($cpf);
                  $url=env('URL_API').'/credito';
                  $data = json_encode([
                    'cpf'=>$cpf
                  ]);
                  $response = $this->callApi('POST',$url,$data);
                  $response_json = json_decode($response);
                  if($response_json == false){
                    return $response;
                  }
                  else{
                    $credits=[];
                    $i=0;
                    foreach($response_json->instituicoes as $company)
                    {
                      $i=0;
                      foreach($company->modalidades as $modalidade){
                        if($i < 3){
                          $data = json_encode(['cpf'=>$cpf,'instituicao_id'=>$company->id,'codModalidade'=>$modalidade->cod]);
                          $url = env("URL_API")."/oferta";
                          $offer = json_decode($this->callApi('POST',$url,$data));
                          if($offer != false)
                          {
                            $installments=12;
                            $amount_pay = round($offer->valorMin * pow((1 + $offer->jurosMes),$installments));
                              array_push($credits,[
                                'instituicaoFinanceira' => $company->nome,
                                'modalidadeCredito'     => $modalidade->nome,
                                'valorAPagar'           => "R$ ".number_format($amount_pay,2,",","."),
                                'valorSolicitado'       => "R$ ".number_format($offer->valorMin,2,",","."),
                                'taxaJuros'             => $offer->jurosMes,
                                'qtdParcelas'           => $installments,
                              ]);
                          }
                        }
                        $i++;
                      }
                    }
                    usort($credits,function($a,$b){
                      return ($a['taxaJuros'] == $b['taxaJuros']) ? 0 : ($a['taxaJuros'] > $b['taxaJuros']) ? 1 : -1;
                    });
                    return response()->json($credits,205);
                  }
          }
          else{
            return response()->json(['message'=>'cpf com formado invalido.'],422);
          }
      }
      else{
        return response()->json(['message'=>'cpf curto'],422);
      }
    }

    function callAPI($method, $url, $data){
       $curl = curl_init();
        if($method == "POST"){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        else if($method == "PUT"){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            )
        );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // EXECUTE:
        $result = curl_exec($curl);
        if(curl_errno($curl) > 0){
          return curl_error($curl);
        }
        curl_close($curl);

       return $result;
    }

}
