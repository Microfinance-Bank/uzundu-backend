<?php

namespace App\Http\Controllers;
use App\Http\Resources\BankResource;
use App\Models\Account;
use App\Models\Banks;
use App\Models\User;
use App\Utils\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneralController extends Controller
{
    public function getBalance()
    {
        $accountNo = Account::where("user_id", auth('sanctum')->user()->id)->value("account_number");
        try {
            $data = "authtoken=". env("BANK_ONE_AUTH_TOKEN"). "&accountNumber=". $accountNo ."&computewithdrawableBalance=false";
            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', 'https://staging.mybankone.com/BankOneWebAPI/api/Account/GetAccountByAccountNumber/2?'. $data, [
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]);
          return  json_decode($response->getBody());
        }catch (\Exception $e){
            $data = [
                "message" => $e->getMessage()
            ];
            Log::info("error ", $data);
        }
    }
    public function getBank(Request $request, Utils $utils)
    {
        try {
            $banks = Banks::all();

            return $utils->message("success", BankResource::collection($banks)  , 200);

        }catch (\Throwable $e) {
            // Do something with your exception
            return $utils->message("error", $e->getMessage() , 400);
        }
    }
    public function verifyAccount(Request $request, Utils $utils): JsonResponse
    {

        $request->validate([
            "account_number" => "required|int|digits:10",
//            "bank_code" => 'required_if:transfer_type,==,inter|int|digits:3',
            "transfer_type" => "required|string"
        ]);

        $bank_code = $request->get("bank_code");
        try {
            $sink_account = $request->get("account_number");
            if ($request->get("transfer_type") ==  "inter" && !empty($bank_code)){
                $account = $utils->validateInterBankUser($sink_account, $bank_code, $utils);
                if ($account->ResponseDescription == "Successful"){
                    $account = $utils->validateInterBankUser($sink_account, $bank_code, $utils);
                }else{

                    $data = [
                        "message" =>  $account->ResponseDescription
                    ];
                    Log::info("Error: ", $data);
                    return $utils->message("success", $account->ResponseDescription, 400);

                }
            }else{
                $account = $utils->validateIntraBankUser($sink_account);
            }
            return $utils->message("success", $account, 200);

        }catch (\Throwable $e) {
            // Do something with your exception

            $data = [
                "message" => $e->getMessage()
            ];
            Log::info("Error: ", $data);
            return $utils->message("error", "Network Error. Please Try Again." , 400);
        }
    }
    public function getBanks(Utils $utils)
    {

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env("PAYSTACK_SEC_KEY") ,
        ];
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'https://staging.mybankone.com/ThirdPartyAPIService/APIService/BillsPayment/GetCommercialBanks/' .  env("BANK_ONE_AUTH_TOKEN"), [
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]);
             $banks = $response->getBody();
            return $utils->message("success", BankResource::collection(json_decode(json_encode($banks["data"])))  , 200);

        }catch (\Throwable $e) {
            // Do something with your exception
            return $utils->message("error",$e->getMessage() , 400);
        }
    }
    public function getAccountDetails($phone, Utils $utils)
    {
        try {
            // execute the request
            $data = "phoneNumber=" . $phone . "&authToken=" . env("BANK_ONE_AUTH_TOKEN") ;
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'https://staging.mybankone.com/BankOneWebAPI/api/Customer/GetByCustomerPhoneNumber/2?' . $data);
            $user = json_decode($response->getBody()->getContents());
            if (isset($user->Message) && !empty($user))
                return $utils->message("success", $user->Message, 404);

                $data = [
                    "name" => $user[0]->Accounts[0]->CustomerName
                ];
                return $utils->message("success", $data , 200);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $utils->message("error", "Network Error. Please Try Again" , 404);
//            $response = $e->getResponse();
//            return $utils->message("error", $response->getBody()->getContents() , 404);

        }
    }
}
